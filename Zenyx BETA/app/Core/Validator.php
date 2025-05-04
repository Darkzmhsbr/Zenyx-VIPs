<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ValidationException;

/**
 * Classe de validação de dados
 * 
 * Fornece métodos para validar diferentes tipos de dados
 * com regras customizáveis e mensagens de erro
 */
final class Validator
{
    private array $data = [];
    private array $rules = [];
    private array $errors = [];
    private array $messages = [];
    private array $customAttributes = [];

    private const DEFAULT_MESSAGES = [
        'required' => 'O campo :attribute é obrigatório.',
        'email' => 'O campo :attribute deve ser um e-mail válido.',
        'min' => 'O campo :attribute deve ter no mínimo :min caracteres.',
        'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
        'numeric' => 'O campo :attribute deve ser um número.',
        'integer' => 'O campo :attribute deve ser um número inteiro.',
        'float' => 'O campo :attribute deve ser um número decimal.',
        'string' => 'O campo :attribute deve ser um texto.',
        'array' => 'O campo :attribute deve ser um array.',
        'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
        'date' => 'O campo :attribute deve ser uma data válida.',
        'date_format' => 'O campo :attribute deve seguir o formato :format.',
        'url' => 'O campo :attribute deve ser uma URL válida.',
        'ip' => 'O campo :attribute deve ser um endereço IP válido.',
        'regex' => 'O campo :attribute possui formato inválido.',
        'in' => 'O campo :attribute deve ser um dos valores: :values.',
        'not_in' => 'O campo :attribute contém um valor inválido.',
        'confirmed' => 'A confirmação do campo :attribute não confere.',
        'unique' => 'O valor do campo :attribute já está em uso.',
        'exists' => 'O valor do campo :attribute não foi encontrado.',
        'between' => 'O campo :attribute deve estar entre :min e :max.',
        'size' => 'O campo :attribute deve ter tamanho :size.',
        'digits' => 'O campo :attribute deve ter :digits dígitos.',
        'alpha' => 'O campo :attribute deve conter apenas letras.',
        'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
        'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, traços e sublinhados.',
        'before' => 'O campo :attribute deve ser uma data anterior a :date.',
        'after' => 'O campo :attribute deve ser uma data posterior a :date.',
        'json' => 'O campo :attribute deve ser um JSON válido.',
        'file' => 'O campo :attribute deve ser um arquivo.',
        'image' => 'O campo :attribute deve ser uma imagem.',
        'mimes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
        'max_file_size' => 'O campo :attribute não pode ser maior que :max KB.',
    ];

    public function __construct(
        private ?Database $db = null
    ) {}

    /**
     * Valida os dados com as regras fornecidas
     * 
     * @throws ValidationException
     */
    public function validate(array $data, array $rules, array $messages = [], array $customAttributes = []): array
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = array_merge(self::DEFAULT_MESSAGES, $messages);
        $this->customAttributes = $customAttributes;
        $this->errors = [];

        foreach ($rules as $field => $ruleSet) {
            $this->validateField($field, $this->parseRules($ruleSet));
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Falha na validação dos dados', $this->errors);
        }

        return $this->getValidatedData();
    }

    /**
     * Valida um campo específico
     */
    private function validateField(string $field, array $rules): void
    {
        $value = $this->getValue($field);

        foreach ($rules as $rule) {
            $ruleName = $rule['rule'];
            $parameters = $rule['parameters'];

            if ($ruleName === 'nullable' && $this->isNullable($value)) {
                return;
            }

            if ($ruleName === 'sometimes' && !$this->hasField($field)) {
                return;
            }

            $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));

            if (method_exists($this, $method)) {
                if (!$this->$method($value, $parameters, $field)) {
                    $this->addError($field, $ruleName, $parameters);
                }
            } else {
                throw new \RuntimeException("Regra de validação '{$ruleName}' não existe");
            }
        }
    }

    /**
     * Parse das regras de validação
     */
    private function parseRules(string|array $rules): array
    {
        if (is_array($rules)) {
            return array_map(fn($rule) => $this->parseRule($rule), $rules);
        }

        return array_map(fn($rule) => $this->parseRule($rule), explode('|', $rules));
    }

    /**
     * Parse de uma regra individual
     */
    private function parseRule(string $rule): array
    {
        if (strpos($rule, ':') === false) {
            return ['rule' => $rule, 'parameters' => []];
        }

        [$ruleName, $parameters] = explode(':', $rule, 2);
        
        return [
            'rule' => $ruleName,
            'parameters' => explode(',', $parameters)
        ];
    }

    /**
     * Obtém valor de um campo (suporte a notação de ponto)
     */
    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Verifica se o campo existe
     */
    private function hasField(string $field): bool
    {
        $keys = explode('.', $field);
        $data = $this->data;

        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return false;
            }
            $data = $data[$key];
        }

        return true;
    }

    /**
     * Verifica se o valor é considerado nulo/vazio
     */
    private function isNullable(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Adiciona erro de validação
     */
    private function addError(string $field, string $rule, array $parameters): void
    {
        $message = $this->messages[$rule] ?? 'Erro de validação no campo :attribute';
        $attribute = $this->customAttributes[$field] ?? str_replace('_', ' ', $field);

        $message = str_replace(':attribute', $attribute, $message);

        foreach ($parameters as $i => $param) {
            $message = str_replace(':' . $i, $param, $message);
        }

        if (isset($parameters[0])) {
            $message = str_replace([':min', ':max', ':size', ':digits', ':date', ':format'], $parameters[0], $message);
        }

        if (count($parameters) > 1) {
            $message = str_replace(':values', implode(', ', $parameters), $message);
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Regra: required
     */
    private function validateRequired(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) < 1) {
            return false;
        }

        return true;
    }

    /**
     * Regra: email
     */
    private function validateEmail(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Regra: min
     */
    private function validateMin(mixed $value, array $parameters): bool
    {
        $min = (int) $parameters[0];

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * Regra: max
     */
    private function validateMax(mixed $value, array $parameters): bool
    {
        $max = (int) $parameters[0];

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * Regra: numeric
     */
    private function validateNumeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Regra: integer
     */
    private function validateInteger(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Regra: float
     */
    private function validateFloat(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * Regra: string
     */
    private function validateString(mixed $value): bool
    {
        return is_string($value);
    }

    /**
     * Regra: array
     */
    private function validateArray(mixed $value): bool
    {
        return is_array($value);
    }

    /**
     * Regra: boolean
     */
    private function validateBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    /**
     * Regra: date
     */
    private function validateDate(mixed $value): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return strtotime($value) !== false;
    }

    /**
     * Regra: date_format
     */
    private function validateDateFormat(mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $format = $parameters[0];
        $date = \DateTime::createFromFormat($format, $value);

        return $date && $date->format($format) === $value;
    }

    /**
     * Regra: url
     */
    private function validateUrl(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Regra: ip
     */
    private function validateIp(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Regra: regex
     */
    private function validateRegex(mixed $value, array $parameters): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match($parameters[0], $value) > 0;
    }

    /**
     * Regra: in
     */
    private function validateIn(mixed $value, array $parameters): bool
    {
        return in_array($value, $parameters, true);
    }

    /**
     * Regra: not_in
     */
    private function validateNotIn(mixed $value, array $parameters): bool
    {
        return !in_array($value, $parameters, true);
    }

    /**
     * Regra: confirmed
     */
    private function validateConfirmed(mixed $value, array $parameters, string $field): bool
    {
        return $value === $this->getValue($field . '_confirmation');
    }

    /**
     * Regra: unique
     */
    private function validateUnique(mixed $value, array $parameters): bool
    {
        if (!$this->db) {
            throw new \RuntimeException("Database necessário para validação 'unique'");
        }

        [$table, $column] = explode(',', $parameters[0]);
        $column = $column ?? $parameters[1] ?? 'id';
        $exceptId = $parameters[2] ?? null;

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $params = [$value];

        if ($exceptId) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $result = $this->db->query($sql, $params)->fetch();
        return $result['COUNT(*)'] === 0;
    }

    /**
     * Regra: exists
     */
    private function validateExists(mixed $value, array $parameters): bool
    {
        if (!$this->db) {
            throw new \RuntimeException("Database necessário para validação 'exists'");
        }

        [$table, $column] = explode(',', $parameters[0]);
        $column = $column ?? 'id';

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $result = $this->db->query($sql, [$value])->fetch();

        return $result['COUNT(*)'] > 0;
    }

    /**
     * Regra: json
     */
    private function validateJson(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Regra: alpha
     */
    private function validateAlpha(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z]+$/', $value);
    }

    /**
     * Regra: alpha_num
     */
    private function validateAlphaNum(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    /**
     * Regra: alpha_dash
     */
    private function validateAlphaDash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9_-]+$/', $value);
    }

    /**
     * Obtém dados validados
     */
    public function getValidatedData(): array
    {
        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if ($this->hasField($field)) {
                $validated[$field] = $this->getValue($field);
            }
        }

        return $validated;
    }

    /**
     * Verifica se há erros
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Obtém erros de validação
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Valida de forma estática
     */
    public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []): self
    {
        $validator = new self();
        $validator->validate($data, $rules, $messages, $customAttributes);
        return $validator;
    }
}