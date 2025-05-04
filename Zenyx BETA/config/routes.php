<?php
declare(strict_types=1);

use App\Core\Router;

/**
 * Arquivo de definição de rotas
 * 
 * @var Router $router
 */

// Rotas públicas - Webhooks
$router->post('/webhook/telegram', 'WebhookController@handleTelegram');
$router->post('/webhook/pushinpay', 'WebhookController@handlePushinPay');

// Health check
$router->get('/health', 'SystemController@health');

// API Pública
$router->group(['prefix' => '/api/v1'], function($router) {
    // Status do sistema
    $router->get('/status', 'SystemController@status');
    
    // Verificação de bot
    $router->post('/bot/verify', 'BotController@verify');
});

// Rotas autenticadas - Admin
$router->group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function($router) {
    // Dashboard
    $router->get('/', 'AdminController@dashboard');
    $router->get('/dashboard', 'AdminController@dashboard');
    
    // Usuários
    $router->get('/users', 'AdminController@users');
    $router->get('/users/{id}', 'AdminController@userDetail');
    $router->post('/users/{id}/ban', 'AdminController@banUser');
    $router->post('/users/{id}/unban', 'AdminController@unbanUser');
    
    // Bots
    $router->get('/bots', 'AdminController@bots');
    $router->get('/bots/{id}', 'AdminController@botDetail');
    $router->post('/bots/{id}/suspend', 'AdminController@suspendBot');
    $router->post('/bots/{id}/activate', 'AdminController@activateBot');
    
    // Pagamentos
    $router->get('/payments', 'AdminController@payments');
    $router->get('/payments/{id}', 'AdminController@paymentDetail');
    $router->post('/payments/{id}/refund', 'AdminController@refundPayment');
    
    // Relatórios
    $router->get('/reports', 'AdminController@reports');
    $router->get('/reports/sales', 'AdminController@salesReport');
    $router->get('/reports/users', 'AdminController@usersReport');
    $router->get('/reports/bots', 'AdminController@botsReport');
    
    // Configurações
    $router->get('/settings', 'AdminController@settings');
    $router->post('/settings', 'AdminController@updateSettings');
    
    // Logs
    $router->get('/logs', 'AdminController@logs');
    $router->get('/logs/download', 'AdminController@downloadLogs');
});

// Rotas autenticadas - Bot Management
$router->group(['prefix' => '/bot', 'middleware' => ['auth']], function($router) {
    // Gerenciamento de Bots
    $router->get('/', 'BotController@index');
    $router->post('/create', 'BotController@create');
    $router->get('/{id}', 'BotController@show');
    $router->post('/{id}/update', 'BotController@update');
    $router->post('/{id}/delete', 'BotController@delete');
    
    // Configurações do Bot
    $router->get('/{id}/settings', 'BotController@settings');
    $router->post('/{id}/settings', 'BotController@updateSettings');
    $router->post('/{id}/pushinpay', 'BotController@updatePushinPay');
    $router->post('/{id}/welcome', 'BotController@updateWelcome');
    $router->post('/{id}/channel', 'BotController@updateChannel');
    
    // Planos
    $router->get('/{id}/plans', 'PlanController@index');
    $router->post('/{id}/plans/create', 'PlanController@create');
    $router->post('/{id}/plans/{planId}/update', 'PlanController@update');
    $router->post('/{id}/plans/{planId}/delete', 'PlanController@delete');
    
    // Estatísticas
    $router->get('/{id}/stats', 'BotController@stats');
    $router->get('/{id}/payments', 'BotController@payments');
    $router->get('/{id}/users', 'BotController@users');
});

// Rotas autenticadas - User
$router->group(['prefix' => '/user', 'middleware' => ['auth']], function($router) {
    // Perfil
    $router->get('/profile', 'UserController@profile');
    $router->post('/profile', 'UserController@updateProfile');
    
    // Saldo
    $router->get('/balance', 'UserController@balance');
    $router->post('/withdraw', 'UserController@withdraw');
    
    // Admin VIP
    $router->get('/vip', 'UserController@vip');
    $router->post('/vip/activate', 'UserController@activateVip');
    
    // Referrals
    $router->get('/referrals', 'UserController@referrals');
    $router->get('/referral-link', 'UserController@referralLink');
    
    // Histórico
    $router->get('/history', 'UserController@history');
    $router->get('/payments', 'UserController@payments');
});

// Rotas de Autenticação
$router->group(['prefix' => '/auth'], function($router) {
    $router->post('/login', 'AuthController@login');
    $router->post('/logout', 'AuthController@logout');
    $router->get('/check', 'AuthController@check');
});

// Fallback para rotas não encontradas
$router->any('/{any}', function() {
    return [
        'error' => 'Route not found',
        'status' => 404
    ];
})->where('any', '.*');