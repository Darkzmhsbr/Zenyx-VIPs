<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use App\Core\Exception\DatabaseException;

/**
 * Model base para todas as entidades
 * 
 * Fornece métodos CRUD básicos e funcionalidades comuns
 * para todas as models do sistema
 */
abstract class Model
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = ['id', 'created_at', 'updated_at'];
    protected bool $timestamps = true;
    protected array $casts = [];
    protected array $hidden = [];

    public function __construct(
        protected Database $db
    ) {
        $this->pdo = $db->getConnection();
    }

    /**
     * Encontra um registro pelo ID
     */
    public function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $this->processModel($result) : null;
    }

    /**
     * Encontra um registro por uma coluna específica
     */
    public function findBy(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$value]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $this->processModel($result) : null;
    }

    /**
     * Encontra múltiplos registros por condições
     */
    public function where(array $conditions, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $operator = $value[0];
                $val = $value[1];
                
                if (strtoupper($operator) === 'IN') {
                    $placeholders = array_fill(0, count($val), '?');
                    $where[] = "{$column} IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $val);
                } else {
                    $where[] = "{$column} {$operator} ?";
                    $params[] = $val;
                }
            } else {
                $where[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if (!empty($orderBy)) {
            $orderClauses = [];
            foreach ($orderBy as $column => $direction) {
                $orderClauses[] = "{$column} {$direction}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
            
            if ($offset !== null) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(fn($row) => $this->processModel($row), $results);
    }

    /**
     * Obtém todos os registros
     */
    public function all(array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->where([], $orderBy, $limit, $offset);
    }

    /**
     * Cria um novo registro
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza um registro
     */
    public function update(int|string $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $set = [];
        $params = [];

        foreach ($data as $column => $value) {
            $set[] = "{$column} = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->table,
            implode(', ', $set),
            $this->primaryKey
        );

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Deleta um registro
     */
    public function delete(int|string $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Conta registros com condições
     */
    public function count(array $conditions = []): int
    {
        $where = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica se existe um registro
     */
    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }

    /**
     * Cria ou atualiza um registro
     */
    public function updateOrCreate(array $conditions, array $data): array
    {
        $existing = $this->where($conditions);
        
        if (!empty($existing)) {
            $record = $existing[0];
            $this->update($record[$this->primaryKey], $data);
            return $this->find($record[$this->primaryKey]);
        }
        
        $id = $this->create(array_merge($conditions, $data));
        return $this->find($id);
    }

    /**
     * Incrementa um valor
     */
    public function increment(int|string $id, string $column, int $amount = 1): bool
    {
        $sql = "UPDATE {$this->table} SET {$column} = {$column} + ? WHERE {$this->primaryKey} = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$amount, $id]);
    }

    /**
     * Decrementa um valor
     */
    public function decrement(int|string $id, string $column, int $amount = 1): bool
    {
        $sql = "UPDATE {$this->table} SET {$column} = {$column} - ? WHERE {$this->primaryKey} = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$amount, $id]);
    }

    /**
     * Filtra dados fillable
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            // Se fillable estiver vazio, remove apenas campos guarded
            return array_diff_key($data, array_flip($this->guarded));
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Processa model após recuperação
     */
    protected function processModel(array $data): array
    {
        // Aplica cast nos campos
        foreach ($this->casts as $key => $type) {
            if (isset($data[$key])) {
                $data[$key] = $this->castAttribute($data[$key], $type);
            }
        }

        // Remove campos hidden
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Faz cast de atributo
     */
    protected function castAttribute(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => json_decode($value, true),
            'datetime' => new \DateTime($value),
            'date' => (new \DateTime($value))->format('Y-m-d'),
            'timestamp' => strtotime($value),
            default => $value
        };
    }

    /**
     * Converte array para JSON na inserção/atualização
     */
    protected function prepareForDb(array $data): array
    {
        foreach ($this->casts as $key => $type) {
            if (!isset($data[$key])) {
                continue;
            }

            if (in_array($type, ['array', 'json'])) {
                $data[$key] = json_encode($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Executa query personalizada
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executa statement personalizado
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * Inicia transação
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Confirma transação
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Desfaz transação
     */
    public function rollback(): void
    {
        $this->db->rollBack();
    }

    /**
     * Pagina resultados
     */
    public function paginate(int $page = 1, int $perPage = 15, array $conditions = [], array $orderBy = []): array
    {
        $total = $this->count($conditions);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        
        $data = $this->where($conditions, $orderBy, $perPage, $offset);
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }
}