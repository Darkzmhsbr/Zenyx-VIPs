#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Funções utilitárias para o Bot Zenyx

Este módulo contém funções auxiliares utilizadas em várias partes do sistema,
facilitando operações comuns e mantendo o código mais limpo e organizado.

Autor: Claude
Data: 04/05/2025
"""

import os
import re
import random
import string
import json
import logging
import time
from datetime import datetime, timedelta
import hashlib
from typing import Optional, Dict, Any, List, Union, Tuple

# Imports do Telegram
from telegram import Update, ChatMember, Bot, User
from telegram.error import TelegramError

# Configuração de logging
logger = logging.getLogger(__name__)

# Funções de validação
def is_valid_bot_token(token: str) -> bool:
    """
    Verifica se um token de bot do Telegram é válido no formato.
    
    Args:
        token: String contendo o token a ser validado
        
    Returns:
        bool: True se o token tem formato válido, False caso contrário
    """
    token_pattern = r'^\d+:[A-Za-z0-9_-]{35}$'
    return bool(re.match(token_pattern, token))

def validate_username(username: str) -> bool:
    """
    Verifica se um nome de usuário do Telegram é válido.
    
    Args:
        username: String contendo o username a ser validado
        
    Returns:
        bool: True se o username tem formato válido, False caso contrário
    """
    if not username:
        return False
    
    # Telegram usernames devem ter entre 5 e 32 caracteres
    # e conter apenas letras latinas, números e underscores
    username_pattern = r'^[a-zA-Z0-9_]{5,32}$'
    return bool(re.match(username_pattern, username))

def validate_phone(phone: str) -> bool:
    """
    Verifica se um número de telefone está em formato válido.
    
    Args:
        phone: String contendo o número de telefone
        
    Returns:
        bool: True se o telefone tem formato válido, False caso contrário
    """
    # Remove caracteres não numéricos
    phone_clean = re.sub(r'\D', '', phone)
    
    # Verifica se tem entre 10 e 15 dígitos (padrão internacional)
    return 10 <= len(phone_clean) <= 15

# Funções de geração
def generate_random_code(length: int = 8) -> str:
    """
    Gera um código aleatório com letras maiúsculas e números.
    
    Args:
        length: Comprimento do código a ser gerado (padrão: 8)
        
    Returns:
        str: Código aleatório gerado
    """
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=length))

def generate_transaction_id() -> str:
    """
    Gera um ID único para transações financeiras.
    
    Returns:
        str: ID de transação único baseado no timestamp e valor aleatório
    """
    timestamp = int(time.time())
    random_part = random.randint(1000, 9999)
    return f"TX{timestamp}{random_part}"

def generate_secure_hash(data: str) -> str:
    """
    Gera um hash seguro a partir de uma string.
    
    Args:
        data: String para gerar o hash
        
    Returns:
        str: Hash SHA-256 em formato hexadecimal
    """
    return hashlib.sha256(data.encode('utf-8')).hexdigest()

# Funções de verificação de Telegram
def is_user_admin(bot: Bot, chat_id: Union[str, int], user_id: int) -> bool:
    """
    Verifica se um usuário é administrador de um chat.
    
    Args:
        bot: Objeto Bot do Telegram
        chat_id: ID do chat a verificar
        user_id: ID do usuário a verificar
        
    Returns:
        bool: True se o usuário é administrador, False caso contrário
    """
    try:
        chat_member = bot.get_chat_member(chat_id=chat_id, user_id=user_id)
        return chat_member.status in ['creator', 'administrator']
    except TelegramError as e:
        logger.error(f"Erro ao verificar status de administrador: {e}")
        return False

def is_user_member(bot: Bot, chat_id: Union[str, int], user_id: int) -> bool:
    """
    Verifica se um usuário é membro de um chat.
    
    Args:
        bot: Objeto Bot do Telegram
        chat_id: ID do chat a verificar
        user_id: ID do usuário a verificar
        
    Returns:
        bool: True se o usuário é membro, False caso contrário
    """
    try:
        chat_member = bot.get_chat_member(chat_id=chat_id, user_id=user_id)
        return chat_member.status in ['creator', 'administrator', 'member']
    except TelegramError as e:
        logger.error(f"Erro ao verificar status de membro: {e}")
        return False

def get_bot_info(token: str) -> Optional[Dict[str, Any]]:
    """
    Obtém informações de um bot a partir do token.
    
    Args:
        token: Token do bot a consultar
        
    Returns:
        Dict ou None: Dicionário com informações do bot ou None em caso de erro
    """
    import requests
    
    try:
        url = f"https://api.telegram.org/bot{token}/getMe"
        response = requests.get(url, timeout=10)
        data = response.json()
        
        if data.get('ok', False):
            return data.get('result')
        else:
            logger.error(f"Erro ao obter informações do bot: {data.get('description')}")
            return None
    except Exception as e:
        logger.error(f"Erro ao consultar API do Telegram: {e}")
        return None

# Funções de formatação
def format_currency(value: float) -> str:
    """
    Formata um valor monetário no padrão brasileiro.
    
    Args:
        value: Valor a ser formatado
        
    Returns:
        str: Valor formatado (ex: R$ 123,45)
    """
    return f"R$ {value:.2f}".replace('.', ',')

def format_duration(days: int) -> str:
    """
    Formata a duração em dias de forma amigável.
    
    Args:
        days: Número de dias
        
    Returns:
        str: Texto formatado (ex: 2 anos, 3 meses, 5 dias)
    """
    if days == 9999:
        return "Vitalício"
        
    if days >= 365:
        years = days // 365
        return f"{years} {'ano' if years == 1 else 'anos'}"
    elif days >= 30:
        months = days // 30
        return f"{months} {'mês' if months == 1 else 'meses'}"
    else:
        return f"{days} {'dia' if days == 1 else 'dias'}"

def format_datetime(dt: datetime) -> str:
    """
    Formata uma data e hora no padrão brasileiro.
    
    Args:
        dt: Objeto datetime a ser formatado
        
    Returns:
        str: Data e hora formatada (ex: 01/01/2025 14:30)
    """
    return dt.strftime("%d/%m/%Y %H:%M")

def format_date(dt: datetime) -> str:
    """
    Formata uma data no padrão brasileiro.
    
    Args:
        dt: Objeto datetime a ser formatado
        
    Returns:
        str: Data formatada (ex: 01/01/2025)
    """
    return dt.strftime("%d/%m/%Y")

# Funções para Redis
def save_state_redis(redis_conn, user_id: int, key: str, value: Any, ttl: int = 3600) -> bool:
    """
    Salva um estado temporário no Redis.
    
    Args:
        redis_conn: Conexão com o Redis
        user_id: ID do usuário
        key: Chave para o estado
        value: Valor a ser armazenado (será convertido para JSON)
        ttl: Tempo de vida em segundos (padrão: 1 hora)
        
    Returns:
        bool: True se salvo com sucesso, False caso contrário
    """
    try:
        redis_key = f"user:{user_id}:{key}"
        redis_conn.set(redis_key, json.dumps(value), ex=ttl)
        return True
    except Exception as e:
        logger.error(f"Erro ao salvar estado no Redis: {e}")
        return False

def get_state_redis(redis_conn, user_id: int, key: str) -> Optional[Any]:
    """
    Obtém um estado temporário do Redis.
    
    Args:
        redis_conn: Conexão com o Redis
        user_id: ID do usuário
        key: Chave para o estado
        
    Returns:
        Any ou None: Valor armazenado ou None se não encontrado/erro
    """
    try:
        redis_key = f"user:{user_id}:{key}"
        value = redis_conn.get(redis_key)
        if value:
            return json.loads(value)
        return None
    except Exception as e:
        logger.error(f"Erro ao obter estado do Redis: {e}")
        return None

def delete_state_redis(redis_conn, user_id: int, key: str) -> bool:
    """
    Remove um estado temporário do Redis.
    
    Args:
        redis_conn: Conexão com o Redis
        user_id: ID do usuário
        key: Chave para o estado
        
    Returns:
        bool: True se removido com sucesso, False caso contrário
    """
    try:
        redis_key = f"user:{user_id}:{key}"
        redis_conn.delete(redis_key)
        return True
    except Exception as e:
        logger.error(f"Erro ao remover estado do Redis: {e}")
        return False

def set_temp_data(redis_conn, key: str, value: Any, ttl: int = 1800) -> bool:
    """
    Armazena dados temporários no Redis (não associados a um usuário específico).
    
    Args:
        redis_conn: Conexão com o Redis
        key: Chave para os dados
        value: Valor a ser armazenado (será convertido para JSON)
        ttl: Tempo de vida em segundos (padrão: 30 minutos)
        
    Returns:
        bool: True se salvo com sucesso, False caso contrário
    """
    try:
        redis_key = f"temp:{key}"
        redis_conn.set(redis_key, json.dumps(value), ex=ttl)
        return True
    except Exception as e:
        logger.error(f"Erro ao salvar dados temporários no Redis: {e}")
        return False

def get_temp_data(redis_conn, key: str) -> Optional[Any]:
    """
    Obtém dados temporários do Redis.
    
    Args:
        redis_conn: Conexão com o Redis
        key: Chave para os dados
        
    Returns:
        Any ou None: Valor armazenado ou None se não encontrado/erro
    """
    try:
        redis_key = f"temp:{key}"
        value = redis_conn.get(redis_key)
        if value:
            return json.loads(value)
        return None
    except Exception as e:
        logger.error(f"Erro ao obter dados temporários do Redis: {e}")
        return None

# Funções para lidar com mensagens do Telegram
def parse_message_variables(text: str, user: User) -> str:
    """
    Substitui variáveis em um texto por valores do usuário.
    
    Args:
        text: Texto com variáveis a serem substituídas
        user: Objeto User do Telegram
        
    Returns:
        str: Texto com variáveis substituídas
    """
    # Substituir variáveis
    replacements = {
        '%firstname%': user.first_name or '',
        '%lastname%': user.last_name or '',
        '%username%': f"@{user.username}" if user.username else '',
        '%userid%': str(user.id),
        '%fullname%': f"{user.first_name or ''} {user.last_name or ''}".strip()
    }
    
    for var, value in replacements.items():
        text = text.replace(var, value)
    
    return text

def truncate_text(text: str, max_length: int = 4096) -> str:
    """
    Trunca um texto para o tamanho máximo permitido no Telegram.
    
    Args:
        text: Texto a ser truncado
        max_length: Comprimento máximo (padrão: 4096, limite do Telegram)
        
    Returns:
        str: Texto truncado se necessário
    """
    if len(text) <= max_length:
        return text
    
    # Truncar preservando palavras completas
    return text[:max_length-3].rsplit(' ', 1)[0] + '...'

# Funções para análise de dados
def calculate_conversion_rate(total: int, conversions: int) -> float:
    """
    Calcula a taxa de conversão.
    
    Args:
        total: Número total de casos
        conversions: Número de conversões
        
    Returns:
        float: Taxa de conversão como porcentagem
    """
    if total == 0:
        return 0.0
    
    return (conversions / total) * 100

def get_date_range(days: int) -> Tuple[datetime, datetime]:
    """
    Retorna um intervalo de datas a partir de hoje.
    
    Args:
        days: Número de dias para o intervalo
        
    Returns:
        Tuple: (data_início, data_fim)
    """
    end_date = datetime.now()
    start_date = end_date - timedelta(days=days)
    return start_date, end_date

# Funções para validação de planos
def is_plan_expired(end_date: Optional[datetime]) -> bool:
    """
    Verifica se um plano expirou.
    
    Args:
        end_date: Data de término do plano (None para planos vitalícios)
        
    Returns:
        bool: True se expirou, False caso contrário
    """
    if end_date is None:
        return False  # Plano vitalício
    
    return end_date < datetime.now()

def days_until_expiration(end_date: Optional[datetime]) -> Optional[int]:
    """
    Calcula quantos dias faltam para um plano expirar.
    
    Args:
        end_date: Data de término do plano (None para planos vitalícios)
        
    Returns:
        int ou None: Número de dias ou None para planos vitalícios
    """
    if end_date is None:
        return None  # Plano vitalício
    
    delta = end_date - datetime.now()
    return max(0, delta.days)

# Tratamento de erros
def safe_execution(func, default_return=None, log_exception=True, *args, **kwargs):
    """
    Executa uma função de forma segura, capturando exceções.
    
    Args:
        func: Função a ser executada
        default_return: Valor a retornar em caso de erro
        log_exception: Se True, registra a exceção no log
        *args, **kwargs: Argumentos para a função
        
    Returns:
        Any: Resultado da função ou default_return em caso de erro
    """
    try:
        return func(*args, **kwargs)
    except Exception as e:
        if log_exception:
            logger.error(f"Erro ao executar {func.__name__}: {e}")
        return default_return