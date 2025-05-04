<?php
declare(strict_types=1);

return [
    'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
    'channel_id' => $_ENV['TELEGRAM_CHANNEL_ID'] ?? '',
    'channel_username' => $_ENV['TELEGRAM_CHANNEL_USERNAME'] ?? '',
    'webhook_url' => $_ENV['TELEGRAM_WEBHOOK_URL'] ?? '',
    'max_connections' => (int)($_ENV['TELEGRAM_MAX_CONNECTIONS'] ?? 40),
    'allowed_updates' => ['message', 'callback_query', 'inline_query'],
    'parse_mode' => 'HTML',
];