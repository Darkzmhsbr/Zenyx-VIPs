<?php
declare(strict_types=1);

namespace App\Core\Exception;

use Exception;

/**
 * Exceção para erros de banco de dados
 * 
 * Lançada quando ocorrem erros relacionados a operações
 * no banco de dados, conexão ou queries
 */
class DatabaseException extends Exception
{
    /**
     * @var string|null Query SQL que causou o erro
     */
    private ?string $sqlQuery = null;

    /**
     * @var array Parâmetros da query
     */
    private array $queryParams = [];

    /**
     * @var string|null Estado SQL do erro
     */
    private ?string $sqlState = null;

    /**
     * Construtor da exceção de banco de dados
     * 
     * @param string $message Mensagem de erro
     * @param int $code Código do erro
     * @param Exception|null $previous Exceção anterior (geralmente PDOException)
     * @param string|null $sqlQuery Query SQL que causou o erro
     * @param array $queryParams Parâmetros da query
     */
    public function __construct(
        string $message = 'Erro no banco de dados',
        int $code = 500,
        ?Exception $previous = null,
        ?string $sqlQuery = null,
        array $queryParams = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->sqlQuery = $sqlQuery;
        $this->queryParams = $queryParams;
        
        // Extrai o SQL State se for uma PDOException
        if ($previous instanceof \PDOException && isset($previous->errorInfo[0])) {
            $this->sqlState = $previous->errorInfo[0];
        }
    }

    /**
     * Obtém a query SQL que causou o erro
     * 
     * @return string|null Query SQL
     */
    public function getSqlQuery(): ?string
    {
        return $this->sqlQuery;
    }

    /**
     * Obtém os parâmetros da query
     * 
     * @return array Parâmetros
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Obtém o estado SQL do erro
     * 
     * @return string|null SQL State
     */
    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }

    /**
     * Verifica se é um erro de conexão
     * 
     * @return bool True se for erro de conexão
     */
    public function isConnectionError(): bool
    {
        return in_array($this->sqlState, ['08001', '08004', '08006', '08007', '08S01', 'HY000']);
    }

    /**
     * Verifica se é um erro de constraint
     * 
     * @return bool True se for erro de constraint
     */
    public function isConstraintError(): bool
    {
        return in_array($this->sqlState, ['23000', '23001', '23502', '23503', '23505', '23514']);
    }

    /**
     * Verifica se é um erro de sintaxe
     * 
     * @return bool True se for erro de sintaxe
     */
    public function isSyntaxError(): bool
    {
        return in_array($this->sqlState, ['42000', '42601', '42P01', '42P02']);
    }

    /**
     * Verifica se é um erro de duplicação
     * 
     * @return bool True se for erro de duplicação
     */
    public function isDuplicateError(): bool
    {
        return $this->sqlState === '23505' || $this->getCode() === 1062;
    }

    /**
     * Verifica se é um erro de deadlock
     * 
     * @return bool True se for erro de deadlock
     */
    public function isDeadlockError(): bool
    {
        return $this->sqlState === '40001' || $this->getCode() === 1213;
    }

    /**
     * Obtém descrição amigável do erro
     * 
     * @return string Descrição amigável
     */
    public function getFriendlyMessage(): string
    {
        if ($this->isConnectionError()) {
            return 'Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.';
        }
        
        if ($this->isDuplicateError()) {
            return 'Este registro já existe no sistema.';
        }
        
        if ($this->isConstraintError()) {
            return 'Não foi possível realizar a operação devido a restrições de dados.';
        }
        
        if ($this->isSyntaxError()) {
            return 'Erro na estrutura da consulta ao banco de dados.';
        }
        
        if ($this->isDeadlockError()) {
            return 'Operação temporariamente indisponível. Por favor, tente novamente.';
        }
        
        return 'Ocorreu um erro ao acessar o banco de dados.';
    }

    /**
     * Obtém detalhes do erro para log
     * 
     * @return array Detalhes do erro
     */
    public function getLogDetails(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'sql_state' => $this->sqlState,
            'query' => $this->sqlQuery,
            'params' => $this->queryParams,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Verifica se o erro deve ser retentado
     * 
     * @return bool True se deve ser retentado
     */
    public function isRetryable(): bool
    {
        return $this->isDeadlockError() || 
               $this->isConnectionError() || 
               $this->sqlState === '40P01'; // Deadlock detected
    }

    /**
     * Obtém sugestão de ação para o erro
     * 
     * @return string Sugestão de ação
     */
    public function getSuggestedAction(): string
    {
        if ($this->isConnectionError()) {
            return 'Verifique a conexão com o banco de dados e as credenciais de acesso.';
        }
        
        if ($this->isDuplicateError()) {
            return 'Verifique se não está tentando inserir um registro duplicado.';
        }
        
        if ($this->isConstraintError()) {
            return 'Verifique as restrições de chave estrangeira e campos obrigatórios.';
        }
        
        if ($this->isSyntaxError()) {
            return 'Revise a sintaxe da query SQL.';
        }
        
        if ($this->isDeadlockError()) {
            return 'Tente executar a operação novamente após alguns instantes.';
        }
        
        return 'Verifique os logs para mais detalhes sobre o erro.';
    }

    /**
     * Verifica se é um erro crítico
     * 
     * @return bool True se for erro crítico
     */
    public function isCritical(): bool
    {
        return $this->isConnectionError() || $this->isSyntaxError();
    }

    /**
     * Obtém representação em array da exceção
     * 
     * @return array Dados da exceção
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'friendly_message' => $this->getFriendlyMessage(),
            'code' => $this->getCode(),
            'sql_state' => $this->sqlState,
            'query' => $this->sqlQuery,
            'params' => $this->queryParams,
            'is_retryable' => $this->isRetryable(),
            'suggested_action' => $this->getSuggestedAction(),
            'is_critical' => $this->isCritical()
        ];
    }
}