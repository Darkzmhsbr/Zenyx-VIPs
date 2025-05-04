<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\TelegramService;
use App\Services\PushinPayService;
use App\Models\User;
use App\Models\Bot;
use App\Models\Payment;
use App\Models\Plan;

/**
 * Controller para gerenciamento de webhooks
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class WebhookController extends Controller
{
    public function __construct(
        private TelegramService $telegram,
        private PushinPayService $pushinPay,
        private User $userModel,
        private Bot $botModel,
        private Payment $paymentModel,
        private Plan $planModel
    ) {
        parent::__construct();
    }

    /**
     * Handle Telegram webhook
     */
    public function handleTelegram(): Response
    {
        try {
            // Obtém dados do webhook
            $input = file_get_contents('php://input');
            $update = json_decode($input, true);

            if (!$update) {
                return $this->error('Invalid update data', 400);
            }

            // Log do webhook recebido
            $this->logger->info('Telegram webhook received', [
                'update_id' => $update['update_id'] ?? null
            ]);

            // Verifica se é webhook de um bot específico
            $botId = $this->request->query('bot_id');
            
            if ($botId) {
                $bot = $this->botModel->find((int) $botId);
                if (!$bot) {
                    return $this->error('Bot not found', 404);
                }
                
                // Configura serviço do Telegram para o bot específico
                $this->telegram = $this->telegram->withToken($bot['token']);
            }

            // Processa update baseado no tipo
            if (isset($update['message'])) {
                return $this->handleMessage($update['message'], $botId);
            } elseif (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query'], $botId);
            } elseif (isset($update['inline_query'])) {
                return $this->handleInlineQuery($update['inline_query'], $botId);
            }

            return $this->success();
        } catch (\Throwable $e) {
            $this->logger->error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Internal error', 500);
        }
    }

    /**
     * Handle PushinPay webhook
     */
    public function handlePushinPay(): Response
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

            $processedData = $this->pushinPay->processWebhook($data, $signature);

            // Log do webhook recebido
            $this->logger->info('PushinPay webhook received', [
                'event' => $processedData['event'] ?? null,
                'transaction_id' => $processedData['transaction_id'] ?? null
            ]);

            // Processa evento
            switch ($processedData['event'] ?? '') {
                case 'payment.confirmed':
                    return $this->handlePaymentConfirmed($processedData);
                    
                case 'payment.failed':
                    return $this->handlePaymentFailed($processedData);
                    
                case 'payment.refunded':
                    return $this->handlePaymentRefunded($processedData);
                    
                default:
                    $this->logger->warning('Unknown PushinPay event', [
                        'event' => $processedData['event'] ?? null
                    ]);
                    return $this->success();
            }
        } catch (\Throwable $e) {
            $this->logger->error('PushinPay webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Internal error', 500);
        }
    }

    /**
     * Handle Telegram message
     */
    private function handleMessage(array $message, ?string $botId): Response
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';

        // Registra ou atualiza usuário
        $userData = [
            'telegram_id' => (string) $userId,
            'username' => $message['from']['username'] ?? null,
            'first_name' => $message['from']['first_name'] ?? null,
            'last_name' => $message['from']['last_name'] ?? null,
            'last_interaction' => date('Y-m-d H:i:s')
        ];

        $user = $this->userModel->createOrUpdateFromTelegram($userData);

        // Se for comando, processa
        if (strpos($text, '/') === 0) {
            return $this->handleCommand($text, $chatId, $user, $botId);
        }

        return $this->success();
    }

    /**
     * Handle Telegram callback query
     */
    private function handleCallbackQuery(array $callbackQuery, ?string $botId): Response
    {
        $callbackId = $callbackQuery['id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        $data = $callbackQuery['data'];
        
        // Responde ao callback para remover loading
        $this->telegram->answerCallbackQuery($callbackId);

        // Processa callback baseado nos dados
        $parts = explode(':', $data);
        $action = $parts[0];
        $params = array_slice($parts, 1);

        switch ($action) {
            case 'plan':
                return $this->handlePlanSelection((int) $params[0], $chatId);
                
            case 'payment':
                return $this->handlePaymentStatus($params[0], $chatId);
                
            case 'menu':
                return $this->showMenu($chatId);
                
            default:
                $this->logger->warning('Unknown callback action', [
                    'action' => $action,
                    'params' => $params
                ]);
                return $this->success();
        }
    }

    /**
     * Handle Telegram inline query
     */
    private function handleInlineQuery(array $inlineQuery, ?string $botId): Response
    {
        // Implementar lógica de inline query se necessário
        return $this->success();
    }

    /**
     * Handle command
     */
    private function handleCommand(string $command, string $chatId, array $user, ?string $botId): Response
    {
        $parts = explode(' ', $command);
        $cmd = $parts[0];
        $args = array_slice($parts, 1);

        switch ($cmd) {
            case '/start':
                return $this->handleStartCommand($chatId, $user, $args, $botId);
                
            case '/help':
                return $this->handleHelpCommand($chatId);
                
            case '/planos':
            case '/plans':
                return $this->handlePlansCommand($chatId, $botId);
                
            case '/status':
                return $this->handleStatusCommand($chatId, $user);
                
            default:
                return $this->success();
        }
    }

    /**
     * Handle /start command
     */
    private function handleStartCommand(string $chatId, array $user, array $args, ?string $botId): Response
    {
        // Verifica se tem parâmetro de referral
        if (!empty($args[0]) && strpos($args[0], 'ref_') === 0) {
            $referrerId = substr($args[0], 4);
            $referrer = $this->userModel->findByTelegramId($referrerId);
            
            if ($referrer && $referrer['id'] !== $user['id']) {
                $this->userModel->update($user['id'], [
                    'referrer_id' => $referrer['id']
                ]);
            }
        }

        // Se for bot específico, mostra mensagem de boas-vindas do bot
        if ($botId) {
            $bot = $this->botModel->find((int) $botId);
            
            if ($bot && $bot['welcome_message']) {
                $message = $this->formatMessage($bot['welcome_message'], $user);
                
                // Se tiver mídia, envia primeiro
                if ($bot['welcome_media']) {
                    $media = $bot['welcome_media'];
                    
                    switch ($media['type']) {
                        case 'photo':
                            $this->telegram->sendPhoto($chatId, $media['file_id'], [
                                'caption' => $message
                            ]);
                            break;
                            
                        case 'video':
                            $this->telegram->sendVideo($chatId, $media['file_id'], [
                                'caption' => $message
                            ]);
                            break;
                    }
                } else {
                    $this->telegram->sendMessage($chatId, $message);
                }
                
                // Mostra planos disponíveis
                return $this->showPlans($chatId, (int) $botId);
            }
        }

        // Mensagem padrão do Bot Zenyx
        $message = "🤖 <b>Bem-vindo ao Bot Zenyx!</b>\n\n";
        $message .= "Crie seu próprio bot de pagamentos de forma simples e rápida.\n\n";
        $message .= "Use os botões abaixo para começar:";

        $keyboard = [
            [
                ['text' => '🤖 Criar seu Bot', 'callback_data' => 'menu:create_bot'],
                ['text' => '💰 Meu Saldo', 'callback_data' => 'menu:balance']
            ],
            [
                ['text' => '👥 Convide e Ganhe', 'callback_data' => 'menu:referral'],
                ['text' => '👑 Seja Admin VIP', 'callback_data' => 'menu:vip']
            ],
            [
                ['text' => 'ℹ️ Como Funciona', 'callback_data' => 'menu:help']
            ]
        ];

        $this->telegram->sendMessage($chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);

        return $this->success();
    }

    /**
     * Format message with user variables
     */
    private function formatMessage(string $message, array $user): string
    {
        $replacements = [
            '%firstname%' => $user['first_name'] ?? '',
            '%lastname%' => $user['last_name'] ?? '',
            '%username%' => $user['username'] ?? '',
            '%fullname%' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))
        ];

        return strtr($message, $replacements);
    }

    /**
     * Show plans
     */
    private function showPlans(string $chatId, int $botId): Response
    {
        $plans = $this->planModel->getBotPlans($botId, true);
        
        if (empty($plans)) {
            $this->telegram->sendMessage($chatId, "❌ Não há planos disponíveis no momento.");
            return $this->success();
        }

        $keyboard = [];
        
        foreach ($plans as $plan) {
            $buttonText = sprintf(
                "💎 %s - R$ %.2f",
                $plan['name'],
                $plan['price']
            );
            
            $keyboard[] = [
                ['text' => $buttonText, 'callback_data' => "plan:{$plan['id']}"]
            ];
        }

        $keyboard[] = [
            ['text' => '🔙 Voltar', 'callback_data' => 'menu:main']
        ];

        $message = "💳 <b>Planos Disponíveis</b>\n\n";
        $message .= "Escolha um plano para continuar:";

        $this->telegram->sendMessage($chatId, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);

        return $this->success();
    }

    /**
     * Handle payment confirmed
     */
    private function handlePaymentConfirmed(array $data): Response
    {
        $payment = $this->paymentModel->findByTransactionId($data['transaction_id']);
        
        if (!$payment) {
            $this->logger->error('Payment not found for confirmed transaction', [
                'transaction_id' => $data['transaction_id']
            ]);
            return $this->success();
        }

        // Atualiza status do pagamento
        $this->paymentModel->markAsPaid($payment['id'], $data['transaction_id']);

        // Notifica usuário
        $user = $this->userModel->find($payment['user_id']);
        
        if ($user) {
            $message = "✅ <b>Pagamento Confirmado!</b>\n\n";
            $message .= "Seu pagamento foi aprovado com sucesso.\n";
            $message .= "Valor: R$ " . number_format($payment['amount'], 2, ',', '.');

            $this->telegram->sendMessage($user['telegram_id'], $message);
        }

        // Processa ações pós-pagamento (liberar acesso, etc)
        $this->processPostPayment($payment);

        return $this->success();
    }

    /**
     * Process post-payment actions
     */
    private function processPostPayment(array $payment): void
    {
        // Implementar lógica de pós-pagamento
        // - Liberar acesso ao grupo/canal
        // - Processar comissões
        // - Atualizar status do usuário
        // etc.
    }
}