<?php
declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Bot Zenyx',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
    
    'version' => '1.0.0',
    
    'bot' => [
        'max_bots_per_user' => (int)($_ENV['MAX_BOTS_PER_USER'] ?? 5),
        'webhook_timeout' => (int)($_ENV['WEBHOOK_TIMEOUT'] ?? 60),
    ],
    
    'payment' => [
        'min_amount' => (float)($_ENV['PAYMENT_MIN_AMOUNT'] ?? 5.00),
        'max_amount' => (float)($_ENV['PAYMENT_MAX_AMOUNT'] ?? 10000.00),
        'expiration_minutes' => (int)($_ENV['PAYMENT_EXPIRATION_MINUTES'] ?? 30),
    ],
    
    'referral' => [
        'commission_rate' => (float)($_ENV['REFERRAL_COMMISSION_RATE'] ?? 0.10),
        'minimum_sales' => (int)($_ENV['REFERRAL_MINIMUM_SALES'] ?? 3),
        'minimum_amount' => (float)($_ENV['REFERRAL_MINIMUM_AMOUNT'] ?? 29.70),
        'period_days' => (int)($_ENV['REFERRAL_PERIOD_DAYS'] ?? 15),
    ],
    
    'admin_vip' => [
        'price' => (float)($_ENV['ADMIN_VIP_PRICE'] ?? 97.90),
        'trial_days' => (int)($_ENV['ADMIN_VIP_TRIAL_DAYS'] ?? 30),
        'commission_rate' => (float)($_ENV['ADMIN_VIP_COMMISSION_RATE'] ?? 0.05),
    ],
];