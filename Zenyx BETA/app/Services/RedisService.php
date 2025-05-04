<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Exception\ServiceException;
use Predis\Client as RedisClient;
use Predis\Connection\ConnectionException;

/**
 * Serviço de cache e armazenamento em memória com Redis
 * 
 * Gerencia cache, sessões e filas usando Redis
 */
final class RedisService
{
    private RedisClient $client;
    private string $prefix;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly ?string $password,
        private readonly int $database,
        private readonly Logger $logger,
        ?string $prefix = null
    ) {
        $this->prefix = $prefix ?? 'zenyx:';
        
        try {
            $options = [
                'scheme' => 'tcp',
                'host' => $this->host,
                'port' => $this->port,
                'database' => $this->database,
            ];

            if ($this->password !== null) {
                $options['password'] = $this->password;
            }

            $this->client = new RedisClient($options, [
                'prefix' => $this->prefix,
                'exceptions' => true
            ]);

            // Testa a conexão
            $this->client->ping();
            
        } catch (ConnectionException $e) {
            $this->logger->error('Redis connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->host,
                'port' => $this->port
            ]);
            
            throw ServiceException::unavailable('Redis', $e->getMessage());
        }
    }

    /**
     * Define um valor com TTL opcional
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $serialized = $this->serialize($value);
            
            if ($ttl !== null) {
                return $this->client->setex($key, $ttl, $serialized) === 'OK';
            }
            
            return $this->client->set($key, $serialized) === 'OK';
        } catch (\Exception $e) {
            $this->handleException($e, 'set', ['key' => $key]);
            return false;
        }
    }

    /**
     * Obtém um valor
     */
    public function get(string $key): mixed
    {
        try {
            $value = $this->client->get($key);
            
            if ($value === null) {
                return null;
            }
            
            return $this->unserialize($value);
        } catch (\Exception $e) {
            $this->handleException($e, 'get', ['key' => $key]);
            return null;
        }
    }

    /**
     * Deleta uma ou mais chaves
     */
    public function delete(string|array $keys): int
    {
        try {
            if (is_string($keys)) {
                $keys = [$keys];
            }
            
            return $this->client->del($keys);
        } catch (\Exception $e) {
            $this->handleException($e, 'delete', ['keys' => $keys]);
            return 0;
        }
    }

    /**
     * Verifica se uma chave existe
     */
    public function exists(string $key): bool
    {
        try {
            return (bool) $this->client->exists($key);
        } catch (\Exception $e) {
            $this->handleException($e, 'exists', ['key' => $key]);
            return false;
        }
    }

    /**
     * Incrementa um valor
     */
    public function increment(string $key, int $value = 1): int
    {
        try {
            return $this->client->incrby($key, $value);
        } catch (\Exception $e) {
            $this->handleException($e, 'increment', ['key' => $key, 'value' => $value]);
            return 0;
        }
    }

    /**
     * Decrementa um valor
     */
    public function decrement(string $key, int $value = 1): int
    {
        try {
            return $this->client->decrby($key, $value);
        } catch (\Exception $e) {
            $this->handleException($e, 'decrement', ['key' => $key, 'value' => $value]);
            return 0;
        }
    }

    /**
     * Define múltiplos valores
     */
    public function setMultiple(array $values, ?int $ttl = null): bool
    {
        try {
            $pipe = $this->client->pipeline();
            
            foreach ($values as $key => $value) {
                $serialized = $this->serialize($value);
                
                if ($ttl !== null) {
                    $pipe->setex($key, $ttl, $serialized);
                } else {
                    $pipe->set($key, $serialized);
                }
            }
            
            $results = $pipe->execute();
            
            return !in_array(false, $results, true);
        } catch (\Exception $e) {
            $this->handleException($e, 'setMultiple', ['keys' => array_keys($values)]);
            return false;
        }
    }

    /**
     * Obtém múltiplos valores
     */
    public function getMultiple(array $keys): array
    {
        try {
            $values = $this->client->mget($keys);
            $result = [];
            
            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? null;
                $result[$key] = $value !== null ? $this->unserialize($value) : null;
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->handleException($e, 'getMultiple', ['keys' => $keys]);
            return array_fill_keys($keys, null);
        }
    }

    /**
     * Define um valor em um hash
     */
    public function hashSet(string $key, string $field, mixed $value): bool
    {
        try {
            $serialized = $this->serialize($value);
            return (bool) $this->client->hset($key, $field, $serialized);
        } catch (\Exception $e) {
            $this->handleException($e, 'hashSet', ['key' => $key, 'field' => $field]);
            return false;
        }
    }

    /**
     * Obtém um valor de um hash
     */
    public function hashGet(string $key, string $field): mixed
    {
        try {
            $value = $this->client->hget($key, $field);
            
            if ($value === null) {
                return null;
            }
            
            return $this->unserialize($value);
        } catch (\Exception $e) {
            $this->handleException($e, 'hashGet', ['key' => $key, 'field' => $field]);
            return null;
        }
    }

    /**
     * Obtém todos os valores de um hash
     */
    public function hashGetAll(string $key): array
    {
        try {
            $values = $this->client->hgetall($key);
            $result = [];
            
            foreach ($values as $field => $value) {
                $result[$field] = $this->unserialize($value);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->handleException($e, 'hashGetAll', ['key' => $key]);
            return [];
        }
    }

    /**
     * Remove um campo de um hash
     */
    public function hashDelete(string $key, string|array $fields): int
    {
        try {
            if (is_string($fields)) {
                $fields = [$fields];
            }
            
            return $this->client->hdel($key, $fields);
        } catch (\Exception $e) {
            $this->handleException($e, 'hashDelete', ['key' => $key, 'fields' => $fields]);
            return 0;
        }
    }

    /**
     * Verifica se um campo existe em um hash
     */
    public function hashExists(string $key, string $field): bool
    {
        try {
            return (bool) $this->client->hexists($key, $field);
        } catch (\Exception $e) {
            $this->handleException($e, 'hashExists', ['key' => $key, 'field' => $field]);
            return false;
        }
    }

    /**
     * Adiciona um item a uma lista
     */
    public function listPush(string $key, mixed $value, bool $right = true): int
    {
        try {
            $serialized = $this->serialize($value);
            
            if ($right) {
                return $this->client->rpush($key, [$serialized]);
            }
            
            return $this->client->lpush($key, [$serialized]);
        } catch (\Exception $e) {
            $this->handleException($e, 'listPush', ['key' => $key, 'right' => $right]);
            return 0;
        }
    }

    /**
     * Remove e retorna um item de uma lista
     */
    public function listPop(string $key, bool $right = true): mixed
    {
        try {
            if ($right) {
                $value = $this->client->rpop($key);
            } else {
                $value = $this->client->lpop($key);
            }
            
            if ($value === null) {
                return null;
            }
            
            return $this->unserialize($value);
        } catch (\Exception $e) {
            $this->handleException($e, 'listPop', ['key' => $key, 'right' => $right]);
            return null;
        }
    }

    /**
     * Obtém todos os itens de uma lista
     */
    public function listGetAll(string $key): array
    {
        try {
            $values = $this->client->lrange($key, 0, -1);
            $result = [];
            
            foreach ($values as $value) {
                $result[] = $this->unserialize($value);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->handleException($e, 'listGetAll', ['key' => $key]);
            return [];
        }
    }

    /**
     * Define TTL para uma chave
     */
    public function expire(string $key, int $seconds): bool
    {
        try {
            return (bool) $this->client->expire($key, $seconds);
        } catch (\Exception $e) {
            $this->handleException($e, 'expire', ['key' => $key, 'seconds' => $seconds]);
            return false;
        }
    }

    /**
     * Obtém TTL de uma chave
     */
    public function ttl(string $key): int
    {
        try {
            return $this->client->ttl($key);
        } catch (\Exception $e) {
            $this->handleException($e, 'ttl', ['key' => $key]);
            return -1;
        }
    }

    /**
     * Busca chaves por padrão
     */
    public function keys(string $pattern = '*'): array
    {
        try {
            // Remove o prefixo do padrão se presente
            if (str_starts_with($pattern, $this->prefix)) {
                $pattern = substr($pattern, strlen($this->prefix));
            }
            
            return $this->client->keys($pattern);
        } catch (\Exception $e) {
            $this->handleException($e, 'keys', ['pattern' => $pattern]);
            return [];
        }
    }

    /**
     * Limpa todo o banco de dados atual
     */
    public function flushDb(): bool
    {
        try {
            return $this->client->flushdb() === 'OK';
        } catch (\Exception $e) {
            $this->handleException($e, 'flushDb');
            return false;
        }
    }

    /**
     * Executa comandos em pipeline
     */
    public function pipeline(callable $callback): array
    {
        try {
            $pipe = $this->client->pipeline();
            $callback($pipe);
            return $pipe->execute();
        } catch (\Exception $e) {
            $this->handleException($e, 'pipeline');
            return [];
        }
    }

    /**
     * Executa comandos em transação
     */
    public function transaction(callable $callback): array
    {
        try {
            $transaction = $this->client->transaction();
            $callback($transaction);
            return $transaction->execute();
        } catch (\Exception $e) {
            $this->handleException($e, 'transaction');
            return [];
        }
    }

    /**
     * Serializa um valor
     */
    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Desserializa um valor
     */
    private function unserialize(string $value): mixed
    {
        return unserialize($value);
    }

    /**
     * Trata exceções
     */
    private function handleException(\Exception $e, string $operation, array $context = []): void
    {
        $this->logger->error('Redis operation failed', [
            'operation' => $operation,
            'error' => $e->getMessage(),
            'context' => $context
        ]);

        if ($e instanceof ConnectionException) {
            throw ServiceException::unavailable('Redis', $e->getMessage());
        }
    }

    /**
     * Verifica a saúde do serviço
     */
    public function healthCheck(): bool
    {
        try {
            return $this->client->ping() === 'PONG';
        } catch (\Exception $e) {
            $this->logger->error('Redis health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém informações do servidor
     */
    public function info(?string $section = null): array
    {
        try {
            $info = $this->client->info($section);
            return $this->parseInfo($info);
        } catch (\Exception $e) {
            $this->handleException($e, 'info', ['section' => $section]);
            return [];
        }
    }

    /**
     * Parse informações do servidor Redis
     */
    private function parseInfo(string $info): array
    {
        $result = [];
        $lines = explode("\r\n", $info);
        $section = 'default';

        foreach ($lines as $line) {
            if (empty($line) || $line[0] === '#') {
                if ($line[0] === '#') {
                    $section = trim(substr($line, 1));
                }
                continue;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $result[$section][$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Lock distribuído
     */
    public function lock(string $key, int $ttl = 5): bool
    {
        $lockKey = "lock:{$key}";
        $identifier = uniqid();

        try {
            $result = $this->client->set($lockKey, $identifier, 'NX', 'EX', $ttl);
            return $result === 'OK';
        } catch (\Exception $e) {
            $this->handleException($e, 'lock', ['key' => $key, 'ttl' => $ttl]);
            return false;
        }
    }

    /**
     * Libera um lock
     */
    public function unlock(string $key): bool
    {
        $lockKey = "lock:{$key}";
        
        try {
            return (bool) $this->client->del([$lockKey]);
        } catch (\Exception $e) {
            $this->handleException($e, 'unlock', ['key' => $key]);
            return false;
        }
    }
}