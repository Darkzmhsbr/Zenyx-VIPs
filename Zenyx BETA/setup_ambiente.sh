#!/bin/bash
# Script para configuração do ambiente Bot Zenyx
# Autor: Claude
# Data: 04/05/2025
#
# Descrição: Este script configura todo o ambiente necessário para o Bot Zenyx,
# criando diretórios, instalando dependências e preparando o ambiente Docker.
#
# Uso: ./setup_ambiente.sh [ID_TELEGRAM_ADMIN]
#
# Exemplo: ./setup_ambiente.sh 123456789

# Configurações
BOLD="\e[1;37m"
GREEN="\e[1;32m"
YELLOW="\e[1;33m"
RED="\e[1;31m"
BLUE="\e[1;34m"
NC="\e[0m" # No Color

# Função para exibir mensagens coloridas
log_message() {
    local msg_type=$1
    local message=$2
    
    case $msg_type in
        "info")
            echo -e "${BLUE}ℹ️ ${message}${NC}"
            ;;
        "success")
            echo -e "${GREEN}✓ ${message}${NC}"
            ;;
        "warning")
            echo -e "${YELLOW}⚠️ ${message}${NC}"
            ;;
        "error")
            echo -e "${RED}✗ ${message}${NC}"
            ;;
        *)
            echo -e "${message}"
            ;;
    esac
}

# Função para verificar comando
check_command() {
    command -v $1 >/dev/null 2>&1
}

# Função para verificar e criar diretório
check_and_create_dir() {
    if [ ! -d "$1" ]; then
        mkdir -p "$1"
        log_message "success" "Diretório '$1' criado com sucesso"
    else
        log_message "info" "Diretório '$1' já existe"
    fi
}

# Mostrar cabeçalho
echo -e "${BLUE}=========================================================${NC}"
echo -e "${BOLD}            CONFIGURAÇÃO DO AMBIENTE BOT ZENYX            ${NC}"
echo -e "${BLUE}=========================================================${NC}"
echo ""

# Verificar parâmetros
ADMIN_ID=${1:-0}
if [[ $ADMIN_ID =~ ^[0-9]+$ ]]; then
    log_message "info" "ID do administrador configurado: $ADMIN_ID"
else
    log_message "warning" "ID do administrador inválido ou não fornecido. Usando valor padrão."
    ADMIN_ID=123456789
fi

# Verificar dependências
log_message "info" "Verificando dependências necessárias..."

MISSING_DEPS=0

if ! check_command docker; then
    log_message "error" "Docker não encontrado. Instale o Docker antes de continuar."
    MISSING_DEPS=1
else
    log_message "success" "Docker encontrado!"
fi

if ! check_command docker-compose; then
    # Verificar se é plugin do docker
    if ! docker compose version >/dev/null 2>&1; then
        log_message "error" "Docker Compose não encontrado. Instale o Docker Compose antes de continuar."
        MISSING_DEPS=1
    else
        log_message "success" "Docker Compose encontrado (plugin)!"
    fi
else
    log_message "success" "Docker Compose encontrado!"
fi

if ! check_command python3; then
    log_message "error" "Python 3 não encontrado. Instale o Python 3 antes de continuar."
    MISSING_DEPS=1
else
    log_message "success" "Python 3 encontrado!"
fi

if [ $MISSING_DEPS -eq 1 ]; then
    log_message "error" "Dependências ausentes. Instale as dependências necessárias e execute o script novamente."
    exit 1
fi

echo ""

# Criar estrutura de diretórios
log_message "info" "Criando estrutura de diretórios..."

check_and_create_dir "handlers"
check_and_create_dir "utils"
check_and_create_dir "models"
check_and_create_dir "scripts"
check_and_create_dir "logs"
check_and_create_dir "temp"
check_and_create_dir "config"

echo ""

# Verificar e criar arquivo .env
log_message "info" "Configurando arquivo .env..."

if [ ! -f ".env" ]; then
    cat > .env << EOL
# Configurações do Bot Principal
BOT_TOKEN=8099618851:AAHGyz4EsTgRrrn795JWnTJ4cNA18pv7iL4
ADMIN_USER_ID=$ADMIN_ID

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
EOL
    log_message "success" "Arquivo .env criado com sucesso"
else
    log_message "info" "Arquivo .env já existe, verificando configurações..."
    
    # Atualizar ADMIN_USER_ID se necessário
    if grep -q "ADMIN_USER_ID=seu_id_telegram" .env || grep -q "ADMIN_USER_ID=SEU_ID_TELEGRAM_AQUI" .env; then
        sed -i "s/ADMIN_USER_ID=.*$/ADMIN_USER_ID=$ADMIN_ID/" .env
        log_message "success" "ID do administrador atualizado no arquivo .env"
    fi
fi

echo ""

# Verificar e criar arquivo requirements.txt
log_message "info" "Configurando arquivo requirements.txt..."

if [ ! -f "requirements.txt" ]; then
    cat > requirements.txt << EOL
python-telegram-bot==13.15
redis==4.5.4
mysql-connector-python==8.0.33
requests==2.29.0
python-dotenv==1.0.0
Pillow==9.5.0
EOL
    log_message "success" "Arquivo requirements.txt criado com sucesso"
else
    log_message "info" "Arquivo requirements.txt já existe"
fi

echo ""

# Verificar e criar arquivo docker-compose.yml
log_message "info" "Configurando arquivo docker-compose.yml..."

if [ ! -f "docker-compose.yml" ]; then
    cat > docker-compose.yml << EOL
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
      - BOT_TOKEN=\${BOT_TOKEN}
      - ADMIN_USER_ID=\${ADMIN_USER_ID}
      - CHANNEL_ID=\${CHANNEL_ID}
      - CHANNEL_LINK=\${CHANNEL_LINK}
      - PUSHIN_PAY_TOKEN=\${PUSHIN_PAY_TOKEN}
      - MYSQL_HOST=\${MYSQL_HOST}
      - MYSQL_USER=\${MYSQL_USER}
      - MYSQL_PASSWORD=\${MYSQL_PASSWORD}
      - MYSQL_DATABASE=\${MYSQL_DATABASE}
      - REDIS_HOST=\${REDIS_HOST}
      - REDIS_PORT=\${REDIS_PORT}
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
      - MARIADB_DATABASE=\${MYSQL_DATABASE}
      - MARIADB_USER=\${MYSQL_USER}
      - MARIADB_PASSWORD=\${MYSQL_PASSWORD}
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
EOL
    log_message "success" "Arquivo docker-compose.yml criado com sucesso"
else
    log_message "info" "Arquivo docker-compose.yml já existe"
fi

echo ""

# Verificar e criar script de inicialização do banco de dados
log_message "info" "Configurando script de inicialização do banco de dados..."

if [ ! -f "scripts/init-db.sql" ]; then
    cat > scripts/init-db.sql << 'EOL'
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
-- Função: Adiciona o ID do administrador como usuário admin no sistema
INSERT INTO `users` (`telegram_id`, `username`, `first_name`, `is_admin`, `balance`)
SELECT ADMIN_USER_ID, 'admin', 'Admin', TRUE, 0.00
FROM (SELECT @ADMIN_USER_ID AS ADMIN_USER_ID) AS temp
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `telegram_id` = @ADMIN_USER_ID);

SET FOREIGN_KEY_CHECKS = 1;
EOL
    
    # Substituir @ADMIN_USER_ID pelo valor real
    sed -i "s/@ADMIN_USER_ID/$ADMIN_ID/g" scripts/init-db.sql
    log_message "success" "Script de inicialização do banco de dados criado com sucesso"
else
    log_message "info" "Script de inicialização do banco de dados já existe"
    
    # Atualizar ADMIN_USER_ID no script SQL se necessário
    sed -i "s/@ADMIN_USER_ID/$ADMIN_ID/g" scripts/init-db.sql
fi

echo ""

# Verificar se o main.py existe, se não, criar
log_message "info" "Verificando arquivo main.py..."

if [ ! -f "main.py" ]; then
    log_message "warning" "Arquivo main.py não encontrado. É necessário criar este arquivo manualmente."
    log_message "info" "Consulte a documentação para obter um exemplo de arquivo main.py"
else
    log_message "success" "Arquivo main.py encontrado!"
fi

echo ""

# Verificar se temos os arquivos de handlers
log_message "info" "Verificando arquivos de handlers..."

MISSING_HANDLERS=0
for handler in "start_handler.py" "menu_handler.py" "channel_handler.py" "bot_handler.py" "payment_handler.py"; do
    if [ ! -f "handlers/$handler" ]; then
        log_message "warning" "Arquivo handlers/$handler não encontrado. É necessário criar este arquivo manualmente."
        MISSING_HANDLERS=1
    else
        log_message "success" "Arquivo handlers/$handler encontrado!"
    fi
done

if [ $MISSING_HANDLERS -eq 1 ]; then
    log_message "info" "Consulte a documentação para obter exemplos dos arquivos de handlers faltantes"
fi

echo ""

# Iniciar ambiente Docker?
log_message "info" "Configuração do ambiente concluída!"
echo ""
read -p "Deseja iniciar o ambiente Docker agora? (s/n): " start_docker

if [[ $start_docker =~ ^[Ss]$ ]]; then
    log_message "info" "Iniciando ambiente Docker..."
    
    docker-compose down 2>/dev/null
    docker-compose up -d
    
    if [ $? -eq 0 ]; then
        log_message "success" "Ambiente Docker iniciado com sucesso!"
        
        # Mostrar status dos containers
        echo ""
        log_message "info" "Status dos containers:"
        docker-compose ps
    else
        log_message "error" "Erro ao iniciar ambiente Docker. Verifique os logs para mais detalhes."
    fi
else
    log_message "info" "Para iniciar o ambiente Docker manualmente, execute: docker-compose up -d"
fi

echo ""
echo -e "${BLUE}=========================================================${NC}"
echo -e "${BOLD}            CONFIGURAÇÃO FINALIZADA!                     ${NC}"
echo -e "${BLUE}=========================================================${NC}"
echo ""
log_message "info" "Para verificar os logs do bot, execute: docker-compose logs -f zenyx-bot"
log_message "info" "Para parar o ambiente, execute: docker-compose down"