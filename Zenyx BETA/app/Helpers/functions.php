<?php
declare(strict_types=1);

/**
 * Funções auxiliares globais
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */

if (!function_exists('env')) {
    /**
     * Obtém valor de variável de ambiente
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Obtém valor de configuração
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = [];
        
        $segments = explode('.', $key);
        $file = array_shift($segments);
        
        if (!isset($config[$file])) {
            $path = __DIR__ . "/../Config/{$file}.php";
            
            if (!file_exists($path)) {
                return $default;
            }
            
            $config[$file] = require $path;
        }
        
        $value = $config[$file];
        
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        die(1);
    }
}

if (!function_exists('base_path')) {
    /**
     * Obtém caminho base da aplicação
     */
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Obtém caminho do storage
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('url')) {
    /**
     * Gera URL completa
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(config('app.url', 'http://localhost'), '/');
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }
}