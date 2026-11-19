<?php
if (!defined('APP_STARTED')) {
    header('Location: ../../../public/index.php?rota=visao-geral');
    exit;
}
$mesAtual = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $titulo ?> | Finanças</title>
    <link rel="preload" href="assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="assets/js/dashboard.js" defer></script>
</head>
<body data-pagina="<?= $pagina ?>">
    <a class="visually-hidden-focusable skip-link" href="#conteudo">Ir para o conteúdo</a>
    <header class="cabecalho">
        <nav class="navbar navbar-expand-md container app-container" aria-label="Menu principal">
            <a class="navbar-brand marca" href="index.php?rota=visao-geral" data-rota="visao-geral"><i class="bi bi-wallet2" aria-hidden="true"></i>Finanças<span>.</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav ms-auto align-items-md-center">
                    <?php foreach (['visao-geral' => 'Visão geral', 'extrato' => 'Extrato', 'contas' => 'Contas'] as $rotaMenu => $label): ?>
                    <li class="nav-item"><a class="nav-link<?= ($pagina === $rotaMenu || ($pagina === 'conta' && $rotaMenu === 'contas')) ? ' active' : '' ?>" href="index.php?rota=<?= $rotaMenu ?>" data-rota="<?= $rotaMenu ?>"<?= $pagina === $rotaMenu ? ' aria-current="page"' : '' ?>><?= $label ?></a></li>
                    <?php endforeach; ?>
                    <li class="nav-item dropdown"><button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Inserir</button><ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" data-novo="entrada">Receita</button></li>
                        <li><button class="dropdown-item" data-novo="saida">Despesa</button></li>
                        <li><button class="dropdown-item" data-novo="transferencia">Transferência</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalMeta">Meta de gastos</button></li>
                    </ul></li>
                    <li class="nav-item"><a class="nav-link<?= $pagina === 'dashboard' ? ' active' : '' ?>" href="index.php?rota=dashboard" data-rota="dashboard"<?= $pagina === 'dashboard' ? ' aria-current="page"' : '' ?>>Dashboard</a></li>
                </ul>
                <button class="perfil" type="button" data-bs-toggle="modal" data-bs-target="#modalPerfil" aria-label="Ver perfil de <?= $nome ?>" title="<?= $nome ?>"><i class="bi bi-person-circle" aria-hidden="true"></i></button>
            </div>
        </nav>
    </header>
    <main id="conteudo" class="container app-container" tabindex="-1">
        <div class="pagina-titulo">
            <div><h1 id="tituloPagina"><?= $titulo ?></h1><p id="subtituloPagina" class="texto-suave">Acompanhe suas finanças.</p></div>
            <label class="seletor-mes" for="mesReferencia"><span>Mês de referência</span><input type="month" id="mesReferencia" value="<?= $mesAtual ?>" min="2000-01" max="2100-12"></label>
        </div>
        <div id="aviso" class="alert alert-danger d-none" role="alert"><span id="textoAviso"></span><button class="btn btn-sm btn-outline-danger ms-2" id="tentarNovamente" type="button">Tentar novamente</button></div>
        <p id="carregamento" class="carregamento" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Carregando suas finanças…</p>
        <div id="painel" aria-busy="true"><?php require __DIR__ . '/paginas.php'; ?></div>
        <footer class="rodape-pagina"><span>Finanças.</span><span>Seu controle financeiro, do seu jeito.</span></footer>
    </main>
    <?php require __DIR__ . '/modais.php'; ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3"><div class="toast" id="toastSucesso" role="status" aria-live="polite" aria-atomic="true"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span id="textoSucesso"></span></div><button type="button" class="btn-close me-3 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div></div></div>
</body>
</html>
