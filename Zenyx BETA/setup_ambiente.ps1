# Script para configuração do ambiente Bot Zenyx no Windows
# Autor: Claude
# Data: 04/05/2025
#
# Descrição: Este script configura todo o ambiente necessário para o Bot Zenyx,
# criando diretórios, preparando arquivos e o ambiente Docker.
#
# Uso: .\Setup-Ambiente.ps1 [ID_TELEGRAM_ADMIN]
#
# Exemplo: .\Setup-Ambiente.ps1 123456789

param (
    [string]$AdminID = "123456789"
)

# Funções de log
function Write-ColorOutput {
    param (
        [string]$Message,
        [string]$Type = "Info"
    )
    
    switch ($Type) {
        "Success" { 
            Write-Host "✓ $Message" -ForegroundColor Green 
        }
        "Warning" { 
            Write-Host "⚠️ $Message" -ForegroundColor Yellow 
        }
        "Error" { 
            Write-Host "✗ $Message" -ForegroundColor Red 
        }
        "Info" { 
            Write-Host "ℹ️ $Message" -ForegroundColor Cyan 
        }
        default { 
            Write-Host $Message 
        }
    }
}

function Test-Command {
    param (
        [string]$Command
    )
    
    $null = Get-Command $Command -ErrorAction SilentlyContinue
    return $?
}

function Test-CreateDir {
    param (
        [string]$DirPath
    )
    
    if (-not (Test-Path $DirPath)) {
        New-Item -ItemType Directory -Path $DirPath | Out-Null
        Write-ColorOutput "Diretório '$DirPath' criado com sucesso" -Type "Success"
    } else {
        Write-ColorOutput "Diretório '$DirPath' já existe" -Type "Info"
    }
}

# Mostrar cabeçalho
Write-Host "=========================================================" -ForegroundColor Blue
Write-Host "            CONFIGURAÇÃO DO AMBIENTE BOT ZENYX            " -ForegroundColor White
Write-Host "=========================================================" -ForegroundColor Blue
Write-Host ""

# Validar ID de administrador
if ($AdminID -match '^\d+$') {
    Write-ColorOutput "ID do administrador configurado: $AdminID" -Type "Info"
} else {
    Write-ColorOutput "ID do administrador inválido. Usando valor padrão." -Type "Warning"
    $AdminID = "123456789"
}

# Verificar dependências
Write-ColorOutput "Verificando dependências necessárias..." -Type "Info"

$MissingDeps = $false

if (-not (Test-Command "docker")) {
    Write-ColorOutput "Docker não encontrado. Instale o Docker Desktop antes de continuar." -Type "Error"
    $MissingDeps = $true
} else {
    Write-ColorOutput "Docker encontrado!" -Type "Success"
}

try {
    $null = docker compose version 2>$null
    Write-ColorOutput "Docker Compose encontrado!" -Type "Success"
} catch {
    Write-ColorOutput "Docker Compose não encontrado. Instale o Docker Desktop com suporte a Docker Compose." -Type "Error"
    $MissingDeps = $true
}

if (-not (Test-Command "python")) {
    Write-ColorOutput "Python não encontrado. Instale o Python 3 antes de continuar." -Type "Error"
    $MissingDeps = $true
} else {
    Write-ColorOutput "Python encontrado!" -Type "Success"
}

if ($MissingDeps) {
    Write-ColorOutput "Dependências ausentes. Instale as dependências necessárias e execute o script novamente." -Type "Error"
    exit 1
}

Write-Host ""

# Criar estrutura de diretórios
Write-ColorOutput "Criando estrutura de diretórios..." -Type "Info"

Test-CreateDir "handlers"
Test-CreateDir "utils"
Test-CreateDir "models"
Test-CreateDir "scripts"
Test-CreateDir "logs"
Test-CreateDir "temp"
Test-CreateDir "config"

Write-Host ""

# Verificar e criar arquivo .env
Write-ColorOutput "Configurando arquivo .env..." -Type "Info"

if (-not (Test-Path ".env")) {
    $envContent = @"
# Configurações do Bot Principal
BOT_TOKEN=8099618851:AAHGyz4EsTgRrrn795JWnTJ4cNA18pv7iL4
ADMIN_USER_ID=$AdminID

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
"@
    Set-Content -Path ".env" -Value $envContent -Encoding UTF8
    Write-ColorOutput "Arquivo .env criado com sucesso" -Type "Success"
} else {
    Write-ColorOutput "Arquivo .env já existe, verificando configurações..." -Type "Info"
    
    # Atualizar ADMIN_USER_ID se necessário
    $envContent = Get-Content -Path ".env" -Raw
    if ($envContent -match "ADMIN_USER_ID=seu_id_telegram" -or $envContent -match "ADMIN_USER_ID=SEU_ID_TELEGRAM_AQUI") {
        $envContent = $envContent -replace "ADMIN_USER_ID=.*", "ADMIN_USER_ID=$AdminID"
        Set-Content -Path ".env" -Value $envContent -Encoding UTF8
        Write-ColorOutput "ID do administrador atualizado no arquivo .env" -Type "Success"
    }
}

Write-Host ""

# Verificar e criar arquivo requirements.txt
Write-ColorOutput "Configurando arquivo requirements.txt..." -Type "Info"

if (-not (Test-Path "requirements.txt")) {
    $requirementsContent = @"
python-telegram-bot==13.15
redis==4.5.4
mysql-connector-python==8.0.33
requests==2.29.0
python-dotenv==1.0.0
Pillow==9.5.0
"@
    Set-Content -Path "requirements.txt" -Value $requirementsContent -Encoding UTF8
    Write-ColorOutput "Arquivo requirements.txt criado com sucesso" -Type "Success"
} else {
    Write-ColorOutput "Arquivo requirements.txt já existe" -Type "Info"
}

Write-Host ""

# Verificar e criar arquivo docker-compose.yml
Write-ColorOutput "Configurando arquivo docker-compose.yml..." -Type "Info"

if (-not (Test-Path "docker-compose.yml")) {
    $dockerComposeContent = @"
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
      - BOT_TOKEN=`${BOT_TOKEN}
      - ADMIN_USER_ID=`${ADMIN_USER_ID}
      - CHANNEL_ID=`${CHANNEL_ID}
      - CHANNEL_LINK=`${CHANNEL_LINK}
      - PUSHIN_PAY_TOKEN=`${PUSHIN_PAY_TOKEN}
      - MYSQL_HOST=`${MYSQL_HOST}
      - MYSQL_USER=`${MYSQL_USER}
      - MYSQL_PASSWORD=`${MYSQL_PASSWORD}
      - MYSQL_DATABASE=`${MYSQL_DATABASE}
      - REDIS_HOST=`${REDIS_HOST}
      - REDIS_PORT=`${REDIS_PORT}
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
      - MARIADB_DATABASE=`${MYSQL_DATABASE}
      - MARIADB_USER=`${MYSQL_USER}
      - MARIADB_PASSWORD=`${MYSQL_PASSWORD}
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
"@
    Set-Content -Path "docker-compose.yml" -Value $dockerComposeContent -Encoding UTF8
    Write-ColorOutput "Arquivo docker-compose.yml criado com sucesso" -Type "Success"
} else {
    Write-ColorOutput "Arquivo docker-compose.yml já existe" -Type "Info"
}

Write-Host ""

# Verificar e criar script de inicialização do banco de dados
Write-ColorOutput "Configurando script de inicialização do banco de dados..." -Type "Info"

if (-not (Test-Path "scripts/init-db.sql")) {
    $initDbContent = @"
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

-- Inserir usuário administrador padrão
INSERT INTO `users` (`telegram_id`, `username`, `first_name`, `is_admin`, `balance`)
SELECT $AdminID, 'admin', 'Admin', TRUE, 0.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `telegram_id` = $AdminID);

SET FOREIGN_KEY_CHECKS = 1;
"@
    New-Item -ItemType Directory -Path "scripts" -Force | Out-Null
    Set-Content -Path "scripts/init-db.sql" -Value $initDbContent -Encoding UTF8
    Write-ColorOutput "Script de inicialização do banco de dados criado com sucesso" -Type "Success"
} else {
    Write-ColorOutput "Script de inicialização do banco de dados já existe" -Type "Info"
    
    # Atualizar ADMIN_ID no script SQL se necessário
    $sqlContent = Get-Content -Path "scripts/init-db.sql" -Raw
    $sqlContent = $sqlContent -replace "SELECT \d+, 'admin'", "SELECT $AdminID, 'admin'"
    $sqlContent = $sqlContent -replace "WHERE NOT EXISTS \(SELECT 1 FROM `users` WHERE `telegram_id` = \d+\);", "WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `telegram_id` = $AdminID);"
    Set-Content -Path "scripts/init-db.sql" -Value $sqlContent -Encoding UTF8
}

Write-Host ""

# Verificar se o main.py existe, se não, alertar
Write-ColorOutput "Verificando arquivo main.py..." -Type "Info"

if (-not (Test-Path "main.py")) {
    Write-ColorOutput "Arquivo main.py não encontrado. É necessário criar este arquivo manualmente." -Type "Warning"
    Write-ColorOutput "Consulte a documentação para obter um exemplo de arquivo main.py" -Type "Info"
} else {
    Write-ColorOutput "Arquivo main.py encontrado!" -Type "Success"
}

Write-Host ""

# Verificar arquivos de handlers
Write-ColorOutput "Verificando arquivos de handlers..." -Type "Info"

$MissingHandlers = $false
foreach ($handler in @("start_handler.py", "menu_handler.py", "channel_handler.py", "bot_handler.py", "payment_handler.py")) {
    if (-not (Test-Path "handlers/$handler")) {
        Write-ColorOutput "Arquivo handlers/$handler não encontrado. É necessário criar este arquivo manualmente." -Type "Warning"
        $MissingHandlers = $true
    } else {
        Write-ColorOutput "Arquivo handlers/$handler encontrado!" -Type "Success"
    }
}

if ($MissingHandlers) {
    Write-ColorOutput "Consulte a documentação para obter exemplos dos arquivos de handlers faltantes" -Type "Info"
}

Write-Host ""

# Perguntar se quer iniciar o ambiente Docker
Write-ColorOutput "Configuração do ambiente concluída!" -Type "Info"
Write-Host ""
$StartDocker = Read-Host "Deseja iniciar o ambiente Docker agora? (S/N)"

if ($StartDocker -eq "S" -or $StartDocker -eq "s") {
    Write-ColorOutput "Iniciando ambiente Docker..." -Type "Info"
    
    # Para os containers existentes
    docker-compose down 2>$null
    # Inicia os containers
    docker-compose up -d
    
    if ($LASTEXITCODE -eq 0) {
        Write-ColorOutput "Ambiente Docker iniciado com sucesso!" -Type "Success"
        
        # Mostrar status dos containers
        Write-Host ""
        Write-ColorOutput "Status dos containers:" -Type "Info"
        docker-compose ps
    } else {
        Write-ColorOutput "Erro ao iniciar ambiente Docker. Verifique os logs para mais detalhes." -Type "Error"
    }
} else {
    Write-ColorOutput "Para iniciar o ambiente Docker manualmente, execute: docker-compose up -d" -Type "Info"
}

Write-Host ""
Write-Host "=========================================================" -ForegroundColor Blue
Write-Host "            CONFIGURAÇÃO FINALIZADA!                     " -ForegroundColor White
Write-Host "=========================================================" -ForegroundColor Blue
Write-Host ""
Write-ColorOutput "Para verificar os logs do bot, execute: docker-compose logs -f zenyx-bot" -Type "Info"
Write-ColorOutput "Para parar o ambiente, execute: docker-compose down" -Type "Info"