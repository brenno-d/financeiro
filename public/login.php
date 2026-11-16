<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

function responder(int $status, array $dados): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(405, ['erro' => 'Método não permitido']);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    responder(403, ['erro' => 'Requisição inválida']);
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados)) {
    responder(400, ['erro' => 'JSON inválido']);
}

$email = $dados['email'] ?? '';
$senha = $dados['senha'] ?? '';

if (!is_string($email) || !is_string($senha)) {
    responder(400, ['erro' => 'Email ou senha inválidos']);
}

$email = strtolower(trim($email));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
    responder(400, ['erro' => 'Email ou senha inválidos']);
}

try {
    require_once __DIR__ . '/../services/SupabaseService.php';

    $supabase = new SupabaseService();
    $resultado = $supabase->select('tb_usuarios', [
        'select' => 'cd_usuario,nm_usuario,ds_senha_usuario',
        'ds_email_usuario' => 'eq.' . $email,
        'limit' => 1
    ]);

    if ($resultado['status'] !== 200) {
        error_log('Login: falha ao consultar o Supabase (HTTP ' . $resultado['status'] . ').');
        responder(502, ['erro' => 'Erro ao consultar o Supabase. Tente novamente.']);
    }

    if (!is_array($resultado['dados']) || !array_is_list($resultado['dados'])) {
        throw new RuntimeException('Resposta inválida do Supabase ao consultar usuários.');
    }

    $usuario = $resultado['dados'][0] ?? null;

    if (
        !$usuario ||
        !is_string($usuario['ds_senha_usuario'] ?? null) ||
        !password_verify($senha, $usuario['ds_senha_usuario'])
    ) {
        responder(401, ['erro' => 'Email ou senha incorretos']);
    }

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['cd_usuario'];
    $_SESSION['usuario_nome'] = $usuario['nm_usuario'];
    $_SESSION['autenticado'] = true;

    responder(200, [
        'sucesso' => true,
        'mensagem' => 'Login realizado com sucesso'
    ]);
} catch (Throwable $e) {
    error_log('Login: ' . $e->getMessage());
    responder(500, ['erro' => 'Erro interno. Tente novamente.']);
}
