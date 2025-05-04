<?php
declare(strict_types=1);

namespace App\Core\Exception;

use Exception;

/**
 * Exceção para erros de autenticação e autorização
 * 
 * Lançada quando ocorrem problemas relacionados a autenticação,
 * autorização, sessões ou permissões de usuário
 */
class AuthException extends Exception
{
    /**
     * Tipos de erro de autenticação
     */
    public const TYPE_INVALID_CREDENTIALS = 'invalid_credentials';
    public const TYPE_EXPIRED_SESSION = 'expired_session';
    public const TYPE_INVALID_TOKEN = 'invalid_token';
    public const TYPE_INSUFFICIENT_PERMISSIONS = 'insufficient_permissions';
    public const TYPE_ACCOUNT_DISABLED = 'account_disabled';
    public const TYPE_ACCOUNT_LOCKED = 'account_locked';
    public const TYPE_TWO_FACTOR_REQUIRED = 'two_factor_required';
    public const TYPE_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';

    /**
     * @var string Tipo do erro de autenticação
     */
    private string $errorType;

    /**
     * @var array Dados adicionais do erro
     */
    private array $metadata = [];

    /**
     * @var string|null Redirecionamento sugerido
     */
    private ?string $redirectTo = null;

    /**
     * Construtor da exceção de autenticação
     * 
     * @param string $message Mensagem de erro
     * @param string $errorType Tipo do erro
     * @param int $code Código HTTP
     * @param array $metadata Metadados adicionais
     * @param Exception|null $previous Exceção anterior
     */
    public function __construct(
        string $message = 'Erro de autenticação',
        string $errorType = self::TYPE_INVALID_CREDENTIALS,
        int $code = 401,
        array $metadata = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->errorType = $errorType;
        $this->metadata = $metadata;
    }

    /**
     * Obtém o tipo do erro
     * 
     * @return string
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Obtém os metadados do erro
     * 
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Define redirecionamento sugerido
     * 
     * @param string $url URL para redirecionamento
     * @return self
     */
    public function setRedirectTo(string $url): self
    {
        $this->redirectTo = $url;
        return $this;
    }

    /**
     * Obtém redirecionamento sugerido
     * 
     * @return string|null
     */
    public function getRedirectTo(): ?string
    {
        return $this->redirectTo;
    }

    /**
     * Cria exceção para credenciais inválidas
     * 
     * @param string|null $username Nome de usuário tentado
     * @return self
     */
    public static function invalidCredentials(?string $username = null): self
    {
        $metadata = [];
        if ($username !== null) {
            $metadata['username'] = $username;
        }
        
        return new self(
            'Credenciais inválidas',
            self::TYPE_INVALID_CREDENTIALS,
            401,
            $metadata
        );
    }

    /**
     * Cria exceção para sessão expirada
     * 
     * @return self
     */
    public static function sessionExpired(): self
    {
        $exception = new self(
            'Sua sessão expirou. Por favor, faça login novamente.',
            self::TYPE_EXPIRED_SESSION,
            401
        );
        
        return $exception->setRedirectTo('/login');
    }

    /**
     * Cria exceção para token inválido
     * 
     * @param string $tokenType Tipo do token (bearer, api, etc)
     * @return self
     */
    public static function invalidToken(string $tokenType = 'bearer'): self
    {
        return new self(
            'Token de autenticação inválido ou expirado',
            self::TYPE_INVALID_TOKEN,
            401,
            ['token_type' => $tokenType]
        );
    }

    /**
     * Cria exceção para permissões insuficientes
     * 
     * @param string|array $requiredPermissions Permissões necessárias
     * @return self
     */
    public static function insufficientPermissions(string|array $requiredPermissions): self
    {
        if (is_string($requiredPermissions)) {
            $requiredPermissions = [$requiredPermissions];
        }
        
        return new self(
            'Você não tem permissão para realizar esta ação',
            self::TYPE_INSUFFICIENT_PERMISSIONS,
            403,
            ['required_permissions' => $requiredPermissions]
        );
    }

    /**
     * Cria exceção para conta desabilitada
     * 
     * @param string|null $reason Motivo da desabilitação
     * @return self
     */
    public static function accountDisabled(?string $reason = null): self
    {
        $metadata = [];
        if ($reason !== null) {
            $metadata['reason'] = $reason;
        }
        
        return new self(
            'Sua conta está desabilitada',
            self::TYPE_ACCOUNT_DISABLED,
            403,
            $metadata
        );
    }

    /**
     * Cria exceção para conta bloqueada
     * 
     * @param int|null $unlockTime Timestamp para desbloqueio
     * @param string|null $reason Motivo do bloqueio
     * @return self
     */
    public static function accountLocked(?int $unlockTime = null, ?string $reason = null): self
    {
        $metadata = [];
        
        if ($unlockTime !== null) {
            $metadata['unlock_time'] = $unlockTime;
            $metadata['unlock_in'] = $unlockTime - time();
        }
        
        if ($reason !== null) {
            $metadata['reason'] = $reason;
        }
        
        $message = 'Sua conta está temporariamente bloqueada';
        
        if ($unlockTime !== null) {
            $minutes = ceil(($unlockTime - time()) / 60);
            $message .= ". Tente novamente em {$minutes} minuto(s)";
        }
        
        return new self(
            $message,
            self::TYPE_ACCOUNT_LOCKED,
            403,
            $metadata
        );
    }

    /**
     * Cria exceção para autenticação de dois fatores necessária
     * 
     * @param string $method Método de 2FA necessário
     * @return self
     */
    public static function twoFactorRequired(string $method = 'totp'): self
    {
        $exception = new self(
            'Autenticação de dois fatores necessária',
            self::TYPE_TWO_FACTOR_REQUIRED,
            403,
            ['method' => $method]
        );
        
        return $exception->setRedirectTo('/auth/two-factor');
    }

    /**
     * Cria exceção para limite de tentativas excedido
     * 
     * @param int $retryAfter Segundos para próxima tentativa
     * @param int $attempts Número de tentativas feitas
     * @return self
     */
    public static function rateLimitExceeded(int $retryAfter = 60, int $attempts = 0): self
    {
        $metadata = [
            'retry_after' => $retryAfter,
            'attempts' => $attempts
        ];
        
        $minutes = ceil($retryAfter / 60);
        
        return new self(
            "Muitas tentativas de login. Tente novamente em {$minutes} minuto(s)",
            self::TYPE_RATE_LIMIT_EXCEEDED,
            429,
            $metadata
        );
    }

    /**
     * Verifica se o erro é temporário
     * 
     * @return bool
     */
    public function isTemporary(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_EXPIRED_SESSION,
            self::TYPE_RATE_LIMIT_EXCEEDED,
            self::TYPE_ACCOUNT_LOCKED
        ]);
    }

    /**
     * Verifica se deve redirecionar
     * 
     * @return bool
     */
    public function shouldRedirect(): bool
    {
        return $this->redirectTo !== null;
    }

    /**
     * Obtém mensagem amigável para o usuário
     * 
     * @return string
     */
    public function getUserFriendlyMessage(): string
    {
        switch ($this->errorType) {
            case self::TYPE_INVALID_CREDENTIALS:
                return 'Usuário ou senha incorretos.';
            
            case self::TYPE_EXPIRED_SESSION:
                return 'Sua sessão expirou. Faça login novamente.';
            
            case self::TYPE_INVALID_TOKEN:
                return 'Token de acesso inválido ou expirado.';
            
            case self::TYPE_INSUFFICIENT_PERMISSIONS:
                return 'Você não tem permissão para acessar este recurso.';
            
            case self::TYPE_ACCOUNT_DISABLED:
                return 'Sua conta foi desabilitada. Entre em contato com o suporte.';
            
            case self::TYPE_ACCOUNT_LOCKED:
                return $this->getMessage(); // Já personalizada no método accountLocked()
            
            case self::TYPE_TWO_FACTOR_REQUIRED:
                return 'É necessário verificar sua identidade para continuar.';
            
            case self::TYPE_RATE_LIMIT_EXCEEDED:
                return $this->getMessage(); // Já personalizada no método rateLimitExceeded()
            
            default:
                return 'Erro de autenticação. Por favor, tente novamente.';
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
            'error_type' => $this->errorType,
            'code' => $this->getCode(),
            'metadata' => $this->metadata,
            'redirect_to' => $this->redirectTo,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Obtém headers HTTP sugeridos
     * 
     * @return array
     */
    public function getSuggestedHeaders(): array
    {
        $headers = [];
        
        if ($this->errorType === self::TYPE_RATE_LIMIT_EXCEEDED && isset($this->metadata['retry_after'])) {
            $headers['Retry-After'] = (string) $this->metadata['retry_after'];
        }
        
        if ($this->redirectTo !== null) {
            $headers['Location'] = $this->redirectTo;
        }
        
        if ($this->errorType === self::TYPE_INVALID_TOKEN) {
            $headers['WWW-Authenticate'] = 'Bearer error="invalid_token"';
        }
        
        return $headers;
    }

    /**
     * Verifica se deve bloquear conta
     * 
     * @return bool
     */
    public function shouldLockAccount(): bool
    {
        return $this->errorType === self::TYPE_RATE_LIMIT_EXCEEDED;
    }

    /**
     * Obtém representação em array da exceção
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'type' => $this->errorType,
            'message' => $this->getUserFriendlyMessage(),
            'code' => $this->getCode(),
            'metadata' => $this->metadata,
            'redirect_to' => $this->redirectTo,
            'is_temporary' => $this->isTemporary(),
            'headers' => $this->getSuggestedHeaders()
        ];
    }

    /**
     * Obtém representação em JSON da exceção
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}