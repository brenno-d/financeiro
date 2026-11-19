<?php
if (!defined('APP_STARTED')) {
    header('Location: ../../../public/index.php?rota=cadastro');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/cadastro.css">
</head>
<body>
    <main class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <aside class="col-lg-4 painel-esquerdo">
                <div class="texto-apresentacao">
                    <h1>Saiba pra<br>onde seu<br>dinheiro vai</h1>
                    <p>Cuide do seu<br>dinheiro com<br>Finanças.</p>
                </div>
            </aside>

            <section class="col-lg-8 painel-direito">
                <div class="login-container">
                    <h2 class="cadastro-titulo">Crie sua conta</h2>
                    <p class="cadastro-subtitulo">
                        Comece a organizar sua vida financeira.
                    </p>

                    <div id="mensagem" class="alert d-none" role="alert"></div>

                    <form id="formCadastro">
                        <div class="mb-4">
                            <label for="nome" class="form-label">Nome:</label>
                            <input
                                type="text"
                                class="form-control"
                                id="nome"
                                name="nome"
                                placeholder="Seu nome"
                                maxlength="70"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">Email:</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                placeholder="Exemplo@dominio.com"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label for="senha" class="form-label">Senha:</label>
                            <input
                                type="password"
                                class="form-control"
                                id="senha"
                                name="senha"
                                placeholder="Crie uma senha"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label for="confirmarSenha" class="form-label">
                                Confirmar senha:
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="confirmarSenha"
                                name="confirmarSenha"
                                placeholder="Digite a senha novamente"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-entrar" id="btnCadastro">
                                Cadastrar
                            </button>

                            <p class="texto-cadastro">
                                Já tem uma conta?
                                <a href="index.php?rota=login">Entrar</a>
                            </p>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script>
        const formulario = document.getElementById('formCadastro');
        const mensagem = document.getElementById('mensagem');
        const botao = document.getElementById('btnCadastro');

        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

        function mostrarMensagem(texto, tipo = 'danger') {
            mensagem.textContent = texto;
            mensagem.className = `alert alert-${tipo}`;
        }

        formulario.addEventListener('submit', async (event) => {
            event.preventDefault();

            const nome = document.getElementById('nome').value.trim();
            const email = document.getElementById('email').value.trim();
            const senha = document.getElementById('senha').value;
            const confirmarSenha = document.getElementById('confirmarSenha').value;

            if (senha !== confirmarSenha) {
                mostrarMensagem('As senhas não coincidem.');
                return;
            }

            if (senha.length < 8) {
                mostrarMensagem('A senha deve ter pelo menos 8 caracteres.');
                return;
            }

            botao.disabled = true;
            botao.textContent = 'Cadastrando...';
            mensagem.classList.add('d-none');

            try {
                const resposta = await fetch('index.php?rota=registrar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nome,
                        email,
                        senha,
                        confirmarSenha
                    })
                });

                const dados = await resposta.json();

                if (!resposta.ok) {
                    throw new Error(dados.erro || 'Erro ao cadastrar usuário.');
                }

                mostrarMensagem(dados.mensagem, 'success');
                formulario.reset();

            } catch (erro) {
                mostrarMensagem(erro.message);
            } finally {
                botao.disabled = false;
                botao.textContent = 'Cadastrar';
            }
        });
    </script>
</body>
</html>
