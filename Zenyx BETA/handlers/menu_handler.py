#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Handlers para o menu principal do Bot Zenyx
"""

import logging
from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import CallbackContext

# Configuração de logging
logger = logging.getLogger(__name__)

def menu_callback(update: Update, context: CallbackContext) -> None:
    """Handler para exibir o menu principal."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "🔥 BOT CRIADOR DE BOTS 🔥\n\n"
        "Este bot permite que você crie seu próprio bot para gerenciar grupos VIP "
        "com sistema de pagamento integrado."
    )
    
    keyboard = [
        [InlineKeyboardButton("🤖 Criar seu Bot", callback_data="criar_bot")],
        [InlineKeyboardButton("💰 Meu Saldo", callback_data="meu_saldo")],
        [InlineKeyboardButton("👥 Convide e Ganhe", callback_data="convite")],
        [InlineKeyboardButton("👑 Seja Admin VIP", callback_data="admin_vip")],
        [InlineKeyboardButton("ℹ️ Como Funciona", callback_data="como_funciona")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def criar_bot_callback(update: Update, context: CallbackContext) -> None:
    """Handler para a opção 'Criar seu Bot'."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "🤖 CRIAR SEU BOT\n\n"
        "Instruções detalhadas:\n\n"
        "1. Acesse o @BotFather e envie o comando /newbot\n"
        "2. Siga o passo a passo e escolha nome/username para seu bot\n"
        "3. Copie o token fornecido pelo BotFather\n"
        "4. Volte neste chat e cole o token aqui\n\n"
        "Exemplo de token:\n"
        "123456789:ABCDefGhIJKlmNoPQRsTUVwxyZ"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_principal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )
    
    # Definir o estado do usuário para aguardar o token
    context.user_data['waiting_for'] = 'bot_token'

def meu_saldo_callback(update: Update, context: CallbackContext) -> None:
    """Handler para a opção 'Meu Saldo'."""
    query = update.callback_query
    query.answer()
    
    # Aqui deveria buscar o saldo real do usuário no banco de dados
    saldo_atual = 0.00
    
    message_text = (
        "💰 SEU SALDO\n\n"
        f"• Saldo atual: R$ {saldo_atual:.2f}\n"
        "• Para saque: mínimo de R$ 30.00\n"
        "• Intervalo entre saques: 24 horas"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_principal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def convite_callback(update: Update, context: CallbackContext) -> None:
    """Handler para a opção 'Convide e Ganhe'."""
    query = update.callback_query
    query.answer()
    
    # Aqui deveria buscar os dados reais do usuário no banco de dados
    user_id = update.effective_user.id
    indicados = 0
    ganhos_totais = 0.00
    link_indicacao = f"https://t.me/GestorPaybot?start=ref_{user_id}"
    
    message_text = (
        "👥 CONVIDE E GANHE\n\n"
        "Ganho mínimo garantido: R$ 125,00 em 15 dias\n"
        "Fontes de monetização:\n"
        "• Rendimentos passivos com saldo\n"
        "• Anúncios pagos no sistema\n"
        "• Programa de afiliados da PushinPay (60% do lucro)\n\n"
        "Regras para receber:\n"
        "• O indicado precisa realizar no mínimo 3 vendas de R$ 9.90 em 15 dias\n"
        "• O vínculo é temporário: após 15 dias o indicado \"desvincula\" de quem o convidou\n\n"
        "Estatísticas do usuário:\n"
        f"• Indicados: {indicados}\n"
        f"• Ganhos totais: R$ {ganhos_totais:.2f}\n"
        f"• Link de indicação:\n{link_indicacao}"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_principal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def admin_vip_callback(update: Update, context: CallbackContext) -> None:
    """Handler para a opção 'Seja Admin VIP'."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "👑 SEJA ADMIN VIP\n\n"
        "Benefícios como Admin VIP:\n"
        "• Comissões vitalícias sobre vendas de bots\n"
        "• Rendimentos automáticos todos os dias\n"
        "• Comissões sobre vendas e rendimento de indicados\n"
        "• Participação nos lucros com anúncios\n\n"
        "Período gratuito: 30 dias\n"
        "Após isso: R$ 97,90/mês"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔓 Ativar mês gratuito", callback_data="ativar_vip")],
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_principal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )

def como_funciona_callback(update: Update, context: CallbackContext) -> None:
    """Handler para a opção 'Como Funciona'."""
    query = update.callback_query
    query.answer()
    
    message_text = (
        "ℹ️ COMO FUNCIONA\n\n"
        "O Bot Zenyx é uma plataforma que permite você criar seu próprio bot "
        "para gerenciar grupos e canais pagos no Telegram. Veja como funciona:\n\n"
        "1. Crie seu bot utilizando o @BotFather\n"
        "2. Configure-o aqui com nossas ferramentas\n"
        "3. Adicione seu bot ao seu grupo ou canal\n"
        "4. Configure os planos de pagamento\n"
        "5. Divulgue seu conteúdo exclusivo\n\n"
        "O sistema gerencia automaticamente:\n"
        "• Pagamentos via PIX\n"
        "• Acessos dos usuários\n"
        "• Renovações de assinaturas\n"
        "• Comissões e indicações"
    )
    
    keyboard = [
        [InlineKeyboardButton("🔙 Voltar", callback_data="menu_principal")]
    ]
    
    reply_markup = InlineKeyboardMarkup(keyboard)
    
    query.edit_message_text(
        text=message_text,
        reply_markup=reply_markup
    )