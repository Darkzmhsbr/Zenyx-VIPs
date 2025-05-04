<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Bot;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\RedisService;
use App\Core\Exception\ValidationException;

/**
 * Controller para gerenciamento de bots
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class BotController extends Controller
{
    public function __construct(
        private Bot $botModel,
        private User $userModel,
        private TelegramService $telegram,
        private RedisService $redis
    ) {
        parent::__construct();
    }

    /**
     * Lista todos os bots do usuário
     */
    public function index(): Response
    {
        try {
            $userId = $this->getUserId();
            $bots = $this->botModel->getUserBots($userId);

            return $this->success($bots);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing bots', [
                'error' => $e->getMessage(),
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao listar bots', 500);
        }
    }

    /**
     * Exibe detalhes de um bot específico
     */
    public function show(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            return $this->success($bot);
        } catch (\Throwable $e) {
            $this->logger->error('Error showing bot', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao exibir bot', 500);
        }
    }

    /**
     * Cria um novo bot
     */
    public function create(): Response
    {
        try {
            $userId = $this->getUserId();
            
            // Validação dos dados
            $data = $this->validate([
                'token' => 'required|string|min:45',
            ]);

            // Verifica se token é válido
            $botInfo = $this->validateBotToken($data['token']);
            
            // Verifica se token já está em uso
            if ($this->botModel->findByToken($data['token'])) {
                throw new ValidationException('Este token já está em uso');
            }

            // Cria o bot
            $botId = $this->botModel->createBot([
                'user_id' => $userId,
                'token' => $data['token'],
                'username' => $botInfo['username'],
                'status' => Bot::STATUS_ACTIVE
            ]);

            // Configura webhook do bot
            $webhookUrl = $_ENV['APP_URL'] . '/webhook/telegram?bot_id=' . $botId;
            $telegram = $this->telegram->withToken($data['token']);
            $telegram->setWebhook($webhookUrl);

            $bot = $this->botModel->find($botId);

            return $this->success($bot, 'Bot criado com sucesso', 201);
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error creating bot', [
                'error' => $e->getMessage(),
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao criar bot', 500);
        }
    }

    /**
     * Atualiza um bot existente
     */
    public function update(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            $data = $this->validate([
                'welcome_message' => 'string|nullable',
                'pushinpay_token' => 'string|nullable',
                'channel_id' => 'string|nullable',
                'group_id' => 'string|nullable'
            ]);

            $this->botModel->update($id, $data);
            $bot = $this->botModel->find($id);

            return $this->success($bot, 'Bot atualizado com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error updating bot', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao atualizar bot', 500);
        }
    }

    /**
     * Remove um bot
     */
    public function delete(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            // Remove webhook do bot
            $telegram = $this->telegram->withToken($bot['token']);
            $telegram->deleteWebhook();

            // Marca bot como inativo (soft delete)
            $this->botModel->updateStatus($id, Bot::STATUS_INACTIVE);

            return $this->success(null, 'Bot removido com sucesso');
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting bot', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao remover bot', 500);
        }
    }

    /**
     * Atualiza configurações do bot
     */
    public function updateSettings(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            $data = $this->validate([
                'settings' => 'required|array'
            ]);

            $this->botModel->updateSettings($id, $data['settings']);
            $bot = $this->botModel->find($id);

            return $this->success($bot, 'Configurações atualizadas com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error updating bot settings', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao atualizar configurações', 500);
        }
    }

    /**
     * Atualiza token PushinPay
     */
    public function updatePushinPay(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            $data = $this->validate([
                'pushinpay_token' => 'required|string|min:32'
            ]);

            // Verifica se token é válido
            // TODO: Implementar validação com PushinPay

            $this->botModel->updatePushinPayToken($id, $data['pushinpay_token']);
            $bot = $this->botModel->find($id);

            return $this->success($bot, 'Token PushinPay atualizado com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error updating PushinPay token', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao atualizar token PushinPay', 500);
        }
    }

    /**
     * Atualiza mensagem de boas-vindas
     */
    public function updateWelcome(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            $data = $this->validate([
                'welcome_message' => 'required|string',
                'welcome_media' => 'array|nullable'
            ]);

            $this->botModel->updateWelcomeMessage(
                $id, 
                $data['welcome_message'], 
                $data['welcome_media'] ?? null
            );

            $bot = $this->botModel->find($id);

            return $this->success($bot, 'Mensagem de boas-vindas atualizada com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error updating welcome message', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao atualizar mensagem de boas-vindas', 500);
        }
    }

    /**
     * Atualiza canal/grupo vinculado
     */
    public function updateChannel(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            $data = $this->validate([
                'channel_id' => 'string|nullable',
                'group_id' => 'string|nullable'
            ]);

            // Verifica se bot é admin do canal/grupo
            if ($data['channel_id']) {
                $telegram = $this->telegram->withToken($bot['token']);
                try {
                    $member = $telegram->getChatMember($data['channel_id'], $bot['telegram_id']);
                    if (!in_array($member['status'], ['administrator', 'creator'])) {
                        throw new ValidationException('Bot não é administrador do canal');
                    }
                } catch (\Exception $e) {
                    throw new ValidationException('Não foi possível verificar o canal');
                }
            }

            $this->botModel->updateChannel($id, $data['channel_id'], $data['group_id']);
            $bot = $this->botModel->find($id);

            return $this->success($bot, 'Canal/grupo atualizado com sucesso');
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error updating channel/group', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao atualizar canal/grupo', 500);
        }
    }

    /**
     * Exibe estatísticas do bot
     */
    public function stats(int $id): Response
    {
        try {
            $userId = $this->getUserId();
            $bot = $this->botModel->find($id);

            if (!$bot || $bot['user_id'] !== $userId) {
                return $this->notFound('Bot não encontrado');
            }

            // TODO: Implementar coleta de estatísticas
            $stats = [
                'total_users' => 0,
                'active_users' => 0,
                'total_payments' => 0,
                'total_revenue' => 0,
                'conversion_rate' => 0
            ];

            return $this->success($stats);
        } catch (\Throwable $e) {
            $this->logger->error('Error getting bot stats', [
                'error' => $e->getMessage(),
                'bot_id' => $id,
                'user_id' => $this->getUserId()
            ]);
            
            return $this->error('Erro ao obter estatísticas', 500);
        }
    }

    /**
     * Valida token do bot
     */
    private function validateBotToken(string $token): array
    {
        try {
            $telegram = $this->telegram->withToken($token);
            $botInfo = $telegram->getMe();

            if (!isset($botInfo['result'])) {
                throw new ValidationException('Token inválido');
            }

            return $botInfo['result'];
        } catch (\Exception $e) {
            throw new ValidationException('Não foi possível validar o token do bot');
        }
    }
}