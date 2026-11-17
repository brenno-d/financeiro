<?php
if (!defined('APP_STARTED')) {
    header('Location: ../../../public/index.php?rota=dashboard');
    exit;
}
?>
<h1>Bem-vindo, <?= $nome ?>!</h1>
<p>Login realizado com sucesso.</p>
