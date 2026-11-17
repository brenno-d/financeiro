<?php

require_once __DIR__ . '/../services/SupabaseService.php';

class Usuario
{
    private SupabaseService $supabase;

    public function __construct()
    {
        $this->supabase = new SupabaseService();
    }

    public function autenticar(string $email, string $senha): array
    {
        return $this->supabase->auth('token', [
            'email' => $email,
            'password' => $senha
        ], ['grant_type' => 'password']);
    }

    public function criar(string $nome, string $email, string $senha): array
    {
        return $this->supabase->auth('signup', [
            'email' => $email,
            'password' => $senha,
            'data' => ['nome' => $nome]
        ], ['redirect_to' => SUPABASE_AUTH_REDIRECT_URL]);
    }

    public function reenviarConfirmacao(string $email): array
    {
        return $this->supabase->auth('resend', [
            'type' => 'signup',
            'email' => $email
        ], ['redirect_to' => SUPABASE_AUTH_REDIRECT_URL]);
    }

    public function vincularPerfil(string $accessToken): array
    {
        $resultado = (new SupabaseService($accessToken))->rpc('vincular_usuario_auth');

        if ($resultado['status'] === 404) {
            throw new RuntimeException(
                'A função vincular_usuario_auth está indisponível na Data API do Supabase.',
                503
            );
        }

        if ($resultado['status'] !== 200 || empty($resultado['dados'][0]['cd_usuario'])) {
            throw new RuntimeException('Falha ao vincular o perfil (HTTP ' . $resultado['status'] . ').');
        }

        return $resultado['dados'][0];
    }
}
