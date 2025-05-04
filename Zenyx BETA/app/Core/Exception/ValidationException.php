<?php
declare(strict_types=1);

namespace App\Core\Exception;

use Exception;

/**
 * Exceção para erros de validação
 * 
 * Lançada quando dados não passam pelas regras de validação,
 * contendo detalhes dos campos e mensagens de erro
 */
class ValidationException extends Exception
{
    /**
     * @var array Array de erros de validação por campo
     */
    private array $errors;

    /**
     * Construtor da exceção de validação
     * 
     * @param string $message Mensagem principal da exceção
     * @param array $errors Array de erros por campo
     * @param int $code Código do erro
     * @param Exception|null $previous Exceção anterior
     */
    public function __construct(
        string $message = 'Erro de validação',
        array $errors = [],
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Obtém todos os erros de validação
     * 
     * @return array Array com todos os erros
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Verifica se há erros para um campo específico
     * 
     * @param string $field Nome do campo
     * @return bool True se houver erros
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Obtém erros de um campo específico
     * 
     * @param string $field Nome do campo
     * @return array|null Array de erros ou null
     */
    public function getError(string $field): ?array
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Obtém o primeiro erro de um campo
     * 
     * @param string $field Nome do campo
     * @return string|null Mensagem de erro ou null
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Obtém todos os erros como string formatada
     * 
     * @param string $separator Separador entre erros
     * @return string Erros formatados
     */
    public function getErrorsAsString(string $separator = "\n"): string
    {
        $messages = [];
        
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }
        
        return implode($separator, $messages);
    }

    /**
     * Obtém apenas as mensagens de erro (sem campos)
     * 
     * @return array Array simples de mensagens
     */
    public function getMessages(): array
    {
        $messages = [];
        
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        
        return $messages;
    }

    /**
     * Adiciona um erro a um campo
     * 
     * @param string $field Nome do campo
     * @param string $message Mensagem de erro
     * @return self
     */
    public function addError(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
        return $this;
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
            'code' => $this->getCode(),
            'errors' => $this->errors
        ];
    }

    /**
     * Obtém representação em JSON da exceção
     * 
     * @return string JSON com dados da exceção
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Verifica se a exceção tem erros
     * 
     * @return bool True se houver erros
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Conta total de erros
     * 
     * @return int Número total de erros
     */
    public function countErrors(): int
    {
        $count = 0;
        
        foreach ($this->errors as $fieldErrors) {
            $count += count($fieldErrors);
        }
        
        return $count;
    }

    /**
     * Limpa todos os erros
     * 
     * @return self
     */
    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }
}