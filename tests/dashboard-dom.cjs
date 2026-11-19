// Exige jsdom instalado na pasta de ferramentas de QA (ver README).
// Verifica o DOM em memória; não controla um navegador e não acessa o Supabase.
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const { JSDOM, VirtualConsole } = require(
  process.env.FINANCAS_JSDOM ||
    path.join(
      require("node:os").tmpdir(),
      "financas-qa-tools/node_modules/jsdom",
    ),
);
const raiz = path.resolve(__dirname, "..");
const origem = "http://127.0.0.1:8095";
const pausa = () => new Promise((r) => setTimeout(r, 25));
(async () => {
  const html = await (
    await fetch(`${origem}/index.php?rota=visao-geral&fixture=normal`)
  ).text();
  const fixture = await (
    await fetch(
      `${origem}/index.php?rota=api/dashboard&mes=2026-11&fixture=normal`,
    )
  ).json();
  const bootstrap = await (
    await fetch(
      "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js",
    )
  ).text();
  const erros = [],
    consoleVirtual = new VirtualConsole();
  consoleVirtual.on("jsdomError", (e) => erros.push(e));
  const dom = new JSDOM(html, {
    url: `${origem}/index.php?rota=visao-geral`,
    runScripts: "outside-only",
    pretendToBeVisual: true,
    virtualConsole: consoleVirtual,
  });
  const w = dom.window,
    d = w.document,
    $ = (id) => d.getElementById(id);
  w.scrollTo = () => {};
  let dataset = structuredClone(fixture),
    falha = false,
    postagens = [],
    falharPost = true;
  w.fetch = async (_, opcoes) => {
    if (opcoes.method === "POST") {
      postagens.push(JSON.parse(opcoes.body));
      await pausa();
      return {
        ok: !falharPost,
        status: falharPost ? 422 : 201,
        json: async () =>
          falharPost
            ? { erro: "Erro de teste: confira os campos." }
            : { mensagem: "Salvo" },
      };
    }
    return {
      ok: !falha,
      status: falha ? 503 : 200,
      json: async () =>
        falha ? { erro: "Falha de teste" } : structuredClone(dataset),
    };
  };
  w.eval(bootstrap);
  w.eval(
    fs.readFileSync(path.join(raiz, "public/assets/js/dashboard.js"), "utf8"),
  );
  await pausa();
  let total = 0;
  const conferir = (v, m) => {
    assert.ok(v, m);
    total++;
  };
  const mudar = (id, valor) => {
    $(id).value = valor;
    $(id).dispatchEvent(new w.Event("change", { bubbles: true }));
  };
  const submit = (id) =>
    $(id).dispatchEvent(
      new w.Event("submit", { bubbles: true, cancelable: true }),
    );
  const clicar = (s) => {
    const e = d.querySelector(s);
    assert.ok(e, `Elemento: ${s}`);
    e.click();
  };
  const digitos = (id) => $(id).textContent.replace(/\D/g, "");
  conferir(digitos("saldoMes") === "293500", "Saldo realizado em centavos");
  conferir(
    digitos("totalContas") === "38970",
    "Pendências incluem recorrências previstas",
  );
  conferir(
    digitos("patrimonioTotal") === "326070",
    "Saldos cadastrados das contas",
  );
  conferir(digitos("totalFixos") === "38970", "Gastos fixos do mês");
  conferir(
    digitos("totalHistorico") === "268640",
    "Histórico soma despesas pagas",
  );
  conferir(
    $("quantidadeLancamentos").textContent.startsWith("6 "),
    "Extrato de 90 dias respeita a data limite",
  );
  clicar("#alternarProjecao");
  conferir(
    digitos("saldoMes") === "254530",
    "Projeção soma recorrências uma única vez",
  );
  clicar('[data-rota="extrato"]');
  conferir(
    !$("listaExtrato").closest("section[data-pagina]").hidden,
    "Navegação para extrato",
  );
  conferir(
    w.location.search.includes("rota=extrato"),
    "Rota acompanha a navegação",
  );
  clicar('[data-periodo="mes"]');
  conferir(
    $("quantidadeLancamentos").textContent.startsWith("5 "),
    "Mês inclui lançamento futuro",
  );
  mudar("filtroStatus", "pendente");
  submit("formFiltro");
  conferir(
    $("quantidadeLancamentos").textContent.startsWith("1 "),
    "Filtro de pendências",
  );
  conferir(
    $("listaExtrato").textContent.includes("Internet"),
    "Filtro mantém a despesa correta",
  );
  clicar("#limparFiltros");
  $("buscaExtrato").value = "consulta";
  $("buscaExtrato").dispatchEvent(new w.Event("input"));
  conferir(
    $("quantidadeLancamentos").textContent.startsWith("1 "),
    "Busca por descrição",
  );
  clicar("#limparFiltros");
  clicar('[data-rota="contas"]');
  clicar('[data-conta="1"]');
  conferir(
    $("tituloPagina").textContent === "Itaú principal",
    "Detalhes da conta escolhida",
  );
  conferir(
    $("chavesPix").textContent.includes("exemplo@example.com"),
    "Chave da conta",
  );
  conferir($("cartoesConta").textContent.includes("1234"), "Cartão da conta");
  clicar('[data-gastos-cartao="1"]');
  conferir(
    $("quantidadeLancamentos").textContent.startsWith("1 "),
    "Gastos por cartão",
  );
  conferir(
    $("listaExtrato").textContent.includes("Consulta"),
    "Despesa ligada ao cartão",
  );
  clicar('[data-novo="saida"]');
  await pausa();
  conferir(!$("camposSaida").hidden, "Campos de despesa");
  mudar("frequenciaMovimento", "parcelada");
  conferir(!$("campoParcelas").hidden, "Número de parcelas visível");
  mudar("tipoMovimento", "transferencia");
  conferir(
    !$("camposTransferencia").hidden && $("campoCategoria").hidden,
    "Campos de transferência",
  );
  conferir(
    $("categoriaMovimento").disabled,
    "Categoria oculta não bloqueia transferência",
  );
  mudar("origemMovimento", "1");
  mudar("destinoMovimento", "1");
  submit("formMovimento");
  conferir(
    postagens.length === 0 &&
      $("formMovimento").textContent.includes("contas diferentes"),
    "Impede transferência para a mesma conta",
  );
  mudar("tipoMovimento", "saida");
  mudar("contaMovimento", "1");
  mudar("categoriaMovimento", "1");
  mudar("formaMovimento", "1");
  $("descricaoMovimento").value = "Descrição preservada";
  $("valorMovimento").value = "100,00";
  submit("formMovimento");
  submit("formMovimento");
  await pausa();
  await pausa();
  conferir(postagens.length === 1, "Evita dois envios simultâneos");
  conferir(
    $("descricaoMovimento").value === "Descrição preservada",
    "Falha preserva os dados do formulário",
  );
  conferir(
    !$("formMovimento")
      .querySelector(".erro-formulario")
      .classList.contains("d-none"),
    "Erro aparece no formulário",
  );
  conferir(postagens[0].pago === false, "Status enviado como booleano");
  falharPost = false;
  submit("formMovimento");
  await pausa();
  await pausa();
  conferir(
    postagens.length === 2 && $("descricaoMovimento").value === "",
    "Sucesso limpa o formulário e permite novo envio",
  );
  dataset.historico_saidas[0].ds_motivo_saida = "<img src=x onerror=alert(1)>";
  clicar("#tentarNovamente");
  await pausa();
  conferir(
    !$("listaExtrato").querySelector("img"),
    "Descrição não injeta HTML",
  );
  falha = true;
  clicar("#tentarNovamente");
  await pausa();
  conferir($("saldoMes").textContent === "—", "Falha não exibe saldo antigo");
  conferir(
    !$("aviso").classList.contains("d-none"),
    "Falha de carregamento visível",
  );
  falha = false;
  dataset = structuredClone(fixture);
  dataset.extensao_disponivel = false;
  dataset.detalhes_contas = [];
  dataset.chaves_pix = [];
  dataset.cartoes = [];
  clicar("#tentarNovamente");
  await pausa();
  conferir($("camposBancarios").hidden, "Banco original usa cadastro básico");
  conferir(
    $("formPix").querySelector('[type="submit"]').disabled,
    "Pix não salva sem extensão",
  );
  conferir(
    $("formCartao").querySelector('[type="submit"]').disabled,
    "Cartão não salva sem extensão",
  );
  conferir(
    [...$("frequenciaMovimento").options].find((o) => o.value === "fixa")
      .disabled,
    "Recorrência não salva sem função SQL",
  );
  for (const k of [
    "contas",
    "entradas",
    "saidas",
    "pendentes",
    "fixas",
    "metas",
    "transferencias",
    "historico_entradas",
    "historico_saidas",
  ])
    dataset[k] = [];
  clicar("#tentarNovamente");
  await pausa();
  conferir(
    $("listaContas").textContent.includes("Nenhuma conta em aberto"),
    "Estado sem pendências",
  );
  conferir(
    $("contasVazias").textContent.includes("Você ainda não tem contas"),
    "Estado sem contas",
  );
  conferir(digitos("totalHistorico") === "000", "Gráfico sem dados exibe zero");
  conferir(
    d.querySelectorAll("[id]").length ===
      new Set([...d.querySelectorAll("[id]")].map((e) => e.id)).size,
    "Identificadores HTML únicos",
  );
  conferir(erros.length === 0, erros.map((e) => e.message).join("\n"));
  console.log(
    `${total} verificações de comportamento aprovadas. Sem acesso ao banco real.`,
  );
  w.close();
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
