# Finanças

Aplicativo PHP com Supabase e interface em português. A fonte Inter é servida localmente, incluindo sua licença em `public/assets/fonts/`.

## Abrir no XAMPP

Com o Apache ativo, acesse `http://localhost/estudos/sistema_financeiro/public/`. A configuração existente de autenticação e as variáveis do `.env` continuam sendo utilizadas.

Depois do login, a página inicial é `index.php?rota=visao-geral`.

| Rota | Conteúdo |
| --- | --- |
| `visao-geral` | Contas a pagar, metas e comparação entre receitas e despesas |
| `extrato` | Últimos 90 dias ou mês selecionado; busca e filtros por tipo, status, conta, categoria e cartão |
| `contas` | Contas ativas e patrimônio total |
| `conta&id=ID` | Titular, agência, número, Pix, cartões e extrato da conta |
| `dashboard` | Gastos fixos por categoria e despesas pagas nos últimos 3 ou 6 meses |

O mês de referência define os resumos e o fim do período consultado. Nos 90 dias do mês atual, a data final é hoje; em outros meses, é o último dia daquele mês. Entradas e saídas com data futura aparecem no modo projetado e na visualização do mês selecionado.

## Banco complementar (opcional, não executado automaticamente)

O arquivo `database/001_detalhes_contas.sql` complementa o esquema enviado com:

- `tb_detalhes_contas`: titular, agência e número da conta;
- `tb_chaves_pix`: chaves que já pertencem à conta;
- `tb_cartoes`: identificação do cartão pelos quatro últimos dígitos, tipo, validade e dia de vencimento;
- vínculo opcional de despesas com cartões;
- funções transacionais para salvar uma conta com seus detalhes e cadastrar/pagar uma despesa fixa.

Revise o arquivo e aplique-o manualmente no SQL Editor do seu projeto Supabase. Ele é independente do esquema de referência anexado e não contém dados de demonstração. As novas tabelas têm RLS e vínculos que exigem o mesmo proprietário da conta. As funções usam as permissões do usuário conectado (`SECURITY INVOKER`). As políticas e permissões das tabelas originais precisam continuar configuradas para os próprios registros de cada usuário.

Antes da aplicação, as telas usam os dados existentes. Campos bancários adicionais e confirmações de Pix, cartões e novas recorrências ficam indisponíveis. Erros de permissão ou conexão são exibidos como falha de carregamento, sem apresentar dados vazios como se fossem reais.

Receitas e despesas únicas, metas, contas básicas, transferências registradas e parcelamentos usam o esquema original. Parcelas são inseridas em um único lote, com divisão exata dos centavos. Só a primeira parcela pode ser registrada como paga no cadastro. A recorrência fixa grava a primeira ocorrência e projeta as seguintes; o pagamento de uma projeção cria a ocorrência do mês, sob uma trava para evitar repetição simultânea.

Os saldos das contas e o patrimônio exibem `vl_saldo_conta_usuario`, editável no formulário da conta. O resumo mensal é calculado a partir dos lançamentos. O SQL complementar não instala gatilhos para alterar saldos: a atualização automática depende das regras de saldo já existentes no banco. O cadastro Pix/cartão e as transferências aqui são registros de controle financeiro, sem integração com operações bancárias.

## Verificações

```powershell
php tests/financeiro.php
node --check public/assets/js/dashboard.js
```

O teste PHP usa um serviço falso e não se conecta ao Supabase. Ele cobre validações, parcelamentos, datas, propriedade dos registros, paginação e tabelas opcionais.

Para inspecionar dados fictícios localmente, sem autenticar ou escrever no banco:

```powershell
php -S 127.0.0.1:8095 -t public tests/preview.php
```

Abra `http://127.0.0.1:8095/index.php?rota=visao-geral`. As opções `&fixture=vazio`, `&fixture=sem-extensao`, `&fixture=erro` e `&fixture=normal` permitem conferir estados da interface. Essa prévia só funciona no servidor de desenvolvimento PHP; não é exposta pelo roteador público da aplicação. Ela rejeita gravações.

O teste de DOM em memória utiliza a prévia e `jsdom`. Para instalar apenas as ferramentas temporárias de teste no Windows:

```powershell
npm install --prefix "$env:TEMP/financas-qa-tools" --no-audit --no-fund jsdom
node tests/dashboard-dom.cjs
```

Ele verifica navegação, totais, filtros, vínculos com cartões, formulários, proteção contra envio duplicado, escape de HTML e estados sem dados ou com erro. Não substitui a conferência visual no navegador.
