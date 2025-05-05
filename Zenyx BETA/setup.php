<?php
/**
 * Script de configuração do ambiente para o Bot Zenyx
 * 
 * Este script prepara o ambiente para execução do Bot Zenyx,
 * criando diretórios, configurando arquivo .env e verificando dependências.
 * 
 * @author Claude
 * @version 1.0.0
 */

declare(strict_types=1);

class ZenyxSetup
{
    private string $envTemplate = <<<EOT
# Configurações do Bot Principal
BOT_TOKEN=8099618851:AAHGyz4EsTgRrrn795JWnTJ4cNA18pv7iL4
ADMIN_USER_ID=%s

# Configurações de Canal
CHANNEL_ID=-1002630794901
CHANNEL_LINK=https://t.me/+c_MeI3rZn7o1MWY5

# Configurações PushinPay
PUSHIN_PAY_TOKEN=26627|5I0lOsq1yvn9R2R6PFn3EdwTUQjuer8NJNBkg8Cr09081214

# Configurações de Banco de Dados
MYSQL_HOST=mysql
MYSQL_USER=zenyx
MYSQL_PASSWORD=zenyx_password
MYSQL_DATABASE=zenyx_db

# Configurações Redis
REDIS_HOST=redis
REDIS_PORT=6379
EOT;

    private array $requiredDirs = [
        'logs',
        'temp',
        'scripts'
    ];
    
    /**
     * Executa o processo de configuração
     * 
     * @return void
     */
    public function run(): void
    {
        echo "========================================================\n";
        echo "        CONFIGURAÇÃO DO AMBIENTE PARA BOT ZENYX         \n";
        echo "========================================================\n\n";
        
        $this->createDirectories();
        $this->setupEnvFile();
        $this->createScriptDirectory();
        $this->checkRequirements();
        $this->printInstructions();
        
        echo "\n========================================================\n";
        echo "          CONFIGURAÇÃO CONCLUÍDA COM SUCESSO!          \n";
        echo "========================================================\n";
    }
    
    /**
     * Cria os diretórios necessários
     * 
     * @return void
     */
    private function createDirectories(): void
    {
        echo "Criando diretórios necessários...\n";
        
        foreach ($this->requiredDirs as $dir) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo "✓ Diretório '$dir' criado com sucesso\n";
                } else {
                    echo "✗ Falha ao criar diretório '$dir'\n";
                }
            } else {
                echo "✓ Diretório '$dir' já existe\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Configura o arquivo .env
     * 
     * @return void
     */
    private function setupEnvFile(): void
    {
        echo "Configurando arquivo .env...\n";
        
        if (file_exists('.env')) {
            echo "✓ Arquivo .env já existe, verificando conteúdo...\n";
            
            $envContent = file_get_contents('.env');
            if (strpos($envContent, 'ADMIN_USER_ID=seu_id_telegram') !== false || 
                strpos($envContent, 'ADMIN_USER_ID=SEU_ID_TELEGRAM_AQUI') !== false) {
                echo "⚠️ ADMIN_USER_ID não configurado no arquivo .env\n";
                $this->updateAdminId();
            } else {
                echo "✓ ADMIN_USER_ID já configurado\n";
            }
        } else {
            echo "Criando novo arquivo .env...\n";
            $this->updateAdminId();
        }
        
        echo "\n";
    }
    
    /**
     * Atualiza o ID de administrador no arquivo .env
     * 
     * @return void
     */
    private function updateAdminId(): void
    {
        echo "Digite seu ID do Telegram para configuração do administrador: ";
        $adminId = trim(fgets(STDIN));
        
        if (empty($adminId)) {
            $adminId = 'seu_id_telegram';
            echo "⚠️ Nenhum ID fornecido, usando valor padrão. Lembre-se de atualizar manualmente depois.\n";
        }
        
        $envContent = sprintf($this->envTemplate, $adminId);
        if (file_put_contents('.env', $envContent)) {
            echo "✓ Arquivo .env criado/atualizado com sucesso\n";
        } else {
            echo "✗ Falha ao criar/atualizar arquivo .env\n";
        }
    }
    
    /**
     * Cria a pasta de scripts e o arquivo SQL
     * 
     * @return void
     */
    private function createScriptDirectory(): void
    {
        echo "Configurando diretório de scripts...\n";
        
        if (!is_dir('scripts')) {
            mkdir('scripts', 0755, true);
        }
        
        $sqlFilePath = 'scripts/init-db.sql';
        
        if (!file_exists($sqlFilePath)) {
            echo "Criando arquivo de inicialização do banco de dados...\n";
            
            $sqlContent = $this->getInitDbSqlContent();
            if (file_put_contents($sqlFilePath, $sqlContent)) {
                echo "✓ Arquivo init-db.sql criado com sucesso\n";
            } else {
                echo "✗ Falha ao criar arquivo init-db.sql\n";
            }
        } else {
            echo "✓ Arquivo init-db.sql já existe\n";
        }
        
        echo "\n";
    }
    
    /**
     * Obtém o conteúdo do arquivo SQL de inicialização
     * 
     * @return string
     */
    private function getInitDbSqlContent(): string
    {
        return <<<SQL
-- Inicialização do Banco de Dados para o Bot Zenyx
-- Autor: Claude
-- Data: 04/05/2025

-- Configuração do banco de dados
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `telegram_id` BIGINT NOT NULL UNIQUE,
    `username` VARCHAR(255),
    `first_name` VARCHAR(255),
    `last_name` VARCHAR(255),
    `is_admin` BOOLEAN DEFAULT FALSE,
    `is_vip` BOOLEAN DEFAULT FALSE,
    `vip_until` DATETIME,
    `balance` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de bots gerenciados
CREATE TABLE IF NOT EXISTS `managed_bots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` BIGINT NOT NULL,
    `bot_token` VARCHAR(255) NOT NULL UNIQUE,
    `bot_username` VARCHAR(255),
    `pushinpay_token` VARCHAR(255),
    `welcome_text` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_managed_bots_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`telegram_id`) ON DELETE CASCADE,
    INDEX `idx_owner_id` (`owner_id`),
    INDEX `idx_bot_username` (`bot_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de media para as mensagens de boas-vindas
CREATE TABLE IF NOT EXISTS `bot_media` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bot_id` INT NOT NULL,
    `file_id` VARCHAR(255) NOT NULL,
    `media_type` ENUM('photo', 'video') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bot_media_bot` FOREIGN KEY (`bot_id`) REFERENCES `managed_bots` (`id`) ON DELETE CASCADE,
    INDEX `idx_bot_id` (`bot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de planos disponíveis
CREATE TABLE IF NOT EXISTS `plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bot_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `duration` INT NOT NULL,  -- Duração em dias
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_plans_bot` FOREIGN KEY (`bot_id`) REFERENCES `managed_bots` (`id`) ON DELETE CASCADE,
    INDEX `idx_bot_id` (`bot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de grupos/canais gerenciados
CREATE TABLE IF NOT EXISTS `managed_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `bot_id` INT NOT NULL,
    `chat_id` BIGINT NOT NULL,
    `chat_title` VARCHAR(255),
    `chat_type` ENUM('group', 'supergroup', 'channel') NOT NULL,
    `invite_link` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_managed_groups_bot` FOREIGN KEY (`bot_id`) REFERENCES `managed_bots` (`id`) ON DELETE CASCADE,
    INDEX `idx_bot_id` (`bot_id`),
    INDEX `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de assinaturas
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `plan_id` INT NOT NULL,
    `group_id` INT NOT NULL,
    `start_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `end_date` TIMESTAMP,
    `payment_id` VARCHAR(255),
    `payment_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`telegram_id`),
    CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`),
    CONSTRAINT `fk_subscriptions_group` FOREIGN KEY (`group_id`) REFERENCES `managed_groups` (`id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_plan_id` (`plan_id`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de referências (sistema de indicação)
CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `referrer_id` BIGINT NOT NULL,
    `referred_id` BIGINT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expire_at` TIMESTAMP,
    `completed` BOOLEAN DEFAULT FALSE,
    CONSTRAINT `fk_referrals_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`telegram_id`),
    CONSTRAINT `fk_referrals_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`telegram_id`),
    INDEX `idx_referrer_id` (`referrer_id`),
    INDEX `idx_referred_id` (`referred_id`),
    INDEX `idx_completed` (`completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de transações financeiras
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `type` ENUM('deposit', 'withdrawal', 'commission', 'refund') NOT NULL,
    `status` ENUM('pending', 'completed', 'rejected') DEFAULT 'pending',
    `reference_id` VARCHAR(255),
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`telegram_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir usuário administrador padrão se não existir
-- Substitua YOUR_TELEGRAM_ID pelo seu ID real do Telegram
INSERT INTO `users` (`telegram_id`, `username`, `first_name`, `is_admin`, `balance`)
SELECT 123456789, 'admin', 'Admin', TRUE, 0.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `is_admin` = TRUE LIMIT 1);

SET FOREIGN_KEY_CHECKS = 1;
SQL;
    }
    
    /**
     * Verifica requisitos do sistema
     * 
     * @return void
     */
    private function checkRequirements(): void
    {
        echo "Verificando requisitos do sistema...\n";
        
        // Verificar Docker
        $hasDocker = $this->commandExists('docker');
        $hasDockerCompose = $this->commandExists('docker-compose');
        
        if ($hasDocker) {
            echo "✓ Docker instalado\n";
        } else {
            echo "✗ Docker não encontrado. Por favor, instale o Docker: https://www.docker.com/products/docker-desktop\n";
        }
        
        if ($hasDockerCompose) {
            echo "✓ Docker Compose instalado\n";
        } else {
            echo "✗ Docker Compose não encontrado. Ele geralmente vem com o Docker Desktop.\n";
        }
        
        // Verificar arquivo docker-compose.yml
        if (file_exists('docker-compose.yml')) {
            echo "✓ Arquivo docker-compose.yml encontrado\n";
        } else {
            echo "✗ Arquivo docker-compose.yml não encontrado. Criando arquivo...\n";
            
            $dockerComposeContent = $this->getDockerComposeContent();
            if (file_put_contents('docker-compose.yml', $dockerComposeContent)) {
                echo "✓ Arquivo docker-compose.yml criado com sucesso\n";
            } else {
                echo "✗ Falha ao criar arquivo docker-compose.yml\n";
            }
        }
        
        // Verificar arquivo requirements.txt
        if (file_exists('requirements.txt')) {
            echo "✓ Arquivo requirements.txt encontrado\n";
        } else {
            echo "✗ Arquivo requirements.txt não encontrado. Este arquivo é necessário para instalar as dependências Python.\n";
            
            // Criar arquivo requirements.txt básico
            $requirements = <<<EOT
python-telegram-bot==13.15
redis==4.5.4
mysql-connector-python==8.0.33
requests==2.29.0
python-dotenv==1.0.0
Pillow==9.5.0
EOT;
            
            if (file_put_contents('requirements.txt', $requirements)) {
                echo "✓ Arquivo requirements.txt criado automaticamente\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Verifica se um comando existe no sistema
     * 
     * @param string $command O comando a ser verificado
     * @return bool
     */
    private function commandExists(string $command): bool
    {
        $whereIsCommand = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        
        $process = proc_open(
            "$whereIsCommand $command",
            [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"],
            ],
            $pipes
        );
        
        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            
            proc_close($process);
            
            return !empty($stdout);
        }
        
        return false;
    }
    
    /**
     * Obtém o conteúdo do arquivo docker-compose.yml
     * 
     * @return string
     */
    private function getDockerComposeContent(): string
    {
        return <<<YAML
version: '3.8'

services:
  # Serviço principal do Bot Zenyx
  zenyx-bot:
    image: python:3.9-slim
    container_name: zenyx-bot
    working_dir: /app
    volumes:
      - ./:/app
    command: >
      bash -c "pip install -r requirements.txt &&
               python main.py"
    environment:
      - BOT_TOKEN=8099618851:AAHGyz4EsTgRrrn795JWnTJ4cNA18pv7iL4
      - ADMIN_USER_ID=\${ADMIN_USER_ID:-seu_id_telegram}
      - CHANNEL_ID=-1002630794901
      - CHANNEL_LINK=https://t.me/+c_MeI3rZn7o1MWY5
      - PUSHIN_PAY_TOKEN=26627|5I0lOsq1yvn9R2R6PFn3EdwTUQjuer8NJNBkg8Cr09081214
      - MYSQL_HOST=mysql
      - MYSQL_USER=zenyx
      - MYSQL_PASSWORD=zenyx_password
      - MYSQL_DATABASE=zenyx_db
      - REDIS_HOST=redis
      - REDIS_PORT=6379
    depends_on:
      - mysql
      - redis
    restart: unless-stopped
    networks:
      - zenyx-network

  # Banco de dados MySQL para o Bot
  mysql:
    image: mariadb:10.6
    container_name: zenyx-mysql
    environment:
      - MARIADB_ROOT_PASSWORD=root_password
      - MARIADB_DATABASE=zenyx_db
      - MARIADB_USER=zenyx
      - MARIADB_PASSWORD=zenyx_password
    volumes:
      - zenyx-mysql-data:/var/lib/mysql
      - ./scripts/init-db.sql:/docker-entrypoint-initdb.d/init-db.sql
    ports:
      - "3306:3306"
    command: --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    restart: unless-stopped
    networks:
      - zenyx-network

  # Servidor Redis para cache e gerenciamento de estado
  redis:
    image: redis:6-alpine
    container_name: zenyx-redis
    volumes:
      - zenyx-redis-data:/data
    ports:
      - "6379:6379"
    restart: unless-stopped
    networks:
      - zenyx-network

volumes:
  zenyx-mysql-data:
  zenyx-redis-data:

networks:
  zenyx-network:
    driver: bridge
YAML;
    }
    
    /**
     * Exibe instruções para o usuário
     * 
     * @return void
     */
    private function printInstructions(): void
    {
        echo "========================================================\n";
        echo "                INSTRUÇÕES DE EXECUÇÃO                  \n";
        echo "========================================================\n\n";
        
        echo "PASSO 1: Verifique se todos os arquivos necessários estão presentes\n";
        echo "- Execute: php verificar_repo.php (se disponível)\n\n";
        
        echo "PASSO 2: Inicie o ambiente Docker\n";
        echo "- Execute: docker-compose up -d\n\n";
        
        echo "PASSO 3: Verifique os logs para confirmar o funcionamento\n";
        echo "- Execute: docker-compose logs -f zenyx-bot\n\n";
        
        echo "PASSO 4: Teste o bot no Telegram\n";
        echo "- Acesse: https://t.me/SEU_BOT_USERNAME\n";
        echo "- Envie o comando /start\n\n";
        
        echo "PARA PARAR O BOT:\n";
        echo "- Execute: docker-compose down\n\n";
        
        echo "PARA DEPLOY NO RAILWAY:\n";
        echo "1. Instale o Railway CLI: npm i -g @railway/cli\n";
        echo "2. Faça login: railway login\n";
        echo "3. Inicie um projeto: railway init\n";
        echo "4. Adicione as variáveis de ambiente: railway variables set\n";
        echo "5. Adicione os serviços: railway add --plugin mysql redis\n";
        echo "6. Faça o deploy: railway up\n\n";
    }
}

// Executar a configuração
$setup = new ZenyxSetup();
$setup->run();