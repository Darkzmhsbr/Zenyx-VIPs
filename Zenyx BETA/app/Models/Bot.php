<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Model para gerenciamento de bots
 * 
 * Representa os bots criados pelos usuários no sistema Bot Zenyx
 */
final class Bot extends Model
{
    protected string $table = 'bots';
    
    protected array $fillable = [
        'user_id',
        'token',
        'username',
        'pushinpay_token',
        'webhook_url',
        'status',
        'settings',
        'welcome_message',
        'welcome_media',
        'channel_id',
        'group_id'
    ];

    protected array $casts = [
        'user_id' => 'integer',
        'settings' => 'json',
        'welcome_media' => 'json'
    ];

    /**
     * Status possíveis do bot
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ERROR = 'error';

    /**
     * Encontra bot pelo token
     */
    public function findByToken(string $token): ?array
    {
        return $this->findBy('token', $token);
    }

    /**
     * Encontra bot pelo username
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findBy('username', $username);
    }

    /**
     * Obtém bots de um usuário
     */
    public function getUserBots(int $userId): array
    {
        return $this->where(['user_id' => $userId], ['created_at' => 'DESC']);
    }

    /**
     * Cria novo bot
     */
    public function createBot(array $data): int
    {
        // Define status padrão
        if (!isset($data['status'])) {
            $data['status'] = self::STATUS_ACTIVE;
        }

        // Gera URL do webhook automaticamente
        if (!isset($data['webhook_url'])) {
            $data['webhook_url'] = $_ENV['APP_URL'] . '/webhook/telegram/' . uniqid();
        }

        return $this->create($data);
    }

    /**
     * Atualiza status do bot
     */
    public function updateStatus(int $botId, string $status): bool
    {
        $validStatuses = [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_ERROR
        ];

        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        return $this->update($botId, ['status' => $status]);
    }

    /**
     * Atualiza token PushinPay do bot
     */
    public function updatePushinPayToken(int $botId, string $token): bool
    {
        return $this->update($botId, ['pushinpay_token' => $token]);
    }

    /**
     * Atualiza mensagem de boas-vindas
     */
    public function updateWelcomeMessage(int $botId, string $message, ?array $media = null): bool
    {
        $data = ['welcome_message' => $message];
        
        if ($media !== null) {
            $data['welcome_media'] = $media;
        }

        return $this->update($botId, $data);
    }

    /**
     * Atualiza canal/grupo vinculado
     */
    public function updateChannel(int $botId, ?string $channelId, ?string $groupId = null): bool
    {
        return $this->update($botId, [
            'channel_id' => $channelId,
            'group_id' => $groupId
        ]);
    }

    /**
     * Obtém configurações do bot
     */
    public function getSettings(int $botId): array
    {
        $bot = $this->find($botId);
        return $bot['settings'] ?? [];
    }

    /**
     * Atualiza configurações do bot
     */
    public function updateSettings(int $botId, array $settings): bool
    {
        $bot = $this->find($botId);
        $currentSettings = $bot['settings'] ?? [];
        
        $newSettings = array_merge($currentSettings, $settings);
        
        return $this->update($botId, ['settings' => $newSettings]);
    }

    /**
     * Verifica se usuário é dono do bot
     */
    public function isOwner(int $botId, int $userId): bool
    {
        $bot = $this->find($botId);
        return $bot && $bot['user_id'] === $userId;
    }

    /**
     * Obtém bots ativos
     */
    public function getActiveBots(): array
    {
        return $this->where(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Obtém bots com erro
     */
    public function getErrorBots(): array
    {
        return $this->where(['status' => self::STATUS_ERROR]);
    }

    /**
     * Obtém estatísticas dos bots
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_bots,
                    COUNT(CASE WHEN status = ? THEN 1 END) as active_bots,
                    COUNT(CASE WHEN status = ? THEN 1 END) as inactive_bots,
                    COUNT(CASE WHEN status = ? THEN 1 END) as suspended_bots,
                    COUNT(CASE WHEN status = ? THEN 1 END) as error_bots,
                    COUNT(CASE WHEN pushinpay_token IS NOT NULL THEN 1 END) as configured_payment,
                    COUNT(CASE WHEN channel_id IS NOT NULL THEN 1 END) as configured_channel
                FROM {$this->table}";
        
        $result = $this->query($sql, [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_ERROR
        ]);
        
        return $result[0] ?? [];
    }

    /**
     * Limpa bots inativos há muito tempo
     */
    public function cleanupInactiveBots(int $days = 90): int
    {
        $sql = "DELETE FROM {$this->table} 
                WHERE status = ? 
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->execute($sql, [self::STATUS_INACTIVE, $days]);
    }

    /**
     * Verifica saúde dos bots
     */
    public function checkBotsHealth(): array
    {
        $sql = "SELECT 
                    b.*,
                    u.username as owner_username,
                    TIMESTAMPDIFF(MINUTE, b.updated_at, NOW()) as minutes_since_update
                FROM {$this->table} b
                JOIN users u ON b.user_id = u.id
                WHERE b.status = ?
                AND b.updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        
        return $this->query($sql, [self::STATUS_ACTIVE]);
    }

    /**
     * Obtém bots por receita gerada
     */
    public function getBotsByRevenue(int $limit = 10): array
    {
        $sql = "SELECT 
                    b.*,
                    u.username as owner_username,
                    COALESCE(SUM(p.amount), 0) as total_revenue,
                    COUNT(p.id) as total_payments
                FROM {$this->table} b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN payments p ON b.id = p.bot_id AND p.status = 'completed'
                GROUP BY b.id
                ORDER BY total_revenue DESC
                LIMIT ?";
        
        return $this->query($sql, [$limit]);
    }

    /**
     * Duplica configurações de um bot para outro
     */
    public function duplicateSettings(int $sourceBotId, int $targetBotId): bool
    {
        $sourceBot = $this->find($sourceBotId);
        
        if (!$sourceBot) {
            return false;
        }

        $settingsToCopy = [
            'settings' => $sourceBot['settings'],
            'welcome_message' => $sourceBot['welcome_message'],
            'welcome_media' => $sourceBot['welcome_media']
        ];

        return $this->update($targetBotId, $settingsToCopy);
    }
}