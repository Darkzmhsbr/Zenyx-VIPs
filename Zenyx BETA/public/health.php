<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Services\RedisService;

try {
    $app = Application::getInstance(__DIR__ . '/..');
    
    // Verifica conexão com banco de dados
    $db = $app->getContainer()->get(\App\Core\Database::class);
    $db->getConnection()->query('SELECT 1');
    
    // Verifica conexão com Redis
    $redis = $app->getContainer()->get(RedisService::class);
    $redis->ping();
    
    http_response_code(200);
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => time(),
        'services' => [
            'database' => 'ok',
            'redis' => 'ok'
        ]
    ]);
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'timestamp' => time(),
        'error' => $e->getMessage()
    ]);
}