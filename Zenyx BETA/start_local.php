<?php
/**
 * Inicialização Local do Bot Zenyx com Docker
 * 
 * Este script prepara e inicia o ambiente local de desenvolvimento para o Bot Zenyx,
 * utilizando Docker e Docker Compose.
 * 
 * @author Claude
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Interface para formatação de saída no terminal
 */
interface OutputFormatterInterface
{
    /**
     * Formata uma mensagem de sucesso
     *
     * @param string $message Mensagem a ser formatada
     * @return string Mensagem formatada
     */
    public function formatSuccess(string $message): string;
    
    /**
     * Formata uma mensagem de erro
     *
     * @param string $message Mensagem a ser formatada
     * @return string Mensagem formatada
     */
    public function formatError(string $message): string;
    
    /**
     * Formata uma mensagem de informação
     *
     * @param string $message Mensagem a ser formatada
     * @return string Mensagem formatada
     */
    public function formatInfo(string $message): string;
    
    /**
     * Formata uma mensagem de aviso
     *
     * @param string $message Mensagem a ser formatada
     * @return string Mensagem formatada
     */
    public function formatWarning(string $message): string;
}

/**
 * Implementação de formatador de saída para terminal
 */
class ConsoleOutputFormatter implements OutputFormatterInterface
{
    /**
     * @inheritDoc
     */
    public function formatSuccess(string $message): string
    {
        return "\033[0;32m✓ " . $message . "\033[0m";
    }
    
    /**
     * @inheritDoc
     */
    public function formatError(string $message): string
    {
        return "\033[0;31m✗ " . $message . "\033[0m";
    }
    
    /**
     * @inheritDoc
     */
    public function formatInfo(string $message): string
    {
        return "\033[0;36m" . $message . "\033[0m";
    }
    
    /**
     * @inheritDoc
     */
    public function formatWarning(string $message): string
    {
        return "\033[0;33m⚠️ " . $message . "\033[0m";
    }
}

/**
 * Interface para verificação de requisitos
 */
interface RequirementsCheckerInterface
{
    /**
     * Verifica se o Docker está instalado
     *
     * @return bool Retorna true se o Docker estiver instalado
     */
    public function isDockerInstalled(): bool;
    
    /**
     * Verifica se o Docker Compose está instalado
     *
     * @return bool Retorna true se o Docker Compose estiver instalado
     */
    public function isDockerComposeInstalled(): bool;
    
    /**
     * Verifica se os arquivos necessários existem
     *
     * @return array<string> Retorna um array com os arquivos que estão faltando
     */
    public function checkRequiredFiles(): array;
}

/**
 * Implementação de verificador de requisitos
 */
class RequirementsChecker implements RequirementsCheckerInterface
{
    /**
     * @var array<string> Arquivos necessários para execução
     */
    private array $requiredFiles = [
        'docker-compose.yml',
        'requirements.txt',
        'main.py'
    ];
    
    /**
     * @inheritDoc
     */
    public function isDockerInstalled(): bool
    {
        $command = PHP_OS_FAMILY === 'Windows' ? 'where docker' : 'which docker';
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
    }
    
    /**
     * @inheritDoc
     */
    public function isDockerComposeInstalled(): bool
    {
        // Verificar usando o comando direto do docker-compose
        $composeCommand = PHP_OS_FAMILY === 'Windows' ? 'where docker-compose' : 'which docker-compose';
        exec($composeCommand, $output, $returnCode);
        
        if ($returnCode === 0) {
            return true;
        }
        
        // Verificar se é um plugin do Docker
        exec('docker compose version', $output, $pluginReturnCode);
        
        return $pluginReturnCode === 0;
    }
    
    /**
     * @inheritDoc
     */
    public function checkRequiredFiles(): array
    {
        $missingFiles = [];
        
        foreach ($this->requiredFiles as $file) {
            if (!file_exists($file)) {
                $missingFiles[] = $file;
            }
        }
        
        return $missingFiles;
    }
}

/**
 * Interface para execução de comandos
 */
interface CommandExecutorInterface
{
    /**
     * Executa um comando e retorna o código de saída
     *
     * @param string $command Comando a ser executado
     * @return int Código de saída
     */
    public function execute(string $command): int;
    
    /**
     * Executa um comando e retorna a saída
     *
     * @param string $command Comando a ser executado
     * @return array{int, string} Array com o código de saída e a saída do comando
     */
    public function executeWithOutput(string $command): array;
}

/**
 * Implementação do executor de comandos
 */
class CommandExecutor implements CommandExecutorInterface
{
    /**
     * @inheritDoc
     */
    public function execute(string $command): int
    {
        passthru($command, $returnCode);
        return $returnCode ?? 1;
    }
    
    /**
     * @inheritDoc
     */
    public function executeWithOutput(string $command): array
    {
        exec($command, $output, $returnCode);
        return [$returnCode, implode(PHP_EOL, $output)];
    }
}

/**
 * Interface para geradores de arquivos
 */
interface FileGeneratorInterface
{
    /**
     * Gera um arquivo docker-compose.yml
     *
     * @return bool Retorna true se o arquivo foi gerado com sucesso
     */
    public function generateDockerCompose(): bool;
    
    /**
     * Gera um arquivo .env de exemplo
     *
     * @return bool Retorna true se o arquivo foi gerado com sucesso
     */
    public function generateEnvFile(): bool;
    
    /**
     * Gera um arquivo requirements.txt básico
     *
     * @return bool Retorna true se o arquivo foi gerado com sucesso
     */
    public function generateRequirementsFile(): bool;
    
    /**
     * Gera o script de inicialização do banco de dados
     *
     * @return bool Retorna true se o arquivo foi gerado com sucesso
     */
    public function generateDatabaseInitScript(): bool;
}

/**
 * Implementação do gerador de arquivos
 */
class FileGenerator implements FileGeneratorInterface
{
    /**
     * @inheritDoc
     */
    public function generateDockerCompose(): bool
    {
        $content = <<<YAML
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
        
        return (bool)file_put_contents('docker-compose.yml', $content);
    }
    
    /**
     * @inheritDoc
     */
    public function generateEnvFile(): bool
    {
        $content = <<<ENV
# Configurações do Bot Principal
BOT_TOKEN=8099618851:AAHGyz4EsTgRrrn795JWnTJ4cNA18pv7iL4
ADMIN_USER_ID=seu_id_telegram

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
ENV;
        
        return (bool)file_put_contents('.env', $content);
    }
    
    /**
     * @inheritDoc
     */
    public function generateRequirementsFile(): bool
    {
        $content = <<<REQ
python-telegram-bot==13.15
redis==4.5.4
mysql-connector-python==8.0.33
requests==2.29.0
python-dotenv==1.0.0
Pillow==9.5.0
REQ;
        
        return (bool)file_put_contents('requirements.txt', $content);
    }
    
    /**
     * @inheritDoc
     */
    public function generateDatabaseInitScript(): bool
    {
        // Garantir que o diretório scripts exista
        if (!is_dir('scripts')) {
            mkdir('scripts', 0755, true);
        }
        
        $content = <<<SQL
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
        
        return (bool)file_put_contents('scripts/init-db.sql', $content);
    }
}

/**
 * Classe responsável pela inicialização do ambiente local
 */
class LocalEnvironment
{
    /**
     * @var OutputFormatterInterface Formatador de saída
     */
    private OutputFormatterInterface $outputFormatter;
    
    /**
     * @var RequirementsCheckerInterface Verificador de requisitos
     */
    private RequirementsCheckerInterface $requirementsChecker;
    
    /**
     * @var CommandExecutorInterface Executor de comandos
     */
    private CommandExecutorInterface $commandExecutor;
    
    /**
     * @var FileGeneratorInterface Gerador de arquivos
     */
    private FileGeneratorInterface $fileGenerator;
    
    /**
     * Construtor com injeção de dependências
     *
     * @param OutputFormatterInterface $outputFormatter
     * @param RequirementsCheckerInterface $requirementsChecker
     * @param CommandExecutorInterface $commandExecutor
     * @param FileGeneratorInterface $fileGenerator
     */
    public function __construct(
        OutputFormatterInterface $outputFormatter,
        RequirementsCheckerInterface $requirementsChecker,
        CommandExecutorInterface $commandExecutor,
        FileGeneratorInterface $fileGenerator
    ) {
        $this->outputFormatter = $outputFormatter;
        $this->requirementsChecker = $requirementsChecker;
        $this->commandExecutor = $commandExecutor;
        $this->fileGenerator = $fileGenerator;
    }
    
    /**
     * Inicia o processo de configuração e inicialização do ambiente local
     * 
     * @return void
     */
    public function start(): void
    {
        $this->printHeader();
        
        try {
            $this->checkDockerInstallation();
            $this->verifyRequiredFiles();
            $this->setupEnvironment();
            $this->startDockerEnvironment();
            $this->showInstructions();
        } catch (\Exception $e) {
            echo $this->outputFormatter->formatError("Erro: " . $e->getMessage()) . PHP_EOL;
            exit(1);
        }
        
        $this->printFooter();
    }
    
    /**
     * Verifica se o Docker e Docker Compose estão instalados
     * 
     * @return void
     * @throws \RuntimeException Se o Docker ou Docker Compose não estiverem instalados
     */
    private function checkDockerInstallation(): void
    {
        echo "Verificando instalação do Docker..." . PHP_EOL;
        
        if (!$this->requirementsChecker->isDockerInstalled()) {
            throw new \RuntimeException(
                "Docker não encontrado. Por favor, instale o Docker antes de continuar.\n" .
                "Download: https://www.docker.com/products/docker-desktop"
            );
        }
        
        echo $this->outputFormatter->formatSuccess("Docker encontrado!") . PHP_EOL;
        
        if (!$this->requirementsChecker->isDockerComposeInstalled()) {
            throw new \RuntimeException(
                "Docker Compose não encontrado. Por favor, instale o Docker Compose antes de continuar.\n" .
                "Ele geralmente vem com o Docker Desktop."
            );
        }
        
        echo $this->outputFormatter->formatSuccess("Docker Compose encontrado!") . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Verifica se todos os arquivos necessários estão presentes
     * 
     * @return void
     */
    private function verifyRequiredFiles(): void
    {
        echo "Verificando arquivos necessários..." . PHP_EOL;
        
        $missingFiles = $this->requirementsChecker->checkRequiredFiles();
        
        if (!empty($missingFiles)) {
            $this->createMissingFiles($missingFiles);
        } else {
            echo $this->outputFormatter->formatSuccess("Todos os arquivos necessários já existem!") . PHP_EOL . PHP_EOL;
        }
    }
    
    /**
     * Cria arquivos que estão faltando
     * 
     * @param array<string> $missingFiles Lista de arquivos faltantes
     * @return void
     * @throws \RuntimeException Se não conseguir criar algum arquivo
     */
    private function createMissingFiles(array $missingFiles): void
    {
        echo $this->outputFormatter->formatWarning("Arquivos faltando, gerando automaticamente:") . PHP_EOL;
        
        foreach ($missingFiles as $file) {
            echo "- Criando $file..." . PHP_EOL;
            
            $result = match ($file) {
                'docker-compose.yml' => $this->fileGenerator->generateDockerCompose(),
                'requirements.txt' => $this->fileGenerator->generateRequirementsFile(),
                'main.py' => false, // Não podemos gerar o main.py automaticamente
                default => false
            };
            
            if (!$result && $file !== 'main.py') {
                throw new \RuntimeException("Não foi possível criar o arquivo $file");
            }
            
            if ($file === 'main.py') {
                echo $this->outputFormatter->formatError("Não é possível gerar automaticamente o arquivo main.py") . PHP_EOL;
                echo $this->outputFormatter->formatInfo("Por favor, adicione manualmente este arquivo ao projeto") . PHP_EOL;
                throw new \RuntimeException("Arquivo main.py não encontrado e não pode ser gerado automaticamente");
            }
        }
        
        echo $this->outputFormatter->formatSuccess("Arquivos gerados com sucesso!") . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Configura o ambiente antes de iniciar o Docker
     * 
     * @return void
     */
    private function setupEnvironment(): void
    {
        echo "Configurando ambiente..." . PHP_EOL;
        
        // Verificar e criar arquivo .env se não existir
        if (!file_exists('.env')) {
            echo "Criando arquivo .env..." . PHP_EOL;
            
            if ($this->fileGenerator->generateEnvFile()) {
                echo $this->outputFormatter->formatSuccess("Arquivo .env criado com sucesso!") . PHP_EOL;
            } else {
                throw new \RuntimeException("Não foi possível criar o arquivo .env");
            }
        } else {
            echo $this->outputFormatter->formatSuccess("Arquivo .env já existe") . PHP_EOL;
        }
        
        // Verificar e criar diretório de scripts e arquivo SQL
        if (!is_dir('scripts') || !file_exists('scripts/init-db.sql')) {
            echo "Criando script de inicialização do banco de dados..." . PHP_EOL;
            
            if ($this->fileGenerator->generateDatabaseInitScript()) {
                echo $this->outputFormatter->formatSuccess("Script de inicialização do banco de dados criado com sucesso!") . PHP_EOL;
            } else {
                throw new \RuntimeException("Não foi possível criar o script de inicialização do banco de dados");
            }
        } else {
            echo $this->outputFormatter->formatSuccess("Script de inicialização do banco de dados já existe") . PHP_EOL;
        }
        
        // Verificar a configuração do ADMIN_USER_ID no .env
        $envContent = file_get_contents('.env');
        if (preg_match('/ADMIN_USER_ID=seu_id_telegram/', $envContent)) {
            echo $this->outputFormatter->formatWarning("ID de Administrador não configurado no arquivo .env") . PHP_EOL;
            echo "Por favor, edite o arquivo .env e substitua 'seu_id_telegram' pelo seu ID do Telegram" . PHP_EOL;
        }
        
        echo PHP_EOL;
    }
    
    /**
     * Inicia o ambiente Docker
     * 
     * @return void
     * @throws \RuntimeException Se não conseguir iniciar o ambiente Docker
     */
    private function startDockerEnvironment(): void
    {
        echo "Iniciando ambiente Docker..." . PHP_EOL;
        
        // Limpar containers antigos
        echo "Parando containers antigos se existirem..." . PHP_EOL;
        $this->commandExecutor->execute('docker-compose down 2>/dev/null');
        
        // Construir e iniciar os containers
        echo "Construindo e iniciando containers..." . PHP_EOL;
        $returnCode = $this->commandExecutor->execute('docker-compose up -d --build');
        
        if ($returnCode !== 0) {
            throw new \RuntimeException("Falha ao iniciar os containers Docker");
        }
        
        echo $this->outputFormatter->formatSuccess("Ambiente Docker iniciado com sucesso!") . PHP_EOL . PHP_EOL;
        
        // Verificar se os containers estão rodando
        echo "Verificando status dos containers..." . PHP_EOL;
        [$returnCode, $output] = $this->commandExecutor->executeWithOutput('docker-compose ps');
        
        if ($returnCode !== 0) {
            throw new \RuntimeException("Falha ao verificar status dos containers");
        }
        
        echo $output . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Mostra instruções para interagir com o ambiente
     * 
     * @return void
     */
    private function showInstructions(): void
    {
        echo $this->outputFormatter->formatInfo("========================================================") . PHP_EOL;
        echo $this->outputFormatter->formatInfo("                INSTRUÇÕES DE USO                       ") . PHP_EOL;
        echo $this->outputFormatter->formatInfo("========================================================") . PHP_EOL . PHP_EOL;
        
        echo "Para verificar os logs do bot:" . PHP_EOL;
        echo "  docker-compose logs -f zenyx-bot" . PHP_EOL . PHP_EOL;
        
        echo "Para parar o ambiente:" . PHP_EOL;
        echo "  docker-compose down" . PHP_EOL . PHP_EOL;
        
        echo "Para reiniciar o ambiente após alterações:" . PHP_EOL;
        echo "  docker-compose restart zenyx-bot" . PHP_EOL . PHP_EOL;
        
        echo "Para acessar o banco de dados MariaDB:" . PHP_EOL;
        echo "  docker-compose exec mysql mysql -uzenyx -pzenyx_password zenyx_db" . PHP_EOL . PHP_EOL;
        
        echo "Para acessar o Redis CLI:" . PHP_EOL;
        echo "  docker-compose exec redis redis-cli" . PHP_EOL . PHP_EOL;
        
        echo "Para testar o bot no Telegram:" . PHP_EOL;
        echo "  1. Certifique-se de que seu ADMIN_USER_ID está configurado no arquivo .env" . PHP_EOL;
        echo "  2. Acesse seu bot no Telegram e envie o comando /start" . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Imprime o cabeçalho do script
     * 
     * @return void
     */
    private function printHeader(): void
    {
        echo $this->outputFormatter->formatInfo("========================================================") . PHP_EOL;
        echo $this->outputFormatter->formatInfo("          INICIALIZAÇÃO LOCAL DO BOT ZENYX              ") . PHP_EOL;
        echo $this->outputFormatter->formatInfo("========================================================") . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Imprime o rodapé do script
     * 
     * @return void
     */
    private function printFooter(): void
    {
        echo PHP_EOL;
        echo $this->outputFormatter->formatInfo("========================================================") . PHP_EOL;
        echo $this->outputFormatter->formatInfo("                AMBIENTE INICIALIZADO                   ") . PHP_EOL;
        echo $this->outputFormatter->formatInfo("========================================================") . PHP_EOL;
    }
}

/**
 * Factory para criar a instância de LocalEnvironment com suas dependências
 */
class LocalEnvironmentFactory
{
    /**
     * Cria uma instância configurada de LocalEnvironment
     *
     * @return LocalEnvironment
     */
    public static function create(): LocalEnvironment
    {
        $outputFormatter = new ConsoleOutputFormatter();
        $requirementsChecker = new RequirementsChecker();
        $commandExecutor = new CommandExecutor();
        $fileGenerator = new FileGenerator();
        
        return new LocalEnvironment(
            $outputFormatter,
            $requirementsChecker,
            $commandExecutor,
            $fileGenerator
        );
    }
}

// Ponto de entrada do script
try {
    set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        return true;
    });
    
    // Iniciar o ambiente local
    $environment = LocalEnvironmentFactory::create();
    $environment->start();
    
    restore_error_handler();
} catch (Throwable $e) {
    echo "\033[0;31mErro: {$e->getMessage()}\033[0m" . PHP_EOL;
    echo "Arquivo: {$e->getFile()}:{$e->getLine()}" . PHP_EOL;
    exit(1);
}