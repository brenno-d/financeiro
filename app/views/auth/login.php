<?php
if (!defined('APP_STARTED')) {
    header('Location: ../../../public/index.php?rota=login');
    exit;
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

    <link rel="stylesheet" href="assets/css/fonts.css">
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
                        Finanças.</p>
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
                                <a href="index.php?rota=cadastro">
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
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
        const retornoAuth = new URLSearchParams(window.location.hash.slice(1));

        if (retornoAuth.has('error')) {
            mensagem.textContent = retornoAuth.get('error_code') === 'otp_expired'
                ? 'O link de confirmação é inválido ou expirou. Informe seu email e solicite um novo link.'
                : 'Não foi possível confirmar seu email. Solicite um novo link de confirmação.';
            mensagem.className = 'alert alert-danger';
        } else if (retornoAuth.get('type') === 'signup' && retornoAuth.has('access_token')) {
            mensagem.textContent = 'Email confirmado. Entre com seu email e senha.';
            mensagem.className = 'alert alert-success';
        }

        if (retornoAuth.has('error') || retornoAuth.has('access_token')) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            form.classList.add('was-validated');

            if (!form.checkValidity()) return;

            mensagem.className = 'alert d-none';
            botao.disabled = true;
            botao.textContent = 'Entrando...';

            try {
                const resposta = await fetch('index.php?rota=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
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

                window.location.href = 'index.php?rota=visao-geral';

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
