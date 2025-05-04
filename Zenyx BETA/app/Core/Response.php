<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Classe de abstração de respostas HTTP
 * 
 * Gerencia respostas HTTP com suporte a diferentes tipos de conteúdo,
 * cabeçalhos, cookies e códigos de status
 */
final class Response
{
    private mixed $content = '';
    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];
    private string $protocolVersion = '1.1';

    // Códigos de status HTTP mais comuns
    private const HTTP_STATUSES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

    /**
     * Define o conteúdo da resposta
     */
    public function setContent(mixed $content): self
    {
        if (is_array($content) || is_object($content)) {
            $this->headers['Content-Type'] = 'application/json';
            $this->content = json_encode($content, JSON_THROW_ON_ERROR);
        } else {
            $this->content = (string) $content;
        }

        return $this;
    }

    /**
     * Obtém o conteúdo da resposta
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * Define o código de status HTTP
     */
    public function setStatusCode(int $code, ?string $text = null): self
    {
        $this->statusCode = $code;
        
        if ($text === null && !isset(self::HTTP_STATUSES[$code])) {
            throw new \InvalidArgumentException("Código de status HTTP inválido: {$code}");
        }

        return $this;
    }

    /**
     * Obtém o código de status HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Define um cabeçalho
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Define múltiplos cabeçalhos
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        return $this;
    }

    /**
     * Obtém um cabeçalho
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Obtém todos os cabeçalhos
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Define um cookie
     */
    public function cookie(
        string $name,
        string $value = '',
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expire' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite
        ];

        return $this;
    }

    /**
     * Remove um cookie
     */
    public function forgetCookie(string $name, string $path = '/', string $domain = ''): self
    {
        return $this->cookie($name, '', time() - 3600, $path, $domain);
    }

    /**
     * Envia a resposta para o cliente
     */
    public function send(): void
    {
        // Envia status
        $this->sendStatusLine();

        // Envia cabeçalhos
        $this->sendHeaders();

        // Envia cookies
        $this->sendCookies();

        // Envia conteúdo
        echo $this->content;

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Envia a linha de status HTTP
     */
    private function sendStatusLine(): void
    {
        $statusText = self::HTTP_STATUSES[$this->statusCode] ?? 'Unknown Status';
        header(sprintf('HTTP/%s %d %s', $this->protocolVersion, $this->statusCode, $statusText), true, $this->statusCode);
    }

    /**
     * Envia os cabeçalhos
     */
    private function sendHeaders(): void
    {
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", true);
        }
    }

    /**
     * Envia os cookies
     */
    private function sendCookies(): void
    {
        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                [
                    'expires' => $cookie['expire'],
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httpOnly'],
                    'samesite' => $cookie['sameSite']
                ]
            );
        }
    }

    /**
     * Cria uma resposta JSON
     */
    public function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return $this
            ->setStatusCode($status)
            ->withHeaders($headers)
            ->setContent($data);
    }

    /**
     * Cria uma resposta de redirecionamento
     */
    public function redirect(string $url, int $status = 302, array $headers = []): self
    {
        return $this
            ->setStatusCode($status)
            ->withHeaders(array_merge($headers, ['Location' => $url]))
            ->setContent('');
    }

    /**
     * Cria uma resposta de download
     */
    public function download(string $file, ?string $name = null, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Arquivo não encontrado: {$file}");
        }

        $name = $name ?? basename($file);
        $headers = array_merge([
            'Content-Description' => 'File Transfer',
            'Content-Type' => mime_content_type($file) ?: 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
            'Content-Transfer-Encoding' => 'binary',
            'Content-Length' => filesize($file),
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate',
            'Expires' => '0'
        ], $headers);

        return $this
            ->setStatusCode(200)
            ->withHeaders($headers)
            ->setContent(file_get_contents($file));
    }

    /**
     * Cria uma resposta de arquivo
     */
    public function file(string $file, array $headers = []): self
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Arquivo não encontrado: {$file}");
        }

        $headers = array_merge([
            'Content-Type' => mime_content_type($file) ?: 'application/octet-stream',
            'Content-Length' => filesize($file)
        ], $headers);

        return $this
            ->setStatusCode(200)
            ->withHeaders($headers)
            ->setContent(file_get_contents($file));
    }

    /**
     * Cria uma resposta vazia
     */
    public function noContent(int $status = 204): self
    {
        return $this
            ->setStatusCode($status)
            ->setContent('');
    }

    /**
     * Cria uma resposta de erro
     */
    public function error(string $message, int $status = 500, array $headers = []): self
    {
        return $this->json([
            'error' => true,
            'message' => $message
        ], $status, $headers);
    }

    /**
     * Cria uma resposta de sucesso
     */
    public function success(mixed $data = null, string $message = 'Success', int $status = 200): self
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Verifica se a resposta é informacional
     */
    public function isInformational(): bool
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Verifica se a resposta é de sucesso
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Verifica se a resposta é de redirecionamento
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Verifica se a resposta é de erro do cliente
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Verifica se a resposta é de erro do servidor
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Verifica se a resposta é de erro
     */
    public function isError(): bool
    {
        return $this->isClientError() || $this->isServerError();
    }

    /**
     * Verifica se a resposta é OK
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Verifica se a resposta é proibida
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Verifica se a resposta é não encontrada
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Define o tipo de conteúdo
     */
    public function withContentType(string $contentType): self
    {
        return $this->header('Content-Type', $contentType);
    }

    /**
     * Define cache control
     */
    public function withCache(int $seconds = 3600, array $options = []): self
    {
        $options = array_merge([
            'public' => true,
            'max_age' => $seconds
        ], $options);

        $cacheControl = [];
        
        if ($options['public']) {
            $cacheControl[] = 'public';
        } else {
            $cacheControl[] = 'private';
        }

        if (isset($options['max_age'])) {
            $cacheControl[] = "max-age={$options['max_age']}";
        }

        return $this->header('Cache-Control', implode(', ', $cacheControl));
    }

    /**
     * Desabilita cache
     */
    public function withoutCache(): self
    {
        return $this
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}