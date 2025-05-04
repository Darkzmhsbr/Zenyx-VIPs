<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Classe de abstração de requisições HTTP
 * 
 * Fornece uma interface orientada a objetos para acessar
 * dados da requisição HTTP atual
 */
final class Request
{
    private array $query;
    private array $request;
    private array $attributes;
    private array $cookies;
    private array $files;
    private array $server;
    private ?array $content = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->content = $content;
    }

    /**
     * Cria uma instância a partir das variáveis globais
     */
    public static function capture(): self
    {
        return new self(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input')
        );
    }

    /**
     * Obtém o método HTTP
     */
    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Verifica se é um método específico
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Obtém a URI da requisição
     */
    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Obtém o path da requisição (sem query string)
     */
    public function getPath(): string
    {
        $uri = $this->getUri();
        $position = strpos($uri, '?');
        
        return $position === false ? $uri : substr($uri, 0, $position);
    }

    /**
     * Obtém parâmetros da query string
     */
    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Obtém dados do POST
     */
    public function post(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->request[$key] ?? $default;
    }

    /**
     * Obtém qualquer input (GET, POST, JSON)
     */
    public function input(string $key = null, mixed $default = null): mixed
    {
        $input = array_merge($this->query, $this->request, $this->getJsonContent());

        if ($key === null) {
            return $input;
        }

        // Suporte para notação de ponto (ex: 'user.name')
        $keys = explode('.', $key);
        $value = $input;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Obtém todos os inputs
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request, $this->getJsonContent());
    }

    /**
     * Obtém apenas os campos especificados
     */
    public function only(array $keys): array
    {
        $results = [];
        $input = $this->all();

        foreach ($keys as $key) {
            $results[$key] = $input[$key] ?? null;
        }

        return $results;
    }

    /**
     * Obtém todos exceto os campos especificados
     */
    public function except(array $keys): array
    {
        $results = $this->all();

        foreach ($keys as $key) {
            unset($results[$key]);
        }

        return $results;
    }

    /**
     * Verifica se um input existe
     */
    public function has(string|array $key): bool
    {
        $keys = is_array($key) ? $key : [$key];
        $input = $this->all();

        foreach ($keys as $k) {
            if (!array_key_exists($k, $input)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtém conteúdo JSON da requisição
     */
    public function getJsonContent(): array
    {
        if ($this->content === null) {
            return [];
        }

        if (is_array($this->content)) {
            return $this->content;
        }

        $contentType = $this->header('Content-Type');
        
        if (stripos($contentType, 'application/json') === false) {
            return [];
        }

        try {
            $this->content = json_decode($this->content, true, 512, JSON_THROW_ON_ERROR);
            return $this->content;
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * Obtém arquivo enviado
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Verifica se tem arquivo
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Obtém todos os arquivos
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * Obtém header específico
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        
        if ($key === 'HTTP_CONTENT_TYPE' && isset($this->server['CONTENT_TYPE'])) {
            return $this->server['CONTENT_TYPE'];
        }

        if ($key === 'HTTP_CONTENT_LENGTH' && isset($this->server['CONTENT_LENGTH'])) {
            return $this->server['CONTENT_LENGTH'];
        }

        return $this->server[$key] ?? $default;
    }

    /**
     * Obtém todos os headers
     */
    public function headers(): array
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerKey = str_replace('_', '-', substr($key, 5));
                $headers[$headerKey] = $value;
            }
        }

        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $this->server['CONTENT_TYPE'];
        }

        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['CONTENT-LENGTH'] = $this->server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Obtém cookie
     */
    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Obtém todos os cookies
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Obtém valor do servidor
     */
    public function server(string $key, ?string $default = null): ?string
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Obtém endereço IP do cliente
     */
    public function ip(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($this->server[$key])) {
                foreach (explode(',', $this->server[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Obtém User Agent
     */
    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * Verifica se é uma requisição AJAX
     */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Verifica se é uma requisição segura (HTTPS)
     */
    public function isSecure(): bool
    {
        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($this->server['HTTP_X_FORWARDED_PROTO']) && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Obtém o protocolo da requisição
     */
    public function getProtocol(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Obtém o host
     */
    public function getHost(): string
    {
        return $this->server['HTTP_HOST'] ?? 'localhost';
    }

    /**
     * Obtém a URL completa
     */
    public function fullUrl(): string
    {
        return $this->getProtocol() . '://' . $this->getHost() . $this->getUri();
    }

    /**
     * Verifica se é um método de leitura
     */
    public function isReadMethod(): bool
    {
        return in_array($this->getMethod(), ['GET', 'HEAD', 'OPTIONS']);
    }

    /**
     * Verifica se é um método de escrita
     */
    public function isWriteMethod(): bool
    {
        return in_array($this->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Obtém dados de autenticação básica
     */
    public function getBasicAuth(): ?array
    {
        $auth = $this->server['PHP_AUTH_USER'] ?? null;
        $pass = $this->server['PHP_AUTH_PW'] ?? null;

        if ($auth !== null && $pass !== null) {
            return ['username' => $auth, 'password' => $pass];
        }

        return null;
    }

    /**
     * Obtém o bearer token
     */
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        
        if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Define um atributo customizado
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Obtém um atributo customizado
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Obtém todos os atributos
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Verifica se aceita um tipo de conteúdo
     */
    public function accepts(string $contentType): bool
    {
        $accept = $this->header('Accept', '*/*');
        
        if ($accept === '*/*') {
            return true;
        }

        return str_contains($accept, $contentType);
    }

    /**
     * Verifica se aceita JSON
     */
    public function acceptsJson(): bool
    {
        return $this->accepts('application/json');
    }

    /**
     * Verifica se aceita HTML
     */
    public function acceptsHtml(): bool
    {
        return $this->accepts('text/html');
    }

    /**
     * Verifica se espera JSON
     */
    public function expectsJson(): bool
    {
        return $this->acceptsJson() && !$this->acceptsHtml();
    }
}