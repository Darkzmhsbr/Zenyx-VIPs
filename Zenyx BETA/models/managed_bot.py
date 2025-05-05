#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Modelo para os bots gerenciados pelo Bot Zenyx
"""

import mysql.connector
from mysql.connector import Error as MySQLError
import logging
import requests
import json
from datetime import datetime

# Configuração de logging
logger = logging.getLogger(__name__)

class ManagedBot:
    """Classe que representa um bot gerenciado pelo sistema."""
    
    def __init__(self, owner_id, bot_token, bot_username=None):
        self.id = None
        self.owner_id = owner_id
        self.bot_token = bot_token
        self.bot_username = bot_username
        self.pushinpay_token = None
        self.welcome_text = "👋🏻 Olá %firstname%, seja bem-vindo!"
        self.created_at = datetime.now()
        self.updated_at = datetime.now()
    
    @classmethod
    def get_by_id(cls, conn, bot_id):
        """Busca um bot gerenciado pelo ID."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM managed_bots WHERE id = %s"
            cursor.execute(query, (bot_id,))
            
            bot_data = cursor.fetchone()
            cursor.close()
            
            if not bot_data:
                return None
            
            bot = cls(
                owner_id=bot_data['owner_id'],
                bot_token=bot_data['bot_token'],
                bot_username=bot_data['bot_username']
            )
            
            bot.id = bot_data['id']
            bot.pushinpay_token = bot_data['pushinpay_token']
            bot.welcome_text = bot_data['welcome_text']
            bot.created_at = bot_data['created_at']
            bot.updated_at = bot_data['updated_at']
            
            return bot
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar bot: {e}")
            return None
    
    @classmethod
    def get_by_username(cls, conn, username):
        """Busca um bot gerenciado pelo username."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM managed_bots WHERE bot_username = %s"
            cursor.execute(query, (username,))
            
            bot_data = cursor.fetchone()
            cursor.close()
            
            if not bot_data:
                return None
            
            bot = cls(
                owner_id=bot_data['owner_id'],
                bot_token=bot_data['bot_token'],
                bot_username=bot_data['bot_username']
            )
            
            bot.id = bot_data['id']
            bot.pushinpay_token = bot_data['pushinpay_token']
            bot.welcome_text = bot_data['welcome_text']
            bot.created_at = bot_data['created_at']
            bot.updated_at = bot_data['updated_at']
            
            return bot
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar bot pelo username: {e}")
            return None
    
    @classmethod
    def get_by_token(cls, conn, token):
        """Busca um bot gerenciado pelo token."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM managed_bots WHERE bot_token = %s"
            cursor.execute(query, (token,))
            
            bot_data = cursor.fetchone()
            cursor.close()
            
            if not bot_data:
                return None
            
            bot = cls(
                owner_id=bot_data['owner_id'],
                bot_token=bot_data['bot_token'],
                bot_username=bot_data['bot_username']
            )
            
            bot.id = bot_data['id']
            bot.pushinpay_token = bot_data['pushinpay_token']
            bot.welcome_text = bot_data['welcome_text']
            bot.created_at = bot_data['created_at']
            bot.updated_at = bot_data['updated_at']
            
            return bot
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar bot pelo token: {e}")
            return None
    
    @classmethod
    def get_by_owner(cls, conn, owner_id):
        """Busca todos os bots gerenciados por um determinado proprietário."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM managed_bots WHERE owner_id = %s"
            cursor.execute(query, (owner_id,))
            
            bots = []
            for bot_data in cursor.fetchall():
                bot = cls(
                    owner_id=bot_data['owner_id'],
                    bot_token=bot_data['bot_token'],
                    bot_username=bot_data['bot_username']
                )
                
                bot.id = bot_data['id']
                bot.pushinpay_token = bot_data['pushinpay_token']
                bot.welcome_text = bot_data['welcome_text']
                bot.created_at = bot_data['created_at']
                bot.updated_at = bot_data['updated_at']
                
                bots.append(bot)
            
            cursor.close()
            return bots
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar bots do proprietário: {e}")
            return []
    
    def save(self, conn):
        """Salva o bot gerenciado no banco de dados."""
        try:
            cursor = conn.cursor()
            
            if self.id is None:
                # Inserir novo bot
                query = """
                INSERT INTO managed_bots (owner_id, bot_token, bot_username, pushinpay_token, welcome_text)
                VALUES (%s, %s, %s, %s, %s)
                """
                values = (
                    self.owner_id,
                    self.bot_token,
                    self.bot_username,
                    self.pushinpay_token,
                    self.welcome_text
                )
                
                cursor.execute(query, values)
                self.id = cursor.lastrowid
            else:
                # Atualizar bot existente
                query = """
                UPDATE managed_bots SET
                    bot_username = %s,
                    pushinpay_token = %s,
                    welcome_text = %s,
                    updated_at = NOW()
                WHERE id = %s
                """
                values = (
                    self.bot_username,
                    self.pushinpay_token,
                    self.welcome_text,
                    self.id
                )
                
                cursor.execute(query, values)
            
            conn.commit()
            cursor.close()
            return True
            
        except MySQLError as e:
            logger.error(f"Erro ao salvar bot: {e}")
            conn.rollback()
            return False
    
    def delete(self, conn):
        """Remove o bot gerenciado do banco de dados."""
        if self.id is None:
            return False
            
        try:
            cursor = conn.cursor()
            query = "DELETE FROM managed_bots WHERE id = %s"
            cursor.execute(query, (self.id,))
            
            conn.commit()
            cursor.close()
            return True
            
        except MySQLError as e:
            logger.error(f"Erro ao remover bot: {e}")
            conn.rollback()
            return False
    
    def validate_token(self):
        """Valida o token do bot com a API do Telegram."""
        try:
            url = f"https://api.telegram.org/bot{self.bot_token}/getMe"
            response = requests.get(url)
            data = response.json()
            
            if data['ok']:
                self.bot_username = data['result']['username']
                return True
            
            return False
            
        except Exception as e:
            logger.error(f"Erro ao validar token do bot: {e}")
            return False
    
    def get_media(self, conn):
        """Obtém a mídia configurada para o bot."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM bot_media WHERE bot_id = %s ORDER BY created_at DESC LIMIT 1"
            cursor.execute(query, (self.id,))
            
            media = cursor.fetchone()
            cursor.close()
            
            return media
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar mídia do bot: {e}")
            return None
    
    def set_media(self, conn, file_id, media_type):
        """Define a mídia para o bot."""
        try:
            cursor = conn.cursor()
            
            # Remover mídia existente
            delete_query = "DELETE FROM bot_media WHERE bot_id = %s"
            cursor.execute(delete_query, (self.id,))
            
            # Inserir nova mídia
            insert_query = "INSERT INTO bot_media (bot_id, file_id, media_type) VALUES (%s, %s, %s)"
            cursor.execute(insert_query, (self.id, file_id, media_type))
            
            conn.commit()
            cursor.close()
            return True
            
        except MySQLError as e:
            logger.error(f"Erro ao definir mídia para o bot: {e}")
            conn.rollback()
            return False
    
    def get_plans(self, conn):
        """Obtém os planos configurados para o bot."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM plans WHERE bot_id = %s ORDER BY price ASC"
            cursor.execute(query, (self.id,))
            
            plans = cursor.fetchall()
            cursor.close()
            
            return plans
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar planos do bot: {e}")
            return []
    
    def add_plan(self, conn, name, price, duration):
        """Adiciona um plano para o bot."""
        try:
            cursor = conn.cursor()
            query = "INSERT INTO plans (bot_id, name, price, duration) VALUES (%s, %s, %s, %s)"
            cursor.execute(query, (self.id, name, price, duration))
            
            plan_id = cursor.lastrowid
            
            conn.commit()
            cursor.close()
            return plan_id
            
        except MySQLError as e:
            logger.error(f"Erro ao adicionar plano para o bot: {e}")
            conn.rollback()
            return None
    
    def remove_plan(self, conn, plan_id):
        """Remove um plano do bot."""
        try:
            cursor = conn.cursor()
            query = "DELETE FROM plans WHERE id = %s AND bot_id = %s"
            cursor.execute(query, (plan_id, self.id))
            
            conn.commit()
            cursor.close()
            return True
            
        except MySQLError as e:
            logger.error(f"Erro ao remover plano do bot: {e}")
            conn.rollback()
            return False
    
    def get_managed_groups(self, conn):
        """Obtém os grupos/canais gerenciados pelo bot."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM managed_groups WHERE bot_id = %s"
            cursor.execute(query, (self.id,))
            
            groups = cursor.fetchall()
            cursor.close()
            
            return groups
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar grupos gerenciados pelo bot: {e}")
            return []
    
    def add_managed_group(self, conn, chat_id, chat_title, chat_type, invite_link=None):
        """Adiciona um grupo/canal gerenciado pelo bot."""
        try:
            cursor = conn.cursor()
            query = """
            INSERT INTO managed_groups (bot_id, chat_id, chat_title, chat_type, invite_link)
            VALUES (%s, %s, %s, %s, %s)
            """
            cursor.execute(query, (self.id, chat_id, chat_title, chat_type, invite_link))
            
            group_id = cursor.lastrowid
            
            conn.commit()
            cursor.close()
            return group_id
            
        except MySQLError as e:
            logger.error(f"Erro ao adicionar grupo gerenciado pelo bot: {e}")
            conn.rollback()
            return None
    
    def remove_managed_group(self, conn, group_id):
        """Remove um grupo/canal gerenciado pelo bot."""
        try:
            cursor = conn.cursor()
            query = "DELETE FROM managed_groups WHERE id = %s AND bot_id = %s"
            cursor.execute(query, (group_id, self.id))
            
            conn.commit()
            cursor.close()
            return True
            
        except MySQLError as e:
            logger.error(f"Erro ao remover grupo gerenciado pelo bot: {e}")
            conn.rollback()
            return False
    
    def __str__(self):
        """Retorna uma representação em string do bot gerenciado."""
        return f"ManagedBot(id={self.id}, username={self.bot_username})"