# Script para configuração local do Zenyx Bot
# Autor: Claude
# Data: 04/05/2025

# Criar ambiente virtual Python
Write-Host "Criando ambiente virtual Python..." -ForegroundColor Green
python -m venv venv

# Ativar ambiente virtual
Write-Host "Ativando ambiente virtual..." -ForegroundColor Green
.\venv\Scripts\Activate.ps1

# Instalar dependências
Write-Host "Instalando dependências necessárias..." -ForegroundColor Green
pip install python-telegram-bot==13.15
pip install redis
pip install mysql-connector-python
pip install requests
pip install python-dotenv
pip install Pillow

# Criar estrutura de diretórios
Write-Host "Criando estrutura de diretórios para logs e arquivos temporários..." -ForegroundColor Green
if (-not (Test-Path "logs")) {
    New-Item -ItemType Directory -Path "logs"
}

if (-not (Test-Path "temp")) {
    New-Item -ItemType Directory -Path "temp"
}

# Criar arquivo .env
if (-not (Test-Path ".env")) {
    Write-Host "Criando arquivo .env com configurações..." -ForegroundColor Green
    @"
# Configurações do Bot Principal
BOT_TOKEN=8099618851:AAHGyz4EsTgRrrn795JWnTJ4cNA18pv7iL4
ADMIN_USER_ID=SEU_ID_TELEGRAM_AQUI

# Configurações de Canal
CHANNEL_ID=-1002630794901
CHANNEL_LINK=https://t.me/+c_MeI3rZn7o1MWY5

# Configurações PushinPay
PUSHIN_PAY_TOKEN=26627|5I0lOsq1yvn9R2R6PFn3EdwTUQjuer8NJNBkg8Cr09081214

# Configurações de Banco de Dados
MYSQL_HOST=localhost
MYSQL_USER=root
MYSQL_PASSWORD=root
MYSQL_DATABASE=zenyx_db

# Configurações Redis
REDIS_HOST=localhost
REDIS_PORT=6379
"@ | Out-File -FilePath ".env" -Encoding utf8
}

Write-Host "Ambiente local configurado com sucesso!" -ForegroundColor Green
Write-Host ""
Write-Host "Instruções para testes:" -ForegroundColor Cyan
Write-Host "1. Certifique-se de ter MySQL/MariaDB instalado e rodando localmente" -ForegroundColor Cyan
Write-Host "2. Certifique-se de ter Redis instalado e rodando localmente" -ForegroundColor Cyan
Write-Host "3. Execute 'python main.py' para iniciar o bot" -ForegroundColor Cyan
Write-Host ""
Write-Host "IMPORTANTE: Edite o arquivo .env e insira seu ID do Telegram em ADMIN_USER_ID" -ForegroundColor Yellow