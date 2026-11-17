<?php

return [
    'login' => [
        'GET' => [AuthController::class, 'exibirLogin'],
        'POST' => [AuthController::class, 'login']
    ],
    'cadastro' => [
        'GET' => [AuthController::class, 'exibirCadastro']
    ],
    'registrar' => [
        'POST' => [AuthController::class, 'registrar']
    ],
    'reenviar-confirmacao' => [
        'POST' => [AuthController::class, 'reenviarConfirmacao']
    ],
    'dashboard' => [
        'GET' => [DashboardController::class, 'index']
    ],
    'api/entradas' => [
        'GET' => [EntradaController::class, 'index']
    ]
];
