<?php
// php tests/financeiro.php — sem rede e sem alterações no banco.
require_once __DIR__ . '/../app/controllers/DashboardApiController.php';
require_once __DIR__ . '/../app/models/Dashboard.php';
$total = 0;
function conferir(bool $condicao, string $mensagem): void {
    global $total;
    if (!$condicao) throw new RuntimeException($mensagem);
    $total++;
}
function rejeita(callable $acao, string $mensagem): void {
    try { $acao(); } catch (InvalidArgumentException $e) { conferir(true,$mensagem); return; }
    throw new RuntimeException($mensagem);
}
$controller = new DashboardApiController();
$chamar = static fn (string $metodo, ...$args) => (new ReflectionMethod($controller,$metodo))->invoke($controller,...$args);
conferir($chamar('valor',['valor'=>'99,95']) === '99.95','Vírgula decimal');
foreach (['-1','0','1.234','1e2','1000000','NaN',[],true] as $v) rejeita(static fn () => $chamar('valor',['valor'=>$v]),'Rejeita valor inválido');
conferir($chamar('saldo','-12,35') === '-12.35','Saldo negativo');
conferir($chamar('data',['data'=>'2024-02-29'],'data') === '2024-02-29','Ano bissexto');
rejeita(static fn () => $chamar('data',['data'=>'2026-02-29'],'data'),'Data inexistente');
rejeita(static fn () => $chamar('validarMes','2026-13'),'Mês inexistente');
conferir($chamar('somarMeses','2026-01-31',1) === '2026-02-28','Ajusta parcela no fim de fevereiro');
conferir($chamar('somarMeses','2024-01-31',1) === '2024-02-29','Ajusta parcela em ano bissexto');
conferir($chamar('somarMeses','2026-01-31',2) === '2026-03-31','Preserva dia original nas parcelas seguintes');
$base = ['vl_saida'=>'100.00','dt_vencimento'=>'2026-01-31','ic_pago'=>true,'dt_pagamento'=>'2026-01-31T12:00:00-03:00'];
$parcelas = $chamar('parcelar',$base,3,'2026-01-31');
conferir(array_column($parcelas,'vl_saida') === ['33.34','33.33','33.33'],'Preserva todos os centavos no parcelamento');
conferir($parcelas[0]['ic_pago'] && !$parcelas[1]['ic_pago'] && $parcelas[1]['dt_pagamento'] === null,'Somente primeira parcela paga');
conferir($parcelas[1]['dt_vencimento'] === '2026-02-28','Vencimento de parcela ajustado');
rejeita(static fn () => $chamar('parcelar',$base,61,'2026-01-31'),'Limite de parcelas');
rejeita(static fn () => $chamar('parcelar',['vl_saida'=>'0.02'] + $base,3,'2026-01-31'),'Não permite parcelas sem centavos');
rejeita(static fn () => $chamar('parcelar',$base,3,'2100-12-31'),'Não ultrapassa data máxima');
conferir($chamar('documentoValido','52998224725',false),'CPF com verificadores válidos');
conferir(!$chamar('documentoValido','11111111111',false),'CPF repetido');
conferir(!$chamar('documentoValido','52998224726',false),'CPF com verificador inválido');
conferir($chamar('documentoValido','11222333000181',true),'CNPJ com verificadores válidos');

class SupabaseFalso extends SupabaseService {
    public array $chamadas = [];
    public array $respostas = [];
    public function __construct() {}
    public function select(string $tabela,array $filtros=[]): array {
        $this->chamadas[] = [$tabela,$filtros];
        $resposta = $this->respostas[$tabela] ?? ['status'=>200,'dados'=>[]];
        return is_callable($resposta) ? $resposta($filtros) : $resposta;
    }
    public function insert(string $tabela,array $dados): array { $this->chamadas[] = [$tabela,$dados]; return ['status'=>201,'dados'=>$dados]; }
    public function update(string $tabela,array $dados,array $filtros): array { $this->chamadas[] = [$tabela,$dados,$filtros]; return ['status'=>200,'dados'=>[]]; }
}
$falso = new SupabaseFalso();
$falso->respostas['tb_entradas'] = static function ($filtros) {
    return ['status'=>200,'dados'=> $filtros['offset'] === 0 ? array_fill(0,500,['cd_entrada'=>1,'dt_entrada'=>'2026-11-01T02:00:00+00:00']) : [['cd_entrada'=>501,'dt_entrada'=>'2026-11-01T03:00:00+00:00']]];
};
$model = new Dashboard('token-falso',7,$falso);
$resultado = $model->carregar('2026-11');
conferir(count($resultado['historico_entradas']) === 501,'Paginação de mais de 500 registros');
conferir(count($resultado['entradas']) === 1,'Respeita virada do mês em São Paulo');
foreach ($falso->chamadas as [$tabela,$filtros]) {
    if (in_array($tabela,['tb_entradas','tb_saidas','tb_contas_usuarios','tb_saidas_fixas','tb_transferencias','tb_cartoes','tb_chaves_pix','tb_detalhes_contas','tb_metas_gastos'],true)) conferir(($filtros['id_usuario'] ?? null) === 'eq.7','Leituras isoladas por proprietário');
}
$falso->respostas['tb_cartoes'] = ['status'=>404,'dados'=>['code'=>'PGRST205']];
conferir(!$model->carregar('2026-11')['extensao_disponivel'],'Banco sem tabelas complementares continua funcionando');
$falso->respostas['tb_cartoes'] = ['status'=>403,'dados'=>[]];
try { $model->carregar('2026-11'); throw new LogicException('Falha de permissão tratada como vazio'); } catch (RuntimeException $e) { conferir($e->getCode() === 403,'Permissão negada não vira estado vazio'); }
rejeita(static fn () => $model->conferirReferencia('tb_contas_usuarios','cd_conta_usuario',99,true),'Não aceita conta de outro usuário');
$model->criarLote('tb_saidas',[['vl_saida'=>'1.00'],['vl_saida'=>'2.00']]);
conferir(array_column(end($falso->chamadas)[1],'id_usuario') === [7,7],'Lote usa proprietário da sessão');
rejeita(static fn () => $model->pagar(9),'Pagamento não encontrado ou já realizado');
conferir(end($falso->chamadas)[2]['ic_pago'] === 'eq.false','Pagamento é condicional ao status pendente');
echo "$total verificações aprovadas. Nenhum acesso real ao banco.\n";
