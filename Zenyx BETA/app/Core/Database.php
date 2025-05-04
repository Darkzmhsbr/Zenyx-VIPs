<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Classe de conexão e manipulação de banco de dados
 * 
 * Gerencia conexões PDO com MySQL/MariaDB usando prepared statements
 * para segurança e performance
 */
final class Database
{
    private ?PDO $connection = null;
    private array $config;
    private int $transactionLevel = 0;
    private bool $transactionRollback = false;

    public function __construct(
        private readonly string $host,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 3306,
        private readonly string $charset = 'utf8mb4',
        private readonly array $options = []
    ) {
        $this->config = [
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'port' => $port,
            'charset' => $charset,
            'options' => array_merge($this->getDefaultOptions(), $options)
        ];
    }

    /**
     * Obtém a conexão PDO (singleton por requisição)
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Estabelece a conexão com o banco de dados
     * 
     * @throws DatabaseException
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );

            // Configurações adicionais pós-conexão
            $this->connection->exec("SET time_zone = '" . date('P') . "'");
            
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Falha na conexão com o banco de dados: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Opções padrão para PDO
     */
    private function getDefaultOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ];
    }

    /**
     * Executa uma query com prepared statement
     * 
     * @param string $sql Query SQL
     * @param array $params Parâmetros para bind
     * @return PDOStatement
     * @throws DatabaseException
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Erro ao executar query: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Executa uma query e retorna o número de linhas afetadas
     * 
     * @param string $sql Query SQL
     * @param array $params Parâmetros para bind
     * @return int Número de linhas afetadas
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Seleciona múltiplos registros
     * 
     * @param string $sql Query SQL
     * @param array $params Parâmetros para bind
     * @return array
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Seleciona um único registro
     * 
     * @param string $sql Query SQL
     * @param array $params Parâmetros para bind
     * @return array|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Insere um registro e retorna o ID
     * 
     * @param string $table Nome da tabela
     * @param array $data Dados para inserir
     * @return int ID do registro inserido
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->escapeIdentifier($table),
            implode(', ', array_map([$this, 'escapeIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        $this->execute($sql, array_values($data));
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Atualiza registros
     * 
     * @param string $table Nome da tabela
     * @param array $data Dados para atualizar
     * @param array $where Condições WHERE
     * @return int Número de linhas afetadas
     */
    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $set[] = sprintf('%s = ?', $this->escapeIdentifier($column));
            $params[] = $value;
        }
        
        $whereClause = $this->buildWhereClause($where, $params);
        
        $sql = sprintf(
            "UPDATE %s SET %s %s",
            $this->escapeIdentifier($table),
            implode(', ', $set),
            $whereClause
        );
        
        return $this->execute($sql, $params);
    }

    /**
     * Deleta registros
     * 
     * @param string $table Nome da tabela
     * @param array $where Condições WHERE
     * @return int Número de linhas afetadas
     */
    public function delete(string $table, array $where): int
    {
        $params = [];
        $whereClause = $this->buildWhereClause($where, $params);
        
        $sql = sprintf(
            "DELETE FROM %s %s",
            $this->escapeIdentifier($table),
            $whereClause
        );
        
        return $this->execute($sql, $params);
    }

    /**
     * Inicia uma transação
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $this->transactionRollback = false;
            $result = $this->getConnection()->beginTransaction();
        } else {
            $this->execute("SAVEPOINT LEVEL{$this->transactionLevel}");
            $result = true;
        }
        
        $this->transactionLevel++;
        return $result;
    }

    /**
     * Confirma uma transação
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 0) {
            throw new DatabaseException('Não há transação ativa para commit');
        }
        
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            if ($this->transactionRollback) {
                $this->getConnection()->rollBack();
                $this->transactionRollback = false;
                return false;
            }
            return $this->getConnection()->commit();
        } else {
            $this->execute("RELEASE SAVEPOINT LEVEL{$this->transactionLevel}");
            return true;
        }
    }

    /**
     * Desfaz uma transação
     */
    public function rollBack(): bool
    {
        if ($this->transactionLevel === 0) {
            throw new DatabaseException('Não há transação ativa para rollback');
        }
        
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            $this->transactionRollback = false;
            return $this->getConnection()->rollBack();
        } else {
            $this->execute("ROLLBACK TO SAVEPOINT LEVEL{$this->transactionLevel}");
            $this->transactionRollback = true;
            return true;
        }
    }

    /**
     * Retorna o último ID inserido
     */
    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Escapa um identificador (nome de tabela/coluna)
     */
    private function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Constrói cláusula WHERE
     */
    private function buildWhereClause(array $where, array &$params): string
    {
        if (empty($where)) {
            return '';
        }
        
        $conditions = [];
        
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                // Suporte para operadores especiais
                $operator = strtoupper($value[0]);
                $operand = $value[1];
                
                switch ($operator) {
                    case 'IN':
                        $placeholders = array_fill(0, count($operand), '?');
                        $conditions[] = sprintf(
                            '%s IN (%s)',
                            $this->escapeIdentifier($column),
                            implode(', ', $placeholders)
                        );
                        $params = array_merge($params, $operand);
                        break;
                        
                    case 'BETWEEN':
                        $conditions[] = sprintf(
                            '%s BETWEEN ? AND ?',
                            $this->escapeIdentifier($column)
                        );
                        $params[] = $operand[0];
                        $params[] = $operand[1];
                        break;
                        
                    case 'LIKE':
                        $conditions[] = sprintf(
                            '%s LIKE ?',
                            $this->escapeIdentifier($column)
                        );
                        $params[] = $operand;
                        break;
                        
                    default:
                        $conditions[] = sprintf(
                            '%s %s ?',
                            $this->escapeIdentifier($column),
                            $operator
                        );
                        $params[] = $operand;
                }
            } else {
                $conditions[] = sprintf('%s = ?', $this->escapeIdentifier($column));
                $params[] = $value;
            }
        }
        
        return 'WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * Bind de parâmetros
     */
    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };
            
            // Suporte para parâmetros nomeados e posicionais
            if (is_string($key)) {
                $stmt->bindValue(':' . $key, $value, $type);
            } else {
                $stmt->bindValue($key + 1, $value, $type);
            }
        }
    }

    /**
     * Prepara um statement
     */
    public function prepare(string $sql): PDOStatement
    {
        try {
            return $this->getConnection()->prepare($sql);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Erro ao preparar statement: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Executa uma query direta (cuidado com SQL injection!)
     */
    public function raw(string $sql): PDOStatement
    {
        try {
            return $this->getConnection()->query($sql);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Erro ao executar query raw: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Verifica se há transação ativa
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * Fecha a conexão
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * Ping para verificar se a conexão está ativa
     */
    public function ping(): bool
    {
        try {
            $this->getConnection()->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Retorna informações da versão do servidor
     */
    public function version(): string
    {
        return $this->selectOne('SELECT VERSION() as version')['version'] ?? 'Unknown';
    }
}