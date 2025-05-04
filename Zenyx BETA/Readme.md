Bot Zenyx - Sistema de Gerenciamento de Bots de Pagamento
Bot Zenyx é uma plataforma robusta desenvolvida em PHP 8.4 para criação e gerenciamento de bots de pagamento para Telegram, com integração nativa com PushinPay e arquitetura MVC customizada.
🚀 Características Principais

Framework MVC Customizado: Desenvolvido do zero com PHP 8.4 e tipagem forte
Multi-Bot: Permite que usuários criem e gerenciem múltiplos bots Telegram
Pagamentos PIX: Integração completa com PushinPay para processamento de pagamentos
Sistema de Afiliados: Programa de indicações com comissões automáticas
Dashboard Administrativo: Controle total sobre usuários, bots e transações
Planos Flexíveis: Criação de planos personalizados com diferentes durações
Notificações em Tempo Real: Integração com Telegram para notificações instantâneas
Segurança Avançada: Prepared statements, validação de dados e tratamento de exceções
Cache com Redis: Performance otimizada com cache em memória
Migrations: Controle de versão do banco de dados

📋 Requisitos do Sistema

PHP 8.4+ com tipagem forte
Composer 2.0+
MariaDB 10.5+
Redis 6.0+
Apache/Nginx
Extensões PHP: PDO, JSON, cURL, mbstring

🔧 Instalação
1. Clone o repositório
bashgit clone https://github.com/seu-usuario/bot-zenyx.git
cd bot-zenyx
2. Instale as dependências
bashcomposer install --no-dev --optimize-autoloader
3. Configure o ambiente
bashcp .env.example .env
4. Configure as variáveis de ambiente
Edite o arquivo .env com suas configurações:
env# Configurações da Aplicação
APP_NAME="Bot Zenyx"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com

# Banco de Dados
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=bot_zenyx
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Telegram
TELEGRAM_BOT_TOKEN=seu_token_bot
TELEGRAM_CHANNEL_ID=@seu_canal

# PushinPay
PUSHINPAY_TOKEN=seu_token_pushinpay
PUSHINPAY_WEBHOOK_SECRET=seu_webhook_secret

# Configurações de Pagamento
PAYMENT_MIN_AMOUNT=5.00
PAYMENT_MAX_AMOUNT=10000.00
PAYMENT_EXPIRATION_MINUTES=30

# Sistema de Afiliados
REFERRAL_COMMISSION_RATE=0.10
REFERRAL_MINIMUM_SALES=3
REFERRAL_MINIMUM_AMOUNT=29.70
REFERRAL_PERIOD_DAYS=15

# Admin VIP
ADMIN_VIP_PRICE=97.90
ADMIN_VIP_TRIAL_DAYS=30
ADMIN_VIP_COMMISSION_RATE=0.05
5. Execute as migrações
bashphp bin/console migrate
6. Popule o banco de dados (opcional)
bashphp bin/console db:seed
7. Configure o webhook do Telegram
bashphp bin/console telegram:webhook
🐳 Deploy com Docker
Docker Compose
yamlversion: '3.8'

services:
  app:
    build: .
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=production
    depends_on:
      - mariadb
      - redis

  mariadb:
    image: mariadb:10.11
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: bot_zenyx
      MYSQL_USER: zenyx
      MYSQL_PASSWORD: zenyx_password
    volumes:
      - mariadb_data:/var/lib/mysql

  redis:
    image: redis:alpine
    volumes:
      - redis_data:/data

volumes:
  mariadb_data:
  redis_data:
Deploy no Railway
O projeto já está configurado para deploy no Railway com o arquivo railway.json:

Crie um novo projeto no Railway
Conecte seu repositório GitHub
Configure as variáveis de ambiente
Deploy automático!

📁 Estrutura do Projeto
bot-zenyx/
├── app/
│   ├── Controllers/     # Controladores MVC
│   ├── Core/           # Classes base do framework
│   ├── Models/         # Modelos de dados
│   ├── Services/       # Serviços de integração
│   ├── Middleware/     # Middlewares de autenticação
│   ├── Config/         # Arquivos de configuração
│   ├── Helpers/        # Funções auxiliares
│   └── Exception/      # Exceções personalizadas
├── bin/
│   └── console         # CLI para tarefas administrativas
├── config/
│   └── routes.php      # Definição de rotas
├── database/
│   ├── migrations/     # Migrations do banco de dados
│   └── seeds/          # Seeds para popular o banco
├── public/
│   └── index.php       # Entry point da aplicação
├── storage/
│   ├── cache/          # Cache temporário
│   └── logs/           # Logs da aplicação
├── .env.example        # Exemplo de configuração
├── composer.json       # Dependências do projeto
├── railway.json        # Configuração do Railway
└── README.md           # Documentação
🛠️ Comandos do Console
bash# Migrations
php bin/console migrate              # Executa migrations pendentes
php bin/console migrate:rollback     # Reverte última batch de migrations
php bin/console migrate:refresh      # Recria todo o banco de dados

# Database
php bin/console db:seed             # Popula o banco com dados de teste

# Telegram
php bin/console telegram:webhook    # Configura webhook do Telegram

# Cache
php bin/console cache:clear         # Limpa todo o cache Redis
🔐 Segurança

Prepared Statements: Todas as queries utilizam prepared statements para prevenção de SQL injection
Validação de Dados: Sistema robusto de validação com classes especializadas
Tratamento de Exceções: Exceções personalizadas para diferentes tipos de erro
Autenticação: Sistema de tokens via Redis para gerenciamento de sessões
CSRF Protection: Proteção contra Cross-Site Request Forgery
XSS Prevention: Sanitização de dados de entrada
Rate Limiting: Limitação de requisições para prevenção de ataques

📚 API Documentation
Rotas Públicas
POST /webhook/telegram       - Webhook para updates do Telegram
POST /webhook/pushinpay     - Webhook para notificações de pagamento
Rotas Protegidas (Autenticação Requerida)
# Autenticação
POST /api/auth/login        - Login via Telegram
POST /api/auth/logout       - Encerra sessão
GET  /api/auth/check        - Verifica autenticação

# Usuário
GET  /api/user/profile      - Perfil do usuário
GET  /api/user/balance      - Saldo atual
GET  /api/user/referrals    - Lista de indicados

# Bots
GET  /api/bot/list          - Lista bots do usuário
POST /api/bot/create        - Cria novo bot
PUT  /api/bot/{id}         - Atualiza configurações do bot
GET  /api/bot/{id}/stats   - Estatísticas do bot

# Pagamentos
POST /api/payment/create    - Cria novo pagamento
GET  /api/payment/status    - Verifica status de pagamento
GET  /api/payment/history   - Histórico de pagamentos

# Planos
GET  /api/plan/list         - Lista planos do bot
POST /api/plan/create       - Cria novo plano
PUT  /api/plan/{id}        - Atualiza plano
Rotas Administrativas
GET  /api/admin/dashboard   - Dashboard administrativo
GET  /api/admin/users       - Lista todos usuários
GET  /api/admin/bots        - Lista todos bots
GET  /api/admin/payments    - Relatório de pagamentos
🧪 Testes
bash# Executar testes
composer test

# Verificar padrões de código
composer cs-check

# Corrigir padrões de código
composer cs-fix
🔄 Fluxo de Trabalho

Usuário acessa @BotPrincipal
Cria seu próprio bot via @BotFather
Registra o token no Bot Zenyx
Configura bot:

Mensagem de boas-vindas
Planos de pagamento
Token PushinPay
Canal/grupo VIP


Usuários finais acessam o bot criado
Realizam pagamentos via PIX
Recebem acesso ao conteúdo premium

🤝 Contribuindo

Fork o projeto
Crie sua feature branch (git checkout -b feature/NovaFuncionalidade)
Commit suas mudanças (git commit -m 'Adiciona nova funcionalidade')
Push para a branch (git push origin feature/NovaFuncionalidade)
Abra um Pull Request

Padrões de Código

PSR-12 para estilo de código
Tipagem forte em todos os métodos
Documentação PHPDoc completa
Testes unitários para novas funcionalidades

📞 Suporte

Email: suporte@botzenyx.com
Telegram: @ZenyxSupport
Issues: GitHub Issues

📄 Licença
Este projeto está sob licença proprietária. Todos os direitos reservados.
👥 Autores

Luis Fernando - Desenvolvedor Full Stack - GitHub

🔮 Roadmap

 Implementação de múltiplos gateways de pagamento
 Dashboard analytics com gráficos
 Sistema de templates para mensagens
 API pública para integrações externas
 Suporte a múltiplos idiomas (i18n)
 Sistema de notificações por email
 Backup automático de dados
 Logs de auditoria detalhados

📊 Status do Projeto
Mostrar Imagem
Mostrar Imagem
Mostrar Imagem

Desenvolvido com ❤️ por Bot Zenyx Team