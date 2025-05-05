#!/usr/bin/env python
# -*- coding: utf-8 -*-

"""
Modelo para usuários do Bot Zenyx
"""

import mysql.connector
from mysql.connector import Error as MySQLError
import logging
from datetime import datetime, timedelta

# Configuração de logging
logger = logging.getLogger(__name__)

class User:
    """Classe que representa um usuário do sistema."""
    
    def __init__(self, telegram_id, username=None, first_name=None, last_name=None):
        self.id = None
        self.telegram_id = telegram_id
        self.username = username
        self.first_name = first_name
        self.last_name = last_name
        self.is_admin = False
        self.is_vip = False
        self.vip_until = None
        self.balance = 0.0
        self.created_at = datetime.now()
        self.updated_at = datetime.now()
    
    @classmethod
    def from_telegram_user(cls, user):
        """Cria um objeto User a partir de um objeto telegram.User."""
        return cls(
            telegram_id=user.id,
            username=user.username,
            first_name=user.first_name,
            last_name=user.last_name
        )
    
    @classmethod
    def get_by_telegram_id(cls, conn, telegram_id):
        """Busca um usuário pelo ID do Telegram."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM users WHERE telegram_id = %s"
            cursor.execute(query, (telegram_id,))
            
            user_data = cursor.fetchone()
            cursor.close()
            
            if not user_data:
                return None
            
            user = cls(
                telegram_id=user_data['telegram_id'],
                username=user_data['username'],
                first_name=user_data['first_name'],
                last_name=user_data['last_name']
            )
            
            user.id = user_data['id']
            user.is_admin = user_data['is_admin']
            user.is_vip = user_data['is_vip']
            user.vip_until = user_data['vip_until']
            user.balance = float(user_data['balance'])
            user.created_at = user_data['created_at']
            user.updated_at = user_data['updated_at']
            
            return user
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar usuário: {e}")
            return None
    
    def save(self, conn):
        """Salva o usuário no banco de dados."""
        try:
            cursor = conn.cursor()
            
            if self.id is None:
                # Inserir novo usuário
                query = """
                INSERT INTO users (telegram_id, username, first_name, last_name, is_admin, is_vip, vip_until, balance)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """
                values = (
                    self.telegram_id,
                    self.username,
                    self.first_name,
                    self.last_name,
                    self.is_admin,
                    self.is_vip,
                    self.vip_until,
                    self.balance
                )
                
                cursor.execute(query, values)
                self.id = cursor.lastrowid
            else:
                # Atualizar usuário existente
                query = """
                UPDATE users SET
                    username = %s,
                    first_name = %s,
                    last_name = %s,
                    is_admin = %s,
                    is_vip = %s,
                    vip_until = %s,
                    balance = %s,
                    updated_at = NOW()
                WHERE id = %s
                """
                values = (
                    self.username,
                    self.first_name,
                    self.last_name,
                    self.is_admin,
                    self.is_vip,
                    self.vip_until,
                    self.balance,
                    self.id
                )
                
                cursor.execute(query, values)
            
            conn.commit()
            cursor.close()
            return True
            
        except MySQLError as e:
            logger.error(f"Erro ao salvar usuário: {e}")
            conn.rollback()
            return False
    
    def activate_vip(self, conn, days):
        """Ativa o status VIP para o usuário por um período determinado."""
        try:
            if self.is_vip and self.vip_until and self.vip_until > datetime.now():
                # Estender período VIP existente
                self.vip_until = self.vip_until + timedelta(days=days)
            else:
                # Novo período VIP
                self.is_vip = True
                self.vip_until = datetime.now() + timedelta(days=days)
            
            return self.save(conn)
            
        except Exception as e:
            logger.error(f"Erro ao ativar VIP para usuário {self.telegram_id}: {e}")
            return False
    
    def add_balance(self, conn, amount, transaction_type="deposit", reference_id=None, description=None):
        """Adiciona um valor ao saldo do usuário e registra a transação."""
        try:
            cursor = conn.cursor()
            
            # Atualizar saldo do usuário
            self.balance += amount
            
            # Registrar transação
            query = """
            INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
            VALUES (%s, %s, %s, %s, %s, %s)
            """
            values = (
                self.telegram_id,
                amount,
                transaction_type,
                "completed",
                reference_id,
                description
            )
            
            cursor.execute(query, values)
            transaction_id = cursor.lastrowid
            
            # Salvar alterações no usuário
            self.save(conn)
            
            conn.commit()
            cursor.close()
            
            return transaction_id
            
        except MySQLError as e:
            logger.error(f"Erro ao adicionar saldo para usuário {self.telegram_id}: {e}")
            conn.rollback()
            return False
    
    def remove_balance(self, conn, amount, transaction_type="withdrawal", reference_id=None, description=None):
        """Remove um valor do saldo do usuário e registra a transação."""
        if self.balance < amount:
            return False
            
        return self.add_balance(conn, -amount, transaction_type, reference_id, description)
    
    @classmethod
    def get_all_admins(cls, conn):
        """Retorna todos os usuários administradores."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = "SELECT * FROM users WHERE is_admin = TRUE"
            cursor.execute(query)
            
            admins = []
            for user_data in cursor.fetchall():
                user = cls(
                    telegram_id=user_data['telegram_id'],
                    username=user_data['username'],
                    first_name=user_data['first_name'],
                    last_name=user_data['last_name']
                )
                
                user.id = user_data['id']
                user.is_admin = user_data['is_admin']
                user.is_vip = user_data['is_vip']
                user.vip_until = user_data['vip_until']
                user.balance = float(user_data['balance'])
                user.created_at = user_data['created_at']
                user.updated_at = user_data['updated_at']
                
                admins.append(user)
            
            cursor.close()
            return admins
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar administradores: {e}")
            return []
    
    @classmethod
    def get_all_vip(cls, conn):
        """Retorna todos os usuários VIP ativos."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = """
            SELECT * FROM users 
            WHERE is_vip = TRUE AND (vip_until IS NULL OR vip_until > NOW())
            """
            cursor.execute(query)
            
            vips = []
            for user_data in cursor.fetchall():
                user = cls(
                    telegram_id=user_data['telegram_id'],
                    username=user_data['username'],
                    first_name=user_data['first_name'],
                    last_name=user_data['last_name']
                )
                
                user.id = user_data['id']
                user.is_admin = user_data['is_admin']
                user.is_vip = user_data['is_vip']
                user.vip_until = user_data['vip_until']
                user.balance = float(user_data['balance'])
                user.created_at = user_data['created_at']
                user.updated_at = user_data['updated_at']
                
                vips.append(user)
            
            cursor.close()
            return vips
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar usuários VIP: {e}")
            return []
    
    def get_referrals(self, conn):
        """Retorna os usuários que foram indicados por este usuário."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = """
            SELECT u.* FROM users u
            JOIN referrals r ON u.telegram_id = r.referred_id
            WHERE r.referrer_id = %s
            """
            cursor.execute(query, (self.telegram_id,))
            
            referrals = []
            for user_data in cursor.fetchall():
                user = User(
                    telegram_id=user_data['telegram_id'],
                    username=user_data['username'],
                    first_name=user_data['first_name'],
                    last_name=user_data['last_name']
                )
                
                user.id = user_data['id']
                user.is_admin = user_data['is_admin']
                user.is_vip = user_data['is_vip']
                user.vip_until = user_data['vip_until']
                user.balance = float(user_data['balance'])
                user.created_at = user_data['created_at']
                user.updated_at = user_data['updated_at']
                
                referrals.append(user)
            
            cursor.close()
            return referrals
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar indicados do usuário {self.telegram_id}: {e}")
            return []
    
    def get_transaction_history(self, conn, limit=10):
        """Retorna o histórico de transações do usuário."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = """
            SELECT * FROM transactions
            WHERE user_id = %s
            ORDER BY created_at DESC
            LIMIT %s
            """
            cursor.execute(query, (self.telegram_id, limit))
            
            transactions = cursor.fetchall()
            cursor.close()
            
            return transactions
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar histórico de transações do usuário {self.telegram_id}: {e}")
            return []
    
    def check_subscription_status(self, conn, group_id):
        """Verifica o status da assinatura do usuário para um determinado grupo/canal."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = """
            SELECT * FROM subscriptions
            WHERE user_id = %s AND group_id = %s AND
            (end_date IS NULL OR end_date > NOW()) AND
            payment_status = 'approved'
            """
            cursor.execute(query, (self.telegram_id, group_id))
            
            subscription = cursor.fetchone()
            cursor.close()
            
            return subscription is not None
            
        except MySQLError as e:
            logger.error(f"Erro ao verificar assinatura do usuário {self.telegram_id} para o grupo {group_id}: {e}")
            return False
    
    def get_managed_bots(self, conn):
        """Retorna os bots gerenciados pelo usuário."""
        try:
            cursor = conn.cursor(dictionary=True)
            query = """
            SELECT * FROM managed_bots
            WHERE owner_id = %s
            ORDER BY created_at DESC
            """
            cursor.execute(query, (self.telegram_id,))
            
            bots = cursor.fetchall()
            cursor.close()
            
            return bots
            
        except MySQLError as e:
            logger.error(f"Erro ao buscar bots gerenciados pelo usuário {self.telegram_id}: {e}")
            return []
    
    def __str__(self):
        """Retorna uma representação em string do usuário."""
        return f"User(id={self.id}, telegram_id={self.telegram_id}, username={self.username})"