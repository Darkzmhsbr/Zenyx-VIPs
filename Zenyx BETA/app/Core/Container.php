<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ServiceException;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Container de Injeção de Dependências
 * 
 * Gerencia o ciclo de vida dos objetos e suas dependências
 * Implementa o padrão Singleton, Factorys e resolução automática
 */
final class Container
{
    /**
     * Instâncias singleton registradas
     */
    private array $instances = [];

    /**
     * Bindings registrados (factories)
     */
    private array $bindings = [];

    /**
     * Aliases de interfaces para implementações
     */
    private array $aliases = [];

    /**
     * Registra um binding singleton
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Registra um binding factory
     */
    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Registra uma instância existente
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Registra um alias para uma interface
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolve uma dependência
     */
    public function get(string $abstract): mixed
    {
        try {
            return $this->resolve($abstract);
        } catch (ReflectionException $e) {
            throw new ServiceException(
                "Erro ao resolver dependência '{$abstract}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Verifica se um binding existe
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || 
               isset($this->instances[$abstract]) || 
               isset($this->aliases[$abstract]);
    }

    /**
     * Resolve uma dependência com parâmetros
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Chama um método injetando suas dependências
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            
            if (is_string($class)) {
                $class = $this->get($class);
            }
            
            $reflection = new ReflectionClass($class);
            $method = $reflection->getMethod($method);
            
            $dependencies = $this->resolveDependencies($method->getParameters(), $parameters);
            
            return $method->invokeArgs($class, $dependencies);
        }
        
        if (is_callable($callback)) {
            $reflection = new \ReflectionFunction($callback);
            $dependencies = $this->resolveDependencies($reflection->getParameters(), $parameters);
            
            return $callback(...$dependencies);
        }
        
        throw new ServiceException('Callback inválido fornecido');
    }

    /**
     * Resolve internamente uma dependência
     * 
     * @throws ReflectionException
     */
    private function resolve(string $abstract, array $parameters = []): mixed
    {
        // Resolver aliases
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        // Retornar instância se já existir
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Resolver binding se existir
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract]['concrete'];
            $shared = $this->bindings[$abstract]['shared'];

            // Se for closure, executar
            if ($concrete instanceof Closure) {
                $object = $concrete($this, $parameters);
            } else {
                $object = $this->build($concrete, $parameters);
            }

            // Se for singleton, armazenar instância
            if ($shared) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // Tentar resolver automaticamente
        return $this->build($abstract, $parameters);
    }

    /**
     * Constrói uma instância com suas dependências
     * 
     * @throws ReflectionException
     */
    private function build(string $concrete, array $parameters = []): object
    {
        $reflection = new ReflectionClass($concrete);

        // Verificar se pode ser instanciada
        if (!$reflection->isInstantiable()) {
            throw new ServiceException(
                "A classe '{$concrete}' não pode ser instanciada"
            );
        }

        $constructor = $reflection->getConstructor();

        // Se não tiver construtor, criar instância diretamente
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        // Resolver dependências do construtor
        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve dependências de parâmetros
     */
    private function resolveDependencies(array $reflectionParameters, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();

            // Se o parâmetro foi fornecido, usar
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Tentar resolver por tipo
            $type = $parameter->getType();
            
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                // Se tiver valor padrão, usar
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                // Se for nullable, passar null
                if ($type && $type->allowsNull()) {
                    $dependencies[] = null;
                    continue;
                }

                throw new ServiceException(
                    "Não foi possível resolver o parâmetro '{$name}'"
                );
            }

            // Resolver tipo complexo
            try {
                $dependencies[] = $this->get($type->getName());
            } catch (ServiceException $e) {
                // Se for opcional ou nullable, usar default/null
                if ($parameter->isOptional() || ($type && $type->allowsNull())) {
                    $dependencies[] = $parameter->isDefaultValueAvailable() 
                        ? $parameter->getDefaultValue() 
                        : null;
                } else {
                    throw $e;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Limpa todas as instâncias (útil para testes)
     */
    public function flush(): void
    {
        $this->instances = [];
    }

    /**
     * Remove um binding específico
     */
    public function forget(string $abstract): void
    {
        unset($this->bindings[$abstract]);
        unset($this->instances[$abstract]);
        unset($this->aliases[$abstract]);
    }

    /**
     * Retorna todas as bindings registradas
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Retorna todas as instâncias
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /**
     * Verifica se uma classe é resolvível
     */
    public function isResolvable(string $abstract): bool
    {
        try {
            $this->resolve($abstract);
            return true;
        } catch (ServiceException) {
            return false;
        }
    }
}