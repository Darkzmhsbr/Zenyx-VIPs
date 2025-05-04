<?php
declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Database;
use App\Models\User;

/**
 * Seeder principal do banco de dados
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class DatabaseSeeder
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Executa todos os seeders
     */
    public function run(): void
    {
        $this->seedAdminUser();
        $this->seedDemoData();
    }

    /**
     * Cria usuário admin padrão
     */
    private function seedAdminUser(): void
    {
        $userModel = new User($this->db);
        
        // Verifica se admin já existe
        $admin = $userModel->findByTelegramId('admin');
        
        if (!$admin) {
            $userModel->create([
                'telegram_id' => 'admin',
                'username' => 'admin',
                'first_name' => 'Administrador',
                'is_admin' => true,
                'status' => 'active',
                'balance' => 0.00
            ]);
        }
    }

    /**
     * Cria dados de demonstração
     */
    private function seedDemoData(): void
    {
        // Implementar dados de demo se necessário
    }
}