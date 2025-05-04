<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ServiceException;
use App\Services\TelegramService;
use App\Services\PushinPayService;
use App\Services\RedisService;
use Dotenv\Dotenv;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Throwable;

/**
 * Classe principal da aplicação
 * 
 * Responsável por inicializar e gerenciar todo o ciclo de vida da aplicação
 */
final class Application
{
    private static ?self $instance = null;
    private Container $container;
    private Router $router;
    private Logger $logger;
    private array $config = [];
    
    private function __construct(
        private readonly string $basePath
    ) {
        $this->bootstrap();
    }

    /**
     * Singleton pattern para garantir única instância
     */
    public static function getInstance(string $basePath = ''): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }
        
        return self::$instance;
    }

    /**
     * Inicializa a aplicação
     */
    private function bootstrap(): void
    {
        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->configureErrorHandling();
        $this->initializeLogger();
        $this->initializeContainer();
        $this->registerCoreServices();
        $this->registerApplicationServices();
        $this->initializeRouter();
    }

    /**
     * Carrega variáveis de ambiente
     */
    private function loadEnvironment(): void
    {
        if (!file_exists($this->basePath . '/.env')) {
            throw new ServiceException('Arquivo .env não encontrado');
        }

        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->load();

        // Validar variáveis obrigatórias
        $dotenv->required([
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'REDIS_HOST',
            'TELEGRAM_BOT_TOKEN',
            'PUSHINPAY_TOKEN'
        ]);
    }

    /**
     * Carrega configurações da aplicação
     */
    private function loadConfiguration(): void
    {
        $configPath = $this->basePath . '/app/Config';
        
        foreach (glob($configPath . '/*.php') as $configFile) {
            $configName = basename($configFile, '.php');
            $this->config[$configName] = require $configFile;
        }
    }

    /**
     * Configura tratamento de erros
     */
    private function configureErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');
        ini_set('display_startup_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $this->basePath . '/storage/logs/php_errors.log');

        // Handler global de exceções
        set_exception_handler([$this, 'handleException']);
        
        // Handler global de erros
        set_error_handler([$this, 'handleError']);
        
        // Handler de shutdown
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Inicializa o sistema de logs
     */
    private function initializeLogger(): void
    {
        $logger = new MonologLogger('app');
        
        // Formato personalizado
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s",
            true,
            true
        );

        // Handler para logs diários
        $handler = new RotatingFileHandler(
            $this->basePath . '/storage/logs/app.log',
            30, // Manter logs por 30 dias
            $_ENV['APP_DEBUG'] === 'true' ? MonologLogger::DEBUG : MonologLogger::WARNING
        );
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);

        // Handler específico para erros
        $errorHandler = new StreamHandler(
            $this->basePath . '/storage/logs/error.log',
            MonologLogger::ERROR
        );
        $errorHandler->setFormatter($formatter);
        $logger->pushHandler($errorHandler);

        $this->logger = new Logger($logger);
    }

    /**
     * Inicializa o container de injeção de dependências
     */
    private function initializeContainer(): void
    {
        $this->container = new Container();
        $this->container->instance(Application::class, $this);
        $this->container->instance(Logger::class, $this->logger);
    }

    /**
     * Registra serviços core
     */
    private function registerCoreServices(): void
    {
        // Database
        $this->container->singleton(Database::class, function() {
            return new Database(
                host: $_ENV['DB_HOST'],
                database: $_ENV['DB_DATABASE'],
                username: $_ENV['DB_USERNAME'],
                password: $_ENV['DB_PASSWORD'],
                port: (int)($_ENV['DB_PORT'] ?? 3306)
            );
        });

        // Redis
        $this->container->singleton(RedisService::class, function() {
            return new RedisService(
                host: $_ENV['REDIS_HOST'],
                port: (int)($_ENV['REDIS_PORT'] ?? 6379),
                password: $_ENV['REDIS_PASSWORD'] ?? null,
                database: (int)($_ENV['REDIS_DATABASE'] ?? 0)
            );
        });

        // Request & Response
        $this->container->singleton(Request::class, function() {
            return Request::capture();
        });

        $this->container->singleton(Response::class, function() {
            return new Response();
        });
    }

    /**
     * Registra serviços da aplicação
     */
    private function registerApplicationServices(): void
    {
        // Telegram Service
        $this->container->singleton(TelegramService::class, function() {
            return new TelegramService(
                token: $_ENV['TELEGRAM_BOT_TOKEN'],
                logger: $this->logger
            );
        });

        // PushinPay Service
        $this->container->singleton(PushinPayService::class, function() {
            return new PushinPayService(
                token: $_ENV['PUSHINPAY_TOKEN'],
                logger: $this->logger
            );
        });

        // Registrar todos os Models
        $modelsPath = $this->basePath . '/app/Models';
        foreach (glob($modelsPath . '/*.php') as $modelFile) {
            $modelName = basename($modelFile, '.php');
            if ($modelName === 'Model') continue; // Pular classe base
            
            $modelClass = "App\\Models\\{$modelName}";
            $this->container->singleton($modelClass, function() use ($modelClass) {
                return new $modelClass($this->container->get(Database::class));
            });
        }
    }

    /**
     * Inicializa o roteador
     */
    private function initializeRouter(): void
    {
        $this->router = new Router($this->container);
        
        // Carregar rotas
        require $this->basePath . '/config/routes.php';
    }

    /**
     * Executa a aplicação
     */
    public function run(): void
    {
        try {
            $this->logger->info('Application started', [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            $response = $this->router->dispatch();
            $response->send();

            $this->logger->info('Request completed', [
                'status' => $response->getStatusCode(),
                'memory' => memory_get_peak_usage(true) / 1024 / 1024 . 'MB'
            ]);
        } catch (Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handler de exceções
     */
    public function handleException(Throwable $e): void
    {
        $this->logger->error('Uncaught exception', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        if ($_ENV['APP_ENV'] === 'production') {
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => 'An unexpected error occurred'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace()
            ]);
        }

        exit(1);
    }

    /**
     * Handler de erros
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $this->logger->error('PHP Error', [
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ]);

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Handler de shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->logger->critical('Fatal error on shutdown', [
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }

    /**
     * Getters
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getConfig(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? null;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}