<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\DatabaseException;
use PDO;

/**
 * Gerenciador de Migrações
 * 
 * Controla a execução, reversão e status das migrações
 * de banco de dados, mantendo histórico e controle de versão
 */
final class MigrationManager
{
    private const MIGRATIONS_TABLE = 'migrations';
    private PDO $pdo;
    private string $migrationsPath;
    private Logger $logger;

    public function __construct(
        private Database $db,
        Logger $logger,
        ?string $migrationsPath = null
    ) {
        $this->pdo = $db->getConnection();
        $this->logger = $logger;
        $this->migrationsPath = $migrationsPath ?? dirname(__DIR__, 2) . '/database/migrations';
        
        $this->createMigrationsTable();
    }

    /**
     * Cria tabela de controle de migrações
     */
    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::MIGRATIONS_TABLE . "` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `migration_unique` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            throw new DatabaseException(
                "Erro ao criar tabela de migrações: " . $e->getMessage()
            );
        }
    }

    /**
     * Executa todas as migrações pendentes
     */
    public function migrate(): array
    {
        $pendingMigrations = $this->getPendingMigrations();
        
        if (empty($pendingMigrations)) {
            $this->logger->info('Nenhuma migração pendente');
            return ['message' => 'Nenhuma migração pendente', 'migrations' => []];
        }

        $batch = $this->getNextBatchNumber();
        $executedMigrations = [];

        foreach ($pendingMigrations as $migrationFile) {
            try {
                $this->runMigration($migrationFile, $batch);
                $executedMigrations[] = basename($migrationFile);
                
                $this->logger->info("Migração executada: " . basename($migrationFile));
            } catch (\Throwable $e) {
                $this->logger->error("Erro na migração: " . basename($migrationFile), [
                    'error' => $e->getMessage()
                ]);
                
                throw new DatabaseException(
                    "Erro ao executar migração " . basename($migrationFile) . ": " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return [
            'message' => count($executedMigrations) . ' migração(ões) executada(s) com sucesso',
            'migrations' => $executedMigrations
        ];
    }

    /**
     * Reverte última batch de migrações
     */
    public function rollback(): array
    {
        $lastBatch = $this->getLastBatch();
        
        if (!$lastBatch) {
            $this->logger->info('Nenhuma migração para reverter');
            return ['message' => 'Nenhuma migração para reverter', 'migrations' => []];
        }

        $migrationsToRollback = $this->getMigrationsByBatch($lastBatch);
        $rolledBackMigrations = [];

        foreach ($migrationsToRollback as $migration) {
            try {
                $this->rollbackMigration($migration['migration']);
                $rolledBackMigrations[] = $migration['migration'];
                
                $this->logger->info("Migração revertida: " . $migration['migration']);
            } catch (\Throwable $e) {
                $this->logger->error("Erro ao reverter migração: " . $migration['migration'], [
                    'error' => $e->getMessage()
                ]);
                
                throw new DatabaseException(
                    "Erro ao reverter migração " . $migration['migration'] . ": " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return [
            'message' => count($rolledBackMigrations) . ' migração(ões) revertida(s) com sucesso',
            'migrations' => $rolledBackMigrations
        ];
    }

    /**
     * Reverte todas as migrações
     */
    public function reset(): array
    {
        $allMigrations = $this->getAllMigrations();
        $resetMigrations = [];

        foreach ($allMigrations as $migration) {
            try {
                $this->rollbackMigration($migration['migration']);
                $resetMigrations[] = $migration['migration'];
                
                $this->logger->info("Migração resetada: " . $migration['migration']);
            } catch (\Throwable $e) {
                $this->logger->error("Erro ao resetar migração: " . $migration['migration'], [
                    'error' => $e->getMessage()
                ]);
                
                throw new DatabaseException(
                    "Erro ao resetar migração " . $migration['migration'] . ": " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return [
            'message' => count($resetMigrations) . ' migração(ões) resetada(s) com sucesso',
            'migrations' => $resetMigrations
        ];
    }

    /**
     * Reverte e executa todas as migrações novamente
     */
    public function refresh(): array
    {
        $resetResult = $this->reset();
        $migrateResult = $this->migrate();

        return [
            'message' => 'Banco de dados atualizado com sucesso',
            'reset' => $resetResult,
            'migrate' => $migrateResult
        ];
    }

    /**
     * Lista status das migrações
     */
    public function status(): array
    {
        $executedMigrations = $this->getExecutedMigrations();
        $allMigrations = $this->getAllMigrationFiles();
        $status = [];

        foreach ($allMigrations as $migration) {
            $migrationName = basename($migration, '.php');
            $status[] = [
                'migration' => $migrationName,
                'executed' => in_array($migrationName, $executedMigrations),
                'batch' => $this->getMigrationBatch($migrationName)
            ];
        }

        return $status;
    }

    /**
     * Obtém migrações pendentes
     */
    private function getPendingMigrations(): array
    {
        $executed = $this->getExecutedMigrations();
        $allFiles = $this->getAllMigrationFiles();
        
        return array_filter($allFiles, function($file) use ($executed) {
            return !in_array(basename($file, '.php'), $executed);
        });
    }

    /**
     * Obtém todas as migrações executadas
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query(
            "SELECT migration FROM " . self::MIGRATIONS_TABLE . " ORDER BY id"
        );
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Obtém todos os arquivos de migração
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            throw new DatabaseException("Diretório de migrações não encontrado: {$this->migrationsPath}");
        }

        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        
        return $files;
    }

    /**
     * Executa uma migração
     */
    private function runMigration(string $file, int $batch): void
    {
        $migration = $this->resolveMigration($file);
        
        $this->db->beginTransaction();
        
        try {
            $migration->up();
            $this->recordMigration(basename($file, '.php'), $batch);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Reverte uma migração
     */
    private function rollbackMigration(string $migrationName): void
    {
        $file = $this->migrationsPath . '/' . $migrationName . '.php';
        $migration = $this->resolveMigration($file);
        
        $this->db->beginTransaction();
        
        try {
            $migration->down();
            $this->removeMigrationRecord($migrationName);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Resolve classe de migração
     */
    private function resolveMigration(string $file): Migration
    {
        if (!file_exists($file)) {
            throw new DatabaseException("Arquivo de migração não encontrado: {$file}");
        }

        require_once $file;
        
        $className = $this->getMigrationClassName($file);
        
        if (!class_exists($className)) {
            throw new DatabaseException("Classe de migração não encontrada: {$className}");
        }

        return new $className($this->db);
    }

    /**
     * Obtém nome da classe da migração
     */
    private function getMigrationClassName(string $file): string
    {
        $filename = basename($file, '.php');
        $parts = explode('_', $filename);
        
        // Remove o timestamp (primeiro elemento)
        array_shift($parts);
        
        // Converte para CamelCase
        $className = implode('', array_map('ucfirst', $parts));
        
        return "Database\\Migrations\\{$className}";
    }

    /**
     * Registra migração executada
     */
    private function recordMigration(string $migration, int $batch): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . self::MIGRATIONS_TABLE . " (migration, batch) VALUES (?, ?)"
        );
        
        $stmt->execute([$migration, $batch]);
    }

    /**
     * Remove registro de migração
     */
    private function removeMigrationRecord(string $migration): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM " . self::MIGRATIONS_TABLE . " WHERE migration = ?"
        );
        
        $stmt->execute([$migration]);
    }

    /**
     * Obtém próximo número de batch
     */
    private function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query(
            "SELECT MAX(batch) as max_batch FROM " . self::MIGRATIONS_TABLE
        );
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_batch'] ?? 0) + 1;
    }

    /**
     * Obtém último batch
     */
    private function getLastBatch(): ?int
    {
        $stmt = $this->pdo->query(
            "SELECT MAX(batch) as last_batch FROM " . self::MIGRATIONS_TABLE
        );
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['last_batch'];
    }

    /**
     * Obtém migrações por batch
     */
    private function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . self::MIGRATIONS_TABLE . " WHERE batch = ? ORDER BY id DESC"
        );
        
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém todas as migrações
     */
    private function getAllMigrations(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM " . self::MIGRATIONS_TABLE . " ORDER BY id DESC"
        );
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém batch de uma migração
     */
    private function getMigrationBatch(string $migration): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT batch FROM " . self::MIGRATIONS_TABLE . " WHERE migration = ?"
        );
        
        $stmt->execute([$migration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int) $result['batch'] : null;
    }
}