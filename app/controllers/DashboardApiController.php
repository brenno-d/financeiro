<?php

require_once __DIR__ . '/../services/AuthSession.php';

class DashboardApiController
{
    public function index(): void
    {
        $this->executar(false);
    }

    public function salvar(): void
    {
        $this->executar(true);
    }

    private function executar(bool $salvar): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if (!AuthSession::autenticado()) {
            $this->responder(401, ['erro' => 'Sua sessão expirou. Entre novamente.']);
        }
        try {
            require_once __DIR__ . '/../models/Dashboard.php';
            $dashboard = new Dashboard($_SESSION['supabase_access_token'], (int) $_SESSION['usuario_id']);
            if (!$salvar) {
                $mes = $_GET['mes'] ?? (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m');
                $this->validarMes($mes);
                $this->responder(200, $dashboard->carregar($mes));
            }
            $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
                $this->responder(403, ['erro' => 'Token de segurança inválido. Atualize a página.']);
            }
            $dados = json_decode(file_get_contents('php://input'), true);
            if (!is_array($dados)) {
                throw new InvalidArgumentException('Dados inválidos.');
            }
            $acao = $dados['acao'] ?? '';
            if ($acao === 'pagar') {
                $dashboard->pagar($this->id($dados, 'id'));
            } elseif ($acao === 'pagar_fixa') {
                $mes = $dados['mes'] ?? '';
                $this->validarMes($mes);
                $dashboard->pagarFixa($this->id($dados, 'id'), $mes);
            } elseif ($acao === 'conta') {
                $tipo = $this->id($dados, 'id_tipo_conta');
                $dashboard->conferirReferencia('tb_tipos_contas', 'cd_tipo_conta', $tipo);
                $detalhes = null;
                if (array_key_exists('titular', $dados)) {
                    $detalhes = [
                        'nm_titular' => $this->opcional($dados, 'titular', 120),
                        'ds_agencia' => $this->opcional($dados, 'agencia', 15),
                        'ds_numero_conta' => $this->opcional($dados, 'numero', 30)
                    ];
                }
                $dashboard->salvarConta([
                    'nm_conta_usuario' => $this->texto($dados, 'nome', 45),
                    'id_tipo_conta' => $tipo,
                    'vl_saldo_conta_usuario' => $this->saldo($dados['saldo'] ?? '0')
                ], $detalhes, empty($dados['id']) ? null : $this->id($dados, 'id'));
            } elseif ($acao === 'transferencia') {
                $origem = $this->id($dados, 'id_conta_origem');
                $destino = $this->id($dados, 'id_conta_destino');
                if ($origem === $destino) {
                    throw new InvalidArgumentException('Escolha contas diferentes para origem e destino.');
                }
                $dashboard->conferirReferencia('tb_contas_usuarios', 'cd_conta_usuario', $origem, true);
                $dashboard->conferirReferencia('tb_contas_usuarios', 'cd_conta_usuario', $destino, true);
                $dashboard->criar('tb_transferencias', [
                    'id_conta_origem' => $origem, 'id_conta_destino' => $destino,
                    'vl_transferencia' => $this->valor($dados),
                    'dt_transferencia' => $this->data($dados, 'data') . 'T12:00:00-03:00',
                    'ds_observacao' => $this->opcional($dados, 'descricao', 60)
                ]);
            } elseif ($acao === 'pix' || $acao === 'cartao') {
                $conta = $this->id($dados, 'id_conta_usuario');
                $dashboard->conferirReferencia('tb_contas_usuarios', 'cd_conta_usuario', $conta, true);
                if ($acao === 'pix') {
                    $tipo = $dados['tipo'] ?? '';
                    $chave = $this->texto($dados, 'chave', 180);
                    $valida = is_string($tipo) && match ($tipo) {
                        'email' => (bool) filter_var($chave, FILTER_VALIDATE_EMAIL),
                        'telefone' => (bool) preg_match('/^\+?[1-9][0-9]{9,14}$/D', $chave),
                        'cpf' => $this->documentoValido($chave, false),
                        'cnpj' => $this->documentoValido($chave, true),
                        'aleatoria' => (bool) preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/iD', $chave),
                        default => false
                    };
                    if (!$valida) {
                        throw new InvalidArgumentException('A chave não corresponde ao tipo selecionado. Use apenas dígitos para CPF, CNPJ e telefone (com DDI).');
                    }
                    $dashboard->criar('tb_chaves_pix', ['id_conta_usuario' => $conta, 'tp_chave' => $tipo, 'ds_chave' => $chave]);
                } else {
                    $tipo = $dados['tipo'] ?? '';
                    $final = $dados['final'] ?? '';
                    if (!in_array($tipo, ['debito', 'credito', 'multiplo'], true) || !is_string($final) || !preg_match('/^\d{4}$/D', $final)) {
                        throw new InvalidArgumentException('Informe o tipo e os últimos quatro dígitos do cartão.');
                    }
                    $validade = $this->opcional($dados, 'validade', 7);
                    if ($validade !== null) $this->validarMes($validade);
                    $dia = empty($dados['dia']) || $tipo === 'debito' ? null : $this->id($dados, 'dia');
                    if ($dia !== null && $dia > 31) throw new InvalidArgumentException('Informe um dia de vencimento entre 1 e 31.');
                    $dashboard->criar('tb_cartoes', [
                        'id_conta_usuario' => $conta, 'nm_cartao' => $this->texto($dados, 'nome', 60),
                        'tp_cartao' => $tipo, 'nr_final' => $final,
                        'dt_validade' => $validade ? $validade . '-01' : null, 'nr_dia_vencimento' => $dia
                    ]);
                }
            } elseif ($acao === 'meta') {
                $categoria = $this->id($dados, 'id_categoria_saida');
                $dashboard->conferirReferencia('tb_categorias_saidas', 'cd_categoria_saida', $categoria);
                $mes = $dados['mes'] ?? '';
                $this->validarMes($mes);
                $dashboard->criar('tb_metas_gastos', [
                    'nm_meta_gasto' => $this->texto($dados, 'nome', 60),
                    'vl_limite_meta' => $this->valor($dados),
                    'id_categoria_saida' => $categoria,
                    'dt_mes_referencia' => $mes . '-01'
                ]);
            } elseif ($acao === 'entrada' || $acao === 'saida') {
                $conta = $this->id($dados, 'id_conta_usuario');
                $dashboard->conferirReferencia('tb_contas_usuarios', 'cd_conta_usuario', $conta, true);
                $categoria = $this->id($dados, 'id_categoria');
                $entrada = $acao === 'entrada';
                $dashboard->conferirReferencia(
                    $entrada ? 'tb_categoria_entrada' : 'tb_categorias_saidas',
                    $entrada ? 'cd_categoria_entrada' : 'cd_categoria_saida',
                    $categoria
                );
                $data = $this->data($dados, 'data');
                $registro = [
                    'ds_motivo_' . $acao => $this->texto($dados, 'descricao', $entrada ? 45 : 60),
                    'vl_' . $acao => $this->valor($dados),
                    'dt_' . $acao => $data . 'T12:00:00-03:00',
                    'id_categoria_' . $acao => $categoria,
                    'id_conta_usuario' => $conta
                ];
                if (!$entrada) {
                    $forma = $this->id($dados, 'id_forma_pagamento');
                    $dashboard->conferirReferencia('tb_formas_pagamentos', 'cd_forma_pagamento', $forma);
                    if (!is_bool($dados['pago'] ?? null)) {
                        throw new InvalidArgumentException('Informe o status do pagamento.');
                    }
                    $pago = $dados['pago'];
                    $registro += [
                        'id_forma_pagamento' => $forma,
                        'ic_pago' => $pago,
                        'dt_vencimento' => $this->data($dados, 'vencimento'),
                        'dt_pagamento' => $pago ? $data . 'T12:00:00-03:00' : null
                    ];
                    if (!empty($dados['id_cartao'])) {
                        $cartao = $this->id($dados, 'id_cartao');
                        $dashboard->conferirCartao($cartao, $conta);
                        $registro['id_cartao'] = $cartao;
                    }
                }
                $frequencia = $dados['frequencia'] ?? 'unica';
                if (!in_array($frequencia, ['unica', 'fixa', 'parcelada'], true) || ($entrada && $frequencia !== 'unica')) {
                    throw new InvalidArgumentException('Selecione uma frequência válida para este lançamento.');
                }
                if ($frequencia === 'fixa') {
                    $dashboard->criarFixa($registro, $data);
                } elseif ($frequencia === 'parcelada') {
                    $dashboard->criarLote('tb_saidas', $this->parcelar($registro, $this->id($dados, 'parcelas'), $data));
                } else {
                    $dashboard->criar($entrada ? 'tb_entradas' : 'tb_saidas', $registro);
                }
            } else {
                throw new InvalidArgumentException('Operação inválida.');
            }
            $this->responder(201, ['mensagem' => 'Registro salvo com sucesso.']);
        } catch (InvalidArgumentException $erro) {
            $this->responder(422, ['erro' => $erro->getMessage()]);
        } catch (Throwable $erro) {
            error_log('Dashboard: ' . $erro->getMessage());
            $this->responder(503, ['erro' => 'Não foi possível acessar os dados. Tente novamente em instantes.']);
        }
    }

    private function texto(array $dados, string $campo, int $maximo): string
    {
        $valor = $dados[$campo] ?? null;
        if (!is_string($valor) || trim($valor) === '' || mb_strlen(trim($valor)) > $maximo) {
            throw new InvalidArgumentException('Preencha os campos de texto respeitando o tamanho indicado.');
        }
        return trim($valor);
    }

    private function opcional(array $dados, string $campo, int $maximo): ?string
    {
        if (!isset($dados[$campo]) || $dados[$campo] === '') return null;
        return $this->texto($dados, $campo, $maximo);
    }

    private function saldo($valor): string
    {
        if (!is_string($valor) || !preg_match('/^-?\d{1,9}(?:[.,]\d{1,2})?$/D', $valor)) {
            throw new InvalidArgumentException('Informe um saldo válido, com até duas casas decimais.');
        }
        return number_format((float) str_replace(',', '.', $valor), 2, '.', '');
    }

    private function somarMeses(string $valor, int $meses): string
    {
        $data = new DateTimeImmutable($valor);
        $mes = $data->modify('first day of this month')->modify('+' . $meses . ' months');
        $dia = min((int) $data->format('d'), (int) $mes->format('t'));
        $resultado = $mes->setDate((int) $mes->format('Y'), (int) $mes->format('m'), $dia)->format('Y-m-d');
        if ($resultado > '2100-12-31') throw new InvalidArgumentException('As parcelas devem terminar até dezembro de 2100.');
        return $resultado;
    }

    private function parcelar(array $registro, int $parcelas, string $data): array
    {
        $total = (int) round((float) $registro['vl_saida'] * 100);
        if ($parcelas < 2 || $parcelas > 60 || $total < $parcelas) {
            throw new InvalidArgumentException('Use de 2 a 60 parcelas, com valor mínimo de R$ 0,01 por parcela.');
        }
        $linhas = [];
        for ($i = 0; $i < $parcelas; $i++) {
            $linha = $registro;
            $linha['nr_parcela'] = $i + 1;
            $linha['vl_saida'] = number_format((intdiv($total, $parcelas) + ($i < $total % $parcelas ? 1 : 0)) / 100, 2, '.', '');
            $linha['dt_saida'] = $this->somarMeses($data, $i) . 'T12:00:00-03:00';
            $linha['dt_vencimento'] = $this->somarMeses($registro['dt_vencimento'], $i);
            if ($i > 0) { $linha['ic_pago'] = false; $linha['dt_pagamento'] = null; }
            $linhas[] = $linha;
        }
        return $linhas;
    }

    private function documentoValido(string $valor, bool $cnpj): bool
    {
        $tamanho = $cnpj ? 14 : 11;
        if (strlen($valor) !== $tamanho || !ctype_digit($valor) || preg_match('/^(\d)\1+$/', $valor)) return false;
        $base = substr($valor, 0, -2);
        for ($digito = 0; $digito < 2; $digito++) {
            $pesos = $cnpj ? ($digito === 0 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2]) : range(strlen($base) + 1, 2);
            $soma = 0;
            foreach ($pesos as $i => $peso) $soma += (int) $base[$i] * $peso;
            $resto = $soma % 11;
            $calculado = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $valor[strlen($base)] !== $calculado) return false;
            $base .= (string) $calculado;
        }
        return true;
    }

    private function id(array $dados, string $campo): int
    {
        $valor = $dados[$campo] ?? null;
        if ((!is_int($valor) && !is_string($valor)) || !ctype_digit((string) $valor) || (int) $valor <= 0) {
            throw new InvalidArgumentException('Selecione uma opção válida.');
        }
        return (int) $valor;
    }

    private function valor(array $dados): string
    {
        $valor = $dados['valor'] ?? null;
        if (!is_string($valor) || !preg_match('/^\d{1,6}(?:[.,]\d{1,2})?$/D', $valor)) {
            throw new InvalidArgumentException('Informe um valor de até R$ 999.999,99, com no máximo duas casas decimais.');
        }
        $numero = (float) str_replace(',', '.', $valor);
        if ($numero <= 0) {
            throw new InvalidArgumentException('O valor deve ser maior que zero.');
        }
        return number_format($numero, 2, '.', '');
    }

    private function data(array $dados, string $campo): string
    {
        $valor = $dados[$campo] ?? null;
        $data = is_string($valor) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valor) : false;
        if (!$data || $data->format('Y-m-d') !== $valor || $valor < '2000-01-01' || $valor > '2100-12-31') {
            throw new InvalidArgumentException('Informe uma data válida entre 2000 e 2100.');
        }
        return $valor;
    }

    private function validarMes($mes): void
    {
        if (!is_string($mes) || !preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/D', $mes)) {
            throw new InvalidArgumentException('Informe um mês válido entre 2000 e 2100.');
        }
    }

    private function responder(int $status, array $dados): void
    {
        http_response_code($status);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}
