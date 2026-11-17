<?php

require_once __DIR__ . '/../services/SupabaseService.php';

class Entrada
{
    private SupabaseService $supabase;

    public function __construct(?string $accessToken = null)
    {
        $this->supabase = new SupabaseService($accessToken);
    }

    public function listarPorUsuario(int $idUsuario): array
    {
        return $this->supabase->select('tb_entradas', [
            'select' => '*',
            'id_usuario' => 'eq.' . $idUsuario
        ]);
    }
}
