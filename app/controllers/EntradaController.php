<?php

require_once __DIR__ . '/../services/AuthSession.php';

class EntradaController
{
    public function index(): void
    {
        header('Content-Type: application/json');

        if (!AuthSession::autenticado()) {
            http_response_code(401);
            echo json_encode(['erro' => 'Não autenticado']);
            exit;
        }

        $idUsuario = (int) $_SESSION['usuario_id'];

        require_once __DIR__ . '/../models/Entrada.php';

        $resultado = (new Entrada($_SESSION['supabase_access_token']))->listarPorUsuario($idUsuario);

        http_response_code($resultado['status']);
        echo json_encode($resultado['dados']);
    }
}
