<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Bot;
use App\Models\User;
use App\Services\PushinPayService;
use App\Services\TelegramService;
use App\Core\Exception\ValidationException;

/**
 * Controller para gerenciamento de pagamentos
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class PaymentController extends Controller
{
    public function __construct(
        private Payment $paymentModel,
        private Plan $planModel,
        private Bot $botModel,
        private User $userModel,
        private PushinPayService $pushinPay,
        private TelegramService $telegram
    ) {
        parent::__construct();
    }

    /**
     * Cria um novo pagamento
     */
    public function create(): Response
    {
        try {
            $data = $this->validate([
                'plan_id' => 'required|integer',
                'bot_id' => 'required|integer',
                'user_telegram_id' => 'required|string'
            ]);

            // Busca o plano
            $plan = $this->planModel->find($data['plan_id']);
            if (!$plan || !$plan['is_active']) {
                return $this->error('Plano não encontrado ou inativo', 404);
            }

            // Busca o bot
            $bot = $this->botModel->find($data['bot_id']);
            if (!$bot || $bot['status'] !== Bot::STATUS_ACTIVE) {
                return $this->error('Bot não encontrado ou inativo', 404);
            }

            // Verifica se o bot tem o plano
            if ($plan['bot_id'] !== $bot['id']) {
                return $this->error('Plano não pertence a este bot', 400);
            }

            // Busca ou cria o usuário
            $user = $this->userModel->findByTelegramId($data['user_telegram_id']);
            if (!$user) {
                $userId = $this->userModel->create([
                    'telegram_id' => $data['user_telegram_id'],
                    'status' => 'active'
                ]);
                $user = $this->userModel->find($userId);
            }

            // Verifica se o bot tem token PushinPay configurado
            if (!$bot['pushinpay_token']) {
                return $this->error('Bot não possui pagamento configurado', 400);
            }

            // Cria o pagamento no PushinPay
            $pushinPayService = new PushinPayService($bot['pushinpay_token'], $this->logger);
            
            $externalReference = uniqid('pay_' . $user['id'] . '_');
            $amount = (int)($plan['price'] * 100); // Converter para centavos
            
            $pixResponse = $pushinPayService->createPixQrCode(
                amount: $amount,
                externalReference: $externalReference,
                customer: [
                    'name' => $user['first_name'] ?? 'Cliente',
                    'document' => $user['telegram_id']
                ],
                description: "Pagamento - {$plan['name']}",
                expirationInMinutes: (int)$_ENV['PAYMENT_EXPIRATION_MINUTES']
            );

            // Cria o registro do pagamento
            $paymentId = $this->paymentModel->createPayment([
                'user_id' => $user['id'],
                'bot_id' => $bot['id'],
                'plan_id' => $plan['id'],
                'transaction_id' => $pixResponse['id'] ?? null,
                'external_reference' => $externalReference,
                'amount' => $plan['price'],
                'status' => Payment::STATUS_PENDING,
                'payment_method' => Payment::METHOD_PIX,
                'pix_code' => $pixResponse['pix_code'] ?? null,
                'pix_qrcode' => $pixResponse['qr_code'] ?? null,
                'expires_at' => date('Y-m-d H:i:s', strtotime("+{$_ENV['PAYMENT_EXPIRATION_MINUTES']} minutes"))
            ]);

            $payment = $this->paymentModel->find($paymentId);

            return $this->success([
                'payment' => $payment,
                'pix' => [
                    'code' => $pixResponse['pix_code'] ?? null,
                    'qrcode' => $pixResponse['qr_code'] ?? null,
                    'qrcode_image' => $pixResponse['qr_code_base64'] ?? null
                ]
            ], 'Pagamento criado com sucesso', 201);

        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error creating payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Erro ao criar pagamento', 500);
        }
    }

    /**
     * Consulta status de um pagamento
     */
    public function status(string $id): Response
    {
        try {
            $payment = $this->paymentModel->findByExternalReference($id);
            
            if (!$payment) {
                return $this->notFound('Pagamento não encontrado');
            }

            // Se pagamento estiver pendente, consulta status no PushinPay
            if ($payment['status'] === Payment::STATUS_PENDING && $payment['transaction_id']) {
                $bot = $this->botModel->find($payment['bot_id']);
                if ($bot && $bot['pushinpay_token']) {
                    $pushinPayService = new PushinPayService($bot['pushinpay_token'], $this->logger);
                    
                    try {
                        $status = $pushinPayService->getPaymentStatus($payment['transaction_id']);
                        
                        // Atualiza status se mudou
                        if ($status['status'] === 'paid' && $payment['status'] !== Payment::STATUS_COMPLETED) {
                            $this->paymentModel->markAsPaid($payment['id'], $payment['transaction_id']);
                            $payment = $this->paymentModel->find($payment['id']);
                            
                            // Notifica usuário
                            $this->notifyPaymentCompleted($payment);
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to check payment status', [
                            'payment_id' => $payment['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            return $this->success($payment);

        } catch (\Throwable $e) {
            $this->logger->error('Error checking payment status', [
                'error' => $e->getMessage(),
                'payment_id' => $id
            ]);
            
            return $this->error('Erro ao consultar status do pagamento', 500);
        }
    }

    /**
     * Lista pagamentos de um usuário
     */
    public function userPayments(string $telegramId): Response
    {
        try {
            $user = $this->userModel->findByTelegramId($telegramId);
            
            if (!$user) {
                return $this->notFound('Usuário não encontrado');
            }

            $payments = $this->paymentModel->getUserPayments($user['id']);

            return $this->success($payments);

        } catch (\Throwable $e) {
            $this->logger->error('Error listing user payments', [
                'error' => $e->getMessage(),
                'telegram_id' => $telegramId
            ]);
            
            return $this->error('Erro ao listar pagamentos', 500);
        }
    }

    /**
     * Webhook de notificação do PushinPay
     */
    public function webhook(): Response
    {
        try {
            // Obtém dados do webhook
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return $this->error('Invalid webhook data', 400);
            }

            // Valida assinatura do webhook
            $signature = $this->request->header('X-Webhook-Signature');
            
            if (!$signature) {
                return $this->error('Missing signature', 401);
            }

            $secret = $_ENV['PUSHINPAY_WEBHOOK_SECRET'] ?? '';
            if (!$this->pushinPay->validateWebhookSignature($input, $signature, $secret)) {
                return $this->error('Invalid signature', 401);
            }

            // Processa evento
            switch ($data['event'] ?? '') {
                case 'payment.confirmed':
                    $this->handlePaymentConfirmed($data);
                    break;
                    
                case 'payment.failed':
                    $this->handlePaymentFailed($data);
                    break;
                    
                case 'payment.refunded':
                    $this->handlePaymentRefunded($data);
                    break;
                    
                default:
                    $this->logger->warning('Unknown payment event', [
                        'event' => $data['event'] ?? null
                    ]);
            }

            return $this->success();

        } catch (\Throwable $e) {
            $this->logger->error('Error processing payment webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Erro ao processar webhook', 500);
        }
    }

    /**
     * Processa pagamento confirmado
     */
    private function handlePaymentConfirmed(array $data): void
    {
        $payment = $this->paymentModel->findByTransactionId($data['transaction_id']);
        
        if (!$payment) {
            $this->logger->error('Payment not found for confirmed transaction', [
                'transaction_id' => $data['transaction_id']
            ]);
            return;
        }

        // Atualiza status do pagamento
        $this->paymentModel->markAsPaid($payment['id'], $data['transaction_id']);

        // Notifica usuário
        $this->notifyPaymentCompleted($payment);

        // Processa ações pós-pagamento
        $this->processPostPayment($payment);
    }

    /**
     * Processa pagamento falhado
     */
    private function handlePaymentFailed(array $data): void
    {
        $payment = $this->paymentModel->findByTransactionId($data['transaction_id']);
        
        if (!$payment) {
            return;
        }

        // Atualiza status do pagamento
        $this->paymentModel->markAsFailed($payment['id'], $data['error'] ?? 'Payment failed');

        // Notifica usuário
        $this->notifyPaymentFailed($payment);
    }

    /**
     * Processa pagamento reembolsado
     */
    private function handlePaymentRefunded(array $data): void
    {
        $payment = $this->paymentModel->findByTransactionId($data['transaction_id']);
        
        if (!$payment) {
            return;
        }

        // Atualiza status do pagamento
        $this->paymentModel->updateStatus($payment['id'], Payment::STATUS_REFUNDED);

        // Notifica usuário
        $this->notifyPaymentRefunded($payment);
    }

    /**
     * Notifica usuário sobre pagamento completado
     */
    private function notifyPaymentCompleted(array $payment): void
    {
        try {
            $user = $this->userModel->find($payment['user_id']);
            $plan = $this->planModel->find($payment['plan_id']);
            $bot = $this->botModel->find($payment['bot_id']);
            
            if (!$user || !$plan || !$bot) {
                return;
            }

            $message = "✅ <b>Pagamento Confirmado!</b>\n\n";
            $message .= "Plano: {$plan['name']}\n";
            $message .= "Valor: R$ " . number_format($payment['amount'], 2, ',', '.') . "\n\n";
            $message .= "Seu acesso foi liberado com sucesso!";

            // Usa o token do bot para enviar a mensagem
            $telegram = new TelegramService($bot['token'], $this->logger);
            $telegram->sendMessage($user['telegram_id'], $message);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify payment completed', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notifica usuário sobre pagamento falhado
     */
    private function notifyPaymentFailed(array $payment): void
    {
        try {
            $user = $this->userModel->find($payment['user_id']);
            $bot = $this->botModel->find($payment['bot_id']);
            
            if (!$user || !$bot) {
                return;
            }

            $message = "❌ <b>Pagamento Não Aprovado</b>\n\n";
            $message .= "Infelizmente seu pagamento não foi aprovado.\n";
            $message .= "Por favor, tente novamente ou entre em contato com o suporte.";

            $telegram = new TelegramService($bot['token'], $this->logger);
            $telegram->sendMessage($user['telegram_id'], $message);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify payment failed', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notifica usuário sobre pagamento reembolsado
     */
    private function notifyPaymentRefunded(array $payment): void
    {
        try {
            $user = $this->userModel->find($payment['user_id']);
            $bot = $this->botModel->find($payment['bot_id']);
            
            if (!$user || !$bot) {
                return;
            }

            $message = "💰 <b>Pagamento Reembolsado</b>\n\n";
            $message .= "Seu pagamento foi reembolsado com sucesso.\n";
            $message .= "Valor: R$ " . number_format($payment['amount'], 2, ',', '.') . "\n\n";
            $message .= "O valor será estornado em sua conta em até 5 dias úteis.";

            $telegram = new TelegramService($bot['token'], $this->logger);
            $telegram->sendMessage($user['telegram_id'], $message);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify payment refunded', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Processa ações pós-pagamento
     */
    private function processPostPayment(array $payment): void
    {
        // 1. Liberar acesso ao grupo/canal
        $this->grantChannelAccess($payment);
        
        // 2. Processar comissões de afiliados
        $this->processAffiliateCommissions($payment);
        
        // 3. Atualizar estatísticas
        $this->updateStatistics($payment);
    }

    /**
     * Libera acesso ao canal/grupo
     */
    private function grantChannelAccess(array $payment): void
    {
        try {
            $user = $this->userModel->find($payment['user_id']);
            $bot = $this->botModel->find($payment['bot_id']);
            
            if (!$user || !$bot || !$bot['channel_id']) {
                return;
            }

            // Adiciona usuário ao canal/grupo se necessário
            // Nota: Bot deve ter permissões de admin para isso
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to grant channel access', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Processa comissões de afiliados
     */
    private function processAffiliateCommissions(array $payment): void
    {
        try {
            $user = $this->userModel->find($payment['user_id']);
            
            if (!$user || !$user['referrer_id']) {
                return;
            }

            $referrer = $this->userModel->find($user['referrer_id']);
            
            if (!$referrer) {
                return;
            }

            // Calcula comissão
            $commissionRate = (float)$_ENV['REFERRAL_COMMISSION_RATE'];
            $commission = $payment['amount'] * $commissionRate;

            // Atualiza saldo do referrer
            $this->userModel->updateBalance($referrer['id'], $commission, 'add');

            // Notifica referrer
            $message = "💰 <b>Comissão Recebida!</b>\n\n";
            $message .= "Você recebeu uma comissão de R$ " . number_format($commission, 2, ',', '.') . "\n";
            $message .= "Referente a uma venda realizada por seu indicado.";

            $this->telegram->sendMessage($referrer['telegram_id'], $message);

        } catch (\Exception $e) {
            $this->logger->error('Failed to process affiliate commission', [
                'payment_id' => $payment['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Atualiza estatísticas
     */
    private function updateStatistics(array $payment): void
    {
        // Implementar atualização de estatísticas
        // - Total de vendas
        // - Receita total
        // - Conversões
        // etc.
    }
}