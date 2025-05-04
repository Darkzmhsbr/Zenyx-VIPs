<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\RedisService;
use App\Models\User;
use App\Core\Exception\AuthException;

/**
 * Middleware de autenticação
 * 
 * Verifica se o usuário está autenticado e possui
 * permissões para acessar os recursos protegidos
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class AuthMiddleware
{
    public function __construct(
        private RedisService $redis,
        private User $userModel
    ) {}

    /**
     * Manipula a requisição e verifica autenticação
     */
    public function handle(Request $request, callable $next): Response
    {
        try {
            // Obtém token do header Authorization
            $token = $request->bearerToken();
            
            if (!$token) {
                throw AuthException::missingToken();
            }

            // Busca sessão no Redis
            $sessionKey = "session:{$token}";
            $sessionData = $this->redis->get($sessionKey);
            
            if (!$sessionData) {
                throw AuthException::invalidToken();
            }

            // Verifica se sessão expirou
            if (isset($sessionData['expires_at']) && time() > $sessionData['expires_at']) {
                $this->redis->delete($sessionKey);
                throw AuthException::expiredToken();
            }

            // Busca usuário
            $user = $this->userModel->find($sessionData['user_id']);
            
            if (!$user || $user['status'] !== 'active') {
                throw AuthException::accountDisabled();
            }

            // Atualiza última atividade
            $sessionData['last_activity'] = time();
            $this->redis->set($sessionKey, $sessionData, $sessionData['ttl'] ?? 86400);

            // Adiciona usuário à request
            $request->setAttribute('user', $user);
            $request->setAttribute('user_id', $user['id']);

            return $next($request);
            
        } catch (AuthException $e) {
            return new Response(
                json_encode([
                    'success' => false,
                    'error' => $e->getUserFriendlyMessage(),
                    'code' => $e->getCode()
                ]),
                $e->getCode(),
                ['Content-Type' => 'application/json']
            );
        } catch (\Throwable $e) {
            return new Response(
                json_encode([
                    'success' => false,
                    'error' => 'Erro de autenticação',
                    'code' => 401
                ]),
                401,
                ['Content-Type' => 'application/json']
            );
        }
    }
}