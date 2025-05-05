#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Handler para o comando /start do Bot Zenyx
"""

import os
import logging
from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import CallbackContext

# Configurações do Bot
CHANNEL_ID = os.environ.get("CHANNEL_ID")
CHANNEL_LINK = os.environ.get("CHANNEL_LINK")

# Configuração de logging
logger = logging.getLogger(__name__)

def start_command(update: Update, context: CallbackContext) -> None:
    """Handler para o comando /start."""
    user = update.effective_user
    logger.info(f"Usuário {user.id} iniciou o bot")
    
    # Verificar se é a primeira vez que o usuário utiliza o bot
    # Isso deveria ser checado no banco de dados na implementação real
    
    # Enviar mensagem de verificação do canal
    message_text = (
        "🔒 VERIFICAÇÃO NECESSÁRIA\n\n"
        "Para utilizar todas as funcionalidades do bot, você precisa entrar no nosso canal oficial:"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔗 Entrar no Canal", url=CHANNEL_LINK)],
        [InlineKeyboardButton("✅ Já entrei no canal", callback_data="verificar_canal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    update.message.reply_text(
        text=message_text,
        reply_markup=reply_markup
    )