#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Handlers para gerenciamento de bots dos usuários
"""

import os
import logging
import re
import json
from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import CallbackContext

# Importações de conexão com DB
from main import get_mysql_connection, get_redis_connection

# Configuração de logging
logger = logging.getLogger(__name__)

def verificar_token_bot(update: Update, context: CallbackContext) -> None:
    """Verifica se o texto enviado é um token de bot válido."""
    if not context.user_data.get('waiting_for') == 'bot_token':
        return
    
    message = update.message
    text = message.text
    user_id = update.effective_user.id
    
    # Limpar o estado de espera
    context.user_data.pop('waiting_for', None)
    
    # Verificar formato do token
    token_pattern = r'^\d+:[A-Za-z0-9_-]{35}$'
    if not re.match(token_pattern, text):
        message.reply_text(
            "❌ Token inválido! O formato deve ser semelhante a:\n"
            "123456789:ABCDefGhIJKlmNoPQRsTUVwxyZ\n\n"
            "Por favor, obtenha um token válido do @BotFather e tente novamente."
        )
        return
    
    # Enviar mensagem de processamento
    processing_message = message.reply_text(
        "🔄 INICIANDO SEU BOT...\n"
        "Seu bot está sendo iniciado. Isso pode levar alguns segundos."
    )
    
    # Aqui seria feita a verificação real do token e a criação do bot
    # Para este exemplo, vamos simular um atraso e sucesso
    
    try:
        # Simular verificação do token no BotFather
        # Na implementação real, você verificaria com a API do Telegram
        # e salvaria as informações no banco de dados
        
        # Obter username do bot (simulação)
        bot_username = "SeuBot"  # Na realidade, seria obtido da API
        
        # Salvar no banco de dados (simulação)
        # Na implementação real, salvar no MySQL
        
        # Preparar mensagem de sucesso
        success_text = (
            f"✅ BOT INICIADO COM SUCESSO!\n"
            f"Seu bot @{bot_username} está online e pronto para uso."
        )
        
        # Preparar botão para iniciar o bot
        keyboard = [
            [InlineKeyboardButton(f"🚀 Iniciar @{bot_username}", url=f"https://t.me/{bot_username}")],
            [InlineKeyboardButton("📝 Configurar bot", callback_data="config_bot")],
            [InlineKeyboardButton("🔙 Voltar ao menu", callback_data="menu_principal")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        # Atualizar a mensagem de processamento
        processing_message.edit_text(
            text=success_text,
            reply_markup=reply_markup
        )
        
    except Exception as e:
        logger.error(f"Erro ao criar bot para usuário {user_id}: {e}")
        processing_message.edit_text(
            "❌ Ocorreu um erro ao iniciar seu bot. Por favor, verifique se o token é válido e tente novamente."
        )

def iniciar_bot_usuario(update: Update, context: CallbackContext) -> None:
    """Manipula o callback quando o usuário acessa seu próprio bot."""
    query = update.callback_query
    query.answer()
    
    user = update.effective_user
    bot_username = context.user_data.get('bot_username', 'SeuBot')  # Valor padrão para simulação
    
    message_text = (
        f"👋🏻 Olá @{user.username}, você é o administrador do @{bot_username}"
    )
    
    keyboard = [
        [InlineKeyboardButton("📝 Configurar mensagens", callback_data="config_mensagens")],
        [InlineKeyboardButton("💰 Integrar PushinPay", callback_data="config_pushinpay")],
        [InlineKeyboardButton("👥 Configurar canal/grupo", callback_data="config_canal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def configurar_mensagens(update: Update, context: CallbackContext) -> None:
    """Exibe as opções de configuração de mensagens do bot."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "📝 CONFIGURAÇÃO PRINCIPAL\n"
        "Escolha o que deseja configurar:"
    )
    
    keyboard = [
        [InlineKeyboardButton("🖼️ Mídia", callback_data="config_midia")],
        [InlineKeyboardButton("📝 Texto", callback_data="config_texto")],
        [InlineKeyboardButton("💰 Criar Planos", callback_data="config_planos")],
        [InlineKeyboardButton("👁️ Visualização completa", callback_data="visualizacao_completa")],
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_bot")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def configurar_midia(update: Update, context: CallbackContext) -> None:
    """Configura a mídia para a mensagem de boas-vindas do bot."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "🖼️ CONFIGURAÇÃO DE MÍDIA\n\n"
        "Envie uma foto ou vídeo para ser usado como mídia de boas-vindas do seu bot.\n\n"
        "A mídia será exibida na mensagem inicial que os usuários receberão ao iniciarem seu bot."
    )
    
    keyboard = [
        [InlineKeyboardButton("🗑️ Remover mídia atual", callback_data="remover_midia")],
        [InlineKeyboardButton("🔙 Voltar", callback_data="config_mensagens")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )
    
    # Definir o estado para aguardar upload de mídia
    context.user_data['waiting_for'] = 'media_upload'

def configurar_texto(update: Update, context: CallbackContext) -> None:
    """Configura o texto de boas-vindas do bot."""
    query = update.callback_query
    query.answer()
    
    # Obter texto atual (simulação)
    current_text = context.user_data.get('welcome_text', "👋🏻 Olá %firstname%, seja bem-vindo!")
    
    message_text = (
        "📝 CONFIGURAÇÃO DE TEXTO\n\n"
        "Envie o texto que será exibido na mensagem de boas-vindas do seu bot.\n\n"
        "Você pode usar as seguintes variáveis:\n"
        "• %firstname% - Primeiro nome do usuário\n"
        "• %lastname% - Sobrenome do usuário\n"
        "• %username% - Nome de usuário (@username)\n\n"
        f"Texto atual:\n{current_text}"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Voltar", callback_data="config_mensagens")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )
    
    # Definir o estado para aguardar novo texto
    context.user_data['waiting_for'] = 'welcome_text'

def configurar_planos(update: Update, context: CallbackContext) -> None:
    """Configura os planos de pagamento do bot."""
    query = update.callback_query
    query.answer()
    
    # Obter planos atuais (simulação)
    # Na implementação real, buscar do banco de dados
    planos = context.user_data.get('planos', [])
    
    if not planos:
        message_text = (
            "💰 CONFIGURAÇÃO DE PLANOS\n\n"
            "Nenhum plano configurado."
        )
    else:
        message_text = (
            "💰 CONFIGURAÇÃO DE PLANOS\n\n"
            "Planos configurados:\n"
        )
        
        for i, plano in enumerate(planos, 1):
            message_text += f"{i}. {plano['nome']} - R$ {plano['preco']:.2f} - {plano['duracao']} dias\n"
    
    keyboard = [
        [InlineKeyboardButton("➕ Adicionar Plano", callback_data="adicionar_plano")],
        [InlineKeyboardButton("🔙 Voltar", callback_data="config_mensagens")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def adicionar_plano(update: Update, context: CallbackContext) -> None:
    """Inicia o processo de adição de um novo plano."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "💰 ADICIONAR PLANO\n\n"
        "Envie o nome do plano (ex: Mensal, Vitalício, etc.):"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Cancelar", callback_data="config_planos")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )
    
    # Definir o estado para aguardar nome do plano
    context.user_data['waiting_for'] = 'plan_name'
    context.user_data['new_plan'] = {}

def processar_nome_plano(update: Update, context: CallbackContext) -> None:
    """Processa o nome do plano enviado pelo usuário."""
    if context.user_data.get('waiting_for') != 'plan_name':
        return
    
    message = update.message
    text = message.text
    
    # Salvar nome do plano
    context.user_data['new_plan']['nome'] = text
    
    # Solicitar o preço
    message.reply_text(
        "💰 ADICIONAR PLANO\n\n"
        "Agora, envie o preço do plano (ex: 19.90):"
    )
    
    # Atualizar estado
    context.user_data['waiting_for'] = 'plan_price'

def processar_preco_plano(update: Update, context: CallbackContext) -> None:
    """Processa o preço do plano enviado pelo usuário."""
    if context.user_data.get('waiting_for') != 'plan_price':
        return
    
    message = update.message
    text = message.text
    
    try:
        # Converter e validar o preço
        preco = float(text.replace(',', '.'))
        if preco <= 0:
            raise ValueError("Preço deve ser maior que zero")
        
        # Salvar preço do plano
        context.user_data['new_plan']['preco'] = preco
        
        # Solicitar a duração
        keyboard = [
            [
                InlineKeyboardButton("1 Dia", callback_data="plan_duration_1"),
                InlineKeyboardButton("7 Dias", callback_data="plan_duration_7"),
                InlineKeyboardButton("15 Dias", callback_data="plan_duration_15")
            ],
            [
                InlineKeyboardButton("30 Dias", callback_data="plan_duration_30"),
                InlineKeyboardButton("3 Meses", callback_data="plan_duration_90"),
                InlineKeyboardButton("6 Meses", callback_data="plan_duration_180")
            ],
            [
                InlineKeyboardButton("1 Ano", callback_data="plan_duration_365"),
                InlineKeyboardButton("Vitalício", callback_data="plan_duration_9999")
            ]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        message.reply_text(
            "💰 ADICIONAR PLANO\n\n"
            "Por fim, escolha a duração do plano:",
            reply_markup=reply_markup
        )
        
        # Atualizar estado
        context.user_data['waiting_for'] = 'plan_duration'
        
    except ValueError:
        message.reply_text(
            "❌ Preço inválido! Por favor, envie um valor numérico válido (ex: 19.90):"
        )

def processar_duracao_plano(update: Update, context: CallbackContext) -> None:
    """Processa a duração do plano selecionada pelo usuário."""
    query = update.callback_query
    query.answer()
    
    if not query.data.startswith("plan_duration_"):
        return
    
    # Extrair duração da callback data
    duracao = int(query.data.split("_")[-1])
    
    # Salvar duração do plano
    context.user_data['new_plan']['duracao'] = duracao
    
    # Obter dados do novo plano
    new_plan = context.user_data['new_plan']
    nome = new_plan['nome']
    preco = new_plan['preco']
    
    # Texto da duração para exibição
    duracao_texto = "vitalício" if duracao == 9999 else f"{duracao} dias"
    
    # Adicionar plano à lista (simulação)
    # Na implementação real, salvar no banco de dados
    if 'planos' not in context.user_data:
        context.user_data['planos'] = []
    
    context.user_data['planos'].append(new_plan)
    
    # Exibir mensagem de sucesso
    message_text = (
        "✅ PLANO ADICIONADO!\n\n"
        f"• Nome: {nome}\n"
        f"• Preço: R$ {preco:.2f}\n"
        f"• Duração: {duracao_texto}"
    )
    
    keyboard = [
        [InlineKeyboardButton("➕ Adicionar outro plano", callback_data="adicionar_plano")],
        [InlineKeyboardButton("🔙 Voltar", callback_data="config_planos")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )
    
    # Limpar dados temporários
    context.user_data.pop('waiting_for', None)
    context.user_data.pop('new_plan', None)

def configurar_canal(update: Update, context: CallbackContext) -> None:
    """Configura o canal ou grupo para o bot."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "👥 CONFIGURAÇÃO DE GRUPO/CANAL\n\n"
        "Para vincular seu bot a um grupo ou canal, siga os passos:\n\n"
        "1. Adicione seu bot ao grupo/canal como administrador\n"
        "2. Conceda permissões para gerenciar membros\n"
        "3. Clique em ➕ Adicionar canal/grupo\n"
        "4. Envie o código gerado no seu grupo/canal"
    )
    
    keyboard = [
        [InlineKeyboardButton("➕ Adicionar canal/grupo", callback_data="gerar_codigo_canal")],
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_bot")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def gerar_codigo_canal(update: Update, context: CallbackContext) -> None:
    """Gera um código de validação para vincular um canal ou grupo."""
    query = update.callback_query
    query.answer()
    
    user_id = update.effective_user.id
    
    # Gerar código aleatório
    from handlers.channel_handler import gerar_codigo_validacao
    codigo = gerar_codigo_validacao()
    
    # Armazenar código temporariamente (na implementação real, usar Redis)
    if not hasattr(context.bot_data, 'codigos_validacao'):
        context.bot_data['codigos_validacao'] = {}
    
    # Salvar informações do código com validade de 1 hora
    context.bot_data['codigos_validacao'][codigo] = {
        'user_id': user_id,
        'bot_id': context.user_data.get('bot_id', 0),  # Simulação
        'expira': time.time() + 3600  # 1 hora
    }
    
    message_text = (
        "👥 CÓDIGO DE VALIDAÇÃO GERADO\n\n"
        f"Código gerado: {codigo}\n"
        "Validade: 1 hora\n\n"
        "Basta enviar esse código no grupo/canal que deseja vincular"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Voltar", callback_data="config_canal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )