# setup-railway.ps1
Write-Host "Configurando variáveis no Railway..."

# Configurações básicas
railway variables set APP_NAME="Bot Zenyx"
railway variables set APP_ENV=production
railway variables set APP_DEBUG=false

# Telegram (use seus valores do .env)
railway variables set TELEGRAM_BOT_TOKEN="seu_token_aqui"
railway variables set TELEGRAM_CHANNEL_ID="seu_channel_id"
railway variables set TELEGRAM_CHANNEL_USERNAME="@seu_canal"

# PushinPay
railway variables set PUSHINPAY_TOKEN="seu_token_pushinpay"
railway variables set PUSHINPAY_WEBHOOK_SECRET="seu_secret"

Write-Host "Variáveis configuradas!"