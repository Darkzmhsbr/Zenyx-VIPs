<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\User;
use App\Models\Bot;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\TelegramService;
use App\Services\RedisService;
use App\Core\Exception\ValidationException;

/**
 * Controller para funções administrativas
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class AdminController extends Controller
{
    public function __construct(
        private User $userModel,
        private Bot $botModel,
        private Payment $paymentModel,
        private Plan $planModel,
        private TelegramService $telegram,
        private RedisService $redis
    ) {
        parent::__construct();
    }

    /**
     * Dashboard administrativo
     */
    public function dashboard(): Response
    {
        try {
            // Estatísticas gerais
            $stats = [
                'users' => $this->userModel->getStatistics(),
                'bots' => $this->botModel->getStatistics(),
                'payments' => $this->paymentModel->getStatistics(),
                'recent_payments' => $this->paymentModel->getRecentPayments(10),
                'top_bots' => $this->botModel->getBotsByRevenue(5),
                'system_health' => $this->getSystemHealth()
            ];

            return $this->success($stats);
        } catch (\Throwable $e) {
            $this->logger->error('Error loading dashboard', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro ao carregar dashboard', 500);
        }
    }

    /**
     * Lista todos os usuários
     */
    public function users(): Response
    {
        try {
            $page = (int) $this->request->query('page', 1);
            $perPage = (int) $this->request->query('per_page', 50);
            $search = $this->request->query('search');
            
            $conditions = [];
            if ($search) {
                $conditions['username'] = ['LIKE', "%{$search}%"];
            }

            $users = $this->userModel->paginate($page, $perPage, $conditions);
            
            return $this->success($users);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing users', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro ao listar usuários', 500);
        }
    }

    /**
     * Detalhes de um usuário
     */
    public function userDetail(int $id): Response
    {
        try {
            $user = $this->userModel->find($id);
            
            if (!$user) {
                return $this->notFound('Usuário não encontrado');
            }

            // Informações adicionais
            $user['bots'] = $this->botModel->getUserBots($id);
            $user['payments'] = $this->paymentModel->getUserPayments($id);
            $user['referrals'] = $this->userModel->getReferrals($id);

            return $this->success($user);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting user details', [
                'error' => $e->getMessage(),
                'user_id' => $id
            ]);
            
            return $this->error('Erro ao obter detalhes do usuário', 500);
        }
    }

    /**
     * Banir usuário
     */
    public function banUser(int $id): Response
    {
        try {
            $user = $this->userModel->find($id);
            
            if (!$user) {
                return $this->notFound('Usuário não encontrado');
            }

            $data = $this->validate([
                'reason' => 'required|string',
                'duration' => 'integer|nullable'
            ]);

            // Atualiza status do usuário
            $this->userModel->update($id, ['status' => 'banned']);

            // Suspende todos os bots do usuário
            $bots = $this->botModel->getUserBots($id);
            foreach ($bots as $bot) {
                $this->botModel->updateStatus($bot['id'], Bot::STATUS_SUSPENDED);
            }

            // Notifica usuário
            try {
                $message = "⚠️ <b>Sua conta foi banida</b>\n\n";
                $message .= "Motivo: {$data['reason']}\n";
                
                if (isset($data['duration'])) {
                    $message .= "Duração: {$data['duration']} dias";
                }

                $this->telegram->sendMessage($user['telegram_id'], $message);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to notify banned user', [
                    'user_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }

            return $this->success(null, 'Usuário banido com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error banning user', [
                'error' => $e->getMessage(),
                'user_id' => $id
            ]);
            
            return $this->error('Erro ao banir usuário', 500);
        }
    }

    /**
     * Desbanir usuário
     */
    public function unbanUser(int $id): Response
    {
        try {
            $user = $this->userModel->find($id);
            
            if (!$user) {
                return $this->notFound('Usuário não encontrado');
            }

            // Atualiza status do usuário
            $this->userModel->update($id, ['status' => 'active']);

            // Reativa bots do usuário
            $bots = $this->botModel->getUserBots($id);
            foreach ($bots as $bot) {
                if ($bot['status'] === Bot::STATUS_SUSPENDED) {
                    $this->botModel->updateStatus($bot['id'], Bot::STATUS_ACTIVE);
                }
            }

            // Notifica usuário
            try {
                $message = "✅ <b>Sua conta foi reativada</b>\n\n";
                $message .= "Você pode voltar a usar nossos serviços normalmente.";

                $this->telegram->sendMessage($user['telegram_id'], $message);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to notify unbanned user', [
                    'user_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }

            return $this->success(null, 'Usuário desbanido com sucesso');
        } catch (\Throwable $e) {
            $this->logger->error('Error unbanning user', [
                'error' => $e->getMessage(),
                'user_id' => $id
            ]);
            
            return $this->error('Erro ao desbanir usuário', 500);
        }
    }

    /**
     * Lista todos os bots
     */
    public function bots(): Response
    {
        try {
            $page = (int) $this->request->query('page', 1);
            $perPage = (int) $this->request->query('per_page', 50);
            $status = $this->request->query('status');
            
            $conditions = [];
            if ($status) {
                $conditions['status'] = $status;
            }

            $bots = $this->botModel->paginate($page, $perPage, $conditions);
            
            return $this->success($bots);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing bots', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro ao listar bots', 500);
        }
    }

    /**
     * Detalhes de um bot
     */
    public function botDetail(int $id): Response
    {
        try {
            $bot = $this->botModel->find($id);
            
            if (!$bot) {
                return $this->notFound('Bot não encontrado');
            }

            // Informações adicionais
            $bot['owner'] = $this->userModel->find($bot['user_id']);
            $bot['payments'] = $this->paymentModel->getBotPayments($id, ['limit' => 10]);
            $bot['plans'] = $this->planModel->getBotPlans($id);
            
            // Estatísticas
            $bot['stats'] = [
                'total_payments' => $this->paymentModel->count(['bot_id' => $id]),
                'total_revenue' => $this->paymentModel->getStatistics('all')['total_revenue'] ?? 0,
                'conversion_rate' => $this->paymentModel->getConversionRate($id)
            ];

            return $this->success($bot);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting bot details', [
                'error' => $e->getMessage(),
                'bot_id' => $id
            ]);
            
            return $this->error('Erro ao obter detalhes do bot', 500);
        }
    }

    /**
     * Suspende um bot
     */
    public function suspendBot(int $id): Response
    {
        try {
            $bot = $this->botModel->find($id);
            
            if (!$bot) {
                return $this->notFound('Bot não encontrado');
            }

            $data = $this->validate([
                'reason' => 'required|string'
            ]);

            // Suspende o bot
            $this->botModel->updateStatus($id, Bot::STATUS_SUSPENDED);

            // Remove webhook
            $telegram = $this->telegram->withToken($bot['token']);
            $telegram->deleteWebhook();

            // Notifica proprietário
            $owner = $this->userModel->find($bot['user_id']);
            if ($owner) {
                try {
                    $message = "⚠️ <b>Seu bot foi suspenso</b>\n\n";
                    $message .= "Bot: @{$bot['username']}\n";
                    $message .= "Motivo: {$data['reason']}";

                    $this->telegram->sendMessage($owner['telegram_id'], $message);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to notify bot suspension', [
                        'bot_id' => $id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->success(null, 'Bot suspenso com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error suspending bot', [
                'error' => $e->getMessage(),
                'bot_id' => $id
            ]);
            
            return $this->error('Erro ao suspender bot', 500);
        }
    }

    /**
     * Relatórios
     */
    public function reports(): Response
    {
        try {
            $period = $this->request->query('period', 'month');
            
            $reports = [
                'sales' => $this->paymentModel->getPaymentReport(['period' => $period]),
                'users' => $this->getUsersReport($period),
                'bots' => $this->getBotsReport($period),
                'revenue' => $this->getRevenueReport($period)
            ];

            return $this->success($reports);
        } catch (\Throwable $e) {
            $this->logger->error('Error generating reports', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro ao gerar relatórios', 500);
        }
    }

    /**
     * Configurações do sistema
     */
    public function settings(): Response
    {
        try {
            $settings = [
                'app' => [
                    'name' => $_ENV['APP_NAME'],
                    'url' => $_ENV['APP_URL'],
                    'env' => $_ENV['APP_ENV'],
                    'debug' => $_ENV['APP_DEBUG']
                ],
                'telegram' => [
                    'channel_id' => $_ENV['TELEGRAM_CHANNEL_ID'],
                    'channel_username' => $_ENV['TELEGRAM_CHANNEL_USERNAME']
                ],
                'payment' => [
                    'min_amount' => $_ENV['PAYMENT_MIN_AMOUNT'],
                    'max_amount' => $_ENV['PAYMENT_MAX_AMOUNT'],
                    'expiration_minutes' => $_ENV['PAYMENT_EXPIRATION_MINUTES']
                ],
                'referral' => [
                    'minimum_sales' => $_ENV['REFERRAL_MINIMUM_SALES'],
                    'minimum_amount' => $_ENV['REFERRAL_MINIMUM_AMOUNT'],
                    'period_days' => $_ENV['REFERRAL_PERIOD_DAYS'],
                    'commission_rate' => $_ENV['REFERRAL_COMMISSION_RATE']
                ],
                'admin_vip' => [
                    'price' => $_ENV['ADMIN_VIP_PRICE'],
                    'trial_days' => $_ENV['ADMIN_VIP_TRIAL_DAYS'],
                    'commission_rate' => $_ENV['ADMIN_VIP_COMMISSION_RATE']
                ]
            ];

            return $this->success($settings);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting settings', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro ao obter configurações', 500);
        }
    }

    /**
     * Verifica saúde do sistema
     */
    private function getSystemHealth(): array
    {
        return [
            'database' => $this->db->ping(),
            'redis' => $this->redis->healthCheck(),
            'telegram' => $this->telegram->getMe() !== null,
            'storage' => is_writable(dirname(__DIR__, 2) . '/storage')
        ];
    }

    /**
     * Relatório de usuários
     */
    private function getUsersReport(string $period): array
    {
        // TODO: Implementar relatório de usuários
        return [];
    }

    /**
     * Relatório de bots
     */
    private function getBotsReport(string $period): array
    {
        // TODO: Implementar relatório de bots
        return [];
    }

    /**
     * Relatório de receita
     */
    private function getRevenueReport(string $period): array
    {
        // TODO: Implementar relatório de receita
        return [];
    }
}