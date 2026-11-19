<?php if (!defined('APP_STARTED')) { http_response_code(404); exit; } ?>
<section data-pagina="visao-geral" <?= $pagina !== 'visao-geral' ? 'hidden' : '' ?> aria-label="Visão geral">
    <div class="visao-grade">
        <div class="visao-esquerda">
            <section class="cartao" aria-labelledby="tituloContas">
                <div class="cabecalho-cartao"><h2 id="tituloContas">Contas a pagar <span id="quantidadeContas" class="contador"></span></h2><button class="btn-icone" type="button" data-novo="saida" aria-label="Adicionar conta a pagar"><i class="bi bi-plus-lg" aria-hidden="true"></i></button></div>
                <div class="corpo-cartao lista-rolagem" id="listaContas"></div>
                <div class="total-contas"><span>Total em aberto</span><strong id="totalContas">—</strong></div>
            </section>
            <section class="cartao metas-cartao" aria-labelledby="tituloMetas">
                <div class="cabecalho-cartao"><h2 id="tituloMetas">Metas de gastos</h2><button class="btn-icone" type="button" data-bs-toggle="modal" data-bs-target="#modalMeta" aria-label="Adicionar meta de gastos"><i class="bi bi-plus-lg" aria-hidden="true"></i></button></div>
                <div class="corpo-cartao lista-rolagem" id="listaMetas"></div>
            </section>
        </div>
        <section class="cartao cartao-resumo" aria-labelledby="tituloResumo">
            <div class="cabecalho-cartao"><div><h2 id="tituloResumo">Receitas e despesas</h2><p id="periodoResumo" class="texto-suave">Neste mês</p></div><button type="button" class="botao-texto" id="alternarProjecao" aria-pressed="false">Ver projetado <i class="bi bi-arrow-repeat" aria-hidden="true"></i></button></div>
            <div class="corpo-cartao resumo-corpo">
                <div class="saldo-mes"><span id="rotuloSaldo">Saldo do mês</span><strong id="saldoMes">—</strong></div>
                <div class="grafico-resumo" id="grafico" role="img" aria-label="Comparativo de receitas e despesas">
                    <div class="coluna-resumo"><strong id="valorReceita">—</strong><div class="area-barra"><div class="barra receita" id="barraReceita"></div></div><span><i class="legenda receita" aria-hidden="true"></i>Receitas</span></div>
                    <div class="coluna-resumo"><strong id="valorDespesa">—</strong><div class="area-barra"><div class="barra despesa" id="barraDespesa"></div></div><span><i class="legenda despesa" aria-hidden="true"></i>Despesas</span></div>
                </div>
                <p id="graficoVazio" class="texto-suave text-center d-none">As movimentações do mês aparecerão aqui.</p>
                <div class="resumo-nota"><span id="descricaoResumo">Receitas recebidas menos despesas pagas.</span><button class="botao-texto" type="button" data-novo="entrada">Adicionar receita</button></div>
            </div>
        </section>
    </div>
</section>
<section data-pagina="extrato" <?= $pagina !== 'extrato' ? 'hidden' : '' ?> aria-label="Extrato">
    <div class="barra-ferramentas"><div class="abas-periodo" role="group" aria-label="Período do extrato"><button type="button" data-periodo="90" class="active" aria-pressed="true">Últimos 90 dias</button><button type="button" data-periodo="mes" aria-pressed="false">Mês selecionado</button></div><div class="acoes"><button class="btn btn-verde" data-novo="entrada" type="button"><i class="bi bi-plus-lg" aria-hidden="true"></i>Novo lançamento</button><button class="btn btn-amarelo" type="button" data-bs-toggle="modal" data-bs-target="#modalFiltro"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar <span id="contadorFiltros" hidden></span></button></div></div>
    <div class="busca-extrato"><label class="campo-busca"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="buscaExtrato" placeholder="Buscar descrição ou conta" aria-label="Buscar no extrato"></label><button id="limparFiltros" class="botao-texto" type="button" hidden>Limpar filtros</button><span id="quantidadeLancamentos" class="texto-suave ms-auto" role="status"></span></div>
    <div id="resumoExtrato" class="resumo-extrato"></div><div id="listaExtrato"></div>
</section>
<section data-pagina="contas" <?= $pagina !== 'contas' ? 'hidden' : '' ?> aria-label="Contas financeiras">
    <div class="patrimonio cartao"><span>Patrimônio total</span><strong id="patrimonioTotal">—</strong><small>Soma dos saldos das contas ativas</small></div>
    <div class="secao-titulo"><h2>Suas contas <span class="contador" id="numeroContas"></span></h2><button class="btn btn-verde" type="button" data-adicionar-conta><i class="bi bi-plus-lg" aria-hidden="true"></i>Adicionar conta</button></div>
    <div class="tabela-container"><table class="tabela tabela-contas"><thead><tr><th>Instituição / nome</th><th>Tipo</th><th>Agência</th><th>Conta</th><th class="text-end">Saldo</th><th><span class="visually-hidden">Ações</span></th></tr></thead><tbody id="tabelaContas"></tbody></table></div><div id="contasVazias" hidden></div>
</section>
<section data-pagina="conta" <?= $pagina !== 'conta' ? 'hidden' : '' ?> aria-label="Detalhes da conta">
    <a class="voltar" href="index.php?rota=contas" data-rota="contas"><i class="bi bi-chevron-left" aria-hidden="true"></i>Voltar para contas</a><div id="detalhesConta"></div>
    <div class="secao-titulo"><h2>Chaves Pix</h2><button class="botao-texto" type="button" data-adicionar-pix><i class="bi bi-plus-lg" aria-hidden="true"></i>Adicionar chave</button></div><div id="chavesPix" class="lista-pix"></div>
    <div class="secao-titulo"><h2>Cartões vinculados</h2><button class="btn btn-verde" type="button" data-adicionar-cartao><i class="bi bi-plus-lg" aria-hidden="true"></i>Adicionar cartão</button></div><div id="cartoesConta" class="cartoes-grade"></div>
    <div class="secao-titulo extrato-conta-titulo"><div><h2>Extrato da conta</h2><p class="texto-suave mb-0">Últimos 90 dias</p></div><button class="botao-texto" type="button" data-extrato-conta>Ver no extrato</button></div><div id="extratoConta"></div>
</section>
<section data-pagina="dashboard" <?= $pagina !== 'dashboard' ? 'hidden' : '' ?> aria-label="Estatísticas financeiras">
    <div class="estatisticas-grade">
        <section class="cartao estatistica-categorias"><div class="cabecalho-cartao"><div><h2>Gastos fixos</h2><strong class="numero-destaque" id="totalFixos">—</strong><p class="texto-suave" id="quantidadeFixos">Despesas recorrentes do mês</p></div><i class="bi bi-arrow-repeat icone-discreto" aria-hidden="true"></i></div><div class="corpo-cartao"><div class="rosca-area"><div id="graficoCategorias" class="rosca" role="img" aria-label="Gastos fixos por categoria"><div class="rosca-centro"><span>por categoria</span><strong id="categoriasTotal">—</strong></div></div></div><div id="legendaCategorias" class="legenda-categorias"></div><div id="listaFixos" class="lista-fixos"></div></div></section>
        <section class="cartao estatistica-historico"><div class="cabecalho-cartao"><div><h2 id="tituloHistorico">Gastos nos últimos 6 meses</h2><strong class="numero-destaque" id="totalHistorico">—</strong><p class="texto-suave">Despesas pagas por mês de lançamento</p></div><label class="periodo-estatisticas"><span class="visually-hidden">Período das estatísticas</span><select id="periodoEstatisticas" class="form-select"><option value="6">6 meses</option><option value="3">3 meses</option></select></label></div><div class="corpo-cartao"><div class="grafico-historico" id="graficoHistorico" role="img" aria-label="Gastos por mês"></div><p class="texto-suave grafico-nota" id="notaHistorico"></p></div></section>
    </div>
</section>
