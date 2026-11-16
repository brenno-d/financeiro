
<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../services/SupabaseService.php';

function responder(int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['erro' => 'Método não permitido.']);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf)
) {
    responder(403, ['erro' => 'Token de segurança inválido.']);
}

$dados = json_decode(file_get_contents('php://input'), true);

if (!is_array($dados)) {
    responder(400, ['erro' => 'Dados inválidos.']);
}

$nome = trim($dados['nome'] ?? '');
$email = mb_strtolower(trim($dados['email'] ?? ''));
$senha = $dados['senha'] ?? '';
$confirmarSenha = $dados['confirmarSenha'] ?? '';

if ($nome === '' || mb_strlen($nome) > 70) {
    responder(400, ['erro' => 'Informe um nome válido.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
    responder(400, ['erro' => 'Informe um email válido.']);
}

if (strlen($senha) < 8) {
    responder(400, ['erro' => 'A senha deve ter pelo menos 8 caracteres.']);
}

if ($senha !== $confirmarSenha) {
    responder(400, ['erro' => 'As senhas não coincidem.']);
}

try {
    $supabase = new SupabaseService();

    // Verifica se o email já está cadastrado
    $consulta = $supabase->select('tb_usuarios', [
        'select' => 'cd_usuario',
        'ds_email_usuario' => 'eq.' . $email,
        'limit' => 1
    ]);

    if ($consulta['status'] !== 200) {
        responder(502, ['erro' => 'Erro ao verificar o email.']);
    }

    if (!empty($consulta['dados'])) {
        responder(409, ['erro' => 'Este email já está cadastrado.']);
    }

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $usuario = [
        'nm_usuario' => $nome,
        'ds_email_usuario' => $email,
        'ds_senha_usuario' => $senhaHash,
        'vl_saldo_total_usuario' => 0
    ];

    $resultado = $supabase->insert('tb_usuarios', $usuario);

    if (!in_array($resultado['status'], [200, 201], true)) {
        if ($resultado['status'] === 409) {
            responder(409, ['erro' => 'Este email já está cadastrado.']);
        }

        error_log('Erro Supabase no cadastro: ' . json_encode($resultado));

        responder(502, ['erro' => 'Não foi possível cadastrar o usuário.']);
    }

    responder(201, [
        'sucesso' => true,
        'mensagem' => 'Usuário cadastrado com sucesso.'
    ]);

} catch (Throwable $erro) {
    error_log('Erro no cadastro: ' . $erro->getMessage());

    responder(500, ['erro' => 'Erro interno do servidor.']);
}
