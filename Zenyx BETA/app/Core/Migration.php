<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Classe base para migrações
 * 
 * Fornece métodos utilitários para criação e alteração de tabelas
 * seguindo boas práticas de migrações de banco de dados
 */
abstract class Migration
{
    protected PDO $pdo;
    protected string $table = '';
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(
        protected Database $db
    ) {
        $this->pdo = $db->getConnection();
    }

    /**
     * Executa a migração (método obrigatório)
     */
    abstract public function up(): void;

    /**
     * Reverte a migração (método obrigatório)
     */
    abstract public function down(): void;

    /**
     * Cria uma nova tabela
     */
    protected function create(string $table, callable $callback): void
    {
        $this->table = $table;
        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];

        $callback($this);

        $sql = "CREATE TABLE `{$table}` (\n";
        $sql .= implode(",\n", $this->columns);

        if (!empty($this->indexes)) {
            $sql .= ",\n" . implode(",\n", $this->indexes);
        }

        if (!empty($this->foreignKeys)) {
            $sql .= ",\n" . implode(",\n", $this->foreignKeys);
        }

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->execute($sql);
    }

    /**
     * Altera uma tabela existente
     */
    protected function alter(string $table, callable $callback): void
    {
        $this->table = $table;
        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];

        $callback($this);

        foreach ($this->columns as $column) {
            $sql = "ALTER TABLE `{$table}` ADD {$column}";
            $this->execute($sql);
        }

        foreach ($this->indexes as $index) {
            $sql = "ALTER TABLE `{$table}` ADD {$index}";
            $this->execute($sql);
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $sql = "ALTER TABLE `{$table}` ADD {$foreignKey}";
            $this->execute($sql);
        }
    }

    /**
     * Remove uma tabela
     */
    protected function drop(string $table): void
    {
        $this->execute("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Remove uma tabela se ela existir
     */
    protected function dropIfExists(string $table): void
    {
        $this->execute("DROP TABLE IF EXISTS `{$table}`");
    }

    /**
     * Renomeia uma tabela
     */
    protected function rename(string $from, string $to): void
    {
        $this->execute("RENAME TABLE `{$from}` TO `{$to}`");
    }

    /**
     * Adiciona coluna ID auto-incrementável
     */
    public function id(string $name = 'id'): self
    {
        $this->columns[] = "`{$name}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    /**
     * Adiciona coluna de string
     */
    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "`{$name}` VARCHAR({$length}) NOT NULL DEFAULT ''";
        return $this;
    }

    /**
     * Adiciona coluna de texto
     */
    public function text(string $name): self
    {
        $this->columns[] = "`{$name}` TEXT NULL";
        return $this;
    }

    /**
     * Adiciona coluna de texto longo
     */
    public function longText(string $name): self
    {
        $this->columns[] = "`{$name}` LONGTEXT NULL";
        return $this;
    }

    /**
     * Adiciona coluna de inteiro
     */
    public function integer(string $name, bool $unsigned = false): self
    {
        $type = $unsigned ? 'INT UNSIGNED' : 'INT';
        $this->columns[] = "`{$name}` {$type} NOT NULL DEFAULT 0";
        return $this;
    }

    /**
     * Adiciona coluna de bigint
     */
    public function bigInteger(string $name, bool $unsigned = false): self
    {
        $type = $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT';
        $this->columns[] = "`{$name}` {$type} NOT NULL DEFAULT 0";
        return $this;
    }

    /**
     * Adiciona coluna de tinyint
     */
    public function tinyInteger(string $name, bool $unsigned = false): self
    {
        $type = $unsigned ? 'TINYINT UNSIGNED' : 'TINYINT';
        $this->columns[] = "`{$name}` {$type} NOT NULL DEFAULT 0";
        return $this;
    }

    /**
     * Adiciona coluna booleana
     */
    public function boolean(string $name): self
    {
        $this->columns[] = "`{$name}` TINYINT(1) NOT NULL DEFAULT 0";
        return $this;
    }

    /**
     * Adiciona coluna decimal
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): self
    {
        $this->columns[] = "`{$name}` DECIMAL({$precision},{$scale}) NOT NULL DEFAULT 0.00";
        return $this;
    }

    /**
     * Adiciona coluna de data
     */
    public function date(string $name): self
    {
        $this->columns[] = "`{$name}` DATE NULL";
        return $this;
    }

    /**
     * Adiciona coluna de datetime
     */
    public function datetime(string $name): self
    {
        $this->columns[] = "`{$name}` DATETIME NULL";
        return $this;
    }

    /**
     * Adiciona coluna de timestamp
     */
    public function timestamp(string $name): self
    {
        $this->columns[] = "`{$name}` TIMESTAMP NULL";
        return $this;
    }

    /**
     * Adiciona colunas created_at e updated_at
     */
    public function timestamps(): self
    {
        $this->columns[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    /**
     * Adiciona coluna de enum
     */
    public function enum(string $name, array $values): self
    {
        $enumValues = array_map(fn($value) => "'{$value}'", $values);
        $enumString = implode(', ', $enumValues);
        $this->columns[] = "`{$name}` ENUM({$enumString}) NOT NULL";
        return $this;
    }

    /**
     * Adiciona coluna JSON
     */
    public function json(string $name): self
    {
        $this->columns[] = "`{$name}` JSON NULL";
        return $this;
    }

    /**
     * Define coluna como nullable
     */
    public function nullable(): self
    {
        $lastIndex = count($this->columns) - 1;
        if (isset($this->columns[$lastIndex])) {
            $this->columns[$lastIndex] = str_replace(' NOT NULL', ' NULL', $this->columns[$lastIndex]);
        }
        return $this;
    }

    /**
     * Define valor default para coluna
     */
    public function default(mixed $value): self
    {
        $lastIndex = count($this->columns) - 1;
        if (isset($this->columns[$lastIndex])) {
            if (is_string($value)) {
                $value = "'{$value}'";
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_null($value)) {
                $value = 'NULL';
            }
            
            $this->columns[$lastIndex] = preg_replace(
                '/DEFAULT .*$/',
                "DEFAULT {$value}",
                $this->columns[$lastIndex]
            );
        }
        return $this;
    }

    /**
     * Define coluna como única
     */
    public function unique(): self
    {
        $lastIndex = count($this->columns) - 1;
        if (isset($this->columns[$lastIndex]) && preg_match('/`([^`]+)`/', $this->columns[$lastIndex], $matches)) {
            $columnName = $matches[1];
            $this->indexes[] = "UNIQUE KEY `{$columnName}_unique` (`{$columnName}`)";
        }
        return $this;
    }

    /**
     * Adiciona índice
     */
    public function index(string $name, string|array $columns): self
    {
        if (is_array($columns)) {
            $columns = implode('`, `', $columns);
        }
        $this->indexes[] = "KEY `{$name}` (`{$columns}`)";
        return $this;
    }

    /**
     * Adiciona chave estrangeira
     */
    public function foreign(string $column): ForeignKeyBuilder
    {
        return new ForeignKeyBuilder($this, $column);
    }

    /**
     * Adiciona definição de chave estrangeira
     */
    public function addForeignKey(string $definition): void
    {
        $this->foreignKeys[] = $definition;
    }

    /**
     * Remove coluna
     */
    protected function dropColumn(string $table, string $column): void
    {
        $this->execute("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
    }

    /**
     * Remove índice
     */
    protected function dropIndex(string $table, string $indexName): void
    {
        $this->execute("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
    }

    /**
     * Remove chave estrangeira
     */
    protected function dropForeign(string $table, string $foreignKeyName): void
    {
        $this->execute("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$foreignKeyName}`");
    }

    /**
     * Executa SQL
     */
    protected function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * Executa query
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Construtor de chaves estrangeiras
 */
class ForeignKeyBuilder
{
    private string $column;
    private string $referenceTable;
    private string $referenceColumn;
    private string $onDelete = 'RESTRICT';
    private string $onUpdate = 'RESTRICT';
    private Migration $migration;

    public function __construct(Migration $migration, string $column)
    {
        $this->migration = $migration;
        $this->column = $column;
    }

    public function references(string $column): self
    {
        $this->referenceColumn = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->referenceTable = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function __destruct()
    {
        $constraintName = "{$this->migration->table}_{$this->column}_foreign";
        $definition = "CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$this->column}`) REFERENCES `{$this->referenceTable}` (`{$this->referenceColumn}`) ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
        
        $this->migration->addForeignKey($definition);
    }
}