<?php

require_once __DIR__ . '/../services/AuthSession.php';

class DashboardController
{
    public function index(): void
    {
        if (!AuthSession::autenticado()) {
            header('Location: index.php?rota=login');
            exit;
        }

        $nome = htmlspecialchars(
            $_SESSION['usuario_nome'],
            ENT_QUOTES,
            'UTF-8'
        );

        require __DIR__ . '/../views/dashboard/index.php';
    }
}
