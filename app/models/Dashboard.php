<?php

require_once __DIR__ . '/../services/SupabaseService.php';

class Dashboard
{
    private SupabaseService $supabase;
    private int $usuario;

    public function __construct(string $token, int $usuario, ?SupabaseService $supabase = null)
    {
        $this->supabase = $supabase ?? new SupabaseService($token);
        $this->usuario = $usuario;
    }

    public function carregar(string $mes): array
    {
        $inicio = $mes . '-01';
        $dataInicio = new DateTimeImmutable($inicio, new DateTimeZone('America/Sao_Paulo'));
        $fim = $dataInicio->modify('+1 month')->format('Y-m-d');
        $inicioHistorico = $dataInicio->modify('-5 months')->format('Y-m-d');
        $periodo = static fn (string $campo): array => ['and' => '(' . $campo . '.gte.' . $inicioHistorico . 'T00:00:00-03:00,' . $campo . '.lt.' . $fim . 'T00:00:00-03:00)'];
        $historicoEntradas = $this->listar('tb_entradas', $periodo('dt_entrada'), 'cd_entrada', true);
        $historicoSaidas = $this->listar('tb_saidas', $periodo('dt_saida'), 'cd_saida', true);
        $doMes = static fn (array $item, string $campo): bool => (new DateTimeImmutable($item[$campo]))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m') === $mes;
        $entradas = array_values(array_filter($historicoEntradas, static fn (array $e): bool => $doMes($e, 'dt_entrada')));
        $saidas = array_values(array_filter($historicoSaidas, static fn (array $s): bool => $doMes($s, 'dt_saida')));
        $pendentes = $this->listar('tb_saidas', ['ic_pago' => 'eq.false'], 'cd_saida', true);
        usort($pendentes, static function (array $a, array $b): int {
            return strcmp($a['dt_vencimento'] ?? $a['dt_saida'], $b['dt_vencimento'] ?? $b['dt_saida']);
        });

        // Metas são opcionais até a migração ser instalada. Outros erros não viram estado vazio.
        $metasDisponiveis = true;
        try {
            $metas = $this->listar('tb_metas_gastos', ['dt_mes_referencia' => 'eq.' . $inicio], 'cd_meta_gasto', true);
        } catch (RuntimeException $erro) {
            if ($erro->getCode() !== 404) {
                throw $erro;
            }
            $metas = [];
            $metasDisponiveis = false;
        }

        $extensao = true;
        $detalhes = $this->opcional('tb_detalhes_contas', 'id_conta_usuario', $extensao);
        $pix = $this->opcional('tb_chaves_pix', 'cd_chave_pix', $extensao);
        $cartoes = $this->opcional('tb_cartoes', 'cd_cartao', $extensao);
        return [
            'mes' => $mes,
            'hoje' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'),
            'entradas' => $entradas,
            'saidas' => $saidas,
            'historico_entradas' => $historicoEntradas,
            'historico_saidas' => $historicoSaidas,
            'transferencias' => $this->listar('tb_transferencias', $periodo('dt_transferencia'), 'cd_transferencia', true),
            'fixas' => $this->listar('tb_saidas_fixas', ['ic_ativa' => 'eq.true'], 'cd_saida_fixa', true),
            'detalhes_contas' => $detalhes,
            'chaves_pix' => $pix,
            'cartoes' => $cartoes,
            'extensao_disponivel' => $extensao,
            'pendentes' => $pendentes,
            'metas' => $metas,
            'metas_disponiveis' => $metasDisponiveis,
            'contas' => $this->listar('tb_contas_usuarios', [], 'cd_conta_usuario', true),
            'categorias_entrada' => $this->listar('tb_categoria_entrada', [], 'cd_categoria_entrada'),
            'categorias_saida' => $this->listar('tb_categorias_saidas', [], 'cd_categoria_saida'),
            'formas_pagamento' => $this->listar('tb_formas_pagamentos', [], 'cd_forma_pagamento'),
            'tipos_conta' => $this->listar('tb_tipos_contas', [], 'cd_tipo_conta')
        ];
    }

    public function conferirReferencia(string $tabela, string $chave, int $id, bool $doUsuario = false): void
    {
        $filtros = [$chave => 'eq.' . $id, 'select' => $chave];
        if ($doUsuario) {
            $filtros['id_usuario'] = 'eq.' . $this->usuario;
            $filtros['ic_ativa'] = 'eq.true';
        }
        $resultado = $this->supabase->select($tabela, $filtros);
        $this->verificar($resultado);
        if (empty($resultado['dados'])) {
            throw new InvalidArgumentException('A conta ou categoria selecionada não está disponível. Atualize a página.');
        }
    }

    public function criar(string $tabela, array $dados): void
    {
        $dados['id_usuario'] = $this->usuario;
        $this->verificar($this->supabase->insert($tabela, $dados));
    }

    public function criarLote(string $tabela, array $linhas): void
    {
        foreach ($linhas as &$linha) {
            $linha['id_usuario'] = $this->usuario;
        }
        unset($linha);
        // O PostgREST insere o lote inteiro em uma única transação.
        $this->verificar($this->supabase->insert($tabela, $linhas));
    }

    public function salvarConta(array $conta, ?array $detalhes, ?int $id): void
    {
        if ($id !== null) {
            $this->conferirReferencia('tb_contas_usuarios', 'cd_conta_usuario', $id, true);
        }
        if ($detalhes !== null) {
            $this->verificar($this->supabase->rpc('fn_salvar_conta_financeira', [
                'p_id' => $id, 'p_conta' => $conta, 'p_detalhes' => $detalhes
            ]));
        } elseif ($id !== null) {
            $resultado = $this->supabase->update('tb_contas_usuarios', $conta, [
                'cd_conta_usuario' => 'eq.' . $id, 'id_usuario' => 'eq.' . $this->usuario
            ]);
            $this->verificar($resultado);
            if (empty($resultado['dados'])) {
                throw new InvalidArgumentException('A conta não está disponível para edição.');
            }
        } else {
            $this->criar('tb_contas_usuarios', $conta + ['ic_ativa' => true]);
        }
    }

    public function criarFixa(array $saida, string $inicio): void
    {
        $this->verificar($this->supabase->rpc('fn_criar_despesa_fixa', ['p_saida' => $saida, 'p_inicio' => $inicio]));
    }

    public function pagarFixa(int $id, string $mes): void
    {
        $this->verificar($this->supabase->rpc('fn_pagar_despesa_fixa', ['p_id' => $id, 'p_mes' => $mes . '-01']));
    }

    public function conferirCartao(int $id, int $conta): void
    {
        $resultado = $this->supabase->select('tb_cartoes', [
            'cd_cartao' => 'eq.' . $id, 'id_usuario' => 'eq.' . $this->usuario,
            'id_conta_usuario' => 'eq.' . $conta, 'select' => 'cd_cartao'
        ]);
        $this->verificar($resultado);
        if (empty($resultado['dados'])) {
            throw new InvalidArgumentException('Selecione um cartão vinculado à conta escolhida.');
        }
    }

    private function opcional(string $tabela, string $chave, bool &$disponivel): array
    {
        try {
            return $this->listar($tabela, [], $chave, true);
        } catch (RuntimeException $erro) {
            if ($erro->getCode() !== 404) {
                throw $erro;
            }
            $disponivel = false;
            return [];
        }
    }

    public function pagar(int $id): void
    {
        $agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $resultado = $this->supabase->update('tb_saidas', [
            'ic_pago' => true,
            'dt_pagamento' => $agora->format(DateTimeInterface::ATOM)
        ], [
            'cd_saida' => 'eq.' . $id,
            'id_usuario' => 'eq.' . $this->usuario,
            'ic_pago' => 'eq.false'
        ]);
        $this->verificar($resultado);
        if (empty($resultado['dados'])) {
            throw new InvalidArgumentException('Essa conta já foi paga ou não está disponível.');
        }
    }

    private function listar(string $tabela, array $filtros, string $ordem, bool $doUsuario = false): array
    {
        if ($doUsuario) {
            $filtros['id_usuario'] = 'eq.' . $this->usuario;
        }
        $filtros += ['select' => '*', 'order' => $ordem . '.asc', 'limit' => 500];
        $linhas = [];
        do {
            $filtros['offset'] = count($linhas);
            $resultado = $this->supabase->select($tabela, $filtros);
            $this->verificar($resultado);
            $pagina = $resultado['dados'];
            $linhas = array_merge($linhas, $pagina);
        } while (count($pagina) === 500);
        return $linhas;
    }

    private function verificar(array $resultado): void
    {
        if ($resultado['status'] >= 200 && $resultado['status'] < 300 && is_array($resultado['dados'])) {
            return;
        }
        if ($resultado['status'] === 409) {
            throw new InvalidArgumentException('Já existe um registro com esses dados. Confira antes de salvar novamente.');
        }
        if ($resultado['status'] === 400 && ($resultado['dados']['code'] ?? '') === '22023') {
            throw new InvalidArgumentException($resultado['dados']['message'] ?? 'Confira os dados informados.');
        }
        error_log('Dashboard: falha no Supabase (HTTP ' . $resultado['status'] . ').');
        throw new RuntimeException('Não foi possível acessar os dados financeiros.', $resultado['status']);
    }
}
