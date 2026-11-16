<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$dotenv->required(['SUPABASE_URL', 'SUPABASE_KEY'])->notEmpty();

// Aceita tanto a URL do projeto quanto a URL copiada da API REST.
$supabaseUrl = preg_replace('~/rest/v1$~', '', rtrim(trim($_ENV['SUPABASE_URL']), '/'));
$urlParts = parse_url($supabaseUrl);

if (
    !filter_var($supabaseUrl, FILTER_VALIDATE_URL) ||
    !in_array($urlParts['scheme'] ?? '', ['http', 'https'], true) ||
    !empty($urlParts['query']) ||
    !empty($urlParts['fragment']) ||
    !empty($urlParts['user']) ||
    !empty($urlParts['pass']) ||
    !in_array($urlParts['path'] ?? '', ['', '/'], true)
) {
    throw new RuntimeException('SUPABASE_URL deve conter a URL base do projeto Supabase.');
}

define('SUPABASE_URL', rtrim($supabaseUrl, '/'));
define('SUPABASE_KEY', trim($_ENV['SUPABASE_KEY']));
