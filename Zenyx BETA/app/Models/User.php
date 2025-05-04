<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Model para gerenciamento de usuários
 * 
 * Representa os usuários do sistema Bot Zenyx
 * com funcionalidades específicas para Telegram
 */
final class User extends Model
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'balance',
        'is_admin_vip',
        'admin_vip_until',
        'referrer_id',
        'channel_verified',
        'last_interaction',
        'settings',
        'status'
    ];

    protected array $casts = [
        'balance' => 'float',
        'is_admin_vip' => 'boolean',
        'channel_verified' => 'boolean',
        'admin_vip_until' => 'datetime',
        'last_interaction' => 'datetime',
        'settings' => 'json'
    ];

    /**
     * Encontra usuário pelo Telegram ID
     */
    public function findByTelegramId(string $telegramId): ?array
    {
        return $this->findBy('telegram_id', $telegramId);
    }

    /**
     * Cria ou atualiza usuário do Telegram
     */
    public function createOrUpdateFromTelegram(array $telegramData): array
    {
        $userData = [
            'telegram_id' => $telegramData['id'],
            'username' => $telegramData['username'] ?? null,
            'first_name' => $telegramData['first_name'] ?? null,
            'last_name' => $telegramData['last_name'] ?? null,
            'last_interaction' => date('Y-m-d H:i:s')
        ];

        return $this->updateOrCreate(
            ['telegram_id' => $telegramData['id']],
            $userData
        );
    }

    /**
     * Atualiza saldo do usuário
     */
    public function updateBalance(int $userId, float $amount, string $operation = 'add'): bool
    {
        $sql = match($operation) {
            'add' => "UPDATE {$this->table} SET balance = balance + ? WHERE id = ?",
            'subtract' => "UPDATE {$this->table} SET balance = balance - ? WHERE id = ?",
            'set' => "UPDATE {$this->table} SET balance = ? WHERE id = ?",
            default => throw new \InvalidArgumentException("Invalid operation: {$operation}")
        };

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([abs($amount), $userId]);
    }

    /**
     * Ativa status de Admin VIP
     */
    public function activateAdminVip(int $userId, ?\DateTime $until = null): bool
    {
        $data = [
            'is_admin_vip' => true,
            'admin_vip_until' => $until?->format('Y-m-d H:i:s')
        ];

        return $this->update($userId, $data);
    }

    /**
     * Desativa status de Admin VIP
     */
    public function deactivateAdminVip(int $userId): bool
    {
        return $this->update($userId, [
            'is_admin_vip' => false,
            'admin_vip_until' => null
        ]);
    }

    /**
     * Obtém referidos de um usuário
     */
    public function getReferrals(int $userId): array
    {
        return $this->where(['referrer_id' => $userId]);
    }

    /**
     * Conta referidos de um usuário
     */
    public function countReferrals(int $userId): int
    {
        return $this->count(['referrer_id' => $userId]);
    }

    /**
     * Obtém usuários Admin VIP ativos
     */
    public function getActiveAdminVips(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE is_admin_vip = 1 
                AND (admin_vip_until IS NULL OR admin_vip_until > NOW())";
        
        return $this->query($sql);
    }

    /**
     * Verifica se Admin VIP expirou e atualiza
     */
    public function checkAndUpdateExpiredAdminVips(): int
    {
        $sql = "UPDATE {$this->table} 
                SET is_admin_vip = 0 
                WHERE is_admin_vip = 1 
                AND admin_vip_until IS NOT NULL 
                AND admin_vip_until <= NOW()";
        
        return $this->execute($sql);
    }

    /**
     * Atualiza verificação de canal
     */
    public function updateChannelVerification(int $userId, bool $verified): bool
    {
        return $this->update($userId, ['channel_verified' => $verified]);
    }

    /**
     * Atualiza última interação
     */
    public function updateLastInteraction(int $userId): bool
    {
        return $this->update($userId, [
            'last_interaction' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtém configurações do usuário
     */
    public function getSettings(int $userId): array
    {
        $user = $this->find($userId);
        return $user['settings'] ?? [];
    }

    /**
     * Atualiza configurações do usuário
     */
    public function updateSettings(int $userId, array $settings): bool
    {
        $user = $this->find($userId);
        $currentSettings = $user['settings'] ?? [];
        
        $newSettings = array_merge($currentSettings, $settings);
        
        return $this->update($userId, ['settings' => $newSettings]);
    }

    /**
     * Obtém usuários inativos
     */
    public function getInactiveUsers(int $days = 30): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE last_interaction < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->query($sql, [$days]);
    }

    /**
     * Obtém estatísticas de usuários
     */
    public function getStatistics(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN is_admin_vip = 1 THEN 1 END) as total_vip,
                    COUNT(CASE WHEN channel_verified = 1 THEN 1 END) as verified_users,
                    COUNT(CASE WHEN last_interaction >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as active_today,
                    COUNT(CASE WHEN last_interaction >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_week,
                    COUNT(CASE WHEN last_interaction >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_month,
                    AVG(balance) as average_balance,
                    SUM(balance) as total_balance
                FROM {$this->table}";
        
        $result = $this->query($sql);
        return $result[0] ?? [];
    }

    /**
     * Busca usuários por critérios
     */
    public function search(array $criteria, int $limit = 50): array
    {
        $conditions = [];
        $params = [];

        if (!empty($criteria['query'])) {
            $query = '%' . $criteria['query'] . '%';
            $conditions[] = "(username LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR telegram_id LIKE ?)";
            $params = array_merge($params, [$query, $query, $query, $query]);
        }

        if (isset($criteria['is_admin_vip'])) {
            $conditions[] = "is_admin_vip = ?";
            $params[] = $criteria['is_admin_vip'] ? 1 : 0;
        }

        if (isset($criteria['channel_verified'])) {
            $conditions[] = "channel_verified = ?";
            $params[] = $criteria['channel_verified'] ? 1 : 0;
        }

        if (!empty($criteria['min_balance'])) {
            $conditions[] = "balance >= ?";
            $params[] = $criteria['min_balance'];
        }

        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        return $this->query($sql, $params);
    }

    /**
     * Obtém ranking de usuários por saldo
     */
    public function getBalanceRanking(int $limit = 10): array
    {
        $sql = "SELECT 
                    id,
                    telegram_id,
                    username,
                    first_name,
                    last_name,
                    balance,
                    (SELECT COUNT(*) + 1 
                     FROM {$this->table} u2 
                     WHERE u2.balance > u1.balance) as ranking
                FROM {$this->table} u1
                WHERE balance > 0
                ORDER BY balance DESC
                LIMIT ?";
        
        return $this->query($sql, [$limit]);
    }
}