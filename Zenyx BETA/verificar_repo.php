<?php
/**
 * Verificador de Estrutura para o Projeto Zenyx Bot
 * 
 * Este script analisa o repositório Zenyx Bot e verifica se contém
 * todos os arquivos e dependências necessários para o funcionamento correto.
 * 
 * @author Claude
 * @version 1.0.0
 */

declare(strict_types=1);

class RepositoryChecker
{
    /** @var array<string> Lista de arquivos essenciais */
    private array $essentialFiles = [
        'main.py',
        'requirements.txt'
    ];
    
    /** @var array<string> Lista de diretórios esperados */
    private array $expectedDirs = [
        'config',
        'handlers',
        'utils',
        'models'
    ];
    
    /** @var array<string> Lista de dependências necessárias */
    private array $requiredDependencies = [
        'python-telegram-bot',
        'redis',
        'mysql-connector-python',
        'requests',
        'python-dotenv',
        'Pillow'
    ];
    
    /** @var array<string> Lista de variáveis de ambiente necessárias */
    private array $envVars = [
        'BOT_TOKEN',
        'ADMIN_USER_ID',
        'CHANNEL_ID',
        'CHANNEL_LINK',
        'PUSHIN_PAY_TOKEN',
        'MYSQL_HOST',
        'MYSQL_USER',
        'MYSQL_PASSWORD',
        'MYSQL_DATABASE',
        'REDIS_HOST',
        'REDIS_PORT'
    ];
    
    /**
     * Executa todas as verificações
     * 
     * @return void
     */
    public function runChecks(): void
    {
        echo "========================================================\n";
        echo "       VERIFICAÇÃO DE ESTRUTURA DO PROJETO ZENYX        \n";
        echo "========================================================\n\n";
        
        $this->checkEssentialFiles();
        $this->checkDirectories();
        $this->checkDependencies();
        $this->checkEnvFile();
        $this->checkMainPy();
        $this->checkDockerConfig();
        
        echo "\n========================================================\n";
        echo "               VERIFICAÇÃO CONCLUÍDA                    \n";
        echo "========================================================\n";
    }
    
    /**
     * Verifica a presença dos arquivos essenciais
     * 
     * @return void
     */
    private function checkEssentialFiles(): void
    {
        echo "Verificando arquivos essenciais:\n";
        $allPresent = true;
        
        foreach ($this->essentialFiles as $file) {
            if (file_exists($file)) {
                echo "✓ $file encontrado\n";
            } else {
                echo "✗ $file NÃO encontrado\n";
                $allPresent = false;
            }
        }
        
        if (!$allPresent) {
            echo "\nATENÇÃO: Arquivos essenciais estão faltando no repositório!\n";
            echo "Verifique se você está no diretório correto do projeto.\n";
        }
        
        echo "\n";
    }
    
    /**
     * Verifica a presença dos diretórios esperados
     * 
     * @return void
     */
    private function checkDirectories(): void
    {
        echo "Verificando estrutura de diretórios:\n";
        $allPresent = true;
        
        foreach ($this->expectedDirs as $dir) {
            if (is_dir($dir)) {
                echo "✓ Diretório '$dir' encontrado\n";
            } else {
                echo "✗ Diretório '$dir' NÃO encontrado\n";
                $allPresent = false;
            }
        }
        
        if (!$allPresent) {
            echo "\nAlguns diretórios não foram encontrados. Isso pode indicar uma estrutura diferente do esperado.\n";
        }
        
        echo "\n";
    }
    
    /**
     * Verifica as dependências no arquivo requirements.txt
     * 
     * @return void
     */
    private function checkDependencies(): void
    {
        echo "Verificando dependências do Python:\n";
        
        if (!file_exists('requirements.txt')) {
            echo "Arquivo requirements.txt não encontrado. Não foi possível verificar dependências.\n\n";
            return;
        }
        
        $requirements = file('requirements.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $allPresent = true;
        
        foreach ($this->requiredDependencies as $dep) {
            $found = false;
            
            foreach ($requirements as $req) {
                if (preg_match("/^$dep(==|>=|<=|~=|>|<)/", $req)) {
                    $found = true;
                    echo "✓ $dep encontrada\n";
                    break;
                }
            }
            
            if (!$found) {
                echo "✗ $dep NÃO encontrada em requirements.txt\n";
                $allPresent = false;
            }
        }
        
        if (!$allPresent) {
            echo "\nATENÇÃO: Algumas dependências necessárias não estão no requirements.txt\n";
            echo "Você pode precisar adicionar estas dependências manualmente.\n";
        }
        
        echo "\n";
    }
    
    /**
     * Verifica o arquivo de configuração .env
     * 
     * @return void
     */
    private function checkEnvFile(): void
    {
        echo "Verificando arquivo de configuração .env:\n";
        
        if (!file_exists('.env')) {
            echo "✗ Arquivo .env NÃO encontrado\n";
            echo "  Você precisa criar um arquivo .env com as variáveis de ambiente necessárias.\n\n";
            return;
        }
        
        echo "✓ Arquivo .env encontrado\n";
        
        $envContent = file_get_contents('.env');
        
        foreach ($this->envVars as $var) {
            if (preg_match("/$var=.+/", $envContent)) {
                echo "✓ Variável $var configurada\n";
            } else {
                echo "✗ Variável $var NÃO configurada\n";
            }
        }
        
        echo "\n";
    }
    
    /**
     * Analisa o arquivo main.py
     * 
     * @return void
     */
    private function checkMainPy(): void
    {
        echo "Analisando main.py:\n";
        
        if (!file_exists('main.py')) {
            echo "Arquivo main.py não encontrado. Não foi possível analisar.\n\n";
            return;
        }
        
        $mainContent = file_get_contents('main.py');
        
        $patterns = [
            'Importação do python-telegram-bot' => 'from telegram',
            'Configuração de variáveis de ambiente' => 'dotenv|os\.environ',
            'Conexão com MySQL' => 'mysql\.connector|MySQLConnection',
            'Conexão com Redis' => 'redis',
            'Manipuladores de comandos' => 'CommandHandler|MessageHandler'
        ];
        
        foreach ($patterns as $description => $pattern) {
            if (preg_match("/$pattern/", $mainContent)) {
                echo "✓ $description encontrada\n";
            } else {
                echo "✗ $description NÃO encontrada\n";
            }
        }
        
        // Análise adicional da estrutura do bot
        $this->checkBotStructure($mainContent);
        
        echo "\n";
    }
    
    /**
     * Verifica a estrutura do bot no arquivo main.py
     * 
     * @param string $content Conteúdo do arquivo main.py
     * @return void
     */
    private function checkBotStructure(string $content): void
    {
        echo "\nAnalisando estrutura do bot:\n";
        
        $checklist = [
            'Inicialização do bot' => 'Updater|Application',
            'Manipulador de comandos /start' => 'start_command|CommandHandler.*start',
            'Verificação de canal' => 'check_channel|verify_channel',
            'Integração com PushinPay' => 'PushinPay|pushin_pay',
            'Sistema de referência' => 'referr?al|invite',
            'Gerenciamento de bots' => 'manage_bot|create_bot|bot_handler'
        ];
        
        foreach ($checklist as $feature => $pattern) {
            if (preg_match("/$pattern/i", $content)) {
                echo "✓ $feature implementado\n";
            } else {
                echo "? $feature possivelmente não implementado ou usando outra nomenclatura\n";
            }
        }
    }
    
    /**
     * Verifica configurações do Docker
     * 
     * @return void
     */
    private function checkDockerConfig(): void
    {
        echo "\nVerificando configuração do Docker:\n";
        
        if (file_exists('docker-compose.yml')) {
            echo "✓ Arquivo docker-compose.yml encontrado\n";
            
            $dockerComposeContent = file_get_contents('docker-compose.yml');
            
            $dockerServices = [
                'Serviço do Bot' => 'zenyx-bot|python|app',
                'Banco de dados MySQL/MariaDB' => 'mysql|mariadb',
                'Servidor Redis' => 'redis'
            ];
            
            foreach ($dockerServices as $service => $pattern) {
                if (preg_match("/$pattern/i", $dockerComposeContent)) {
                    echo "✓ $service configurado\n";
                } else {
                    echo "✗ $service não encontrado na configuração Docker\n";
                }
            }
        } else {
            echo "✗ Arquivo docker-compose.yml não encontrado\n";
            echo "  Para desenvolvimento local com Docker, recomendamos criar este arquivo.\n";
        }
        
        echo "\n";
    }
    
    /**
     * Verifica se existe um arquivo para implantação no Railway
     * 
     * @return void
     */
    private function checkRailwayConfig(): void
    {
        echo "Verificando configuração para deploy no Railway:\n";
        
        $railwayFiles = [
            'railway.json',
            'railway.toml',
            '.railway',
            'Procfile'
        ];
        
        $found = false;
        foreach ($railwayFiles as $file) {
            if (file_exists($file)) {
                echo "✓ Arquivo $file encontrado para configuração do Railway\n";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "✗ Nenhum arquivo de configuração para Railway encontrado\n";
            echo "  Para deploy no Railway, considere criar um arquivo Procfile ou railway.json\n";
        }
        
        echo "\n";
    }
    
    /**
     * Verifica a estrutura e qualidade geral do código
     * 
     * @return void
     */
    private function checkCodeQuality(): void
    {
        echo "Analisando qualidade geral do código:\n";
        
        // Verificar se existe arquivos .py
        $pythonFiles = glob("*.py");
        $pythonFiles = array_merge($pythonFiles, glob("*/*.py"));
        
        if (count($pythonFiles) === 0) {
            echo "✗ Nenhum arquivo Python encontrado no repositório\n";
            return;
        }
        
        echo "✓ " . count($pythonFiles) . " arquivos Python encontrados\n";
        
        // Verificar tamanho dos arquivos (arquivos muito grandes podem indicar problemas)
        $largeFiles = [];
        foreach ($pythonFiles as $file) {
            $lineCount = count(file($file));
            if ($lineCount > 500) {
                $largeFiles[] = "$file ($lineCount linhas)";
            }
        }
        
        if (count($largeFiles) > 0) {
            echo "⚠️ Arquivos Python grandes detectados (podem precisar de refatoração):\n";
            foreach ($largeFiles as $file) {
                echo "  - $file\n";
            }
        } else {
            echo "✓ Nenhum arquivo Python excessivamente grande detectado\n";
        }
        
        echo "\n";
    }
}

/**
 * ColorOutput trait para melhorar a saída no terminal
 */
trait ColorOutput
{
    /**
     * Retorna texto colorido para terminal
     * 
     * @param string $text Texto a ser colorido
     * @param string $color Cor desejada (red, green, yellow, blue, magenta, cyan)
     * @return string Texto com código de cor ANSI
     */
    private function colorize(string $text, string $color): string
    {
        $colors = [
            'red' => "\033[0;31m",
            'green' => "\033[0;32m",
            'yellow' => "\033[0;33m",
            'blue' => "\033[0;34m",
            'magenta' => "\033[0;35m",
            'cyan' => "\033[0;36m",
            'reset' => "\033[0m",
        ];
        
        return $colors[$color] . $text . $colors['reset'];
    }
}

// Executar a verificação
$checker = new RepositoryChecker();
$checker->runChecks();