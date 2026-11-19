<?php

require_once __DIR__ . '/../services/AuthSession.php';

class AuthController
{
    public function exibirLogin(): void
    {
        $this->exibirFormulario('login');
    }

    public function exibirCadastro(): void
    {
        $this->exibirFormulario('cadastro');
    }

    private function exibirFormulario(string $tela): void
    {
        if (AuthSession::autenticado()) {
            header('Location: index.php?rota=visao-geral');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        require __DIR__ . '/../views/auth/' . $tela . '.php';
    }

    public function login(): void
    {
        $dados = $this->lerDados();

        $email = $dados['email'] ?? '';
        $senha = $dados['senha'] ?? '';

        if (!is_string($email) || !is_string($senha)) {
            $this->responder(400, ['erro' => 'Email ou senha inválidos']);
        }

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
            $this->responder(400, ['erro' => 'Email ou senha inválidos']);
        }

        try {
            require_once __DIR__ . '/../models/Usuario.php';

            $usuarios = new Usuario();
            $resultado = $usuarios->autenticar($email, $senha);

            if ($resultado['status'] !== 200) {
                $this->responderErroAuth($resultado);
            }

            if (!AuthSession::respostaValida($resultado['dados'])) {
                throw new RuntimeException('Resposta inválida do Supabase Auth.');
            }

            $perfil = $usuarios->vincularPerfil($resultado['dados']['access_token']);
            AuthSession::iniciar($resultado['dados'], $perfil);

            $this->responder(200, [
                'sucesso' => true,
                'mensagem' => 'Login realizado com sucesso'
            ]);
        } catch (Throwable $e) {
            error_log('Login: ' . $e->getMessage());

            if ($e->getCode() === 503) {
                $this->responder(503, ['erro' => 'A configuração dos perfis de usuário ainda não foi concluída.']);
            }

            $this->responder(500, ['erro' => 'Erro interno. Tente novamente.']);
        }
    }

    public function registrar(): void
    {
        $dados = $this->lerDados();

        $nome = $dados['nome'] ?? '';
        $email = $dados['email'] ?? '';
        $senha = $dados['senha'] ?? '';
        $confirmarSenha = $dados['confirmarSenha'] ?? '';

        if (!is_string($nome) || !is_string($email) || !is_string($senha) || !is_string($confirmarSenha)) {
            $this->responder(400, ['erro' => 'Dados inválidos.']);
        }

        $nome = trim($nome);
        $email = mb_strtolower(trim($email));

        if ($nome === '' || mb_strlen($nome) > 70) {
            $this->responder(400, ['erro' => 'Informe um nome válido.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            $this->responder(400, ['erro' => 'Informe um email válido.']);
        }

        if (strlen($senha) < 8) {
            $this->responder(400, ['erro' => 'A senha deve ter pelo menos 8 caracteres.']);
        }

        if ($senha !== $confirmarSenha) {
            $this->responder(400, ['erro' => 'As senhas não coincidem.']);
        }

        try {
            require_once __DIR__ . '/../models/Usuario.php';

            $resultado = (new Usuario())->criar($nome, $email, $senha);

            if (!in_array($resultado['status'], [200, 201], true)) {
                $this->responderErroAuth($resultado);
            }

            $dadosAuth = $resultado['dados'];
            $usuarioAuth = $dadosAuth['user'] ?? $dadosAuth;

            if (!is_array($usuarioAuth) || !is_string($usuarioAuth['id'] ?? null)) {
                throw new RuntimeException('Resposta inválida do Supabase Auth no cadastro.');
            }

            $confirmacaoPendente = empty($dadosAuth['access_token']);

            $this->responder(201, [
                'sucesso' => true,
                'confirmacao_pendente' => $confirmacaoPendente,
                'mensagem' => $confirmacaoPendente
                    ? 'Verifique seu email para confirmar o cadastro e depois entre na sua conta.'
                    : 'Conta criada com sucesso. Entre com seu email e senha.'
            ]);

        } catch (Throwable $erro) {
            error_log('Erro no cadastro: ' . $erro->getMessage());

            $this->responder(500, ['erro' => 'Erro interno do servidor.']);
        }
    }

    public function reenviarConfirmacao(): void
    {
        $dados = $this->lerDados();

        $email = $dados['email'] ?? null;

        if (!is_string($email)) {
            $this->responder(400, ['erro' => 'Informe um email válido.']);
        }

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            $this->responder(400, ['erro' => 'Informe um email válido.']);
        }

        try {
            require_once __DIR__ . '/../models/Usuario.php';

            $resultado = (new Usuario())->reenviarConfirmacao($email);

            if (!in_array($resultado['status'], [200, 201], true)) {
                $this->responderErroAuth($resultado);
            }

            $this->responder(200, [
                'sucesso' => true,
                'mensagem' => 'Se houver um cadastro pendente para esse email, você receberá um novo link de confirmação. Use o email mais recente.'
            ]);
        } catch (Throwable $erro) {
            error_log('Reenvio de confirmação: ' . $erro->getMessage());
            $this->responder(502, ['erro' => 'Não foi possível reenviar a confirmação. Tente novamente.']);
        }
    }

    private function lerDados(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Allow: POST');
            $this->responder(405, ['erro' => 'Método não permitido.']);
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
            $this->responder(403, ['erro' => 'Token de segurança inválido.']);
        }

        $dados = json_decode(file_get_contents('php://input'), true);

        if (!is_array($dados)) {
            $this->responder(400, ['erro' => 'JSON inválido.']);
        }

        return $dados;
    }

    private function responderErroAuth(array $resultado): void
    {
        $codigo = $resultado['dados']['error_code'] ?? '';

        switch ($codigo) {
            case 'invalid_credentials':
                $this->responder(401, ['erro' => 'Email ou senha incorretos.']);
            case 'email_not_confirmed':
                $this->responder(403, ['erro' => 'Confirme seu email antes de entrar.']);
            case 'user_already_exists':
            case 'email_exists':
                $this->responder(409, ['erro' => 'Este email já está cadastrado.']);
            case 'weak_password':
                $this->responder(400, ['erro' => 'A senha não atende aos requisitos de segurança.']);
            case 'signup_disabled':
                $this->responder(403, ['erro' => 'O cadastro de novas contas está desativado.']);
        }

        if ($resultado['status'] === 429) {
            $this->responder(429, ['erro' => 'Muitas tentativas. Aguarde e tente novamente.']);
        }

        error_log('Supabase Auth: falha na requisição (HTTP ' . $resultado['status'] . ').');
        $this->responder(502, ['erro' => 'Não foi possível concluir a autenticação. Tente novamente.']);
    }

    private function responder(int $status, array $dados): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
