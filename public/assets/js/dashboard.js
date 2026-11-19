(() => {
  "use strict";
  const $ = (id) => document.getElementById(id);
  const todos = (s) => [...document.querySelectorAll(s)];
  const moeda = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
  const reais = (v) => moeda.format(v / 100);
  const centavos = (v) => Math.round(Number(v || 0) * 100);
  const soma = (a, campo) => a.reduce((t, e) => t + centavos(e[campo]), 0);
  const sim = (v) => v === true || v === 1 || v === "1" || v === "true";
  const esc = (v) =>
    String(v ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const dia = (v) => {
    if (!v) return "";
    if (String(v).length === 10) return v;
    const p = new Intl.DateTimeFormat("en-CA", {
      timeZone: "America/Sao_Paulo",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).formatToParts(new Date(v));
    return ["year", "month", "day"]
      .map((k) => p.find((e) => e.type === k)?.value)
      .join("-");
  };
  const dataBR = (v) => dia(v).split("-").reverse().join("/");
  const mesNome = (mes) =>
    new Date(`${mes}-01T12:00:00`).toLocaleDateString("pt-BR", {
      month: "long",
      year: "numeric",
    });
  const nomeConta = (id) =>
    dados?.contas.find((c) => Number(c.cd_conta_usuario) === Number(id))
      ?.nm_conta_usuario || "Conta indisponível";
  const categoria = (tipo, id) =>
    tipo === "entrada"
      ? dados.categorias_entrada.find(
          (c) => Number(c.cd_categoria_entrada) === Number(id),
        )?.nm_categoria_entrada
      : dados.categorias_saida.find(
          (c) => Number(c.cd_categoria_saida) === Number(id),
        )?.ds_categoria_saida;
  const forma = (id) =>
    dados.formas_pagamento.find(
      (f) => Number(f.cd_forma_pagamento) === Number(id),
    )?.nm_forma_pagamento || "Não informado";
  const titulos = {
    "visao-geral": [
      "Visão geral",
      "Suas contas, metas e movimentações do mês.",
    ],
    extrato: ["Extrato", "Cada entrada e saída, em um só lugar."],
    contas: ["Contas", "Organize as contas que fazem parte do seu dia a dia."],
    conta: ["Detalhes da conta", "Informações, cartões e movimentações."],
    dashboard: ["Dashboard", "Visualize suas estatísticas e gastos."],
  };
  let dados = null,
    pagina = document.body.dataset.pagina,
    contaId = new URLSearchParams(location.search).get("id");
  let projetado = false,
    periodo = "90",
    filtros = {},
    salvando = false,
    requisicao,
    voltarMovimento = false;

  async function api(opcoes = {}) {
    const r = await fetch(
      `index.php?rota=api/dashboard&mes=${encodeURIComponent($("mesReferencia").value)}`,
      {
        credentials: "same-origin",
        ...opcoes,
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": document.querySelector('meta[name="csrf-token"]')
            .content,
        },
      },
    );
    if (r.status === 401) {
      location.assign("index.php?rota=login");
      throw new Error("Sua sessão expirou. Entre novamente.");
    }
    let resultado;
    try {
      resultado = await r.json();
    } catch {
      throw new Error(
        "O servidor não retornou uma resposta válida. Tente novamente.",
      );
    }
    if (!r.ok)
      throw new Error(
        resultado.erro || "Não foi possível concluir a operação.",
      );
    return resultado;
  }
  function aviso(t) {
    $("textoAviso").textContent = t;
    $("aviso").classList.remove("d-none");
  }
  function sucesso(t) {
    $("textoSucesso").textContent = t;
    bootstrap.Toast.getOrCreateInstance($("toastSucesso")).show();
  }
  function vazio(titulo, descricao, atributos = "", texto = "") {
    return `<div class="vazio"><i class="bi bi-journal" aria-hidden="true"></i><h3>${esc(titulo)}</h3><p>${esc(descricao)}</p>${texto ? `<button type="button" class="botao-texto" ${atributos}>${esc(texto)}</button>` : ""}</div>`;
  }
  function mostrarPagina(nova, id = null, historico = true) {
    if (!titulos[nova]) nova = "visao-geral";
    pagina = nova;
    contaId = id;
    document.body.dataset.pagina = nova;
    todos("section[data-pagina]").forEach((s) => {
      s.hidden = s.dataset.pagina !== nova;
    });
    todos(".nav-link[data-rota]").forEach((a) => {
      const ativo = a.dataset.rota === (nova === "conta" ? "contas" : nova);
      a.classList.toggle("active", ativo);
      if (ativo) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    });
    $("tituloPagina").textContent = titulos[nova][0];
    $("subtituloPagina").textContent = titulos[nova][1];
    document.title = `${titulos[nova][0]} | Finanças`;
    if (historico) {
      const url = new URL(location.href);
      url.searchParams.set("rota", nova);
      if (id) url.searchParams.set("id", id);
      else url.searchParams.delete("id");
      history.pushState(null, "", url);
      $("conteudo").focus({ preventScroll: true });
      window.scrollTo({ top: 0 });
    }
    bootstrap.Collapse.getInstance($("menuPrincipal"))?.hide();
    if (dados && nova === "conta") renderizarDetalhes();
  }
  async function carregar() {
    if (!$("mesReferencia").checkValidity() || !$("mesReferencia").value)
      return;
    requisicao?.abort();
    const atual = new AbortController();
    requisicao = atual;
    $("painel").setAttribute("aria-busy", "true");
    $("carregamento").classList.remove("d-none");
    $("aviso").classList.add("d-none");
    try {
      const resultado = await api({ signal: atual.signal });
      if (atual.signal.aborted) return;
      dados = resultado;
      renderizar();
    } catch (e) {
      if (e.name === "AbortError") return;
      dados = null;
      aviso(e.message);
      for (const id of [
        "listaContas",
        "listaMetas",
        "listaExtrato",
        "detalhesConta",
        "chavesPix",
        "cartoesConta",
        "extratoConta",
        "legendaCategorias",
        "listaFixos",
        "graficoHistorico",
      ])
        $(id).innerHTML =
          '<p class="aviso-inline">Dados indisponíveis. Tente novamente.</p>';
      for (const id of [
        "totalContas",
        "saldoMes",
        "valorReceita",
        "valorDespesa",
        "patrimonioTotal",
        "totalFixos",
        "totalHistorico",
        "categoriasTotal",
      ])
        $(id).textContent = "—";
      $("tabelaContas").replaceChildren();
      $("resumoExtrato").replaceChildren();
      $("quantidadeContas").textContent = "";
      $("numeroContas").textContent = "";
      $("quantidadeLancamentos").textContent = "";
      $("quantidadeFixos").textContent = "Dados indisponíveis";
      $("graficoCategorias").style.background = "#eef0ec";
      $("barraReceita").style.height = "0";
      $("barraDespesa").style.height = "0";
      $("grafico").setAttribute("aria-label", "Dados indisponíveis");
    } finally {
      if (requisicao === atual) {
        $("carregamento").classList.add("d-none");
        $("painel").setAttribute("aria-busy", "false");
      }
    }
  }
  function fixasDoMes() {
    const mes = dados.mes;
    const ultimoDia = new Date(
      Number(mes.slice(0, 4)),
      Number(mes.slice(5, 7)),
      0,
    ).getDate();
    return dados.fixas
      .filter(
        (f) =>
          f.dt_inicio.slice(0, 7) <= mes &&
          (!f.dt_fim || f.dt_fim.slice(0, 7) >= mes),
      )
      .map((f) => ({
        ...f,
        vencimento: `${mes}-${String(Math.min(ultimoDia, Number(f.dt_dia_saida_fixa))).padStart(2, "0")}`,
      }));
  }
  function pendentes() {
    const virtuais = fixasDoMes()
      .filter(
        (f) =>
          !dados.saidas.some(
            (s) => Number(s.id_saida_fixa) === Number(f.cd_saida_fixa),
          ),
      )
      .map((f) => ({
        cd_saida: `fixa-${f.cd_saida_fixa}`,
        fixa: f.cd_saida_fixa,
        ds_motivo_saida: f.nm_saida_fixa,
        vl_saida: f.vl_saida_fixa,
        id_conta_usuario: f.id_conta_usuario,
        dt_vencimento: f.vencimento,
        dt_saida: f.vencimento,
      }));
    return [...dados.pendentes, ...virtuais].sort((a, b) =>
      (a.dt_vencimento || a.dt_saida).localeCompare(
        b.dt_vencimento || b.dt_saida,
      ),
    );
  }
  function renderizarContasPagar() {
    const itens = pendentes();
    $("listaContas").innerHTML = itens.length
      ? itens
          .map((c) => {
            const venc = dia(c.dt_vencimento || c.dt_saida);
            const mes = new Date(`${venc}T12:00:00`)
              .toLocaleDateString("pt-BR", { month: "short" })
              .replace(".", "");
            const indisponivel = c.fixa && !dados.extensao_disponivel;
            return `<div class="conta-linha"><div class="data-conta" title="${esc(dataBR(venc))}"><strong>${esc(venc.slice(8))}</strong><span>${esc(mes)}</span></div><div class="conta-descricao"><h3>${esc(c.ds_motivo_saida)}</h3><p class="${venc < dados.hoje ? "atrasada" : ""}">${venc < dados.hoje ? "Em atraso" : "Em aberto"} · ${esc(nomeConta(c.id_conta_usuario))}${c.fixa ? " · Fixa" : ""}</p></div><strong class="conta-valor">− ${reais(centavos(c.vl_saida))}</strong><button class="btn-icone" type="button" data-pagar="${esc(c.cd_saida)}" ${indisponivel ? "disabled" : ""} aria-label="Marcar ${esc(c.ds_motivo_saida)} como paga" title="${indisponivel ? "O pagamento de recorrências precisa ser habilitado." : "Marcar como paga"}"><i class="bi bi-check2-circle" aria-hidden="true"></i></button></div>`;
          })
          .join("")
      : vazio(
          "Nenhuma conta em aberto",
          "Cadastre suas próximas despesas para acompanhar os vencimentos.",
          'data-novo="saida"',
          "Adicionar conta a pagar",
        );
    $("quantidadeContas").textContent = itens.length;
    $("totalContas").textContent = reais(soma(itens, "vl_saida"));
  }
  function renderizarMetas() {
    $("avisoMetas").classList.toggle("d-none", dados.metas_disponiveis);
    $("formMeta").querySelector('[type="submit"]').disabled =
      !dados.metas_disponiveis;
    $("listaMetas").innerHTML = !dados.metas_disponiveis
      ? vazio(
          "Metas indisponíveis",
          "O cadastro de metas ainda precisa ser habilitado.",
        )
      : dados.metas.length
        ? dados.metas
            .map((m) => {
              const gasto = soma(
                dados.saidas.filter(
                  (s) =>
                    sim(s.ic_pago) &&
                    dia(s.dt_pagamento || s.dt_saida) <= dados.hoje &&
                    Number(s.id_categoria_saida) ===
                      Number(m.id_categoria_saida),
                ),
                "vl_saida",
              );
              const limite = centavos(m.vl_limite_meta),
                perc = limite ? Math.round((gasto / limite) * 100) : 0;
              return `<div class="meta-item ${gasto > limite ? "meta-excedida" : ""}"><div class="meta-topo"><h3>${esc(m.nm_meta_gasto)}</h3><span>${perc}% utilizado</span></div><div class="progress" role="progressbar" aria-label="${esc(m.nm_meta_gasto)}" aria-valuenow="${Math.min(100, perc)}" aria-valuemin="0" aria-valuemax="100" aria-valuetext="${perc}% do limite utilizado"><div class="progress-bar" style="width:${Math.min(100, perc)}%"></div></div><div class="meta-valores"><div><span>Meta</span><strong>${reais(limite)}</strong></div><div><span>Gasto</span><strong>${reais(gasto)}</strong></div><div><span>${gasto > limite ? "Excedido" : "Restam"}</span><strong>${reais(Math.abs(limite - gasto))}</strong></div></div></div>`;
            })
            .join("")
        : vazio(
            "Suas metas de gastos",
            "Defina um limite para uma categoria neste mês.",
            'data-bs-toggle="modal" data-bs-target="#modalMeta"',
            "Adicionar meta",
          );
  }
  function renderizarResumo() {
    if (!dados) return;
    const entrada = soma(
      dados.entradas.filter(
        (e) => projetado || dia(e.dt_entrada) <= dados.hoje,
      ),
      "vl_entrada",
    );
    let saida = soma(
      dados.saidas.filter(
        (s) =>
          projetado ||
          (sim(s.ic_pago) && dia(s.dt_pagamento || s.dt_saida) <= dados.hoje),
      ),
      "vl_saida",
    );
    if (projetado)
      saida += soma(
        pendentes().filter((s) => s.fixa),
        "vl_saida",
      );
    const max = Math.max(entrada, saida, 1);
    $("saldoMes").textContent = reais(entrada - saida);
    $("saldoMes").classList.toggle("negativo", saida > entrada);
    $("valorReceita").textContent = reais(entrada);
    $("valorDespesa").textContent = reais(saida);
    $("barraReceita").style.height = `${(entrada / max) * 100}%`;
    $("barraDespesa").style.height = `${(saida / max) * 100}%`;
    $("grafico").setAttribute(
      "aria-label",
      `Receitas: ${reais(entrada)}. Despesas: ${reais(saida)}.`,
    );
    $("graficoVazio").classList.toggle("d-none", Boolean(entrada || saida));
    $("rotuloSaldo").textContent = projetado
      ? "Saldo projetado do mês"
      : "Saldo realizado do mês";
    $("periodoResumo").textContent = mesNome(dados.mes);
    $("descricaoResumo").textContent = projetado
      ? "Inclui lançamentos futuros e gastos fixos previstos no mês."
      : "Receitas e despesas efetivadas, por mês de lançamento.";
    $("alternarProjecao").innerHTML =
      `${projetado ? "Ver realizado" : "Ver projetado"} <i class="bi bi-arrow-repeat" aria-hidden="true"></i>`;
    $("alternarProjecao").setAttribute("aria-pressed", String(projetado));
  }
  function marca(nome, grande = false) {
    const n = nome.toLowerCase();
    const bancos = [
      ["itaú", "itau", "itaú"],
      ["itau", "itau", "itaú"],
      ["nubank", "nubank", "nu"],
      ["santander", "santander", "S"],
      ["bradesco", "bradesco", "B"],
      ["inter", "inter", "inter"],
      ["caixa", "caixa", "C"],
      ["brasil", "bb", "BB"],
    ];
    const b = bancos.find(([busca]) => n.includes(busca));
    return `<span class="banco-marca ${b?.[1] || ""} ${grande ? "grande" : ""}" aria-hidden="true">${esc(b?.[2] || nome.trim().slice(0, 2).toUpperCase())}</span>`;
  }
  function detalhes(id) {
    return (
      dados.detalhes_contas.find(
        (d) => Number(d.id_conta_usuario) === Number(id),
      ) || {}
    );
  }
  function renderizarBancos() {
    const contas = dados.contas.filter((c) => sim(c.ic_ativa));
    $("patrimonioTotal").textContent = reais(
      soma(contas, "vl_saldo_conta_usuario"),
    );
    $("numeroContas").textContent = contas.length;
    $("contasVazias").hidden = Boolean(contas.length);
    $("tabelaContas").closest(".tabela-container").hidden = !contas.length;
    $("contasVazias").innerHTML = vazio(
      "Você ainda não tem contas",
      "Cadastre uma conta para começar a registrar suas movimentações.",
      "data-adicionar-conta",
      "Adicionar conta",
    );
    $("tabelaContas").innerHTML = contas
      .map((c) => {
        const d = detalhes(c.cd_conta_usuario),
          tipo =
            dados.tipos_conta.find(
              (t) => Number(t.cd_tipo_conta) === Number(c.id_tipo_conta),
            )?.nm_tipo_conta || "Não informado";
        return `<tr><td><div class="banco-nome">${marca(c.nm_conta_usuario)}<strong>${esc(c.nm_conta_usuario)}</strong></div></td><td>${esc(tipo)}</td><td>${esc(d.ds_agencia || "—")}</td><td>${esc(d.ds_numero_conta || "—")}</td><td class="text-end"><strong class="${centavos(c.vl_saldo_conta_usuario) < 0 ? "negativo" : ""}">${reais(centavos(c.vl_saldo_conta_usuario))}</strong></td><td class="text-end"><a class="botao-texto" href="index.php?rota=conta&amp;id=${Number(c.cd_conta_usuario)}" data-conta="${Number(c.cd_conta_usuario)}">Ver detalhes</a></td></tr>`;
      })
      .join("");
  }
  function movimentos() {
    return [
      ...dados.historico_entradas.map((e) => ({
        id: e.cd_entrada,
        tipo: "entrada",
        descricao: e.ds_motivo_entrada,
        valor: centavos(e.vl_entrada),
        data: e.dt_entrada,
        conta: e.id_conta_usuario,
        categoria:
          categoria("entrada", e.id_categoria_entrada) || "Sem categoria",
        categoriaId: `entrada:${e.id_categoria_entrada}`,
        metodo: "Não informado",
        efetivado: dia(e.dt_entrada) <= dados.hoje,
      })),
      ...dados.historico_saidas.map((s) => ({
        id: s.cd_saida,
        tipo: "saida",
        descricao: s.ds_motivo_saida,
        valor: centavos(s.vl_saida),
        data: s.dt_saida,
        conta: s.id_conta_usuario,
        categoria: categoria("saida", s.id_categoria_saida) || "Sem categoria",
        categoriaId: `saida:${s.id_categoria_saida}`,
        metodo: forma(s.id_forma_pagamento),
        efetivado:
          sim(s.ic_pago) && dia(s.dt_pagamento || s.dt_saida) <= dados.hoje,
        pago: sim(s.ic_pago),
        parcela: s.nr_parcela,
        cartao: s.id_cartao,
      })),
      ...dados.transferencias.map((t) => ({
        id: t.cd_transferencia,
        tipo: "transferencia",
        descricao: t.ds_observacao || "Transferência entre contas",
        valor: centavos(t.vl_transferencia),
        data: t.dt_transferencia,
        conta: t.id_conta_origem,
        destino: t.id_conta_destino,
        categoria: "Transferência",
        categoriaId: "transferencia",
        metodo: "Entre contas",
        efetivado: dia(t.dt_transferencia) <= dados.hoje,
      })),
    ].sort((a, b) => new Date(b.data) - new Date(a.data) || b.id - a.id);
  }
  function inicio90() {
    const referencia =
      dados.mes === dados.hoje.slice(0, 7)
        ? dados.hoje
        : `${dados.mes}-${new Date(Number(dados.mes.slice(0, 4)), Number(dados.mes.slice(5, 7)), 0).getDate()}`;
    const d = new Date(`${referencia}T12:00:00`);
    d.setDate(d.getDate() - 89);
    return { inicio: dia(d.toISOString()), fim: referencia };
  }
  function extratoHTML(itens) {
    if (!itens.length)
      return vazio(
        "Nenhuma movimentação encontrada",
        "Os lançamentos deste período aparecerão aqui.",
      );
    const grupos = new Map();
    for (const item of itens) {
      const d = dia(item.data);
      if (!grupos.has(d)) grupos.set(d, []);
      grupos.get(d).push(item);
    }
    return [...grupos]
      .map(
        ([data, linhas]) =>
          `<section class="extrato-dia"><h3 class="extrato-data">${esc(dataBR(data))}</h3><div class="tabela-container"><table class="tabela tabela-extrato"><thead><tr><th><span class="visually-hidden">Tipo</span></th><th>Valor</th><th>Descrição</th><th>Método pag.</th><th>Horário</th><th>Categoria</th><th>Conta</th><th>Status</th><th><span class="visually-hidden">Ações</span></th></tr></thead><tbody>${linhas
            .map((m) => {
              const icon =
                m.tipo === "entrada"
                  ? "bi-arrow-down-left"
                  : m.tipo === "saida"
                    ? "bi-arrow-up-right"
                    : "bi-arrow-left-right";
              const hora = new Date(m.data).toLocaleTimeString("pt-BR", {
                timeZone: "America/Sao_Paulo",
                hour: "2-digit",
                minute: "2-digit",
              });
              const status = m.efetivado
                ? m.tipo === "saida"
                  ? "Paga"
                  : m.tipo === "entrada"
                    ? "Recebida"
                    : "Efetivada"
                : m.tipo === "saida" && !m.pago
                  ? "Em aberto"
                  : "Prevista";
              return `<tr><td><i class="bi ${icon} tipo-transacao ${m.tipo}" role="img" aria-label="${m.tipo === "entrada" ? "Entrada" : m.tipo === "saida" ? "Saída" : "Transferência"}"></i></td><td><strong class="${m.tipo === "entrada" ? "positivo" : m.tipo === "saida" ? "negativo" : ""}">${m.tipo === "entrada" ? "+" : m.tipo === "saida" ? "−" : ""} ${reais(m.valor)}</strong></td><td>${esc(m.descricao)}${m.parcela ? `<small>Parcela ${Number(m.parcela)}</small>` : ""}</td><td>${esc(m.metodo)}</td><td>${esc(hora)}</td><td>${esc(m.categoria)}</td><td>${esc(nomeConta(m.conta))}${m.destino ? `<small>Para ${esc(nomeConta(m.destino))}</small>` : ""}</td><td><span class="status-lancamento ${!m.efetivado ? "pendente" : ""}">${status}</span></td><td class="acao-linha">${m.tipo === "saida" && !m.pago ? `<button class="btn-icone" data-pagar="${Number(m.id)}" type="button" title="Marcar como paga" aria-label="Marcar ${esc(m.descricao)} como paga"><i class="bi bi-check2-circle" aria-hidden="true"></i></button>` : ""}</td></tr>`;
            })
            .join("")}</tbody></table></div></section>`,
      )
      .join("");
  }
  function renderizarExtrato() {
    if (!dados) return;
    const busca = $("buscaExtrato").value.trim().toLocaleLowerCase("pt-BR");
    const { inicio, fim } = inicio90();
    const itens = movimentos().filter((m) => {
      const data = dia(m.data);
      return (
        (periodo === "mes"
          ? data.slice(0, 7) === dados.mes
          : data >= inicio && data <= fim) &&
        (!filtros.tipo || m.tipo === filtros.tipo) &&
        (!filtros.status || m.efetivado === (filtros.status === "efetivado")) &&
        (!filtros.conta ||
          Number(m.conta) === Number(filtros.conta) ||
          Number(m.destino) === Number(filtros.conta)) &&
        (!filtros.categoria || m.categoriaId === filtros.categoria) &&
        (!filtros.cartao || Number(m.cartao) === Number(filtros.cartao)) &&
        (!busca ||
          `${m.descricao} ${nomeConta(m.conta)} ${m.destino ? nomeConta(m.destino) : ""}`
            .toLocaleLowerCase("pt-BR")
            .includes(busca))
      );
    });
    $("listaExtrato").innerHTML = extratoHTML(itens);
    $("quantidadeLancamentos").textContent =
      `${itens.length} lançamento${itens.length === 1 ? "" : "s"}`;
    const entradas = itens
        .filter((m) => m.tipo === "entrada")
        .reduce((t, m) => t + m.valor, 0),
      saidas = itens
        .filter((m) => m.tipo === "saida")
        .reduce((t, m) => t + m.valor, 0);
    $("resumoExtrato").innerHTML =
      `<span>Entradas <strong class="positivo">${reais(entradas)}</strong></span><span>Saídas <strong class="negativo">${reais(saidas)}</strong></span><span>Resultado dos lançamentos <strong>${reais(entradas - saidas)}</strong></span>`;
    const n = Object.values(filtros).filter(Boolean).length;
    $("contadorFiltros").hidden = !n;
    $("contadorFiltros").textContent = `(${n})`;
    $("limparFiltros").hidden = !(n || busca);
  }
  function renderizarDetalhes() {
    const c = dados.contas.find(
      (c) => Number(c.cd_conta_usuario) === Number(contaId),
    );
    if (!c) {
      $("detalhesConta").innerHTML = vazio(
        "Conta não encontrada",
        "Escolha uma conta na lista de contas financeiras.",
      );
      for (const id of ["chavesPix", "cartoesConta", "extratoConta"])
        $(id).replaceChildren();
      return;
    }
    const d = detalhes(contaId);
    $("tituloPagina").textContent = c.nm_conta_usuario;
    $("detalhesConta").innerHTML =
      `<div class="conta-detalhe-topo">${marca(c.nm_conta_usuario, true)}<dl class="ficha-conta mb-0"><div><dt>Titular</dt><dd>${esc(d.nm_titular || "Não informado")}</dd></div><div><dt>Agência</dt><dd>${esc(d.ds_agencia || "Não informada")}</dd></div><div><dt>Conta</dt><dd>${esc(d.ds_numero_conta || "Não informada")}</dd></div><div><dt>Saldo cadastrado</dt><dd>${reais(centavos(c.vl_saldo_conta_usuario))}</dd></div><button type="button" class="btn-icone editar-conta" data-editar-conta="${Number(contaId)}" aria-label="Editar conta" title="Editar conta"><i class="bi bi-pencil" aria-hidden="true"></i></button></dl></div>`;
    const pix = dados.chaves_pix.filter(
      (p) => Number(p.id_conta_usuario) === Number(contaId),
    );
    const tipoPix = {
      email: "Email",
      telefone: "Telefone",
      cpf: "CPF",
      cnpj: "CNPJ",
      aleatoria: "Aleatória",
    };
    $("chavesPix").innerHTML = pix.length
      ? pix
          .map(
            (p) =>
              `<div class="chave-pix"><i class="bi bi-qr-code" aria-hidden="true"></i><div class="chave-texto"><small>${esc(tipoPix[p.tp_chave])}</small>${esc(p.ds_chave)}</div><button type="button" class="btn-icone" data-copiar-pix="${Number(p.cd_chave_pix)}" title="Copiar chave" aria-label="Copiar chave ${esc(tipoPix[p.tp_chave])}"><i class="bi bi-copy" aria-hidden="true"></i></button></div>`,
          )
          .join("")
      : `<p class="aviso-inline">${dados.extensao_disponivel ? "Nenhuma chave Pix cadastrada nesta conta." : "O cadastro de chaves Pix ainda precisa ser habilitado."}</p>`;
    const cartoes = dados.cartoes.filter(
      (p) => Number(p.id_conta_usuario) === Number(contaId),
    );
    const tipos = {
      debito: "Débito",
      credito: "Crédito",
      multiplo: "Múltiplo",
    };
    $("cartoesConta").innerHTML = cartoes.length
      ? cartoes
          .map(
            (p) =>
              `<article class="cartao-bancario"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i><div><h3>${esc(p.nm_cartao)}</h3><p>${tipos[p.tp_cartao]} · final ${esc(p.nr_final)}</p>${p.dt_validade ? `<p>Validade: ${esc(p.dt_validade.slice(0, 7).split("-").reverse().join("/"))}</p>` : ""}${p.nr_dia_vencimento ? `<p>Vencimento: dia ${Number(p.nr_dia_vencimento)}</p>` : ""}<button type="button" class="botao-texto" data-gastos-cartao="${Number(p.cd_cartao)}">Gastos neste cartão</button></div></article>`,
          )
          .join("")
      : `<p class="aviso-inline">${dados.extensao_disponivel ? "Nenhum cartão vinculado a esta conta." : "O cadastro de cartões ainda precisa ser habilitado."}</p>`;
    const { inicio, fim } = inicio90();
    $("extratoConta").innerHTML = extratoHTML(
      movimentos().filter(
        (m) =>
          (Number(m.conta) === Number(contaId) ||
            Number(m.destino) === Number(contaId)) &&
          dia(m.data) >= inicio &&
          dia(m.data) <= fim,
      ),
    );
  }
  function renderizarEstatisticas() {
    if (!dados) return;
    const fixas = fixasDoMes(),
      total = soma(fixas, "vl_saida_fixa"),
      cores = [
        "#8180f6",
        "#f25ca1",
        "#ab58bf",
        "#cba2e9",
        "#8bcda9",
        "#e4ce75",
      ];
    $("totalFixos").textContent = reais(total);
    $("quantidadeFixos").textContent =
      `${fixas.length} gasto${fixas.length === 1 ? "" : "s"} fixo${fixas.length === 1 ? "" : "s"} em ${mesNome(dados.mes)}`;
    const grupos = new Map();
    for (const f of fixas)
      grupos.set(
        f.id_categoria_saida,
        (grupos.get(f.id_categoria_saida) || 0) + centavos(f.vl_saida_fixa),
      );
    const categorias = [...grupos].sort((a, b) => b[1] - a[1]);
    let acumulado = 0;
    const fatias = categorias.map(([, valor], i) => {
      const inicio = acumulado;
      acumulado += (valor / total) * 100;
      return `${cores[i % cores.length]} ${inicio}% ${acumulado}%`;
    });
    $("graficoCategorias").style.background = total
      ? `conic-gradient(${fatias.join(",")})`
      : "#eef0ec";
    $("categoriasTotal").textContent = reais(total);
    $("graficoCategorias").setAttribute(
      "aria-label",
      categorias.length
        ? categorias
            .map(([id, v]) => `${categoria("saida", id)}: ${reais(v)}`)
            .join(". ")
        : "Nenhum gasto fixo no mês",
    );
    $("legendaCategorias").innerHTML = categorias.length
      ? categorias
          .map(
            ([id, v], i) =>
              `<div class="legenda-item"><i style="background:${cores[i % cores.length]}" aria-hidden="true"></i><span>${esc(categoria("saida", id) || "Sem categoria")}</span><strong>${Math.round((v / total) * 100)}% · ${reais(v)}</strong></div>`,
          )
          .join("")
      : '<p class="texto-suave text-center">Cadastre uma saída com frequência fixa para acompanhar suas categorias.</p>';
    $("listaFixos").innerHTML = fixas
      .map(
        (f) =>
          `<div class="fixo-item"><span>${esc(f.nm_saida_fixa)} · dia ${Number(f.dt_dia_saida_fixa)}</span><strong>${reais(centavos(f.vl_saida_fixa))}</strong></div>`,
      )
      .join("");
    const meses = Number($("periodoEstatisticas").value),
      base = new Date(`${dados.mes}-01T12:00:00`),
      serie = [];
    for (let i = meses - 1; i >= 0; i--) {
      const d = new Date(base);
      d.setMonth(d.getMonth() - i);
      const mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
      serie.push({
        mes,
        nome: d
          .toLocaleDateString("pt-BR", { month: "short" })
          .replace(".", ""),
        valor: soma(
          dados.historico_saidas.filter(
            (s) =>
              dia(s.dt_saida).slice(0, 7) === mes &&
              sim(s.ic_pago) &&
              dia(s.dt_pagamento || s.dt_saida) <= dados.hoje,
          ),
          "vl_saida",
        ),
      });
    }
    const max = Math.max(...serie.map((s) => s.valor), 1),
      historicoTotal = serie.reduce((t, s) => t + s.valor, 0);
    $("tituloHistorico").textContent = `Gastos nos últimos ${meses} meses`;
    $("totalHistorico").textContent = reais(historicoTotal);
    $("graficoHistorico").innerHTML = serie
      .map(
        (s) =>
          `<div class="historico-coluna"><strong>${reais(s.valor)}</strong><div class="historico-barra" style="height:${(s.valor / max) * 80}%"></div><span>${esc(s.nome)}</span></div>`,
      )
      .join("");
    $("graficoHistorico").setAttribute(
      "aria-label",
      serie.map((s) => `${mesNome(s.mes)}: ${reais(s.valor)}`).join(". "),
    );
    $("notaHistorico").textContent = historicoTotal
      ? `${mesNome(serie[0].mes)} a ${mesNome(serie.at(-1).mes)}`
      : "Nenhuma despesa paga neste período.";
  }
  function opcoes(id, itens, chave, texto, vazio = "Selecione") {
    const s = $(id),
      anterior = s.value;
    s.replaceChildren(new Option(vazio, ""));
    for (const i of itens) s.add(new Option(i[texto], i[chave]));
    if ([...s.options].some((o) => o.value === anterior)) s.value = anterior;
  }
  function preencherFormularios() {
    const contas = dados.contas.filter((c) => sim(c.ic_ativa));
    for (const id of [
      "contaMovimento",
      "origemMovimento",
      "destinoMovimento",
      "filtroConta",
    ])
      opcoes(
        id,
        contas,
        "cd_conta_usuario",
        "nm_conta_usuario",
        id === "filtroConta"
          ? "Todas as contas"
          : contas.length
            ? "Selecione"
            : "Cadastre uma conta",
      );
    opcoes(
      "categoriaMeta",
      dados.categorias_saida,
      "cd_categoria_saida",
      "ds_categoria_saida",
    );
    opcoes(
      "formaMovimento",
      dados.formas_pagamento,
      "cd_forma_pagamento",
      "nm_forma_pagamento",
    );
    opcoes("tipoConta", dados.tipos_conta, "cd_tipo_conta", "nm_tipo_conta");
    opcoes(
      "filtroCartao",
      dados.cartoes,
      "cd_cartao",
      "nm_cartao",
      "Todos os cartões",
    );
    opcoes(
      "filtroCategoria",
      [
        ...dados.categorias_entrada.map((c) => ({
          id: `entrada:${c.cd_categoria_entrada}`,
          nome: `Entrada · ${c.nm_categoria_entrada}`,
        })),
        ...dados.categorias_saida.map((c) => ({
          id: `saida:${c.cd_categoria_saida}`,
          nome: `Saída · ${c.ds_categoria_saida}`,
        })),
      ],
      "id",
      "nome",
      "Todas as categorias",
    );
    $("camposBancarios").hidden = !dados.extensao_disponivel;
    $("camposBancarios")
      .querySelectorAll("input")
      .forEach((c) => {
        c.disabled = !dados.extensao_disponivel;
      });
    $("avisoBancario").textContent = dados.extensao_disponivel
      ? "Informe o saldo atual desta conta."
      : "Agência, número e titular ficam disponíveis após habilitar os detalhes bancários.";
    for (const id of ["formPix", "formCartao"]) {
      $(id).querySelector(".aviso-extensao").hidden = dados.extensao_disponivel;
      $(id).querySelector('[type="submit"]').disabled =
        !dados.extensao_disponivel;
    }
    atualizarMovimento();
  }
  function campoVisivel(id, visivel) {
    $(id).hidden = !visivel;
    $(id)
      .querySelectorAll("input,select")
      .forEach((c) => {
        c.disabled = !visivel;
      });
  }
  function atualizarCartoes() {
    if (!dados) return;
    const cartoes = dados.cartoes.filter(
      (c) => Number(c.id_conta_usuario) === Number($("contaMovimento").value),
    );
    campoVisivel(
      "campoCartaoMovimento",
      $("tipoMovimento").value === "saida" &&
        dados.extensao_disponivel &&
        cartoes.length > 0,
    );
    opcoes("cartaoMovimento", cartoes, "cd_cartao", "nm_cartao", "Sem cartão");
  }
  function atualizarMovimento() {
    const tipo = $("tipoMovimento").value,
      saida = tipo === "saida",
      transfer = tipo === "transferencia";
    const freq = $("frequenciaMovimento");
    [...freq.options].forEach((o) => {
      o.disabled =
        o.value !== "unica" &&
        (!saida || (o.value === "fixa" && !dados?.extensao_disponivel));
    });
    if (!saida || (freq.value === "fixa" && !dados?.extensao_disponivel))
      freq.value = "unica";
    freq.disabled = !saida;
    campoVisivel("campoConta", !transfer);
    campoVisivel("campoCategoria", !transfer);
    campoVisivel("camposTransferencia", transfer);
    campoVisivel("camposSaida", saida);
    campoVisivel("campoParcelas", saida && freq.value === "parcelada");
    $("descricaoMovimento").required = !transfer;
    $("descricaoMovimento").placeholder = transfer
      ? "Observação (opcional)"
      : saida
        ? "Ex.: Conta de internet"
        : "Ex.: Salário do mês";
    $("descricaoMovimento").maxLength = tipo === "entrada" ? 45 : 60;
    if (dados)
      opcoes(
        "categoriaMovimento",
        saida ? dados.categorias_saida : dados.categorias_entrada,
        saida ? "cd_categoria_saida" : "cd_categoria_entrada",
        saida ? "ds_categoria_saida" : "nm_categoria_entrada",
      );
    $("ajudaMovimento").textContent = transfer
      ? "Registra uma movimentação entre suas contas."
      : !saida
        ? "Receitas com data futura entram no saldo projetado."
        : freq.value === "parcelada"
          ? "Informe o valor total da compra. Ele será dividido entre as parcelas mensais; somente a primeira pode ser marcada como paga agora."
          : freq.value === "fixa"
            ? "Este valor se repete todo mês, a partir da data informada. Os próximos meses entram no planejamento."
            : $("pagoMovimento").checked
              ? "O pagamento será registrado na data do lançamento."
              : "A despesa ficará em aberto até você marcar o pagamento.";
    atualizarCartoes();
  }
  function abrirMovimento(tipo) {
    $("formMovimento").reset();
    $("tipoMovimento").value = tipo;
    $("dataMovimento").value = dados?.hoje || dia(new Date().toISOString());
    $("vencimentoMovimento").value = $("dataMovimento").value;
    atualizarMovimento();
    bootstrap.Modal.getOrCreateInstance($("modalMovimento")).show();
  }
  function abrirConta(id = null) {
    const f = $("formConta");
    f.reset();
    $("idConta").value = id || "";
    $("tituloNovaConta").textContent = id ? "Editar conta" : "Adicionar conta";
    if (id && dados) {
      const c = dados.contas.find(
          (c) => Number(c.cd_conta_usuario) === Number(id),
        ),
        d = detalhes(id);
      if (!c) return;
      $("nomeConta").value = c.nm_conta_usuario;
      $("tipoConta").value = c.id_tipo_conta;
      $("saldoConta").value = Number(c.vl_saldo_conta_usuario)
        .toFixed(2)
        .replace(".", ",");
      $("titularConta").value = d.nm_titular || "";
      $("agenciaConta").value = d.ds_agencia || "";
      $("numeroConta").value = d.ds_numero_conta || "";
    }
    bootstrap.Modal.getOrCreateInstance($("modalContas")).show();
  }
  function limparFiltros() {
    filtros = {};
    $("formFiltro").reset();
    $("buscaExtrato").value = "";
    renderizarExtrato();
  }
  function irExtrato(conta, cartao = "") {
    filtros = { conta: String(conta), cartao: String(cartao) };
    $("buscaExtrato").value = "";
    periodo = "90";
    atualizarPeriodo();
    renderizarExtrato();
    mostrarPagina("extrato");
  }
  function atualizarPeriodo() {
    todos("[data-periodo]").forEach((b) => {
      const ativo = b.dataset.periodo === periodo;
      b.classList.toggle("active", ativo);
      b.setAttribute("aria-pressed", String(ativo));
    });
  }
  function renderizar() {
    renderizarContasPagar();
    renderizarMetas();
    renderizarResumo();
    renderizarBancos();
    renderizarExtrato();
    renderizarEstatisticas();
    preencherFormularios();
    if (pagina === "conta") renderizarDetalhes();
  }

  document.addEventListener("click", async (e) => {
    const rota = e.target.closest("[data-rota]"),
      conta = e.target.closest("[data-conta]");
    if (
      (rota || conta) &&
      !e.ctrlKey &&
      !e.metaKey &&
      !e.shiftKey &&
      e.button === 0
    ) {
      e.preventDefault();
      mostrarPagina(conta ? "conta" : rota.dataset.rota, conta?.dataset.conta);
    }
    const novo = e.target.closest("[data-novo]");
    if (novo) abrirMovimento(novo.dataset.novo);
    if (e.target.closest("[data-adicionar-conta]")) abrirConta();
    const editar = e.target.closest("[data-editar-conta]");
    if (editar) abrirConta(editar.dataset.editarConta);
    if (
      e.target.closest("[data-adicionar-pix]") &&
      dados?.contas.some((c) => Number(c.cd_conta_usuario) === Number(contaId))
    ) {
      $("formPix").reset();
      $("contaPix").value = contaId;
      bootstrap.Modal.getOrCreateInstance($("modalPix")).show();
    }
    if (
      e.target.closest("[data-adicionar-cartao]") &&
      dados?.contas.some((c) => Number(c.cd_conta_usuario) === Number(contaId))
    ) {
      $("formCartao").reset();
      $("contaCartao").value = contaId;
      campoVisivel("campoDiaCartao", false);
      bootstrap.Modal.getOrCreateInstance($("modalCartao")).show();
    }
    const gastos = e.target.closest("[data-gastos-cartao]");
    if (gastos) irExtrato(contaId, gastos.dataset.gastosCartao);
    if (e.target.closest("[data-extrato-conta]") && contaId) irExtrato(contaId);
    const copiar = e.target.closest("[data-copiar-pix]");
    if (copiar && dados) {
      const chave = dados.chaves_pix.find(
        (p) => Number(p.cd_chave_pix) === Number(copiar.dataset.copiarPix),
      );
      try {
        await navigator.clipboard.writeText(chave.ds_chave);
        sucesso("Chave Pix copiada.");
      } catch {
        aviso(
          "Não foi possível copiar automaticamente. Selecione a chave para copiar.",
        );
      }
    }
    const pagar = e.target.closest("[data-pagar]");
    if (!pagar || salvando || !dados) return;
    salvando = true;
    pagar.disabled = true;
    const fixa = pagar.dataset.pagar.startsWith("fixa-");
    try {
      await api({
        method: "POST",
        body: JSON.stringify({
          acao: fixa ? "pagar_fixa" : "pagar",
          id: fixa ? pagar.dataset.pagar.slice(5) : pagar.dataset.pagar,
          mes: dados.mes,
        }),
      });
      sucesso("Pagamento registrado.");
      await carregar();
    } catch (erro) {
      aviso(erro.message);
    } finally {
      salvando = false;
      pagar.disabled = false;
    }
  });
  for (const id of [
    "formMovimento",
    "formMeta",
    "formConta",
    "formPix",
    "formCartao",
  ])
    $(id).addEventListener("submit", async (e) => {
      e.preventDefault();
      if (salvando) return;
      const form = e.currentTarget,
        erro = form.querySelector(".erro-formulario");
      erro.classList.add("d-none");
      if (!dados) {
        erro.textContent = "Carregue os dados antes de continuar.";
        erro.classList.remove("d-none");
        return;
      }
      const corpo = Object.fromEntries(new FormData(form));
      if (corpo.acao === "saida") corpo.pago = $("pagoMovimento").checked;
      if (
        corpo.acao === "transferencia" &&
        corpo.id_conta_origem === corpo.id_conta_destino
      ) {
        erro.textContent = "Escolha contas diferentes para origem e destino.";
        erro.classList.remove("d-none");
        return;
      }
      const botao = form.querySelector('[type="submit"]'),
        modal = form.closest(".modal");
      salvando = true;
      botao.disabled = true;
      botao.textContent = "Salvando…";
      modal.dataset.enviando = "true";
      modal.querySelectorAll("[data-bs-dismiss]").forEach((b) => {
        b.disabled = true;
      });
      try {
        await api({ method: "POST", body: JSON.stringify(corpo) });
        if (corpo.mes || corpo.data)
          $("mesReferencia").value = corpo.mes || corpo.data.slice(0, 7);
        form.reset();
        delete modal.dataset.enviando;
        bootstrap.Modal.getInstance(modal)?.hide();
        sucesso("Registro salvo com sucesso.");
        await carregar();
      } catch (e) {
        erro.textContent = e.message;
        erro.classList.remove("d-none");
      } finally {
        salvando = false;
        delete modal.dataset.enviando;
        botao.disabled =
          !dados ||
          (id === "formMeta" && !dados.metas_disponiveis) ||
          (["formPix", "formCartao"].includes(id) &&
            !dados.extensao_disponivel);
        botao.textContent = "Confirmar";
        modal.querySelectorAll("[data-bs-dismiss]").forEach((b) => {
          b.disabled = false;
        });
      }
    });
  todos(".modal").forEach((m) => {
    m.addEventListener("show.bs.modal", () => {
      m.querySelector(".erro-formulario")?.classList.add("d-none");
      if (m.id === "modalMeta") $("mesMeta").value = $("mesReferencia").value;
      if (m.id === "modalFiltro") {
        $("formFiltro").reset();
        for (const [k, v] of Object.entries(filtros))
          if ($("formFiltro").elements[k])
            $("formFiltro").elements[k].value = v;
      }
    });
    m.addEventListener("shown.bs.modal", () =>
      m
        .querySelector(
          'input:not([type="hidden"]):not(:disabled),select:not(:disabled)',
        )
        ?.focus(),
    );
    m.addEventListener("hide.bs.modal", (e) => {
      if (m.dataset.enviando === "true") e.preventDefault();
    });
  });
  $("cadastrarContaMovimento").addEventListener("click", () => {
    voltarMovimento = true;
    $("modalMovimento").addEventListener(
      "hidden.bs.modal",
      () => abrirConta(),
      { once: true },
    );
    bootstrap.Modal.getInstance($("modalMovimento")).hide();
  });
  $("modalContas").addEventListener("hidden.bs.modal", () => {
    if (voltarMovimento) {
      voltarMovimento = false;
      bootstrap.Modal.getOrCreateInstance($("modalMovimento")).show();
    }
  });
  $("formFiltro").addEventListener("submit", (e) => {
    e.preventDefault();
    filtros = Object.fromEntries(new FormData(e.currentTarget));
    renderizarExtrato();
    bootstrap.Modal.getInstance($("modalFiltro"))?.hide();
  });
  $("limparFiltros").addEventListener("click", limparFiltros);
  $("buscaExtrato").addEventListener("input", renderizarExtrato);
  todos("[data-periodo]").forEach((b) =>
    b.addEventListener("click", () => {
      periodo = b.dataset.periodo;
      atualizarPeriodo();
      renderizarExtrato();
    }),
  );
  for (const id of ["tipoMovimento", "frequenciaMovimento", "pagoMovimento"])
    $(id).addEventListener("change", atualizarMovimento);
  $("contaMovimento").addEventListener("change", atualizarCartoes);
  $("tipoCartao").addEventListener("change", () =>
    campoVisivel("campoDiaCartao", $("tipoCartao").value !== "debito"),
  );
  $("periodoEstatisticas").addEventListener("change", renderizarEstatisticas);
  $("mesReferencia").addEventListener("change", carregar);
  $("tentarNovamente").addEventListener("click", carregar);
  $("alternarProjecao").addEventListener("click", () => {
    projetado = !projetado;
    renderizarResumo();
  });
  window.addEventListener("popstate", () => {
    const p = new URLSearchParams(location.search);
    mostrarPagina(p.get("rota"), p.get("id"), false);
  });
  mostrarPagina(pagina, contaId, false);
  carregar();
})();
