<?php
declare(strict_types=1);

namespace App\Core\Exception;

use Exception;

/**
 * Exceção para erros de serviço
 * 
 * Lançada quando ocorrem erros em serviços externos, APIs,
 * ou na camada de serviços da aplicação
 */
class ServiceException extends Exception
{
    /**
     * @var string|null Nome do serviço que falhou
     */
    private ?string $serviceName = null;

    /**
     * @var array Dados de contexto do erro
     */
    private array $context = [];

    /**
     * @var bool Indica se o erro é temporário
     */
    private bool $isTemporary = false;

    /**
     * @var int|null Tempo sugerido para retry (em segundos)
     */
    private ?int $retryAfter = null;

    /**
     * Construtor da exceção de serviço
     * 
     * @param string $message Mensagem de erro
     * @param int $code Código do erro
     * @param Exception|null $previous Exceção anterior
     * @param string|null $serviceName Nome do serviço
     * @param array $context Contexto adicional
     */
    public function __construct(
        string $message = 'Erro no serviço',
        int $code = 500,
        ?Exception $previous = null,
        ?string $serviceName = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->serviceName = $serviceName;
        $this->context = $context;
    }

    /**
     * Obtém o nome do serviço
     * 
     * @return string|null Nome do serviço
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    /**
     * Obtém o contexto do erro
     * 
     * @return array Contexto
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Define se o erro é temporário
     * 
     * @param bool $isTemporary
     * @param int|null $retryAfter Tempo em segundos para retry
     * @return self
     */
    public function setTemporary(bool $isTemporary = true, ?int $retryAfter = null): self
    {
        $this->isTemporary = $isTemporary;
        $this->retryAfter = $retryAfter;
        return $this;
    }

    /**
     * Verifica se o erro é temporário
     * 
     * @return bool
     */
    public function isTemporary(): bool
    {
        return $this->isTemporary;
    }

    /**
     * Obtém tempo sugerido para retry
     * 
     * @return int|null Tempo em segundos
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Cria exceção para timeout
     * 
     * @param string $serviceName Nome do serviço
     * @param int $timeout Timeout em segundos
     * @return self
     */
    public static function timeout(string $serviceName, int $timeout): self
    {
        $exception = new self(
            "Timeout ao acessar o serviço {$serviceName} após {$timeout} segundos",
            504,
            null,
            $serviceName,
            ['timeout' => $timeout]
        );
        
        return $exception->setTemporary(true, 5);
    }

    /**
     * Cria exceção para serviço indisponível
     * 
     * @param string $serviceName Nome do serviço
     * @param string|null $reason Motivo da indisponibilidade
     * @return self
     */
    public static function unavailable(string $serviceName, ?string $reason = null): self
    {
        $message = "O serviço {$serviceName} está temporariamente indisponível";
        
        if ($reason) {
            $message .= ": {$reason}";
        }
        
        $exception = new self(
            $message,
            503,
            null,
            $serviceName,
            ['reason' => $reason]
        );
        
        return $exception->setTemporary(true, 30);
    }

    /**
     * Cria exceção para erro de configuração
     * 
     * @param string $serviceName Nome do serviço
     * @param string $configKey Chave de configuração
     * @return self
     */
    public static function configurationError(string $serviceName, string $configKey): self
    {
        return new self(
            "Configuração inválida ou ausente para {$serviceName}: {$configKey}",
            500,
            null,
            $serviceName,
            ['config_key' => $configKey]
        );
    }

    /**
     * Cria exceção para erro de autenticação
     * 
     * @param string $serviceName Nome do serviço
     * @param string|null $details Detalhes adicionais
     * @return self
     */
    public static function authenticationError(string $serviceName, ?string $details = null): self
    {
        $message = "Falha na autenticação com o serviço {$serviceName}";
        
        if ($details) {
            $message .= ": {$details}";
        }
        
        return new self(
            $message,
            401,
            null,
            $serviceName,
            ['details' => $details]
        );
    }

    /**
     * Cria exceção para resposta inválida
     * 
     * @param string $serviceName Nome do serviço
     * @param string $reason Motivo da invalidez
     * @return self
     */
    public static function invalidResponse(string $serviceName, string $reason): self
    {
        return new self(
            "Resposta inválida do serviço {$serviceName}: {$reason}",
            502,
            null,
            $serviceName,
            ['reason' => $reason]
        );
    }

    /**
     * Cria exceção para limite de taxa excedido
     * 
     * @param string $serviceName Nome do serviço
     * @param int $retryAfter Tempo para retry em segundos
     * @return self
     */
    public static function rateLimitExceeded(string $serviceName, int $retryAfter = 60): self
    {
        $exception = new self(
            "Limite de requisições excedido para o serviço {$serviceName}",
            429,
            null,
            $serviceName,
            ['retry_after' => $retryAfter]
        );
        
        return $exception->setTemporary(true, $retryAfter);
    }

    /**
     * Obtém mensagem amigável para o usuário
     * 
     * @return string
     */
    public function getUserFriendlyMessage(): string
    {
        if ($this->isTemporary) {
            return 'O serviço está temporariamente indisponível. Por favor, tente novamente em alguns instantes.';
        }
        
        switch ($this->getCode()) {
            case 401:
            case 403:
                return 'Acesso não autorizado ao serviço.';
            case 404:
                return 'Recurso não encontrado no serviço.';
            case 429:
                return 'Muitas requisições. Por favor, aguarde um momento antes de tentar novamente.';
            case 500:
            case 502:
            case 503:
            case 504:
                return 'O serviço está enfrentando problemas técnicos. Nossa equipe foi notificada.';
            default:
                return 'Ocorreu um erro ao acessar o serviço. Por favor, tente novamente.';
        }
    }

    /**
     * Obtém detalhes para log
     * 
     * @return array
     */
    public function getLogDetails(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'service' => $this->serviceName,
            'context' => $this->context,
            'is_temporary' => $this->isTemporary,
            'retry_after' => $this->retryAfter,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Verifica se deve fazer retry
     * 
     * @return bool
     */
    public function shouldRetry(): bool
    {
        return $this->isTemporary || in_array($this->getCode(), [408, 429, 500, 502, 503, 504]);
    }

    /**
     * Obtém tempo de espera para retry
     * 
     * @param int $attempt Número da tentativa
     * @return int Tempo em segundos
     */
    public function getRetryDelay(int $attempt = 1): int
    {
        if ($this->retryAfter !== null) {
            return $this->retryAfter;
        }
        
        // Exponential backoff: 2^attempt * 1 segundo
        return (int) pow(2, $attempt);
    }

    /**
     * Obtém representação em array da exceção
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'user_message' => $this->getUserFriendlyMessage(),
            'code' => $this->getCode(),
            'service' => $this->serviceName,
            'context' => $this->context,
            'is_temporary' => $this->isTemporary,
            'retry_after' => $this->retryAfter,
            'should_retry' => $this->shouldRetry()
        ];
    }
}