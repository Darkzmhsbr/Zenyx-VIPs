<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Model para gerenciamento de planos
 * 
 * Representa os planos de assinatura criados pelos bots
 */
final class Plan extends Model
{
    protected string $table = 'plans';
    
    protected array $fillable = [
        'bot_id',
        'name',
        'description',
        'price',
        'duration_type',
        'duration_value',
        'features',
        'is_active',
        'trial_days',
        'max_users'
    ];

    protected array $casts = [
        'bot_id' => 'integer',
        'price' => 'float',
        'duration_value' => 'integer',
        'is_active' => 'boolean',
        'features' => 'json',
        'trial_days' => 'integer',
        'max_users' => 'integer'
    ];

    /**
     * Tipos de duração disponíveis
     */
    public const DURATION_DAYS = 'days';
    public const DURATION_MONTHS = 'months';
    public const DURATION_YEARS = 'years';
    public const DURATION_LIFETIME = 'lifetime';

    /**
     * Obtém planos de um bot
     */
    public function getBotPlans(int $botId, bool $activeOnly = false): array
    {
        $conditions = ['bot_id' => $botId];
        
        if ($activeOnly) {
            $conditions['is_active'] = true;
        }

        return $this->where($conditions, ['price' => 'ASC']);
    }

    /**
     * Cria novo plano
     */
    public function createPlan(array $data): int
    {
        // Define valores padrão
        $data['is_active'] = $data['is_active'] ?? true;
        $data['trial_days'] = $data['trial_days'] ?? 0;
        
        // Valida tipo de duração
        if (isset($data['duration_type'])) {
            $validTypes = [
                self::DURATION_DAYS,
                self::DURATION_MONTHS,
                self::DURATION_YEARS,
                self::DURATION_LIFETIME
            ];
            
            if (!in_array($data['duration_type'], $validTypes)) {
                throw new \InvalidArgumentException("Invalid duration type: {$data['duration_type']}");
            }
        }

        return $this->create($data);
    }

    /**
     * Atualiza status do plano
     */
    public function updateStatus(int $planId, bool $isActive): bool
    {
        return $this->update($planId, ['is_active' => $isActive]);
    }

    /**
     * Clona um plano para outro bot
     */
    public function clonePlan(int $planId, int $targetBotId): ?int
    {
        $plan = $this->find($planId);
        
        if (!$plan) {
            return null;
        }

        $newPlanData = $plan;
        unset($newPlanData['id'], $newPlanData['created_at'], $newPlanData['updated_at']);
        $newPlanData['bot_id'] = $targetBotId;
        $newPlanData['name'] = $newPlanData['name'] . ' (Cópia)';
        
        return $this->create($newPlanData);
    }

    /**
     * Verifica se bot tem planos ativos
     */
    public function hasActivePlans(int $botId): bool
    {
        return $this->count(['bot_id' => $botId, 'is_active' => true]) > 0;
    }

    /**
     * Obtém plano mais vendido de um bot
     */
    public function getMostSoldPlan(int $botId): ?array
    {
        $sql = "SELECT 
                    p.*,
                    COUNT(pay.id) as total_sales,
                    SUM(CASE WHEN pay.status = 'completed' THEN 1 ELSE 0 END) as completed_sales,
                    SUM(CASE WHEN pay.status = 'completed' THEN pay.amount ELSE 0 END) as total_revenue
                FROM {$this->table} p
                LEFT JOIN payments pay ON p.id = pay.plan_id
                WHERE p.bot_id = ?
                GROUP BY p.id
                ORDER BY completed_sales DESC
                LIMIT 1";
        
        $result = $this->query($sql, [$botId]);
        return $result[0] ?? null;
    }

    /**
     * Obtém estatísticas dos planos
     */
    public function getPlanStatistics(int $planId): array
    {
        $sql = "SELECT 
                    p.*,
                    COUNT(DISTINCT pay.user_id) as unique_buyers,
                    COUNT(pay.id) as total_sales,
                    COUNT(CASE WHEN pay.status = 'completed' THEN 1 END) as completed_sales,
                    COUNT(CASE WHEN pay.status = 'pending' THEN 1 END) as pending_sales,
                    COUNT(CASE WHEN pay.status = 'failed' THEN 1 END) as failed_sales,
                    SUM(CASE WHEN pay.status = 'completed' THEN pay.amount ELSE 0 END) as total_revenue,
                    AVG(CASE WHEN pay.status = 'completed' THEN pay.amount END) as average_price
                FROM {$this->table} p
                LEFT JOIN payments pay ON p.id = pay.plan_id
                WHERE p.id = ?
                GROUP BY p.id";
        
        $result = $this->query($sql, [$planId]);
        return $result[0] ?? [];
    }

    /**
     * Calcula data de expiração baseado no plano
     */
    public function calculateExpirationDate(int $planId, ?\DateTime $startDate = null): ?\DateTime
    {
        $plan = $this->find($planId);
        
        if (!$plan) {
            return null;
        }

        $startDate = $startDate ?? new \DateTime();
        
        switch ($plan['duration_type']) {
            case self::DURATION_DAYS:
                $startDate->modify("+{$plan['duration_value']} days");
                break;
                
            case self::DURATION_MONTHS:
                $startDate->modify("+{$plan['duration_value']} months");
                break;
                
            case self::DURATION_YEARS:
                $startDate->modify("+{$plan['duration_value']} years");
                break;
                
            case self::DURATION_LIFETIME:
                return null; // Sem expiração
        }
        
        return $startDate;
    }

    /**
     * Obtém planos por faixa de preço
     */
    public function getPlansByPriceRange(float $minPrice, float $maxPrice, ?int $botId = null): array
    {
        $conditions = [
            'price' => ['>=', $minPrice],
            'is_active' => true
        ];
        
        $params = [$minPrice, $maxPrice];
        $sql = "SELECT * FROM {$this->table} 
                WHERE price >= ? AND price <= ? AND is_active = 1";
        
        if ($botId !== null) {
            $sql .= " AND bot_id = ?";
            $params[] = $botId;
        }
        
        $sql .= " ORDER BY price ASC";
        
        return $this->query($sql, $params);
    }

    /**
     * Obtém comparativo de planos
     */
    public function comparePlans(array $planIds): array
    {
        if (empty($planIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($planIds) - 1) . '?';
        
        $sql = "SELECT * FROM {$this->table} 
                WHERE id IN ({$placeholders})
                ORDER BY price ASC";
        
        return $this->query($sql, $planIds);
    }

    /**
     * Atualiza recursos do plano
     */
    public function updateFeatures(int $planId, array $features): bool
    {
        return $this->update($planId, ['features' => $features]);
    }

    /**
     * Obtém planos com período de teste
     */
    public function getPlansWithTrial(int $botId): array
    {
        return $this->where([
            'bot_id' => $botId,
            'trial_days' => ['>', 0],
            'is_active' => true
        ]);
    }

    /**
     * Obtém preço médio dos planos
     */
    public function getAveragePrice(int $botId): float
    {
        $sql = "SELECT AVG(price) as avg_price 
                FROM {$this->table} 
                WHERE bot_id = ? AND is_active = 1";
        
        $result = $this->query($sql, [$botId]);
        return (float) ($result[0]['avg_price'] ?? 0);
    }

    /**
     * Reordena planos
     */
    public function reorderPlans(int $botId, array $planIds): bool
    {
        $this->beginTransaction();
        
        try {
            foreach ($planIds as $index => $planId) {
                $this->update($planId, ['sort_order' => $index]);
            }
            
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * Obtém planos mais vendidos globalmente
     */
    public function getTopSellingPlans(int $limit = 10): array
    {
        $sql = "SELECT 
                    p.*,
                    b.username as bot_username,
                    COUNT(pay.id) as total_sales,
                    SUM(CASE WHEN pay.status = 'completed' THEN pay.amount ELSE 0 END) as total_revenue
                FROM {$this->table} p
                JOIN bots b ON p.bot_id = b.id
                LEFT JOIN payments pay ON p.id = pay.plan_id
                WHERE p.is_active = 1
                GROUP BY p.id
                HAVING total_sales > 0
                ORDER BY total_sales DESC
                LIMIT ?";
        
        return $this->query($sql, [$limit]);
    }
}