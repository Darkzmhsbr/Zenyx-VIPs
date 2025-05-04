<?php
declare(strict_types=1);

/**
 * Bot Zenyx - Entry Point
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */

// Configurações de erro para produção
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Configurações de sessão segura
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', '3600');

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Autoloader do Composer
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use Dotenv\Dotenv;

try {
    // Carrega variáveis de ambiente
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    
    // Validação de variáveis de ambiente obrigatórias
    $dotenv->required([
        'APP_ENV',
        'APP_DEBUG',
        'APP_URL',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'REDIS_HOST',
        'REDIS_PORT',
        'TELEGRAM_BOT_TOKEN',
        'PUSHINPAY_TOKEN'
    ])->notEmpty();

    // Inicializa e executa a aplicação
    $app = Application::getInstance(dirname(__DIR__));
    $app->run();

} catch (Throwable $e) {
    // Em produção, registra o erro e mostra página genérica
    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        error_log(sprintf(
            "CRITICAL ERROR: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Please try again later.'
        ]);
    } else {
        // Em desenvolvimento, mostra erro detalhado
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]);
    }
    
    exit(1);
}