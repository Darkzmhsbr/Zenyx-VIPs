<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ServiceException;
use Closure;

/**
 * Roteador da aplicação
 * 
 * Gerencia rotas, middlewares e despacha requisições para os controladores
 */
final class Router
{
    private array $routes = [];
    private array $middlewareGroups = [];
    private array $currentGroupMiddleware = [];
    private string $currentGroupPrefix = '';
    private array $patterns = [
        ':any' => '([^/]+)',
        ':num' => '([0-9]+)',
        ':all' => '(.*)',
        ':alpha' => '([a-zA-Z]+)',
        ':alphanum' => '([a-zA-Z0-9]+)',
        ':slug' => '([a-z0-9-]+)'
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * Registra uma rota GET
     */
    public function get(string $uri, string|array|Closure $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    /**
     * Registra uma rota POST
     */
    public function post(string $uri, string|array|Closure $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Registra uma rota PUT
     */
    public function put(string $uri, string|array|Closure $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Registra uma rota PATCH
     */
    public function patch(string $uri, string|array|Closure $action): self
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Registra uma rota DELETE
     */
    public function delete(string $uri, string|array|Closure $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Registra uma rota OPTIONS
     */
    public function options(string $uri, string|array|Closure $action): self
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Registra rotas para múltiplos métodos
     */
    public function match(array $methods, string $uri, string|array|Closure $action): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $uri, $action);
        }
        return $this;
    }

    /**
     * Registra rotas para todos os métodos
     */
    public function any(string $uri, string|array|Closure $action): self
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        return $this->match($methods, $uri, $action);
    }

    /**
     * Define um grupo de rotas
     */
    public function group(array $attributes, Closure $callback): void
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $previousGroupMiddleware = $this->currentGroupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->currentGroupPrefix = $previousGroupPrefix . '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) 
                ? $attributes['middleware'] 
                : [$attributes['middleware']];
            
            $this->currentGroupMiddleware = array_merge(
                $previousGroupMiddleware,
                $middleware
            );
        }

        $callback($this);

        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentGroupMiddleware = $previousGroupMiddleware;
    }

    /**
     * Registra middleware
     */
    public function middleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        $lastRoute = array_key_last($this->routes);
        if ($lastRoute !== null) {
            $this->routes[$lastRoute]['middleware'] = array_merge(
                $this->routes[$lastRoute]['middleware'] ?? [],
                $middleware
            );
        }

        return $this;
    }

    /**
     * Define um grupo de middleware
     */
    public function middlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Adiciona uma rota
     */
    private function addRoute(string $method, string $uri, string|array|Closure $action): self
    {
        $uri = $this->currentGroupPrefix . '/' . trim($uri, '/');
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');

        $route = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $this->currentGroupMiddleware,
            'pattern' => $this->convertUriToRegex($uri)
        ];

        $this->routes[] = $route;

        return $this;
    }

    /**
     * Converte URI para expressão regular
     */
    private function convertUriToRegex(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
        
        foreach ($this->patterns as $key => $value) {
            $pattern = str_replace($key, $value, $pattern);
        }

        return '#^' . $pattern . '$#';
    }

    /**
     * Despacha a requisição
     */
    public function dispatch(): Response
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Procura pela rota correspondente
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Remove índices numéricos das matches
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Executa middlewares
                $middleware = $this->resolveMiddleware($route['middleware']);
                $next = function() use ($route, $params) {
                    return $this->runAction($route['action'], $params);
                };

                foreach (array_reverse($middleware) as $m) {
                    $next = function() use ($m, $next) {
                        return $this->container->call([$m, 'handle'], ['next' => $next]);
                    };
                }

                return $next();
            }
        }

        // Rota não encontrada
        return $this->handleNotFound();
    }

    /**
     * Resolve os middlewares
     */
    private function resolveMiddleware(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $m) {
            if (isset($this->middlewareGroups[$m])) {
                $resolved = array_merge($resolved, $this->middlewareGroups[$m]);
            } else {
                $resolved[] = $m;
            }
        }

        return array_map(function($m) {
            return is_string($m) ? $this->container->get($m) : $m;
        }, $resolved);
    }

    /**
     * Executa a ação da rota
     */
    private function runAction(string|array|Closure $action, array $params): Response
    {
        if ($action instanceof Closure) {
            return $this->container->call($action, $params);
        }

        if (is_string($action)) {
            if (strpos($action, '@') !== false) {
                [$controller, $method] = explode('@', $action);
            } else {
                $controller = $action;
                $method = '__invoke';
            }
        } else {
            [$controller, $method] = $action;
        }

        $controllerClass = "App\\Controllers\\{$controller}";
        
        if (!class_exists($controllerClass)) {
            throw new ServiceException("Controller {$controllerClass} não encontrado");
        }

        $controllerInstance = $this->container->get($controllerClass);

        if (!method_exists($controllerInstance, $method)) {
            throw new ServiceException("Método {$method} não encontrado no controller {$controllerClass}");
        }

        return $this->container->call([$controllerInstance, $method], $params);
    }

    /**
     * Trata rota não encontrada
     */
    private function handleNotFound(): Response
    {
        $response = $this->container->get(Response::class);
        $response->setStatusCode(404);
        $response->setContent([
            'error' => 'Not Found',
            'message' => 'A rota solicitada não foi encontrada'
        ]);
        
        return $response;
    }

    /**
     * Registra rotas de recurso (CRUD)
     */
    public function resource(string $uri, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? ['index', 'store', 'show', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        
        $methods = array_diff($only, $except);

        if (in_array('index', $methods)) {
            $this->get($uri, "{$controller}@index");
        }

        if (in_array('store', $methods)) {
            $this->post($uri, "{$controller}@store");
        }

        if (in_array('show', $methods)) {
            $this->get("{$uri}/{id}", "{$controller}@show");
        }

        if (in_array('update', $methods)) {
            $this->put("{$uri}/{id}", "{$controller}@update");
            $this->patch("{$uri}/{id}", "{$controller}@update");
        }

        if (in_array('destroy', $methods)) {
            $this->delete("{$uri}/{id}", "{$controller}@destroy");
        }
    }

    /**
     * Registra rotas da API
     */
    public function apiResource(string $uri, string $controller): void
    {
        $this->resource($uri, $controller, [
            'except' => ['create', 'edit']
        ]);
    }

    /**
     * Lista todas as rotas registradas
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Adiciona um padrão customizado
     */
    public function pattern(string $key, string $pattern): void
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * Define rotas para Webhook
     */
    public function webhook(string $uri, string|array|Closure $action): self
    {
        return $this->post($uri, $action)
            ->middleware('webhook.validation');
    }
}