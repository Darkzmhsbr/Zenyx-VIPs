<?php
declare(strict_types=1);

return [
    'token' => $_ENV['PUSHINPAY_TOKEN'] ?? '',
    'webhook_secret' => $_ENV['PUSHINPAY_WEBHOOK_SECRET'] ?? '',
    'api_url' => $_ENV['PUSHINPAY_API_URL'] ?? 'https://api.pushinpay.com.br/api/v1',
    'pix_expiration_minutes' => (int)($_ENV['PUSHINPAY_PIX_EXPIRATION'] ?? 30),
    'webhook_version' => $_ENV['PUSHINPAY_WEBHOOK_VERSION'] ?? '1.0',
];