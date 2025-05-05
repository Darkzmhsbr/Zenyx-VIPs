#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Bot Gerenciador Zenyx - Sistema gerenciador de bots, pagamentos e grupos/canais VIP
Autor: Claude
Data: 04/05/2025
"""

import os
import logging
import mysql.connector
from mysql.connector import Error as MySQLError
import redis
import json
from dotenv import load_dotenv
from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import (
    Updater, CommandHandler, CallbackQueryHandler, 
    MessageHandler, Filters, ConversationHandler,
    CallbackContext
)

# Configuração de logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# Carregar variáveis de ambiente
load_dotenv()

# Configurações do Bot
BOT_TOKEN = os.environ.get("BOT_TOKEN")
ADMIN_USER_ID = int(os.environ.get("ADMIN_USER_ID", 0))
CHANNEL_ID = os.environ.get("CHANNEL_ID")
CHANNEL_LINK = os.environ.get("CHANNEL_LINK")
PUSHIN_PAY_TOKEN = os.environ.get("PUSHIN_PAY_TOKEN")

# Configurações do MySQL
MYSQL_HOST = os.environ.get("MYSQL_HOST")
MYSQL_USER = os.environ.get("MYSQL_USER")
MYSQL_PASSWORD = os.environ.get("MYSQL_PASSWORD")
MYSQL_DATABASE = os.environ.get("MYSQL_DATABASE")

# Configurações do Redis
REDIS_HOST = os.environ.get("REDIS_HOST")
REDIS_PORT = int(os.environ.get("REDIS_PORT", 6379))

# Estados para ConversationHandler
VERIFICAR_CANAL, MENU_PRINCIPAL, CRIAR_BOT, MEU_SALDO, CONVITE, ADMIN_VIP = range(6)
CONFIG_BOT, CONFIG_MENSAGENS, CONFIG_MIDIA, CONFIG_TEXTO, CONFIG_PLANOS, CONFIG_CANAL = range(6, 12)

# Conexão com MySQL
def get_mysql_connection():
    """Estabelece e retorna uma conexão com o banco de dados MySQL"""
    try:
        connection = mysql.connector.connect(
            host=MYSQL_HOST,
            user=MYSQL_USER,
            password=MYSQL_PASSWORD,
            database=MYSQL_DATABASE
        )
        return connection
    except MySQLError as e:
        logger.error(f"Erro ao conectar ao MySQL: {e}")
        return None

# Conexão com Redis
def get_redis_connection():
    """Estabelece e retorna uma conexão com o Redis"""
    try:
        r = redis.Redis(
            host=REDIS_HOST,
            port=REDIS_PORT,
            decode_responses=True
        )
        return r
    except Exception as e:
        logger.error(f"Erro ao conectar ao Redis: {e}")
        return None

# Importar handlers
from handlers.start_handler import start_command
from handlers.menu_handler import (
    menu_callback, criar_bot_callback, meu_saldo_callback,
    convite_callback, admin_vip_callback, como_funciona_callback
)
from handlers.bot_handler import (
    verificar_token_bot, iniciar_bot_usuario, configurar_mensagens,
    configurar_midia, configurar_texto, configurar_planos, configurar_canal
)
from handlers.channel_handler import verificar_canal, validar_codigo_canal
from handlers.payment_handler import processar_pagamento, callback_pagamento

def error_handler(update: Update, context: CallbackContext) -> None:
    """Tratamento de erros global."""
    logger.error(f"Update {update} causou o erro: {context.error}")

def main() -> None:
    """Função principal que inicializa o bot."""
    # Verificar configurações críticas
    if not BOT_TOKEN:
        logger.error("Token do bot não configurado!")
        return
    
    if ADMIN_USER_ID == 0:
        logger.warning("ID do administrador não configurado corretamente!")
    
    # Inicializar o Updater
    updater = Updater(BOT_TOKEN)
    dispatcher = updater.dispatcher
    
    # Registrar handlers
    dispatcher.add_handler(CommandHandler("start", start_command))
    
    # Handler para verificação de canal
    dispatcher.add_handler(CallbackQueryHandler(verificar_canal, pattern="^verificar_canal$"))
    
    # Handlers para menu principal
    dispatcher.add_handler(CallbackQueryHandler(menu_callback, pattern="^menu_principal$"))
    dispatcher.add_handler(CallbackQueryHandler(criar_bot_callback, pattern="^criar_bot$"))
    dispatcher.add_handler(CallbackQueryHandler(meu_saldo_callback, pattern="^meu_saldo$"))
    dispatcher.add_handler(CallbackQueryHandler(convite_callback, pattern="^convite$"))
    dispatcher.add_handler(CallbackQueryHandler(admin_vip_callback, pattern="^admin_vip$"))
    dispatcher.add_handler(CallbackQueryHandler(como_funciona_callback, pattern="^como_funciona$"))
    
    # Handlers para gerenciamento de bots
    dispatcher.add_handler(MessageHandler(Filters.text & ~Filters.command, verificar_token_bot))
    dispatcher.add_handler(CallbackQueryHandler(configurar_mensagens, pattern="^config_mensagens$"))
    dispatcher.add_handler(CallbackQueryHandler(configurar_midia, pattern="^config_midia$"))
    dispatcher.add_handler(CallbackQueryHandler(configurar_texto, pattern="^config_texto$"))
    dispatcher.add_handler(CallbackQueryHandler(configurar_planos, pattern="^config_planos$"))
    dispatcher.add_handler(CallbackQueryHandler(configurar_canal, pattern="^config_canal$"))
    
    # Handler para pagamentos
    dispatcher.add_handler(CallbackQueryHandler(processar_pagamento, pattern="^pagar_"))
    
    # Handler global para erros
    dispatcher.add_error_handler(error_handler)
    
    # Iniciar o bot
    logger.info("Bot iniciado!")
    updater.start_polling()
    updater.idle()

if __name__ == "__main__":
    main()