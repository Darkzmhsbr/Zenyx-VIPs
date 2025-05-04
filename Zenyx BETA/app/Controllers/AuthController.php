<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\RedisService;
use App\Core\Exception\ValidationException;
use App\Core\Exception\AuthException;

/**
 * Controller para autenticação
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class AuthController extends Controller
{
    public function __construct(
        private User $userModel,
        private TelegramService $telegram,
        private RedisService $redis
    ) {
        parent::__construct();
    }

    /**
     * Login via Telegram
     */
    public function login(): Response
    {
        try {
            $data = $this->validate([
                'telegram_id' => 'required|string',
                'first_name' => 'string|nullable',
                'last_name' => 'string|nullable',
                'username' => 'string|nullable',
                'auth_date' => 'required|integer',
                'hash' => 'required|string'
            ]);

            // Valida dados de autenticação do Telegram
            if (!$this->validateTelegramAuth($data)) {
                throw AuthException::invalidCredentials();
            }

            // Busca ou cria usuário
            $user = $this->userModel->findByTelegramId($data['telegram_id']);
            
            if (!$user) {
                // Cria novo usuário
                $userId = $this->userModel->create([
                    'telegram_id' => $data['telegram_id'],
                    'username' => $data['username'] ?? null,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'status' => 'active'
                ]);
                
                $user = $this->userModel->find($userId);
            } else {
                // Atualiza dados do usuário
                $this->userModel->update($user['id'], [
                    'username' => $data['username'] ?? $user['username'],
                    'first_name' => $data['first_name'] ?? $user['first_name'],
                    'last_name' => $data['last_name'] ?? $user['last_name'],
                    'last_interaction' => date('Y-m-d H:i:s')
                ]);
            }

            // Verifica se usuário está banido
            if ($user['status'] === 'banned') {
                throw AuthException::accountDisabled('Sua conta foi banida');
            }

            // Gera token de sessão
            $token = $this->generateSessionToken($user);

            return $this->success([
                'user' => $user,
                'token' => $token
            ], 'Login realizado com sucesso');

        } catch (AuthException $e) {
            return $this->error($e->getUserFriendlyMessage(), $e->getCode());
        } catch (ValidationException $e) {
            return $this->validationError($e->getErrors());
        } catch (\Throwable $e) {
            $this->logger->error('Error during login', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro durante o login', 500);
        }
    }

    /**
     * Logout
     */
    public function logout(): Response
    {
        try {
            $token = $this->request->bearerToken();
            
            if ($token) {
                // Remove token do Redis
                $this->redis->delete("session:{$token}");
            }

            return $this->success(null, 'Logout realizado com sucesso');
        } catch (\Throwable $e) {
            $this->logger->error('Error during logout', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro durante o logout', 500);
        }
    }

    /**
     * Verifica autenticação
     */
    public function check(): Response
    {
        try {
            $token = $this->request->bearerToken();
            
            if (!$token) {
                return $this->unauthorized();
            }

            // Busca sessão no Redis
            $session = $this->redis->get("session:{$token}");
            
            if (!$session) {
                return $this->unauthorized();
            }

            // Busca usuário
            $user = $this->userModel->find($session['user_id']);
            
            if (!$user || $user['status'] !== 'active') {
                return $this->unauthorized();
            }

            return $this->success([
                'authenticated' => true,
                'user' => $user
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Error checking authentication', [
                'error' => $e->getMessage()
            ]);
            
            return $this->error('Erro ao verificar autenticação', 500);
        }
    }

    /**
     * Valida autenticação do Telegram
     */
    private function validateTelegramAuth(array $data): bool
    {
        $check_hash = $data['hash'];
        unset($data['hash']);
        
        $data_check_arr = [];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                $data_check_arr[] = "$key=$value";
            }
        }
        sort($data_check_arr);
        
        $data_check_string = implode("\n", $data_check_arr);
        $secret_key = hash('sha256', $_ENV['TELEGRAM_BOT_TOKEN'], true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);
        
        if (strcmp($hash, $check_hash) !== 0) {
            return false;
        }
        
        // Verifica se não é muito antigo (1 hora)
        if ((time() - $data['auth_date']) > 3600) {
            return false;
        }
        
        return true;
    }

    /**
     * Gera token de sessão
     */
    private function generateSessionToken(array $user): string
    {
        $token = bin2hex(random_bytes(32));
        
        // Salva sessão no Redis
        $this->redis->set("session:{$token}", [
            'user_id' => $user['id'],
            'telegram_id' => $user['telegram_id'],
            'created_at' => time(),
            'last_activity' => time()
        ], 86400); // 24 horas
        
        return $token;
    }
}