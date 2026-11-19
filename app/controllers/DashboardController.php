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

        $pagina = $_GET['rota'] ?? 'visao-geral';
        $titulos = ['visao-geral' => 'Visão geral', 'extrato' => 'Extrato', 'contas' => 'Contas', 'conta' => 'Detalhes da conta', 'dashboard' => 'Dashboard'];
        $pagina = isset($titulos[$pagina]) ? $pagina : 'visao-geral';
        $titulo = $titulos[$pagina];

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        require __DIR__ . '/../views/dashboard/index.php';
    }
}
