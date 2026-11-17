<?php

class AuthSession
{
    public static function iniciar(array $dados, array $perfil): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $perfil['cd_usuario'];
        $_SESSION['usuario_nome'] = $perfil['nm_usuario'];
        $_SESSION['supabase_user_id'] = $dados['user']['id'];
        $_SESSION['supabase_access_token'] = $dados['access_token'];
        $_SESSION['supabase_refresh_token'] = $dados['refresh_token'];
        $_SESSION['supabase_expires_at'] = time() + (int) $dados['expires_in'];
        $_SESSION['autenticado'] = true;
    }

    public static function autenticado(): bool
    {
        if (
            empty($_SESSION['usuario_id']) ||
            empty($_SESSION['supabase_user_id']) ||
            empty($_SESSION['supabase_access_token']) ||
            empty($_SESSION['supabase_refresh_token'])
        ) {
            self::limpar();
            return false;
        }

        // Renova o token apenas quando estiver perto de vencer.
        if (($_SESSION['supabase_expires_at'] ?? 0) > time() + 30) {
            return true;
        }

        require_once __DIR__ . '/SupabaseService.php';

        $resultado = (new SupabaseService())->auth('token', [
            'refresh_token' => $_SESSION['supabase_refresh_token']
        ], ['grant_type' => 'refresh_token']);

        if (in_array($resultado['status'], [400, 401, 403], true)) {
            self::limpar();
            return false;
        }

        $dados = $resultado['dados'];

        if (
            $resultado['status'] !== 200 ||
            !self::respostaValida($dados) ||
            $dados['user']['id'] !== $_SESSION['supabase_user_id']
        ) {
            throw new RuntimeException('Não foi possível renovar a sessão do Supabase.');
        }

        $_SESSION['supabase_access_token'] = $dados['access_token'];
        $_SESSION['supabase_refresh_token'] = $dados['refresh_token'];
        $_SESSION['supabase_expires_at'] = time() + (int) $dados['expires_in'];

        return true;
    }

    public static function respostaValida($dados): bool
    {
        return is_array($dados)
            && is_string($dados['access_token'] ?? null)
            && $dados['access_token'] !== ''
            && is_string($dados['refresh_token'] ?? null)
            && $dados['refresh_token'] !== ''
            && is_string($dados['user']['id'] ?? null)
            && $dados['user']['id'] !== ''
            && is_numeric($dados['expires_in'] ?? null)
            && (int) $dados['expires_in'] > 0;
    }

    private static function limpar(): void
    {
        unset(
            $_SESSION['usuario_id'],
            $_SESSION['usuario_nome'],
            $_SESSION['autenticado'],
            $_SESSION['supabase_user_id'],
            $_SESSION['supabase_access_token'],
            $_SESSION['supabase_refresh_token'],
            $_SESSION['supabase_expires_at']
        );
    }
}
