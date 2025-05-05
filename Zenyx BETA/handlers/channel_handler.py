#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Handlers para verificação de canais e grupos no Bot Zenyx
"""

import os
import logging
import random
import string
import time
from telegram import Update, ChatMember, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import CallbackContext
from telegram.error import TelegramError

# Configuração de logging
logger = logging.getLogger(__name__)

# Configurações do Bot
CHANNEL_ID = os.environ.get("CHANNEL_ID")
CHANNEL_LINK = os.environ.get("CHANNEL_LINK")

def verificar_canal(update: Update, context: CallbackContext) -> None:
    """Verifica se o usuário está no canal oficial."""
    query = update.callback_query
    query.answer()
    
    user_id = update.effective_user.id
    
    try:
        # Verificar se o usuário é membro do canal
        member_status = context.bot.get_chat_member(chat_id=CHANNEL_ID, user_id=user_id)
        
        # Se o usuário não for membro, pedir para entrar no canal
        if member_status.status not in ['member', 'administrator', 'creator']:
            message_text = (
                "⚠️ Você ainda não entrou no canal!\n\n"
                "Para utilizar o bot, você precisa ser membro do nosso canal oficial."
            )
            
            keyboard = [
                [InlineKeyboardButton("🔗 Entrar no Canal", url=CHANNEL_LINK)],
                [InlineKeyboardButton("✅ Já entrei no canal", callback_data="verificar_canal")]
            ]
            
            reply_markup = InlineKeyboardMarkup(keyboard)
            
            query.edit_message_text(
                text=message_text,
                reply_markup=reply_markup
            )
            return
        
        # Se chegou aqui, o usuário é membro do canal
        from handlers.menu_handler import menu_callback
        menu_callback(update, context)
        
    except TelegramError as e:
        logger.error(f"Erro ao verificar status do membro no canal: {e}")
        
        message_text = (
            "Ocorreu um erro ao verificar sua inscrição no canal. "
            "Por favor, tente novamente mais tarde."
        )
        
        query.edit_message_text(text=message_text)

def gerar_codigo_validacao() -> str:
    """Gera um código aleatório para validação de canal/grupo."""
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=8))

def validar_codigo_canal(update: Update, context: CallbackContext) -> None:
    """Valida o código enviado no canal/grupo para vinculação."""
    message = update.message
    text = message.text
    
    # Verificar se o texto é um código de validação
    if not hasattr(context.bot_data, 'codigos_validacao'):
        return
    
    codigos_validacao = context.bot_data.get('codigos_validacao', {})
    
    for codigo, info in list(codigos_validacao.items()):
        if codigo == text:
            # Código encontrado, validar
            user_id = info.get('user_id')
            bot_id = info.get('bot_id')
            expira = info.get('expira', 0)
            
            # Verificar se o código expirou
            if time.time() > expira:
                # Remover código expirado
                codigos_validacao.pop(codigo, None)
                continue
            
            # Obter informações do chat
            chat_id = message.chat.id
            chat_title = message.chat.title
            chat_type = message.chat.type
            
            # Salvar vínculo no banco de dados
            # Esta parte deverá ser implementada conforme a estrutura real do banco
            
            # Enviar mensagem de confirmação para o usuário
            context.bot.send_message(
                chat_id=user_id,
                text=f"✅ Canal/grupo '{chat_title}' vinculado com sucesso!"
            )
            
            # Remover código usado
            codigos_validacao.pop(codigo, None)
            
            # Enviar confirmação no grupo/canal
            message.reply_text(
                "✅ Este canal/grupo foi vinculado com sucesso ao sistema Zenyx!"
            )
            
            return