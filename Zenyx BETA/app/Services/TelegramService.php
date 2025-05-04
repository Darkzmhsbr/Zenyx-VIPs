<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Exception\ServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Serviço de integração com a API do Telegram
 * 
 * Gerencia todas as interações com a API do Telegram,
 * incluindo envio de mensagens, gerenciamento de bots e webhooks
 */
final class TelegramService
{
    private const API_BASE_URL = 'https://api.telegram.org/bot';
    private const TIMEOUT = 10;
    private const CONNECT_TIMEOUT = 5;
    
    private Client $client;
    private string $baseUrl;

    public function __construct(
        private readonly string $token,
        private readonly Logger $logger,
        ?Client $client = null
    ) {
        $this->baseUrl = self::API_BASE_URL . $token . '/';
        
        $this->client = $client ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'http_errors' => false,
            'verify' => true
        ]);
    }

    /**
     * Envia uma mensagem de texto
     */
    public function sendMessage(
        string|int $chatId,
        string $text,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
            'disable_notification' => false
        ], $options);

        return $this->request('sendMessage', $params);
    }

    /**
     * Envia uma foto
     */
    public function sendPhoto(
        string|int $chatId,
        string $photo,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
            'parse_mode' => 'HTML'
        ], $options);

        return $this->request('sendPhoto', $params);
    }

    /**
     * Envia um vídeo
     */
    public function sendVideo(
        string|int $chatId,
        string $video,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'video' => $video,
            'parse_mode' => 'HTML'
        ], $options);

        return $this->request('sendVideo', $params);
    }

    /**
     * Envia um documento
     */
    public function sendDocument(
        string|int $chatId,
        string $document,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'document' => $document,
            'parse_mode' => 'HTML'
        ], $options);

        return $this->request('sendDocument', $params);
    }

    /**
     * Edita uma mensagem existente
     */
    public function editMessageText(
        string|int $chatId,
        int $messageId,
        string $text,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ], $options);

        return $this->request('editMessageText', $params);
    }

    /**
     * Edita o markup de uma mensagem
     */
    public function editMessageReplyMarkup(
        string|int $chatId,
        int $messageId,
        array $replyMarkup = []
    ): array {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($replyMarkup)
        ];

        return $this->request('editMessageReplyMarkup', $params);
    }

    /**
     * Deleta uma mensagem
     */
    public function deleteMessage(string|int $chatId, int $messageId): bool
    {
        $result = $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);

        return $result['result'] ?? false;
    }

    /**
     * Responde a um callback query
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        array $options = []
    ): bool {
        $params = array_merge([
            'callback_query_id' => $callbackQueryId,
            'show_alert' => false
        ], $options);

        $result = $this->request('answerCallbackQuery', $params);
        return $result['result'] ?? false;
    }

    /**
     * Obtém informações do bot
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Obtém informações de um membro do chat
     */
    public function getChatMember(string|int $chatId, int $userId): array
    {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    /**
     * Configura o webhook
     */
    public function setWebhook(string $url, array $options = []): bool
    {
        $params = array_merge([
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'inline_query'],
            'drop_pending_updates' => false,
            'max_connections' => 40
        ], $options);

        $result = $this->request('setWebhook', $params);
        return $result['result'] ?? false;
    }

    /**
     * Remove o webhook
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        $result = $this->request('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates
        ]);

        return $result['result'] ?? false;
    }

    /**
     * Obtém informações do webhook
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Cria um teclado inline
     */
    public function createInlineKeyboard(array $buttons): array
    {
        return [
            'inline_keyboard' => $buttons
        ];
    }

    /**
     * Cria um teclado de resposta
     */
    public function createReplyKeyboard(
        array $buttons,
        bool $resizeKeyboard = true,
        bool $oneTimeKeyboard = false
    ): array {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resizeKeyboard,
            'one_time_keyboard' => $oneTimeKeyboard
        ];
    }

    /**
     * Remove teclado de resposta
     */
    public function removeReplyKeyboard(): array
    {
        return [
            'remove_keyboard' => true
        ];
    }

    /**
     * Define ação de chat (typing, upload_photo, etc)
     */
    public function sendChatAction(string|int $chatId, string $action): bool
    {
        $result = $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);

        return $result['result'] ?? false;
    }

    /**
     * Obtém atualizações (para polling)
     */
    public function getUpdates(array $options = []): array
    {
        $params = array_merge([
            'offset' => 0,
            'limit' => 100,
            'timeout' => 0,
            'allowed_updates' => []
        ], $options);

        return $this->request('getUpdates', $params);
    }

    /**
     * Banir usuário de um chat
     */
    public function banChatMember(
        string|int $chatId,
        int $userId,
        ?int $untilDate = null
    ): bool {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        if ($untilDate !== null) {
            $params['until_date'] = $untilDate;
        }

        $result = $this->request('banChatMember', $params);
        return $result['result'] ?? false;
    }

    /**
     * Desbanir usuário de um chat
     */
    public function unbanChatMember(
        string|int $chatId,
        int $userId,
        bool $onlyIfBanned = true
    ): bool {
        $result = $this->request('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned
        ]);

        return $result['result'] ?? false;
    }

    /**
     * Cria link de convite para chat
     */
    public function createChatInviteLink(
        string|int $chatId,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId
        ], $options);

        return $this->request('createChatInviteLink', $params);
    }

    /**
     * Faz requisição à API do Telegram
     */
    private function request(string $method, array $params = []): array
    {
        try {
            $this->logger->debug("Telegram API request", [
                'method' => $method,
                'params' => $params
            ]);

            $response = $this->client->post($method, [
                'json' => $params
            ]);

            $body = $response->getBody()->getContents();
            $result = json_decode($body, true);

            if ($result === null) {
                throw new ServiceException(
                    "Resposta inválida da API do Telegram",
                    500,
                    null,
                    'Telegram'
                );
            }

            if (!$result['ok']) {
                $this->logger->error("Telegram API error", [
                    'method' => $method,
                    'error_code' => $result['error_code'] ?? 'unknown',
                    'description' => $result['description'] ?? 'unknown'
                ]);

                throw new ServiceException(
                    $result['description'] ?? 'Erro desconhecido na API do Telegram',
                    $result['error_code'] ?? 500,
                    null,
                    'Telegram'
                );
            }

            $this->logger->debug("Telegram API response", [
                'method' => $method,
                'result' => $result
            ]);

            return $result;

        } catch (GuzzleException $e) {
            $this->logger->error("Telegram API request failed", [
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            throw ServiceException::unavailable('Telegram', $e->getMessage());
        }
    }

    /**
     * Cria instância do serviço para um bot específico
     */
    public function withToken(string $token): self
    {
        return new self($token, $this->logger);
    }

    /**
     * Verifica se um usuário é membro de um canal/grupo
     */
    public function isMemberOf(string|int $chatId, int $userId): bool
    {
        try {
            $member = $this->getChatMember($chatId, $userId);
            $status = $member['result']['status'] ?? '';
            
            return in_array($status, ['creator', 'administrator', 'member']);
        } catch (ServiceException) {
            return false;
        }
    }

    /**
     * Envia mensagem com botões inline
     */
    public function sendMessageWithInlineKeyboard(
        string|int $chatId,
        string $text,
        array $buttons,
        array $options = []
    ): array {
        $options['reply_markup'] = json_encode([
            'inline_keyboard' => $buttons
        ]);

        return $this->sendMessage($chatId, $text, $options);
    }

    /**
     * Envia mensagem com teclado de resposta
     */
    public function sendMessageWithReplyKeyboard(
        string|int $chatId,
        string $text,
        array $buttons,
        array $options = []
    ): array {
        $options['reply_markup'] = json_encode([
            'keyboard' => $buttons,
            'resize_keyboard' => true
        ]);

        return $this->sendMessage($chatId, $text, $options);
    }
}