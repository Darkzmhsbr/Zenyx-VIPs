<?php
/**
 * Deploy para Railway do Bot Zenyx
 * 
 * Este script automatiza o processo de deploy do Bot Zenyx para a plataforma Railway.
 * Gerencia a instalação do CLI, configuração do projeto e envio do código.
 * 
 * @author Claude
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Classe responsável pelo processo de deploy no Railway
 */
class RailwayDeployer
{
    /** @var string Diretório onde o script está sendo executado */
    private string $baseDir;
    
    /** @var array<string,string> Variáveis de ambiente para o Railway */
    private array $envVars = [];
    
    /** @var array<string> Plugins necessários no Railway */
    private array $requiredPlugins = [
        'mysql',
        'redis'
    ];
    
    /**
     * Construtor que inicializa o deployer
     */
    public function __construct()
    {
        $this->baseDir = dirname(__FILE__);
        $this->loadEnvVars();
    }
    
    /**
     * Executa o processo de deploy completo
     * 
     * @return void
     */
    public function deploy(): void
    {
        $this->printHeader();
        
        if (!$this->checkRailwayCLI()) {
            $this->installRailwayCLI();
        }
        
        $this->loginToRailway();
        $this->prepareDeployFiles();
        $this->initRailwayProject();
        $this->setEnvironmentVariables();
        $this->addPlugins();
        $this->deployProject();
        
        $this->printFooter();
    }
    
    /**
     * Verifica se o Railway CLI está instalado
     * 
     * @return bool True se o CLI estiver instalado
     */
    private function checkRailwayCLI(): bool
    {
        echo "Verificando se o Railway CLI está instalado...\n";
        
        $command = PHP_OS_FAMILY === 'Windows' ? 'where railway' : 'which railway';
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "✓ Railway CLI encontrado!\n\n";
            return true;
        } else {
            echo "✗ Railway CLI não encontrado.\n\n";
            return false;
        }
    }
    
    /**
     * Instala o Railway CLI via npm
     * 
     * @return void
     * @throws RuntimeException Se não conseguir instalar o CLI
     */
    private function installRailwayCLI(): void
    {
        echo "Instalando Railway CLI...\n";
        
        // Verificar se o npm está instalado
        $command = PHP_OS_FAMILY === 'Windows' ? 'where npm' : 'which npm';
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException(
                "npm não encontrado. Por favor, instale o Node.js antes de continuar.\n" .
                "Download: https://nodejs.org/"
            );
        }
        
        // Instalar o Railway CLI
        echo "Executando: npm i -g @railway/cli\n";
        passthru('npm i -g @railway/cli', $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Falha ao instalar o Railway CLI. Verifique sua conexão com a internet."
            );
        }
        
        echo "✓ Railway CLI instalado com sucesso!\n\n";
    }
    
    /**
     * Realiza login no Railway
     * 
     * @return void
     * @throws RuntimeException Se o login falhar
     */
    private function loginToRailway(): void
    {
        echo "Realizando login no Railway...\n";
        echo "Você será redirecionado para o navegador para autenticação.\n\n";
        
        passthru('railway login', $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Falha ao fazer login no Railway. Tente novamente manualmente."
            );
        }
        
        echo "✓ Login realizado com sucesso!\n\n";
    }
    
    /**
     * Prepara os arquivos necessários para o deploy
     * 
     * @return void
     */
    private function prepareDeployFiles(): void
    {
        echo "Preparando arquivos para deploy...\n";
        
        // Criar arquivo .railwayignore se não existir
        if (!file_exists('.railwayignore')) {
            $railwayIgnore = <<<EOT
venv/
__pycache__/
*.pyc
.env.example
*.php
*.ps1
.git/
.gitignore
README.md
temp/
logs/
EOT;
            
            file_put_contents('.railwayignore', $railwayIgnore);
            echo "✓ Arquivo .railwayignore criado\n";
        }
        
        // Criar arquivo Procfile se não existir
        if (!file_exists('Procfile')) {
            file_put_contents('Procfile', "web: python main.py\n");
            echo "✓ Arquivo Procfile criado\n";
        }
        
        // Criar arquivo railway.json se não existir
        if (!file_exists('railway.json')) {
            $railwayJson = json_encode([
                'schemaVersion' => 1,
                'build' => [
                    'builder' => 'NIXPACKS',
                    'buildCommand' => ''
                ],
                'deploy' => [
                    'numReplicas' => 1,
                    'startCommand' => 'python main.py',
                    'healthcheckPath' => '/',
                    'healthcheckTimeout' => 300,
                    'restartPolicyType' => 'ON_FAILURE'
                ]
            ], JSON_PRETTY_PRINT);
            
            file_put_contents('railway.json', $railwayJson);
            echo "✓ Arquivo railway.json criado\n";
        }
        
        echo "✓ Arquivos preparados com sucesso!\n\n";
    }
    
    /**
     * Inicializa o projeto no Railway
     * 
     * @return void
     * @throws RuntimeException Se a inicialização falhar
     */
    private function initRailwayProject(): void
    {
        echo "Inicializando projeto no Railway...\n";
        echo "Por favor, escolha 'Empty Project' quando solicitado o template.\n\n";
        
        passthru('railway init', $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Falha ao inicializar o projeto no Railway. Verifique os logs."
            );
        }
        
        echo "✓ Projeto inicializado com sucesso!\n\n";
    }
    
    /**
     * Configura as variáveis de ambiente no Railway
     * 
     * @return void
     */
    private function setEnvironmentVariables(): void
    {
        echo "Configurando variáveis de ambiente no Railway...\n";
        
        // Modificar variáveis para o ambiente Railway
        $this->envVars['MYSQL_HOST'] = '${RAILWAY_PRIVATE_MYSQL_HOST}';
        $this->envVars['MYSQL_USER'] = '${RAILWAY_PRIVATE_MYSQL_USER}';
        $this->envVars['MYSQL_PASSWORD'] = '${RAILWAY_PRIVATE_MYSQL_PASSWORD}';
        $this->envVars['REDIS_HOST'] = '${RAILWAY_PRIVATE_REDIS_HOST}';
        $this->envVars['REDIS_PORT'] = '${RAILWAY_PRIVATE_REDIS_PORT}';
        
        // Configurar variáveis no Railway
        foreach ($this->envVars as $key => $value) {
            echo "Configurando: $key\n";
            $command = sprintf('railway variables set "%s=%s"', $key, $value);
            passthru($command, $returnCode);
            
            if ($returnCode !== 0) {
                echo "⚠️ Falha ao configurar variável $key\n";
            }
        }
        
        echo "✓ Variáveis de ambiente configuradas!\n\n";
    }
    
    /**
     * Adiciona os plugins necessários ao projeto Railway
     * 
     * @return void
     */
    private function addPlugins(): void
    {
        echo "Adicionando serviços ao projeto Railway...\n";
        
        foreach ($this->requiredPlugins as $plugin) {
            echo "Adicionando plugin: $plugin\n";
            passthru("railway add --plugin $plugin", $returnCode);
            
            if ($returnCode !== 0) {
                echo "⚠️ Falha ao adicionar plugin $plugin. Pode ser que já esteja instalado.\n";
            }
        }
        
        echo "✓ Serviços adicionados com sucesso!\n\n";
    }
    
    /**
     * Realiza o deploy do projeto no Railway
     * 
     * @return void
     */
    private function deployProject(): void
    {
        echo "Realizando deploy do projeto...\n";
        
        passthru('railway up', $returnCode);
        
        if ($returnCode === 0) {
            echo "✓ Deploy concluído com sucesso!\n\n";
            
            echo "Informações do projeto:\n";
            passthru('railway status');
            echo "\n";
            
            echo "URL do projeto:\n";
            passthru('railway domain');
            echo "\n";
            
            echo "Para visualizar os logs em tempo real, execute:\n";
            echo "railway logs\n\n";
        } else {
            echo "⚠️ Falha no deploy. Verifique os logs para mais informações.\n";
            echo "Execute: railway logs\n\n";
        }
    }
    
    /**
     * Carrega as variáveis de ambiente do arquivo .env
     * 
     * @return void
     */
    private function loadEnvVars(): void
    {
        if (!file_exists('.env')) {
            return;
        }
        
        $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentários
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            // Processar variáveis de ambiente
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $this->envVars[trim($key)] = trim($value);
            }
        }
    }
    
    /**
     * Imprime o cabeçalho do script
     * 
     * @return void
     */
    private function printHeader(): void
    {
        echo "========================================================\n";
        echo "          DEPLOY DO BOT ZENYX PARA O RAILWAY           \n";
        echo "========================================================\n\n";
    }
    
    /**
     * Imprime o rodapé do script
     * 
     * @return void
     */
    private function printFooter(): void
    {
        echo "========================================================\n";
        echo "                   DEPLOY FINALIZADO                   \n";
        echo "========================================================\n";
    }
}

// Executar o deploy
try {
    $deployer = new RailwayDeployer();
    $deployer->deploy();
} catch (Exception $e) {
    echo "Erro durante o deploy: " . $e->getMessage() . "\n";
    exit(1);
}