-- Complemento OPCIONAL do esquema fornecido. Revisar e aplicar manualmente no Supabase.
-- Não altera saldos ou políticas das tabelas financeiras já existentes.
begin;

create unique index if not exists uq_contas_proprietario
    on public.tb_contas_usuarios (cd_conta_usuario, id_usuario);

create table if not exists public.tb_detalhes_contas (
    id_conta_usuario integer primary key,
    id_usuario integer not null references public.tb_usuarios(cd_usuario),
    nm_titular varchar(120),
    ds_agencia varchar(15),
    ds_numero_conta varchar(30),
    constraint detalhes_conta_proprietario_fk foreign key (id_conta_usuario, id_usuario)
        references public.tb_contas_usuarios(cd_conta_usuario, id_usuario) on delete cascade
);

create table if not exists public.tb_chaves_pix (
    cd_chave_pix bigint generated always as identity primary key,
    id_conta_usuario integer not null,
    id_usuario integer not null references public.tb_usuarios(cd_usuario),
    tp_chave varchar(12) not null check (tp_chave in ('email','telefone','cpf','cnpj','aleatoria')),
    ds_chave varchar(180) not null check (length(trim(ds_chave)) > 0),
    constraint pix_conta_proprietario_fk foreign key (id_conta_usuario, id_usuario)
        references public.tb_contas_usuarios(cd_conta_usuario, id_usuario) on delete cascade,
    constraint uq_chave_pix_usuario unique (id_usuario, ds_chave)
);

create table if not exists public.tb_cartoes (
    cd_cartao bigint generated always as identity primary key,
    id_conta_usuario integer not null,
    id_usuario integer not null references public.tb_usuarios(cd_usuario),
    nm_cartao varchar(60) not null check (length(trim(nm_cartao)) > 0),
    tp_cartao varchar(12) not null check (tp_cartao in ('debito','credito','multiplo')),
    nr_final varchar(4) not null check (nr_final ~ '^[0-9]{4}$'),
    dt_validade date check (extract(day from dt_validade) = 1),
    nr_dia_vencimento integer check (nr_dia_vencimento between 1 and 31),
    constraint cartao_conta_proprietario_fk foreign key (id_conta_usuario, id_usuario)
        references public.tb_contas_usuarios(cd_conta_usuario, id_usuario) on delete cascade,
    constraint uq_cartao_conta_usuario unique (cd_cartao, id_conta_usuario, id_usuario)
);

alter table public.tb_saidas add column if not exists id_cartao bigint;
alter table public.tb_saidas_fixas add column if not exists id_cartao bigint;
do $$ begin
    if not exists (select 1 from pg_constraint where conname = 'saidas_cartao_proprietario_fk' and conrelid = 'public.tb_saidas'::regclass) then
        alter table public.tb_saidas add constraint saidas_cartao_proprietario_fk
            foreign key (id_cartao, id_conta_usuario, id_usuario)
            references public.tb_cartoes(cd_cartao, id_conta_usuario, id_usuario);
    end if;
    if not exists (select 1 from pg_constraint where conname = 'fixas_cartao_proprietario_fk' and conrelid = 'public.tb_saidas_fixas'::regclass) then
        alter table public.tb_saidas_fixas add constraint fixas_cartao_proprietario_fk
            foreign key (id_cartao, id_conta_usuario, id_usuario)
            references public.tb_cartoes(cd_cartao, id_conta_usuario, id_usuario);
    end if;
end $$;

create index if not exists idx_pix_usuario on public.tb_chaves_pix(id_usuario);
create index if not exists idx_cartoes_usuario on public.tb_cartoes(id_usuario);
create index if not exists idx_detalhes_usuario on public.tb_detalhes_contas(id_usuario);

alter table public.tb_detalhes_contas enable row level security;
alter table public.tb_chaves_pix enable row level security;
alter table public.tb_cartoes enable row level security;

-- Políticas limitadas às tabelas novas. As tabelas originais devem manter suas RLS.
do $$ declare t text; begin
    foreach t in array array['tb_detalhes_contas','tb_chaves_pix','tb_cartoes'] loop
        execute format('drop policy if exists acesso_do_proprietario on public.%I', t);
        execute format(
            'create policy acesso_do_proprietario on public.%I for all to authenticated
             using (exists (select 1 from public.tb_usuarios u where u.cd_usuario = id_usuario and u.auth_user_id = (select auth.uid())))
             with check (exists (select 1 from public.tb_usuarios u where u.cd_usuario = id_usuario and u.auth_user_id = (select auth.uid())))', t);
        execute format('revoke all on public.%I from anon',t);
        execute format('grant select, insert, update, delete on public.%I to authenticated',t);
    end loop;
end $$;
grant usage, select on sequence public.tb_chaves_pix_cd_chave_pix_seq to authenticated;
grant usage, select on sequence public.tb_cartoes_cd_cartao_seq to authenticated;

-- SECURITY INVOKER preserva as políticas RLS e permissões do usuário conectado.
-- Conta e seus detalhes são gravados juntos, sem cadastros pela metade.
create or replace function public.fn_salvar_conta_financeira(p_id integer, p_conta jsonb, p_detalhes jsonb)
returns jsonb language plpgsql security invoker set search_path = '' as $$
declare v_usuario integer; v_id integer;
begin
    select cd_usuario into strict v_usuario from public.tb_usuarios where auth_user_id = auth.uid();
    if p_id is null then
        insert into public.tb_contas_usuarios(nm_conta_usuario, vl_saldo_conta_usuario, id_usuario, id_tipo_conta, ic_ativa)
        values (p_conta->>'nm_conta_usuario', (p_conta->>'vl_saldo_conta_usuario')::numeric, v_usuario, (p_conta->>'id_tipo_conta')::integer, true)
        returning cd_conta_usuario into v_id;
    else
        update public.tb_contas_usuarios set nm_conta_usuario = p_conta->>'nm_conta_usuario',
            vl_saldo_conta_usuario = (p_conta->>'vl_saldo_conta_usuario')::numeric,
            id_tipo_conta = (p_conta->>'id_tipo_conta')::integer
        where cd_conta_usuario = p_id and id_usuario = v_usuario and ic_ativa
        returning cd_conta_usuario into v_id;
        if v_id is null then raise exception 'Conta indisponível.' using errcode = '22023'; end if;
    end if;
    insert into public.tb_detalhes_contas(id_conta_usuario,id_usuario,nm_titular,ds_agencia,ds_numero_conta)
    values (v_id,v_usuario,p_detalhes->>'nm_titular',p_detalhes->>'ds_agencia',p_detalhes->>'ds_numero_conta')
    on conflict (id_conta_usuario) do update set nm_titular = excluded.nm_titular,
        ds_agencia = excluded.ds_agencia, ds_numero_conta = excluded.ds_numero_conta;
    return jsonb_build_object('id',v_id);
end $$;

-- Uma despesa fixa e sua primeira ocorrência são cadastradas na mesma transação.
create or replace function public.fn_criar_despesa_fixa(p_saida jsonb, p_inicio date)
returns jsonb language plpgsql security invoker set search_path = '' as $$
declare v_usuario integer; v_fixa integer; v_conta integer;
begin
    select cd_usuario into strict v_usuario from public.tb_usuarios where auth_user_id = auth.uid();
    v_conta := (p_saida->>'id_conta_usuario')::integer;
    if not exists (select 1 from public.tb_contas_usuarios where cd_conta_usuario = v_conta and id_usuario = v_usuario and ic_ativa) then
        raise exception 'Conta indisponível.' using errcode = '22023';
    end if;
    insert into public.tb_saidas_fixas(id_usuario,nm_saida_fixa,dt_dia_saida_fixa,id_forma_pagamento,id_conta_usuario,vl_saida_fixa,id_categoria_saida,dt_inicio,id_cartao)
    values (v_usuario,p_saida->>'ds_motivo_saida',extract(day from (p_saida->>'dt_vencimento')::date)::integer,
        (p_saida->>'id_forma_pagamento')::integer,v_conta,(p_saida->>'vl_saida')::numeric,
        (p_saida->>'id_categoria_saida')::integer,p_inicio,(p_saida->>'id_cartao')::bigint)
    returning cd_saida_fixa into v_fixa;
    insert into public.tb_saidas(ds_motivo_saida,vl_saida,dt_saida,id_usuario,id_categoria_saida,id_forma_pagamento,
        id_conta_usuario,dt_vencimento,dt_pagamento,ic_pago,id_saida_fixa,id_cartao)
    values (p_saida->>'ds_motivo_saida',(p_saida->>'vl_saida')::numeric,(p_saida->>'dt_saida')::timestamptz,v_usuario,
        (p_saida->>'id_categoria_saida')::integer,(p_saida->>'id_forma_pagamento')::integer,v_conta,
        (p_saida->>'dt_vencimento')::date,(p_saida->>'dt_pagamento')::timestamptz,(p_saida->>'ic_pago')::boolean,
        v_fixa,(p_saida->>'id_cartao')::bigint);
    return jsonb_build_object('id',v_fixa);
end $$;

-- A trava na recorrência evita duas ocorrências quando há cliques simultâneos.
create or replace function public.fn_pagar_despesa_fixa(p_id integer, p_mes date)
returns jsonb language plpgsql security invoker set search_path = '' as $$
declare v_usuario integer; v_fixa public.tb_saidas_fixas%rowtype; v_data date; v_id integer;
begin
    select cd_usuario into strict v_usuario from public.tb_usuarios where auth_user_id = auth.uid();
    if extract(day from p_mes) <> 1 or p_mes < date '2000-01-01' or p_mes > date '2100-12-01' then
        raise exception 'Mês inválido.' using errcode = '22023';
    end if;
    select * into strict v_fixa from public.tb_saidas_fixas
        where cd_saida_fixa = p_id and id_usuario = v_usuario and ic_ativa for update;
    if not exists (select 1 from public.tb_contas_usuarios where cd_conta_usuario = v_fixa.id_conta_usuario and id_usuario = v_usuario and ic_ativa) then
        raise exception 'Conta indisponível.' using errcode = '22023';
    end if;
    v_data := p_mes + (least(v_fixa.dt_dia_saida_fixa,extract(day from (p_mes + interval '1 month - 1 day'))::integer) - 1);
    if date_trunc('month', v_fixa.dt_inicio)::date > p_mes
        or (v_fixa.dt_fim is not null and date_trunc('month', v_fixa.dt_fim)::date < p_mes) then
        raise exception 'Despesa fora do período de recorrência.' using errcode = '22023';
    end if;
    if exists (select 1 from public.tb_saidas where id_saida_fixa = p_id and id_usuario = v_usuario
        and date_trunc('month',dt_saida at time zone 'America/Sao_Paulo')::date = p_mes) then
        raise exception 'Este mês já possui um lançamento para a despesa fixa. Atualize a página.' using errcode = '22023';
    end if;
    insert into public.tb_saidas(ds_motivo_saida,vl_saida,dt_saida,id_usuario,id_categoria_saida,id_forma_pagamento,
        id_conta_usuario,dt_vencimento,dt_pagamento,ic_pago,id_saida_fixa,id_cartao)
    values (v_fixa.nm_saida_fixa,v_fixa.vl_saida_fixa,(v_data + time '12:00') at time zone 'America/Sao_Paulo',
        v_usuario,v_fixa.id_categoria_saida,v_fixa.id_forma_pagamento,v_fixa.id_conta_usuario,
        v_data,now(),true,p_id,v_fixa.id_cartao) returning cd_saida into v_id;
    return jsonb_build_object('id',v_id);
end $$;

revoke all on function public.fn_salvar_conta_financeira(integer,jsonb,jsonb) from public;
revoke all on function public.fn_criar_despesa_fixa(jsonb,date) from public;
revoke all on function public.fn_pagar_despesa_fixa(integer,date) from public;
grant execute on function public.fn_salvar_conta_financeira(integer,jsonb,jsonb) to authenticated;
grant execute on function public.fn_criar_despesa_fixa(jsonb,date) to authenticated;
grant execute on function public.fn_pagar_despesa_fixa(integer,date) to authenticated;

notify pgrst, 'reload schema';
commit;
