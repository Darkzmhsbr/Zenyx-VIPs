<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ServiceException;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Wrapper para o Monolog com funcionalidades adicionais
 * 
 * Facilita o uso de logs em toda a aplicação com diferentes handlers,
 * formatters e processadores
 */
final class Logger implements LoggerInterface
{
    private MonologLogger $monolog;
    private string $defaultChannel;
    private array $channels = [];
    private string $logPath;
    private bool $isDevelopment;

    public function __construct(
        string $defaultChannel = 'app',
        ?string $logPath = null,
        ?bool $isDevelopment = null
    ) {
        $this->defaultChannel = $defaultChannel;
        $this->logPath = $logPath ?? dirname(__DIR__, 2) . '/storage/logs';
        $this->isDevelopment = $isDevelopment ?? ($_ENV['APP_ENV'] ?? 'production') !== 'production';
        
        // Criar diretório de logs se não existir
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
        
        $this->monolog = $this->createChannel($defaultChannel);
    }

    /**
     * Cria um canal de log
     */
    public function createChannel(string $channel): MonologLogger
    {
        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }

        $logger = new MonologLogger($channel);
        
        // Configurar handlers
        $logger->pushHandler($this->createDefaultHandler($channel));
        
        if ($this->isDevelopment) {
            $logger->pushHandler($this->createDevelopmentHandler());
        }
        
        // Adicionar processadores
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new IntrospectionProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
        
        // Adicionar processador customizado
        $logger->pushProcessor(function ($record) {
            // Adicionar informações do usuário se disponível
            if (isset($_SESSION['user_id'])) {
                $record['extra']['user_id'] = $_SESSION['user_id'];
            }
            
            // Adicionar ID da requisição
            $record['extra']['request_id'] = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_', true);
            
            return $record;
        });
        
        $this->channels[$channel] = $logger;
        return $logger;
    }

    /**
     * Handler padrão para arquivos rotativos
     */
    private function createDefaultHandler(string $channel): HandlerInterface
    {
        $handler = new RotatingFileHandler(
            $this->logPath . '/' . $channel . '.log',
            30, // Manter 30 dias de logs
            $this->isDevelopment ? LogLevel::DEBUG : LogLevel::WARNING
        );
        
        $handler->setFormatter($this->createDefaultFormatter());
        
        return $handler;
    }

    /**
     * Handler para desenvolvimento (mais detalhado)
     */
    private function createDevelopmentHandler(): HandlerInterface
    {
        $handler = new StreamHandler(
            $this->logPath . '/development.log',
            LogLevel::DEBUG
        );
        
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s.u",
            true,
            true
        );
        
        $handler->setFormatter($formatter);
        
        return $handler;
    }

    /**
     * Formatter padrão
     */
    private function createDefaultFormatter(): LineFormatter
    {
        return new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            "Y-m-d H:i:s",
            true,
            true
        );
    }

    /**
     * Obtém um canal específico
     */
    public function channel(string $channel): MonologLogger
    {
        return $this->createChannel($channel);
    }

    /**
     * System is unusable.
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->alert($message, $context);
    }

    /**
     * Critical conditions.
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->warning($message, $context);
    }

    /**
     * Normal but significant events.
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->notice($message, $context);
    }

    /**
     * Interesting events.
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->info($message, $context);
    }

    /**
     * Detailed debug information.
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->monolog->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->monolog->log($level, $message, $context);
    }

    /**
     * Adiciona um handler customizado
     */
    public function pushHandler(HandlerInterface $handler): self
    {
        $this->monolog->pushHandler($handler);
        return $this;
    }

    /**
     * Adiciona um processador customizado
     */
    public function pushProcessor(ProcessorInterface|callable $processor): self
    {
        $this->monolog->pushProcessor($processor);
        return $this;
    }

    /**
     * Log de exceções com contexto detalhado
     */
    public function exception(\Throwable $exception, array $context = []): void
    {
        $this->error($exception->getMessage(), array_merge($context, [
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        ]));
    }

    /**
     * Log de queries SQL
     */
    public function query(string $sql, array $params = [], float $duration = 0.0): void
    {
        $this->debug('SQL Query', [
            'sql' => $sql,
            'params' => $params,
            'duration' => round($duration * 1000, 2) . 'ms'
        ]);
    }

    /**
     * Log de requisições HTTP
     */
    public function request(string $method, string $url, array $headers = [], mixed $body = null): void
    {
        $this->info('HTTP Request', [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body
        ]);
    }

    /**
     * Log de respostas HTTP
     */
    public function response(int $statusCode, array $headers = [], mixed $body = null, float $duration = 0.0): void
    {
        $logLevel = match(true) {
            $statusCode >= 500 => LogLevel::ERROR,
            $statusCode >= 400 => LogLevel::WARNING,
            default => LogLevel::INFO
        };

        $this->log($logLevel, 'HTTP Response', [
            'status_code' => $statusCode,
            'headers' => $headers,
            'body' => $body,
            'duration' => round($duration * 1000, 2) . 'ms'
        ]);
    }

    /**
     * Log de performance
     */
    public function performance(string $operation, float $duration, array $context = []): void
    {
        $this->info('Performance', array_merge($context, [
            'operation' => $operation,
            'duration' => round($duration * 1000, 2) . 'ms'
        ]));
    }

    /**
     * Log de auditoria
     */
    public function audit(string $action, string $resource, array $context = []): void
    {
        $this->channel('audit')->info('Audit Log', array_merge($context, [
            'action' => $action,
            'resource' => $resource,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]));
    }

    /**
     * Log de segurança
     */
    public function security(string $event, array $context = []): void
    {
        $this->channel('security')->warning('Security Event', array_merge($context, [
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]));
    }

    /**
     * Limpa logs antigos
     */
    public function cleanOldLogs(int $days = 30): void
    {
        $files = glob($this->logPath . '/*.log.*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) >= $days * 86400)) {
                unlink($file);
            }
        }
    }

    /**
     * Retorna estatísticas dos logs
     */
    public function getStats(): array
    {
        $stats = [];
        $files = glob($this->logPath . '/*.log');

        foreach ($files as $file) {
            $stats[basename($file)] = [
                'size' => filesize($file),
                'modified' => filemtime($file),
                'lines' => count(file($file))
            ];
        }

        return $stats;
    }
}