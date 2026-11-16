<?php

session_start();

require_once __DIR__ . '/../../services/SupabaseService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

$idUsuario = (int) $_SESSION['usuario_id'];

$supabase = new SupabaseService();

$resultado = $supabase->select('tb_entradas', [
    'select' => '*',
    'id_usuario' => 'eq.' . $idUsuario
]);

http_response_code($resultado['status']);
echo json_encode($resultado['dados']);