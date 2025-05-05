#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Handlers para processamento de pagamentos com PushinPay no Bot Zenyx
"""

import os
import logging
import requests
import json
import time
from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import CallbackContext

# Importações de conexão com DB
from main import get_mysql_connection, get_redis_connection

# Configuração de logging
logger = logging.getLogger(__name__)

# Configurações do PushinPay
PUSHIN_PAY_TOKEN = os.environ.get("PUSHIN_PAY_TOKEN")
PUSHIN_PAY_API_URL = "https://api.pushinpay.com.br/v1"  # URL de exemplo

def processar_pagamento(update: Update, context: CallbackContext) -> None:
    """Processa um novo pagamento."""
    query = update.callback_query
    query.answer()
    
    # Verificar se a callback data começa com 'pagar_'
    if not query.data.startswith("pagar_"):
        return
    
    # Extrair ID do plano da callback data
    try:
        plano_id = int(query.data.split("_")[1])
    except (IndexError, ValueError):
        query.edit_message_text("❌ Erro ao processar o pagamento. Plano inválido.")
        return
    
    # Buscar informações do plano (simulação)
    # Na implementação real, buscar do banco de dados
    planos = context.user_data.get('planos', [])
    plano = None
    
    for p in planos:
        if p.get('id', 0) == plano_id:
            plano = p
            break
    
    if not plano:
        # Criar plano fictício para demonstração
        plano = {
            'id': plano_id,
            'nome': 'Plano Teste',
            'preco': 19.90,
            'duracao': 30
        }
    
    user = update.effective_user
    
    # Preparar mensagem de processamento
    processing_text = (
        "💰 PROCESSANDO PAGAMENTO\n\n"
        f"Plano: {plano['nome']}\n"
        f"Valor: R$ {plano['preco']:.2f}\n"
        f"Duração: {plano['duracao']} dias\n\n"
        "Gerando QR Code PIX..."
    )
    
    query.edit_message_text(
        text=processing_text
    )
    
    # Gerar pagamento no PushinPay (simulação)
    # Na implementação real, fazer requisição à API do PushinPay
    try:
        # Simular atraso na requisição
        time.sleep(1)
        
        # Gerar ID de transação único
        transaction_id = f"TX{int(time.time())}"
        
        # Dados do pagamento (simulação)
        payment_data = {
            'qrcode_text': 'PIX_QR_CODE_EXAMPLE_12345',
            'qrcode_image_url': 'https://example.com/qrcode.png',
            'expiration_time': int(time.time()) + 1800,  # 30 minutos
            'transaction_id': transaction_id
        }
        
        # Preparar mensagem com QR Code
        payment_text = (
            "💰 PAGAMENTO PIX GERADO\n\n"
            f"Plano: {plano['nome']}\n"
            f"Valor: R$ {plano['preco']:.2f}\n"
            f"Duração: {plano['duracao']} dias\n\n"
            "Escaneie o QR Code abaixo ou copie o código PIX:"
        )
        
        # Adicionar código PIX
        pix_code = payment_data['qrcode_text']
        
        # Botões para copiar código e verificar pagamento
        keyboard = [
            [InlineKeyboardButton("📋 Copiar Código PIX", callback_data=f"copy_pix_{transaction_id}")],
            [InlineKeyboardButton("🔄 Verificar Pagamento", callback_data=f"check_payment_{transaction_id}")],
            [InlineKeyboardButton("❌ Cancelar", callback_data="menu_principal")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        # Enviar mensagem com botão para copiar o código PIX
        query.edit_message_text(
            text=f"{payment_text}\n\n`{pix_code}`",
            reply_markup=reply_markup,
            parse_mode='Markdown'
        )
        
        # Salvar informações do pagamento (simulação)
        # Na implementação real, salvar no banco de dados
        if not hasattr(context.bot_data, 'pagamentos'):
            context.bot_data['pagamentos'] = {}
        
        context.bot_data['pagamentos'][transaction_id] = {
            'user_id': user.id,
            'plano_id': plano_id,
            'valor': plano['preco'],
            'status': 'pending',
            'created_at': time.time()
        }
        
    except Exception as e:
        logger.error(f"Erro ao gerar pagamento: {e}")
        query.edit_message_text(
            "❌ Ocorreu um erro ao gerar o pagamento. Por favor, tente novamente mais tarde."
        )

def verificar_pagamento(update: Update, context: CallbackContext) -> None:
    """Verifica o status de um pagamento."""
    query = update.callback_query
    query.answer()
    
    # Verificar se a callback data começa com 'check_payment_'
    if not query.data.startswith("check_payment_"):
        return
    
    # Extrair ID da transação da callback data
    transaction_id = query.data.split("_")[2]
    
    # Buscar informações do pagamento (simulação)
    # Na implementação real, verificar na API do PushinPay
    pagamentos = getattr(context.bot_data, 'pagamentos', {})
    pagamento = pagamentos.get(transaction_id)
    
    if not pagamento:
        query.edit_message_text("❌ Pagamento não encontrado ou expirado.")
        return
    
    # Simular verificação do pagamento
    # Na implementação real, fazer requisição à API do PushinPay
    try:
        # Aqui, vamos simular um pagamento aprovado
        # Na implementação real, verificar na API do PushinPay
        
        # Atualizar status do pagamento (simulação)
        pagamento['status'] = 'approved'
        
        # Preparar mensagem de sucesso
        success_text = (
            "✅ PAGAMENTO APROVADO!\n\n"
            "Seu acesso foi liberado com sucesso.\n\n"
            "Agradecemos pela sua compra!"
        )
        
        keyboard = [
            [InlineKeyboardButton("🔙 Voltar ao Menu", callback_data="menu_principal")]
        ]
        
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        query.edit_message_text(
            text=success_text,
            reply_markup=reply_markup
        )
        
        # Atualizar assinatura do usuário (simulação)
        # Na implementação real, atualizar no banco de dados
        
    except Exception as e:
        logger.error(f"Erro ao verificar pagamento: {e}")
        query.edit_message_text(
            "❌ Ocorreu um erro ao verificar o pagamento. Por favor, tente novamente mais tarde."
        )

def callback_pagamento(update: Update, context: CallbackContext) -> None:
    """Recebe callbacks de pagamentos do PushinPay."""
    # Esta função seria chamada por um webhook que o PushinPay aciona
    # quando o status de um pagamento muda
    
    # Na implementação real, você precisaria configurar um endpoint
    # para receber as notificações do PushinPay
    
    # O código abaixo é apenas um esboço do que seria feito
    
    try:
        data = json.loads(update.request.body)
        transaction_id = data.get('transaction_id')
        status = data.get('status')
        
        if not transaction_id or not status:
            return
        
        # Atualizar status do pagamento no banco de dados
        # e realizar ações necessárias (liberar acesso, etc)
        
        if status == 'approved':
            # Pagamento aprovado
            pass
        elif status == 'rejected':
            # Pagamento rejeitado
            pass
        elif status == 'canceled':
            # Pagamento cancelado
            pass
        
    except Exception as e:
        logger.error(f"Erro ao processar callback de pagamento: {e}")