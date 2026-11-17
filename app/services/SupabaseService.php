<?php

require_once __DIR__ . '/../../config/supabase.php';

class SupabaseService
{
    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token;
    }

    private function request(
        string $metodo,
        string $endpoint,
        array $parametros = [],
        ?array $dados = null
    ): array {
        $url = SUPABASE_URL . $endpoint;

        if ($parametros) {
            $url .= '?' . http_build_query(
                $parametros,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
        }

        $headers = [
            'apikey: ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
            'Prefer: return=representation'
        ];

        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20
        ]);

        if ($dados !== null) {
            curl_setopt(
                $curl,
                CURLOPT_POSTFIELDS,
                json_encode($dados, JSON_THROW_ON_ERROR)
            );
        }

        $resposta = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erro = curl_error($curl);

        curl_close($curl);

        if ($resposta === false) {
            throw new RuntimeException($erro);
        }

        return [
            'status' => $status,
            'dados' => json_decode($resposta, true)
        ];
    }

    public function auth(
        string $endpoint,
        array $dados,
        array $parametros = []
    ): array {
        return $this->request(
            'POST',
            '/auth/v1/' . $endpoint,
            $parametros,
            $dados
        );
    }

    public function rpc(string $funcao, array $dados = []): array
    {
        return $this->request('POST', '/rest/v1/rpc/' . $funcao, [], $dados ?: null);
    }

    public function select(
        string $tabela,
        array $filtros = []
    ): array {
        return $this->request(
            'GET',
            '/rest/v1/' . $tabela,
            $filtros
        );
    }

    public function insert(
        string $tabela,
        array $dados
    ): array {
        return $this->request(
            'POST',
            '/rest/v1/' . $tabela,
            [],
            $dados
        );
    }

    public function update(
        string $tabela,
        array $dados,
        array $filtros
    ): array {
        if (!$filtros) {
            throw new InvalidArgumentException(
                'Informe um filtro para atualizar.'
            );
        }

        return $this->request(
            'PATCH',
            '/rest/v1/' . $tabela,
            $filtros,
            $dados
        );
    }

    public function delete(
        string $tabela,
        array $filtros
    ): array {
        if (!$filtros) {
            throw new InvalidArgumentException(
                'Informe um filtro para excluir.'
            );
        }

        return $this->request(
            'DELETE',
            '/rest/v1/' . $tabela,
            $filtros
        );
    }
}
