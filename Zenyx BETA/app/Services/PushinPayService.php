<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Exception\ServiceException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Serviço de integração com a API PushinPay
 * 
 * Gerencia pagamentos via PIX através da plataforma PushinPay
 */
final class PushinPayService
{
    private const API_BASE_URL = 'https://api.pushinpay.com.br/api/pix/v1/';
    private const TIMEOUT = 15;
    private const CONNECT_TIMEOUT = 5;

    private Client $client;

    public function __construct(
        private readonly string $token,
        private readonly Logger $logger,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => self::API_BASE_URL,
            'timeout' => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'http_errors' => false,
            'verify' => true,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Cria um QR Code PIX para pagamento
     */
    public function createPixQrCode(
        int $amount,
        string $externalReference,
        array $customer = [],
        ?string $description = null,
        ?int $expirationInMinutes = null
    ): array {
        $data = [
            'amount' => $amount,
            'external_reference' => $externalReference,
            'customer' => $customer,
            'description' => $description ?? 'Pagamento Bot Zenyx',
            'expiration_in_minutes' => $expirationInMinutes ?? 30,
            'notification_url' => $_ENV['APP_URL'] . '/webhook/pushinpay'
        ];

        return $this->request('POST', 'create_qrcode', $data);
    }

    /**
     * Consulta status de um pagamento
     */
    public function getPaymentStatus(string $transactionId): array
    {
        return $this->request('GET', "transactions/{$transactionId}");
    }

    /**
     * Lista transações
     */
    public function listTransactions(array $filters = []): array
    {
        $queryParams = http_build_query($filters);
        
        return $this->request('GET', "transactions?{$queryParams}");
    }

    /**
     * Cancela um pagamento pendente
     */
    public function cancelPayment(string $transactionId): array
    {
        return $this->request('POST', "transactions/{$transactionId}/cancel");
    }

    /**
     * Solicita um reembolso
     */
    public function refundPayment(string $transactionId, ?int $amount = null): array
    {
        $data = [];
        
        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        return $this->request('POST', "transactions/{$transactionId}/refund", $data);
    }

    /**
     * Obtém saldo da conta
     */
    public function getBalance(): array
    {
        return $this->request('GET', 'balance');
    }

    /**
     * Solicita saque
     */
    public function requestWithdrawal(int $amount, array $bankData): array
    {
        $data = [
            'amount' => $amount,
            'bank_data' => $bankData
        ];

        return $this->request('POST', 'withdrawals', $data);
    }

    /**
     * Lista saques
     */
    public function listWithdrawals(array $filters = []): array
    {
        $queryParams = http_build_query($filters);
        
        return $this->request('GET', "withdrawals?{$queryParams}");
    }

    /**
     * Valida webhook signature
     */
    public function validateWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Processa webhook
     */
    public function processWebhook(array $data, string $signature): array
    {
        // Valida assinatura
        $secret = $_ENV['PUSHINPAY_WEBHOOK_SECRET'] ?? '';
        $payload = json_encode($data);
        
        if (!$this->validateWebhookSignature($payload, $signature, $secret)) {
            throw new ServiceException(
                'Assinatura do webhook inválida',
                401,
                null,
                'PushinPay'
            );
        }

        $this->logger->info('PushinPay webhook received', [
            'event' => $data['event'] ?? 'unknown',
            'transaction_id' => $data['transaction_id'] ?? null
        ]);

        return $data;
    }

    /**
     * Gera link de pagamento
     */
    public function generatePaymentLink(
        int $amount,
        string $description,
        array $customer = [],
        ?int $expirationInMinutes = null
    ): string {
        $result = $this->createPixQrCode(
            $amount,
            uniqid('link_'),
            $customer,
            $description,
            $expirationInMinutes
        );

        return $result['payment_link'] ?? '';
    }

    /**
     * Faz requisição à API
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        try {
            $this->logger->debug("PushinPay API request", [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data
            ]);

            $options = [];
            
            if ($method === 'GET' && !empty($data)) {
                $endpoint .= '?' . http_build_query($data);
            } elseif ($method !== 'GET' && !empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            $result = json_decode($body, true);

            if ($result === null) {
                throw new ServiceException(
                    'Resposta inválida da API PushinPay',
                    502,
                    null,
                    'PushinPay'
                );
            }

            if ($statusCode >= 400) {
                $this->logger->error("PushinPay API error", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $statusCode,
                    'response' => $result
                ]);

                throw new ServiceException(
                    $result['message'] ?? 'Erro na API PushinPay',
                    $statusCode,
                    null,
                    'PushinPay',
                    ['response' => $result]
                );
            }

            $this->logger->debug("PushinPay API response", [
                'method' => $method,
                'endpoint' => $endpoint,
                'response' => $result
            ]);

            return $result;

        } catch (GuzzleException $e) {
            $this->logger->error("PushinPay API request failed", [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            throw ServiceException::unavailable('PushinPay', $e->getMessage());
        }
    }

    /**
     * Verifica status do serviço
     */
    public function healthCheck(): bool
    {
        try {
            $result = $this->getBalance();
            return isset($result['balance']);
        } catch (ServiceException) {
            return false;
        }
    }

    /**
     * Cria payload para QR Code estático
     */
    public function createStaticQrCode(
        string $pixKey,
        string $merchantName,
        string $merchantCity,
        ?float $amount = null,
        ?string $reference = null
    ): string {
        // Implementação do payload PIX estático
        // Este é um exemplo simplificado
        $payload = "00020126580014br.gov.bcb.pix0136{$pixKey}";
        
        if ($amount !== null) {
            $payload .= "54" . sprintf("%02d", strlen($amount)) . $amount;
        }
        
        $payload .= "5802BR";
        $payload .= "59" . sprintf("%02d", strlen($merchantName)) . $merchantName;
        $payload .= "60" . sprintf("%02d", strlen($merchantCity)) . $merchantCity;
        
        if ($reference !== null) {
            $payload .= "62" . sprintf("%02d", strlen($reference)) . $reference;
        }
        
        // Adiciona CRC16
        $payload .= "6304";
        $payload .= $this->calculateCRC16($payload);
        
        return $payload;
    }

    /**
     * Calcula CRC16 para payload PIX
     */
    private function calculateCRC16(string $payload): string
    {
        $polynomial = 0x1021;
        $result = 0xFFFF;

        if (($length = strlen($payload)) > 0) {
            for ($offset = 0; $offset < $length; $offset++) {
                $result ^= (ord($payload[$offset]) << 8);
                for ($bitwise = 0; $bitwise < 8; $bitwise++) {
                    if (($result <<= 1) & 0x10000) {
                        $result ^= $polynomial;
                    }
                    $result &= 0xFFFF;
                }
            }
        }
        
        return strtoupper(dechex($result));
    }

    /**
     * Formata valor em centavos para reais
     */
    public function formatCentsToReais(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Formata valor em reais para centavos
     */
    public function formatReaisToCents(float $reais): int
    {
        return (int) round($reais * 100);
    }
}