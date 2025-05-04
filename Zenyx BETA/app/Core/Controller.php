<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Controller base da aplicação
 * 
 * Fornece funcionalidades comuns para todos os controllers,
 * incluindo validação, respostas e acesso aos serviços
 */
abstract class Controller
{
    protected Container $container;
    protected Request $request;
    protected Response $response;
    protected Database $db;
    protected Logger $logger;
    protected Validator $validator;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->request = $container->get(Request::class);
        $this->response = $container->get(Response::class);
        $this->db = $container->get(Database::class);
        $this->logger = $container->get(Logger::class);
        $this->validator = $container->get(Validator::class);
    }

    /**
     * Valida dados da requisição
     * 
     * @throws ValidationException
     */
    protected function validate(array $rules, array $messages = [], array $customAttributes = []): array
    {
        return $this->validator->validate(
            $this->request->all(),
            $rules,
            $messages,
            $customAttributes
        );
    }

    /**
     * Resposta JSON padrão
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return $this->response->json($data, $status, $headers);
    }

    /**
     * Resposta de sucesso
     */
    protected function success(mixed $data = null, string $message = 'Operação realizada com sucesso', int $status = 200): Response
    {
        return $this->response->success($data, $message, $status);
    }

    /**
     * Resposta de erro
     */
    protected function error(string $message, int $status = 500, array $data = []): Response
    {
        return $this->response->json([
            'success' => false,
            'error' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Resposta de validação inválida
     */
    protected function validationError(array $errors): Response
    {
        return $this->error('Dados inválidos', 422, ['errors' => $errors]);
    }

    /**
     * Resposta não encontrado
     */
    protected function notFound(string $message = 'Recurso não encontrado'): Response
    {
        return $this->error($message, 404);
    }

    /**
     * Resposta não autorizado
     */
    protected function unauthorized(string $message = 'Não autorizado'): Response
    {
        return $this->error($message, 401);
    }

    /**
     * Resposta proibido
     */
    protected function forbidden(string $message = 'Acesso negado'): Response
    {
        return $this->error($message, 403);
    }

    /**
     * Redirecionamento
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return $this->response->redirect($url, $status);
    }

    /**
     * Download de arquivo
     */
    protected function download(string $file, ?string $name = null): Response
    {
        return $this->response->download($file, $name);
    }

    /**
     * Obtém serviço do container
     */
    protected function get(string $service): mixed
    {
        return $this->container->get($service);
    }

    /**
     * Verifica se usuário está autenticado
     */
    protected function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Obtém ID do usuário autenticado
     */
    protected function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Obtém dados do usuário autenticado
     */
    protected function getUser(): ?array
    {
        $userId = $this->getUserId();
        
        if (!$userId) {
            return null;
        }

        $userModel = $this->get(\App\Models\User::class);
        return $userModel->find($userId);
    }

    /**
     * Verifica se usuário é admin
     */
    protected function isAdmin(): bool
    {
        $user = $this->getUser();
        return $user && ($user['is_admin'] ?? false);
    }

    /**
     * Exige autenticação
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \App\Core\Exception\AuthException('Autenticação necessária');
        }
    }

    /**
     * Exige privilégio de admin
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        
        if (!$this->isAdmin()) {
            throw new \App\Core\Exception\AuthException('Privilégios de administrador necessários');
        }
    }

    /**
     * Pagina resultados
     */
    protected function paginate(array $data, int $page = 1, int $perPage = 15, int $total = 0): array
    {
        $lastPage = (int) ceil($total / $perPage);
        
        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ]
        ];
    }

    /**
     * Log de ação
     */
    protected function logAction(string $action, array $context = []): void
    {
        $this->logger->info("Action: {$action}", array_merge([
            'user_id' => $this->getUserId(),
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent()
        ], $context));
    }

    /**
     * Cache de resposta
     */
    protected function cacheResponse(string $key, mixed $data, int $ttl = 3600): void
    {
        $redis = $this->get(\App\Services\RedisService::class);
        $redis->set($key, $data, $ttl);
    }

    /**
     * Obtém resposta do cache
     */
    protected function getCachedResponse(string $key): mixed
    {
        $redis = $this->get(\App\Services\RedisService::class);
        return $redis->get($key);
    }

    /**
     * Limpa cache
     */
    protected function clearCache(string|array $keys): void
    {
        $redis = $this->get(\App\Services\RedisService::class);
        
        if (is_array($keys)) {
            foreach ($keys as $key) {
                $redis->delete($key);
            }
        } else {
            $redis->delete($keys);
        }
    }

    /**
     * Valida CSRF token
     */
    protected function validateCsrfToken(): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        $requestToken = $this->request->input('_token') ?? $this->request->header('X-CSRF-TOKEN');

        return $sessionToken && $requestToken && hash_equals($sessionToken, $requestToken);
    }

    /**
     * Gera CSRF token
     */
    protected function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Sanitiza input de usuário
     */
    protected function sanitize(mixed $input): mixed
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }

        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }

        return $input;
    }

    /**
     * Upload de arquivo
     */
    protected function uploadFile(string $field, string $directory, array $allowedTypes = []): ?string
    {
        if (!$this->request->hasFile($field)) {
            return null;
        }

        $file = $this->request->file($field);
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erro no upload do arquivo');
        }

        if (!empty($allowedTypes)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedTypes)) {
                throw new \RuntimeException('Tipo de arquivo não permitido');
            }
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $destination = $directory . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Erro ao mover arquivo');
        }

        return $filename;
    }

    /**
     * Rate limiting
     */
    protected function checkRateLimit(string $key, int $limit = 60, int $window = 60): bool
    {
        $redis = $this->get(\App\Services\RedisService::class);
        $current = $redis->increment($key);
        
        if ($current === 1) {
            $redis->expire($key, $window);
        }

        return $current <= $limit;
    }

    /**
     * Transação do banco de dados
     */
    protected function transaction(callable $callback): mixed
    {
        $this->db->beginTransaction();

        try {
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}