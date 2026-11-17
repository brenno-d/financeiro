<?php

define('APP_STARTED', true);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

$rotas = require __DIR__ . '/../config/routes.php';
$rota = $_GET['rota'] ?? 'login';
$metodo = $_SERVER['REQUEST_METHOD'];

$metodoRota = $metodo === 'HEAD' ? 'GET' : $metodo;

if (!is_string($rota) || !isset($rotas[$rota])) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Página não encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($rotas[$rota][$metodoRota])) {
    http_response_code(405);
    $metodosPermitidos = array_keys($rotas[$rota]);

    if (isset($rotas[$rota]['GET'])) {
        $metodosPermitidos[] = 'HEAD';
    }

    header('Allow: ' . implode(', ', $metodosPermitidos));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$controller, $acao] = $rotas[$rota][$metodoRota];

require_once __DIR__ . '/../app/controllers/' . $controller . '.php';

try {
    (new $controller())->$acao();
} catch (Throwable $erro) {
    error_log('Requisição: ' . $erro->getMessage());
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Serviço indisponível. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}
