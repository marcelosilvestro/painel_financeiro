<?php
require_once __DIR__ . '/config.php';
$pf_pagina = 'carteira';
$pf_perm_pagina = 'ver';
include('nav/header.php');
$pf_nominal = $pf_schema_ok && Permissao::tem('nominal');
?>
<body data-ext="<?= pf_h($ext_mk) ?>">
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 pf-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$pf_bloqueado): ?>

    <div class="pf-filtros" role="search">
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-mes">Mês de referência</label>
            <input type="month" class="lc-input-date" id="f-mes">
        </div>
        <div class="pf-selo" id="pf-selo" aria-live="polite"></div>
        <button type="button" class="pf-mini-btn" id="btn-atualizar"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
    </div>

    <div class="pf-kpis" id="pf-kpis">
        <div class="pf-kpi" id="k-base"></div>
        <div class="pf-kpi" id="k-mrr"></div>
        <div class="pf-kpi" id="k-churn"></div>
        <div class="pf-kpi" id="k-recup"></div>
        <div class="pf-kpi" id="k-baixa"></div>
    </div>

    <div class="pf-g11">
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-arrow-left-right"></i> Entradas e saídas
                    <span class="pf-pergunta">a base cresce ou encolhe?</span></span>
                <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-mov')"><i class="bi bi-table"></i></button></span>
            </div>
            <div class="pf-graf" id="b-mov"><canvas id="g-mov"></canvas></div>
            <div class="pf-legenda">
                <span><i style="background:#2a78d6"></i> Novos (instalação)</span>
                <span><i style="background:#eb6834"></i> Desativados</span>
            </div>
            <div class="pf-nota" id="n-mov"></div>
            <div class="pf-tab-alt" id="t-mov"></div>
        </div>
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-1-circle"></i> Primeira fatura dos novos
                    <span class="pf-pergunta">quem entra paga?</span></span>
                <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-prim')"><i class="bi bi-table"></i></button></span>
            </div>
            <div class="pf-graf" id="b-prim"><canvas id="g-prim"></canvas></div>
            <div class="pf-legenda">
                <span><i style="background:#1d9e75"></i> Pagou em dia</span>
                <span><i style="background:#e0a100"></i> Pagou com atraso</span>
                <span><i style="background:#e74c3c"></i> Não pagou</span>
                <span><i style="background:#9db5dc"></i> Ainda não venceu</span>
            </div>
            <div class="pf-nota">Por mês de instalação. Cliente que não paga a primeira fatura costuma ser venda ruim ou golpe.</div>
            <div class="pf-tab-alt" id="t-prim"></div>
        </div>
    </div>

    <div class="pf-g11">
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-slash-circle"></i> Bloqueios e desbloqueios
                    <span class="pf-pergunta">o corte está funcionando?</span></span>
                <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-bloq')"><i class="bi bi-table"></i></button></span>
            </div>
            <div class="pf-graf" id="b-bloq"><canvas id="g-bloq"></canvas></div>
            <div class="pf-legenda">
                <span><i style="background:#e74c3c"></i> Bloqueios</span>
                <span><i style="background:#1d9e75"></i> Desbloqueio automático (pagou)</span>
                <span><i style="background:#9aa3b2"></i> Desbloqueio manual</span>
            </div>
            <div class="pf-nota" id="n-bloq"></div>
            <div class="pf-tab-alt" id="t-bloq"></div>
        </div>
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-diagram-3"></i> Receita recorrente por plano
                    <span class="pf-pergunta">de onde vem o MRR?</span></span>
                <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-planos')"><i class="bi bi-table"></i></button></span>
            </div>
            <div class="pf-graf" id="b-planos"><canvas id="g-planos"></canvas></div>
            <div class="pf-nota">Valor do plano − desconto + acréscimo do cadastro, clientes ativos não isentos.</div>
            <div class="pf-tab-alt" id="t-planos"></div>
        </div>
    </div>

    <div class="lc-section-panel pf-painel">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-arrow-repeat"></i> Recuperação de crédito
                <span class="pf-pergunta">quanto ainda dá para cobrar de quem saiu?</span></span>
            <span class="lc-badge-count" id="r-conta">–</span>
        </div>
        <div class="pf-g2" style="margin:0;padding:0 0 4px">
            <div>
                <div class="pf-graf baixo" id="b-recup"><canvas id="g-recup"></canvas></div>
                <div class="pf-nota">Por ano da desativação. Só títulos que venceram até a desativação. A parte com mais de 5 anos pode estar prescrita (Código Civil, art. 206) — confira com o jurídico. <?= $pf_nominal ? 'Clique num ano para filtrar a lista.' : '' ?></div>
            </div>
            <div>
                <?php if ($pf_nominal): ?>
                <div class="lc-table-wrap">
                    <table class="lc-table pf-tabela"><thead><tr>
                        <th class="ord" data-ordem="nome" scope="col">Cliente</th>
                        <th class="ord prio-6" data-ordem="desativado" scope="col">Desativado</th>
                        <th class="num" scope="col">Títulos</th>
                        <th class="num ord" data-ordem="valor" scope="col">Valor</th>
                    </tr></thead><tbody id="r-linhas"></tbody></table>
                </div>
                <div class="pf-paginacao" id="r-pag"></div>
                <?php else: ?>
                <div class="pf-aviso" style="margin:12px 16px"><i class="bi bi-eye-slash"></i>
                    <div>A lista com o nome dos clientes exige o papel <b>Ver listas com nome</b>.</div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>
<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var NOMINAL = <?= $pf_nominal ? 'true' : 'false' ?>;
    var R_ORDEM = 'valor', R_ANO = '', R_PAG = 1;

    function periodo() { return { mes: $('#f-mes').val() || '' }; }
    function kpi(id, ico, rotulo, valor, sub, rodape) {
        $('#' + id).removeClass('erro').html('<div class="pf-kpi-rotulo"><i class="bi ' + ico + '"></i> ' + PF.esc(rotulo) + '</div>' +
            '<div class="pf-kpi-valor">' + PF.esc(valor) + '</div>' + (sub ? '<div class="pf-kpi-sub">' + sub + '</div>' : '') +
            (rodape ? '<div class="pf-kpi-rodape">' + rodape + '</div>' : ''));
    }

    function carregarKpis() {
        $('#pf-kpis .pf-kpi').html('<div class="pf-esqueleto"></div>');
        PF.api('cart.kpis', periodo()).then(function (k) {
            var m = k.mrr, mv = k.movimento, r = k.recuperavel;
            kpi('k-base', 'bi-people', 'Clientes ativos', PF.num(m.ativos), PF.num(m.isentos) + ' isento(s)', '<span class="pf-regime">hoje</span>');
            kpi('k-mrr', 'bi-graph-up', 'Receita recorrente (MRR)', PF.brl(m.mrr), 'ARPU ' + PF.brl(m.arpu),
                '<span class="pf-regime">hoje</span>' + (m.sem_plano ? ' ' + m.sem_plano + ' sem plano cadastrado' : ''));
            kpi('k-churn', 'bi-box-arrow-right', 'Churn do mês', PF.pct(mv.churn, 2),
                PF.num(mv.saidas) + ' saída(s) · ' + PF.num(mv.novos) + ' novo(s)',
                '<span class="pf-regime">mês</span> ' + PF.num(mv.saidas_com_divida) + ' saíram devendo');
            kpi('k-recup', 'bi-arrow-repeat', 'Recuperável', PF.brl(r.valor), PF.num(r.clientes) + ' cliente(s) · ' + PF.num(r.titulos) + ' título(s)',
                r.pos_titulos ? '+ ' + PF.brlCurto(r.pos_valor) + ' gerados após desativar (fora)' : '<span class="pf-regime">hoje</span>');
            kpi('k-baixa', 'bi-eraser', 'Baixa sem pagamento', PF.brl(k.baixa_12m.valor), PF.num(k.baixa_12m.qtd) + ' título(s) excluído(s) depois de vencer',
                '<span class="pf-regime">12 meses</span> renegociação ou perdão');
            $('#pf-selo').text('Calculado ' + PF.dataHora(k.calculado_em).slice(-5));
        }).catch(function (e) { $('#pf-kpis .pf-kpi').addClass('erro').html('<div class="pf-kpi-valor">' + PF.esc(e.mensagem) + '</div>'); });
    }

    function grafico(id, acao, desenhar) {
        var $b = $('#b-' + id);
        PF.estado($b, 'carregando');
        PF.api(acao, periodo()).then(function (d) { PF.estado($b, null); desenhar(d, $b); })
            .catch(function (e) { PF.estado($b, 'erro', e.mensagem, function () { grafico(id, acao, desenhar); }); });
    }

    function carregarGraficos() {
        grafico('mov', 'cart.movimento', function (d) {
            PFG.agrupado('g-mov', d.meses.map(PF.mesCurto), [
                { rotulo: 'Novos', cor: '#2a78d6', valores: d.novos },
                { rotulo: 'Desativados', cor: '#eb6834', valores: d.saidas }
            ]);
            var n = d.meses.length - 1;
            var saldo = d.novos.reduce(function (a, b) { return a + b; }, 0) - d.saidas.reduce(function (a, b) { return a + b; }, 0);
            $('#n-mov').text('Base no fim de ' + PF.mesCurto(d.meses[n]) + ': ' + PF.num(d.base[n]) + ' clientes. Saldo em 12 meses: ' +
                (saldo >= 0 ? '+' : '') + saldo + '.');
            PFG.tabela($('#t-mov'), ['Mês', 'Novos', 'Desativados', 'Saíram devendo', 'Base no fim', 'Churn'], d.meses.map(function (m, i) {
                return [PF.mesCurto(m), d.novos[i], d.saidas[i], d.saidas_com_divida[i], PF.num(d.base[i]), PF.pct(d.churn[i], 2)];
            }));
        });
        grafico('prim', 'cart.primeira_fatura', function (d, $b) {
            var ms = d.meses;
            if (!ms.some(function (x) { return x.em_dia + x.atraso + x.nao_pagou + x.a_vencer; })) { PF.estado($b, 'vazio', 'Nenhum cliente novo nos últimos 12 meses.'); return; }
            PFG.agrupado('g-prim', ms.map(function (x) { return PF.mesCurto(x.mes); }), [
                { rotulo: 'Em dia', cor: PFG.COR.verde, valores: ms.map(function (x) { return x.em_dia; }) },
                { rotulo: 'Com atraso', cor: PFG.COR.ambar, valores: ms.map(function (x) { return x.atraso; }) },
                { rotulo: 'Não pagou', cor: PFG.COR.vermelho, valores: ms.map(function (x) { return x.nao_pagou; }) },
                { rotulo: 'Ainda não venceu', cor: PFG.COR.azulClaro, valores: ms.map(function (x) { return x.a_vencer; }) }
            ]);
            PFG.tabela($('#t-prim'), ['Instalação', 'Em dia', 'Com atraso', 'Não pagou', 'A vencer'], ms.map(function (x) {
                return [PF.mesCurto(x.mes), x.em_dia, x.atraso, x.nao_pagou, x.a_vencer];
            }));
        });
        grafico('bloq', 'cart.bloqueios', function (d, $b) {
            var ms = d.meses;
            if (!d.historico_desde) { PF.estado($b, 'vazio', 'Sem histórico de bloqueios: o processamento ainda não leu o log do MK-AUTH.'); return; }
            PFG.agrupado('g-bloq', ms.map(function (x) { return PF.mesCurto(x.mes); }), [
                { rotulo: 'Bloqueios', cor: PFG.COR.vermelho, valores: ms.map(function (x) { return x.bloqueios; }) },
                { rotulo: 'Desbloqueio automático', cor: PFG.COR.verde, valores: ms.map(function (x) { return x.desbloqueios_auto; }) },
                { rotulo: 'Desbloqueio manual', cor: PFG.COR.cinza, valores: ms.map(function (x) { return x.desbloqueios_manual; }) }
            ]);
            $('#n-bloq').text('Cortes lidos do log do MK-AUTH desde ' + PF.data(d.historico_desde) +
                ' (o MK-AUTH apaga log antigo; o addon guarda cada evento daqui para frente). Desbloqueios: só os que o MK-AUTH registra no log.');
            PFG.tabela($('#t-bloq'), ['Mês', 'Bloqueios', 'Desbloq. automático', 'Desbloq. manual', 'Tempo bloqueado (mediana, dias)'], ms.map(function (x) {
                return [PF.mesCurto(x.mes), x.bloqueios, x.desbloqueios_auto, x.desbloqueios_manual, x.mediana_dias === null ? '—' : String(x.mediana_dias).replace('.', ',')];
            }));
        });
        grafico('planos', 'cart.planos', function (d, $b) {
            if (!d.itens.length) { PF.estado($b, 'vazio', 'Nenhum cliente ativo.'); return; }
            PFG.barrasH('g-planos', d.itens.slice(0, 12).map(function (i) {
                return { rotulo: i.plano, valor: i.mrr, dica: PF.brl(i.mrr) + ' · ' + PF.num(i.clientes) + ' cliente(s)' };
            }), PFG.COR.azul);
            PFG.tabela($('#t-planos'), ['Plano', 'Clientes', 'MRR'], d.itens.map(function (i) { return [i.plano, PF.num(i.clientes), PF.brl(i.mrr)]; }));
        });
        grafico('recup', 'cart.recuperacao', function (d, $b) {
            if (!d.anos.length) { PF.estado($b, 'vazio', 'Nenhuma dívida de cliente desativado.'); return; }
            PFG.empilhado('g-recup', d.anos.map(function (a) { return a.ano; }), [
                { rotulo: 'Cobrável', cor: PFG.COR.azul, valores: d.anos.map(function (a) { return a.valor - a.prescrito; }) },
                { rotulo: 'Mais de 5 anos', cor: PFG.COR.cinza, valores: d.anos.map(function (a) { return a.prescrito; }) }
            ], 'valor', NOMINAL ? function (i) { R_ANO = R_ANO === d.anos[i].ano ? '' : d.anos[i].ano; R_PAG = 1; carregarLista(); } : null, function (i) {
                return d.anos[i].clientes + ' cliente(s) · ' + d.anos[i].titulos + ' título(s)';
            });
        });
    }

    function carregarLista() {
        if (!NOMINAL) return;
        $('#r-linhas').html('<tr><td colspan="4" class="lc-loading">Carregando...</td></tr>');
        PF.api('cart.recuperacao_lista', { pagina: R_PAG, ordem: R_ORDEM, ano: R_ANO }).then(function (d) {
            $('#r-conta').text(d.total + (R_ANO ? ' · ' + R_ANO : ''));
            $('#r-linhas').html(d.linhas.length ? d.linhas.map(function (c) {
                return '<tr><td>' + PF.linkCliente(c.uuid, c.nome) + '<div class="pf-sub">' + PF.esc(c.login) + (c.cidade ? ' · ' + PF.esc(c.cidade) : '') + '</div></td>' +
                    '<td class="prio-6">' + PF.data(c.desativado) + '</td><td class="num">' + c.titulos +
                    '<div class="pf-sub">desde ' + PF.data(c.mais_antigo) + '</div></td><td class="num"><b>' + PF.brl(c.valor) + '</b></td></tr>';
            }).join('') : '<tr><td colspan="4" class="lc-empty">Nenhum cliente.</td></tr>');
            PF.paginacao($('#r-pag'), d.pagina, d.por_pagina, d.total, function (p) { R_PAG = p; carregarLista(); });
        }).catch(function (e) { $('#r-linhas').html('<tr><td colspan="4">' + PF.esc(e.mensagem) + '</td></tr>'); });
    }

    function carregarTudo() { carregarKpis(); carregarGraficos(); carregarLista(); }

    $(function () {
        PF.api('inicio.estado').then(function (d) {
            $('#f-mes').val(d.hoje.slice(0, 7)).attr('max', d.hoje.slice(0, 7));
            carregarTudo();
        }).catch(PF.erro);
        $('#f-mes').on('change', function () { carregarKpis(); carregarGraficos(); });
        $('#btn-atualizar').on('click', carregarTudo);
        $('th.ord').on('click', function () { R_ORDEM = $(this).attr('data-ordem'); R_PAG = 1; carregarLista(); });
    });
})();
</script>
</body>
</html>
