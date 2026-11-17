<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$dotenv->required(['SUPABASE_URL', 'SUPABASE_KEY'])->notEmpty();

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

$authRedirectUrl = trim($_ENV['SUPABASE_AUTH_REDIRECT_URL'] ?? '');
$authRedirectParts = parse_url($authRedirectUrl);

if (
    !filter_var($authRedirectUrl, FILTER_VALIDATE_URL) ||
    !in_array($authRedirectParts['scheme'] ?? '', ['http', 'https'], true) ||
    !empty($authRedirectParts['user']) ||
    !empty($authRedirectParts['pass']) ||
    !empty($authRedirectParts['fragment'])
) {
    throw new RuntimeException('Configure SUPABASE_AUTH_REDIRECT_URL com a URL da tela de login.');
}

define('SUPABASE_AUTH_REDIRECT_URL', $authRedirectUrl);
