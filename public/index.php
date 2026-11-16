<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Entrar | Gerenciamento Financeiro</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <main class="container-fluid p-0">
        <div class="row g-0 min-vh-100">

            <aside class="col-lg-4 painel-esquerdo">
                <div class="texto-apresentacao">
                    <h1>Saiba pra<br>
                        onde seu<br>
                        dinheiro vai</h1>

                    <p>Cuide do seu<br>
                        dinheiro com<br>
                        &lt;nome do<br>
                        site&gt;</p>
                </div>
            </aside>

            <section class="col-lg-8 painel-direito">
                <div class="login-container">

                    <form id="formLogin" novalidate>

                        <div id="mensagem"
                            class="alert d-none"
                            role="alert"></div>

                        <div class="mb-4">
                            <label for="email"
                                class="form-label">
                                Email:
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                placeholder="Exemplo@dominio.com"
                                autocomplete="email"
                                maxlength="180"
                                required>
                            <div class="invalid-feedback">
                                Informe um email válido.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="senha"
                                class="form-label">
                                Senha:
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="senha"
                                name="senha"
                                placeholder="*********"
                                autocomplete="current-password"
                                required>
                            <div class="invalid-feedback">
                                Informe sua senha.
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit"
                                id="btnEntrar"
                                class="btn btn-entrar">
                                Entrar
                            </button>

                            <p class="texto-cadastro">
                                Não tem uma conta?
                                <a href="cadastro.php">
                                    Cadastre-se
                                </a>
                            </p>
                        </div>

                    </form>
                </div>
            </section>

        </div>
    </main>

    <script>
        const form = document.getElementById('formLogin');
        const mensagem = document.getElementById('mensagem');
        const botao = document.getElementById('btnEntrar');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            form.classList.add('was-validated');

            if (!form.checkValidity()) return;

            mensagem.className = 'alert d-none';
            botao.disabled = true;
            botao.textContent = 'Entrando...';

            try {
                const resposta = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token']) ?>
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        email: document.getElementById('email').value.trim(),
                        senha: document.getElementById('senha').value
                    })
                });

                const dados = await resposta.json();
                
                if (!resposta.ok) {
                    throw new Error(
                        dados.erro || 'Não foi possível entrar.'
                    );
                }

                window.location.href = 'dashboard.php';

            } catch (erro) {
                mensagem.textContent = erro.message;
                mensagem.className = 'alert alert-danger';
            } finally {
                botao.disabled = false;
                botao.textContent = 'Entrar';
            }
        });
    </script>

</body>

</html>