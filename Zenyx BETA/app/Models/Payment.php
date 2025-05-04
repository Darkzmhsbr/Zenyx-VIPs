<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Model para gerenciamento de pagamentos
 * 
 * Representa os pagamentos realizados através dos bots
 */
final class Payment extends Model
{
    protected string $table = 'payments';
    
    protected array $fillable = [
        'user_id',
        'bot_id',
        'plan_id',
        'transaction_id',
        'external_reference',
        'amount',
        'status',
        'payment_method',
        'pix_code',
        'pix_qrcode',
        'paid_at',
        'expires_at',
        'metadata'
    ];

    protected array $casts = [
        'user_id' => 'integer',
        'bot_id' => 'integer',
        'plan_id' => 'integer',
        'amount' => 'float',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'json'
    ];

    /**
     * Status possíveis do pagamento
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Métodos de pagamento
     */
    public const METHOD_PIX = 'pix';
    public const METHOD_CREDIT_CARD = 'credit_card';
    public const METHOD_BOLETO = 'boleto';

    /**
     * Cria novo pagamento
     */
    public function createPayment(array $data): int
    {
        // Define valores padrão
        $data['status'] = $data['status'] ?? self::STATUS_PENDING;
        $data['payment_method'] = $data['payment_method'] ?? self::METHOD_PIX;
        
        if (!isset($data['external_reference'])) {
            $data['external_reference'] = uniqid('pay_');
        }

        return $this->create($data);
    }

    /**
     * Encontra pagamento por transaction_id
     */
    public function findByTransactionId(string $transactionId): ?array
    {
        return $this->findBy('transaction_id', $transactionId);
    }

    /**
     * Encontra pagamento por external_reference
     */
    public function findByExternalReference(string $reference): ?array
    {
        return $this->findBy('external_reference', $reference);
    }

    /**
     * Atualiza status do pagamento
     */
    public function updateStatus(int $paymentId, string $status, ?array $additionalData = null): bool
    {
        $data = ['status' => $status];
        
        if ($status === self::STATUS_COMPLETED && !isset($additionalData['paid_at'])) {
            $data['paid_at'] = date('Y-m-d H:i:s');
        }
        
        if ($additionalData) {
            $data = array_merge($data, $additionalData);
        }

        return $this->update($paymentId, $data);
    }

    /**
     * Marca pagamento como pago
     */
    public function markAsPaid(int $paymentId, ?string $transactionId = null): bool
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
            'paid_at' => date('Y-m-d H:i:s')
        ];

        if ($transactionId) {
            $data['transaction_id'] = $transactionId;
        }

        return $this->update($paymentId, $data);
    }

    /**
     * Marca pagamento como falhado
     */
    public function markAsFailed(int $paymentId, ?string $reason = null): bool
    {
        $data = ['status' => self::STATUS_FAILED];
        
        if ($reason) {
            $payment = $this->find($paymentId);
            $metadata = $payment['metadata'] ?? [];
            $metadata['failure_reason'] = $reason;
            $data['metadata'] = $metadata;
        }

        return $this->update($paymentId, $data);
    }

    /**
     * Obtém pagamentos de um usuário
     */
    public function getUserPayments(int $userId, array $filters = []): array
    {
        $conditions = ['user_id' => $userId];
        
        if (isset($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        
        if (isset($filters['bot_id'])) {
            $conditions['bot_id'] = $filters['bot_id'];
        }

        return $this->where($conditions, ['created_at' => 'DESC']);
    }

    /**
     * Obtém pagamentos de um bot
     */
    public function getBotPayments(int $botId, array $filters = []): array
    {
        $conditions = ['bot_id' => $botId];
        
        if (isset($filters['status'])) {
            $conditions['status'] = $filters['status'];
        }
        
        if (isset($filters['date_from'])) {
            $conditions['created_at'] = ['>=', $filters['date_from']];
        }
        
        if (isset($filters['date_to'])) {
            $conditions['created_at'] = ['<=', $filters['date_to']];
        }

        return $this->where($conditions, ['created_at' => 'DESC']);
    }

    /**
     * Obtém pagamentos pendentes expirados
     */
    public function getExpiredPendingPayments(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = ? 
                AND expires_at IS NOT NULL 
                AND expires_at <= NOW()";
        
        return $this->query($sql, [self::STATUS_PENDING]);
    }

    /**
     * Atualiza pagamentos expirados
     */
    public function updateExpiredPayments(): int
    {
        $sql = "UPDATE {$this->table} 
                SET status = ? 
                WHERE status = ? 
                AND expires_at IS NOT NULL 
                AND expires_at <= NOW()";
        
        return $this->execute($sql, [self::STATUS_EXPIRED, self::STATUS_PENDING]);
    }

    /**
     * Obtém relatório de pagamentos
     */
    public function getPaymentReport(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['bot_id'])) {
            $conditions[] = "bot_id = ?";
            $params[] = $filters['bot_id'];
        }

        if (isset($filters['user_id'])) {
            $conditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (isset($filters['date_from'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transactions,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions,
                    COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_transactions,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) as total_refunded,
                    AVG(CASE WHEN status = 'completed' THEN amount END) as average_ticket
                FROM {$this->table}
                {$whereClause}
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        return $this->query($sql, $params);
    }

    /**
     * Obtém estatísticas gerais de pagamentos
     */
    public function getStatistics(string $period = 'all'): array
    {
        $dateCondition = match($period) {
            'today' => "AND created_at >= CURDATE()",
            'week' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
            default => ""
        };

        $sql = "SELECT 
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
                    COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_payments,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) as total_refunded,
                    AVG(CASE WHEN status = 'completed' THEN amount END) as average_ticket,
                    MIN(CASE WHEN status = 'completed' THEN amount END) as min_payment,
                    MAX(CASE WHEN status = 'completed' THEN amount END) as max_payment
                FROM {$this->table}
                WHERE 1=1 {$dateCondition}";
        
        $result = $this->query($sql);
        return $result[0] ?? [];
    }

    /**
     * Obtém pagamentos por método de pagamento
     */
    public function getPaymentsByMethod(string $period = 'month'): array
    {
        $dateCondition = match($period) {
            'today' => "AND created_at >= CURDATE()",
            'week' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
            default => ""
        };

        $sql = "SELECT 
                    payment_method,
                    COUNT(*) as total_count,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as success_count,
                    ROUND((COUNT(CASE WHEN status = 'completed' THEN 1 END) / COUNT(*)) * 100, 2) as success_rate
                FROM {$this->table}
                WHERE 1=1 {$dateCondition}
                GROUP BY payment_method
                ORDER BY total_amount DESC";
        
        return $this->query($sql);
    }

    /**
     * Processa reembolso
     */
    public function processRefund(int $paymentId, float $refundAmount, string $reason = ''): bool
    {
        $payment = $this->find($paymentId);
        
        if (!$payment || $payment['status'] !== self::STATUS_COMPLETED) {
            return false;
        }

        if ($refundAmount > $payment['amount']) {
            return false;
        }

        $data = [
            'status' => self::STATUS_REFUNDED,
            'metadata' => array_merge($payment['metadata'] ?? [], [
                'refund_amount' => $refundAmount,
                'refund_reason' => $reason,
                'refund_date' => date('Y-m-d H:i:s'),
                'original_amount' => $payment['amount']
            ])
        ];

        return $this->update($paymentId, $data);
    }

    /**
     * Obtém pagamentos recentes
     */
    public function getRecentPayments(int $limit = 10): array
    {
        $sql = "SELECT 
                    p.*,
                    u.username as user_username,
                    b.username as bot_username,
                    pl.name as plan_name
                FROM {$this->table} p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN bots b ON p.bot_id = b.id
                LEFT JOIN plans pl ON p.plan_id = pl.id
                ORDER BY p.created_at DESC
                LIMIT ?";
        
        return $this->query($sql, [$limit]);
    }

    /**
     * Obtém taxa de conversão
     */
    public function getConversionRate(int $botId, string $period = 'month'): float
    {
        $dateCondition = match($period) {
            'today' => "AND created_at >= CURDATE()",
            'week' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
            default => ""
        };

        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                FROM {$this->table}
                WHERE bot_id = ? {$dateCondition}";
        
        $result = $this->query($sql, [$botId]);
        
        if (empty($result[0]) || $result[0]['total'] == 0) {
            return 0.0;
        }
        
        return ($result[0]['completed'] / $result[0]['total']) * 100;
    }

    /**
     * Obtém tempo médio para pagamento
     */
    public function getAveragePaymentTime(int $botId, string $period = 'month'): ?float
    {
        $dateCondition = match($period) {
            'today' => "AND created_at >= CURDATE()",
            'week' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'year' => "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
            default => ""
        };

        $sql = "SELECT 
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, paid_at)) as avg_time
                FROM {$this->table}
                WHERE bot_id = ? 
                AND status = 'completed'
                AND paid_at IS NOT NULL
                {$dateCondition}";
        
        $result = $this->query($sql, [$botId]);
        
        return $result[0]['avg_time'] ?? null;
    }
}