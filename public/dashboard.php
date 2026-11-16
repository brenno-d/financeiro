<?php
session_start();

if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$nome = htmlspecialchars(
    $_SESSION['usuario_nome'],
    ENT_QUOTES,
    'UTF-8'
);
?>

<h1>Bem-vindo, <?= $nome ?>!</h1>
<p>Login realizado com sucesso.</p>