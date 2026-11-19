<?php
// Servidor de inspeção isolado: php -S 127.0.0.1:8095 -t public tests/preview.php
// Sem acesso ao Supabase; não é uma rota do aplicativo.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($caminho !== '/' && $caminho !== '/index.php') return false;
session_start();
if (isset($_GET['fixture'])) $_SESSION['fixture'] = $_GET['fixture'];
$modo = $_SESSION['fixture'] ?? 'normal';
if (($_GET['rota'] ?? '') === 'api/dashboard') {
    header('Content-Type: application/json; charset=utf-8');
    if ($modo === 'erro') { http_response_code(503); echo json_encode(['erro' => 'Erro simulado para conferir a recuperação da tela.']); exit; }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(422); echo json_encode(['erro' => 'Prévia de teste: os dados não são gravados.']); exit; }
    $mes = $_GET['mes'] ?? '2026-11';
    $d = [
        'mes'=>$mes,'hoje'=>'2026-11-19','metas_disponiveis'=>true,'extensao_disponivel'=>$modo !== 'sem-extensao',
        'contas'=>[
            ['cd_conta_usuario'=>1,'nm_conta_usuario'=>'Itaú principal','id_tipo_conta'=>1,'vl_saldo_conta_usuario'=>2450.50,'ic_ativa'=>true],
            ['cd_conta_usuario'=>2,'nm_conta_usuario'=>'Nubank','id_tipo_conta'=>2,'vl_saldo_conta_usuario'=>810.20,'ic_ativa'=>true]
        ],
        'detalhes_contas'=>[['id_conta_usuario'=>1,'nm_titular'=>'Usuário de teste','ds_agencia'=>'0346','ds_numero_conta'=>'12345678-9']],
        'chaves_pix'=>[['cd_chave_pix'=>1,'id_conta_usuario'=>1,'tp_chave'=>'email','ds_chave'=>'exemplo@example.com']],
        'cartoes'=>[['cd_cartao'=>1,'id_conta_usuario'=>1,'nm_cartao'=>'Cartão do dia a dia','tp_cartao'=>'debito','nr_final'=>'1234','dt_validade'=>'2029-10-01','nr_dia_vencimento'=>null]],
        'tipos_conta'=>[['cd_tipo_conta'=>1,'nm_tipo_conta'=>'Corrente'],['cd_tipo_conta'=>2,'nm_tipo_conta'=>'Poupança']],
        'categorias_entrada'=>[['cd_categoria_entrada'=>1,'nm_categoria_entrada'=>'Salário'],['cd_categoria_entrada'=>2,'nm_categoria_entrada'=>'Freelance']],
        'categorias_saida'=>[['cd_categoria_saida'=>1,'ds_categoria_saida'=>'Estudos'],['cd_categoria_saida'=>2,'ds_categoria_saida'=>'Assinaturas'],['cd_categoria_saida'=>3,'ds_categoria_saida'=>'Saúde'],['cd_categoria_saida'=>4,'ds_categoria_saida'=>'Moradia']],
        'formas_pagamento'=>[['cd_forma_pagamento'=>1,'nm_forma_pagamento'=>'Pix'],['cd_forma_pagamento'=>2,'nm_forma_pagamento'=>'Débito']],
        'entradas'=>[['cd_entrada'=>1,'ds_motivo_entrada'=>'Salário de novembro','vl_entrada'=>3200,'dt_entrada'=>"$mes-05T12:00:00-03:00",'id_conta_usuario'=>1,'id_categoria_entrada'=>1]],
        'saidas'=>[
            ['cd_saida'=>1,'ds_motivo_saida'=>'Livros para o semestre','vl_saida'=>65,'dt_saida'=>"$mes-18T12:12:00-03:00",'dt_pagamento'=>"$mes-18T12:12:00-03:00",'id_conta_usuario'=>1,'id_categoria_saida'=>1,'id_forma_pagamento'=>1,'ic_pago'=>true,'id_cartao'=>null],
            ['cd_saida'=>2,'ds_motivo_saida'=>'Consulta','vl_saida'=>200,'dt_saida'=>"$mes-18T09:20:00-03:00",'dt_pagamento'=>"$mes-18T09:20:00-03:00",'id_conta_usuario'=>1,'id_categoria_saida'=>3,'id_forma_pagamento'=>2,'ic_pago'=>true,'id_cartao'=>1],
            ['cd_saida'=>3,'ds_motivo_saida'=>'Internet','vl_saida'=>99.90,'dt_saida'=>"$mes-20T12:00:00-03:00",'dt_vencimento'=>"$mes-20",'dt_pagamento'=>null,'id_conta_usuario'=>1,'id_categoria_saida'=>4,'id_forma_pagamento'=>1,'ic_pago'=>false,'id_saida_fixa'=>1]
        ],
        'fixas'=>[
            ['cd_saida_fixa'=>1,'nm_saida_fixa'=>'Internet','vl_saida_fixa'=>99.9,'id_categoria_saida'=>4,'id_conta_usuario'=>1,'dt_dia_saida_fixa'=>20,'dt_inicio'=>'2026-01-01','dt_fim'=>null],
            ['cd_saida_fixa'=>2,'nm_saida_fixa'=>'Streaming','vl_saida_fixa'=>39.9,'id_categoria_saida'=>2,'id_conta_usuario'=>2,'dt_dia_saida_fixa'=>25,'dt_inicio'=>'2026-01-01','dt_fim'=>null],
            ['cd_saida_fixa'=>3,'nm_saida_fixa'=>'Plano de saúde','vl_saida_fixa'=>249.9,'id_categoria_saida'=>3,'id_conta_usuario'=>1,'dt_dia_saida_fixa'=>10,'dt_inicio'=>'2026-01-01','dt_fim'=>null]
        ],
        'metas'=>[['cd_meta_gasto'=>1,'nm_meta_gasto'=>'Livros','vl_limite_meta'=>100,'id_categoria_saida'=>1],['cd_meta_gasto'=>2,'nm_meta_gasto'=>'Saúde','vl_limite_meta'=>350,'id_categoria_saida'=>3]],
        'transferencias'=>[['cd_transferencia'=>1,'id_conta_origem'=>1,'id_conta_destino'=>2,'vl_transferencia'=>150,'dt_transferencia'=>"$mes-17T08:30:00-03:00",'ds_observacao'=>'Reserva do mês']]
    ];
    $d['pendentes'] = [$d['saidas'][2]];
    $d['historico_entradas'] = $d['entradas']; $d['historico_saidas'] = $d['saidas'];
    foreach ([6=>450.5,7=>650.3,8=>589.5,9=>320.7,10=>410.4] as $m=>$v) {
        $d['historico_saidas'][] = ['cd_saida'=>100+$m,'ds_motivo_saida'=>'Despesas do mês','vl_saida'=>$v,'dt_saida'=>sprintf('2026-%02d-10T12:00:00-03:00',$m),'dt_pagamento'=>sprintf('2026-%02d-10T12:00:00-03:00',$m),'id_conta_usuario'=>1,'id_categoria_saida'=>4,'id_forma_pagamento'=>1,'ic_pago'=>true];
    }
    if ($modo === 'sem-extensao') foreach (['detalhes_contas','chaves_pix','cartoes'] as $k) $d[$k] = [];
    if ($modo === 'vazio') foreach (['contas','detalhes_contas','chaves_pix','cartoes','entradas','saidas','pendentes','fixas','metas','transferencias','historico_entradas','historico_saidas'] as $k) $d[$k] = [];
    echo json_encode($d,JSON_UNESCAPED_UNICODE); exit;
}
define('APP_STARTED',true);
$_SESSION['csrf_token'] = 'preview-token';
$nome = 'Usuário de teste';
$titulos = ['visao-geral'=>'Visão geral','extrato'=>'Extrato','contas'=>'Contas','conta'=>'Detalhes da conta','dashboard'=>'Dashboard'];
$pagina = $_GET['rota'] ?? 'visao-geral'; if (!isset($titulos[$pagina])) $pagina = 'visao-geral';
$titulo = $titulos[$pagina];
require __DIR__ . '/../app/views/dashboard/index.php';
