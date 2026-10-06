<?php
require_once __DIR__ . '/config.php';
$pf_pagina = 'recebimentos';
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
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-dv">Vencimento</label>
            <select class="lc-input-date" id="f-dv"><option value="">Todos os dias</option></select>
        </div>
        <div class="pf-selo" id="pf-selo" aria-live="polite"></div>
        <button type="button" class="pf-mini-btn" id="btn-atualizar"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
    </div>

    <div class="pf-kpis" id="pf-kpis">
        <div class="pf-kpi" id="k-caixa"></div>
        <div class="pf-kpi" id="k-qtd"></div>
        <div class="pf-kpi" id="k-juros"></div>
        <div class="pf-kpi" id="k-desc"></div>
        <div class="pf-kpi" id="k-falta"></div>
    </div>

    <div class="lc-section-panel pf-painel">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-calendar3"></i> Recebido por dia
                <span class="pf-pergunta">em que dias o dinheiro entra, e por qual meio?</span></span>
            <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-dia')"><i class="bi bi-table"></i> Tabela</button></span>
        </div>
        <div class="pf-graf" id="b-dia"><canvas id="g-dia"></canvas></div>
        <div class="pf-legenda" id="l-formas"></div>
        <div class="pf-nota"><?= $pf_nominal ? 'Clique numa coluna para ver os pagamentos do dia. ' : '' ?>Caixa: pela data do pagamento, todos os tipos de título.</div>
        <div class="pf-tab-alt" id="t-dia"></div>
    </div>

    <div class="pf-g2">
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-bar-chart-line"></i> Últimos 12 meses
                    <span class="pf-pergunta">a receita cresce? para onde migram as formas?</span></span>
                <span class="pf-cab-acoes">
                    <button type="button" class="pf-mini-btn ativo" data-modo="valor" aria-pressed="true">R$</button>
                    <button type="button" class="pf-mini-btn" data-modo="100" aria-pressed="false">%</button>
                    <button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-mensal')"><i class="bi bi-table"></i></button>
                </span>
            </div>
            <div class="pf-graf" id="b-mensal"><canvas id="g-mensal"></canvas></div>
            <div class="pf-legenda" id="l-mensal"></div>
            <div class="pf-nota">Colunas: o que entrou no mês (caixa). Linha tracejada: o faturamento previsto dos títulos que venceram no mês (competência).</div>
            <div class="pf-tab-alt" id="t-mensal"></div>
        </div>
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-person-check"></i> Quem deu a baixa
                    <span class="pf-pergunta">quanto é automático?</span></span>
                <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-col')"><i class="bi bi-table"></i></button></span>
            </div>
            <div class="pf-graf" id="b-col"><canvas id="g-col"></canvas></div>
            <div class="pf-nota" id="n-col"></div>
            <div class="pf-tab-alt" id="t-col"></div>
        </div>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<div class="lc-overlay" id="pf-modal-pag">
    <div class="lc-modal-box" style="max-width:860px">
        <div class="lc-modal-header">
            <span class="lc-modal-title" id="mp-titulo">Pagamentos</span>
            <button type="button" class="lc-modal-close" aria-label="Fechar" onclick="PF.fecharModal('pf-modal-pag')">&times;</button>
        </div>
        <div class="lc-modal-body" style="padding:0">
            <div class="lc-table-wrap" style="max-height:60vh;overflow:auto">
                <table class="lc-table pf-tabela"><thead><tr>
                    <th scope="col">Cliente</th><th scope="col" class="prio-6">Vencimento</th><th class="num" scope="col">Título</th>
                    <th class="num" scope="col">Pago</th><th scope="col">Forma</th><th scope="col" class="prio-7">Baixa por</th><th class="num" scope="col">Dias</th>
                </tr></thead><tbody id="mp-linhas"></tbody></table>
            </div>
            <div class="pf-paginacao" id="mp-pag"></div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="PF.fecharModal('pf-modal-pag')">Fechar</button>
        </div>
    </div>
</div>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var NOMINAL = <?= $pf_nominal ? 'true' : 'false' ?>;
    var ORDEM = ['pix', 'boleto', 'dinheiro', 'cartao', 'outras'];
    var MODO = 'valor', ULT_MENSAL = null, ULT_DIA = null;

    function periodo() { return { mes: $('#f-mes').val() || '', dv: $('#f-dv').val() || '' }; }
    function nomeForma(f) { return PFG.FORMA_ROTULO[f] || f; }
    function legendaFormas($alvo, extra) {
        $alvo.html(ORDEM.map(function (f) { return '<span><i style="background:' + PFG.FORMA[f] + '"></i> ' + nomeForma(f) + '</span>'; }).join('') + (extra || ''));
    }
    function kpi(id, ico, rotulo, valor, sub, rodape) {
        $('#' + id).removeClass('erro').html('<div class="pf-kpi-rotulo"><i class="bi ' + ico + '"></i> ' + PF.esc(rotulo) + '</div>' +
            '<div class="pf-kpi-valor">' + PF.esc(valor) + '</div>' + (sub ? '<div class="pf-kpi-sub">' + sub + '</div>' : '') +
            (rodape ? '<div class="pf-kpi-rodape">' + rodape + '</div>' : ''));
    }
    function variacao(a, b, rotulo) {
        if (!b) return '';
        var d = 100 * (a - b) / b;
        return '<span class="pf-var ' + (d >= 0 ? 'bom' : 'ruim') + '">' + (d >= 0 ? '▲ ' : '▼ ') + PF.pct(Math.abs(d)) + '</span> ' + rotulo;
    }

    function carregarKpis() {
        $('#pf-kpis .pf-kpi').html('<div class="pf-esqueleto"></div>');
        PF.api('rec.kpis', periodo()).then(function (k) {
            var c = k.caixa;
            kpi('k-caixa', 'bi-cash-stack', 'Entrou no mês', PF.brl(c.valor),
                variacao(c.valor, k.caixa_ant, 'vs. mês anterior') + '<br>' + variacao(c.valor, k.caixa_ano_ant, 'vs. mesmo mês do ano passado'),
                '<span class="pf-regime">caixa</span> todos os tipos de título');
            kpi('k-qtd', 'bi-receipt', 'Pagamentos', PF.num(c.qtd), 'ticket médio ' + (c.qtd ? PF.brl(c.valor / c.qtd) : '—'),
                '<span class="pf-regime">caixa</span>');
            kpi('k-juros', 'bi-plus-circle', 'Juros e multa recebidos', PF.brl(c.juros), 'pago acima do valor do título',
                '<span class="pf-regime">caixa</span>');
            kpi('k-desc', 'bi-dash-circle', 'Descontos concedidos', PF.brl(c.desconto), 'pago abaixo do valor do título',
                '<span class="pf-regime">caixa</span>');
            var a = k.em_aberto;
            kpi('k-falta', 'bi-hourglass', 'Falta entrar do mês', PF.brl(a.a_vencer + a.vencido),
                PF.brl(a.a_vencer) + ' a vencer · ' + PF.brl(a.vencido) + ' vencido',
                '<span class="pf-regime">competência</span> ' + PF.num(a.qtd_a_vencer + a.qtd_vencido) + ' título(s)');
            $('#pf-selo').text('Calculado ' + PF.dataHora(k.calculado_em).slice(-5));
        }).catch(function (e) { $('#pf-kpis .pf-kpi').addClass('erro').html('<div class="pf-kpi-valor">' + PF.esc(e.mensagem) + '</div>'); });
    }

    function carregarDia() {
        var $b = $('#b-dia');
        PF.estado($b, 'carregando');
        PF.api('rec.dia', periodo()).then(function (d) {
            PF.estado($b, null);
            ULT_DIA = d;
            var total = ORDEM.reduce(function (s, f) { return s + d.series[f].reduce(function (a, b) { return a + b; }, 0); }, 0);
            if (!total) { PF.estado($b, 'vazio', 'Nenhum pagamento neste mês.'); return; }
            PFG.empilhado('g-dia', d.dias.map(String), ORDEM.map(function (f) {
                return { rotulo: nomeForma(f), cor: PFG.FORMA[f], valores: d.series[f] };
            }), 'valor', NOMINAL ? function (i) { abrirDia(d.mes + '-' + String(i + 1).padStart(2, '0'), 1); } : null, function (i) {
                return d.qtd[i] + ' pagamento(s)';
            });
            legendaFormas($('#l-formas'));
            PFG.tabela($('#t-dia'), ['Dia'].concat(ORDEM.map(nomeForma)).concat(['Total', 'Pagamentos']), d.dias.map(function (dia, i) {
                var t = ORDEM.reduce(function (s, f) { return s + d.series[f][i]; }, 0);
                return [dia].concat(ORDEM.map(function (f) { return PF.brl(d.series[f][i]); })).concat([PF.brl(t), d.qtd[i]]);
            }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarDia); });
    }

    function desenharMensal() {
        var d = ULT_MENSAL;
        PFG.empilhado('g-mensal', d.meses.map(PF.mesCurto), ORDEM.map(function (f) {
            return { rotulo: nomeForma(f), cor: PFG.FORMA[f], valores: d.series[f] };
        }), MODO, null, function (i) {
            return 'Total: ' + PF.brl(d.total[i]) + (d.previsto[i] ? '\nPrevisto: ' + PF.brl(d.previsto[i]) : '');
        }, MODO === 'valor' ? { rotulo: 'Previsto (competência)', cor: '#111827', valores: d.previsto } : null);
        legendaFormas($('#l-mensal'), MODO === 'valor' ? '<span><i class="tracejada" style="border-color:#111827"></i> Previsto</span>' : '');
    }

    function carregarMensal() {
        var $b = $('#b-mensal');
        PF.estado($b, 'carregando');
        PF.api('rec.mensal', periodo()).then(function (d) {
            PF.estado($b, null);
            ULT_MENSAL = d;
            desenharMensal();
            PFG.tabela($('#t-mensal'), ['Mês', 'Entrou', 'Previsto'].concat(ORDEM.map(nomeForma)).concat(['Juros', 'Descontos']), d.meses.map(function (m, i) {
                return [PF.mesCurto(m), PF.brl(d.total[i]), PF.brl(d.previsto[i])].concat(ORDEM.map(function (f) { return PF.brl(d.series[f][i]); }))
                    .concat([PF.brl(d.juros[i]), PF.brl(d.desconto[i])]);
            }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarMensal); });
    }

    function carregarColetor() {
        var $b = $('#b-col');
        PF.estado($b, 'carregando');
        PF.api('rec.coletor', periodo()).then(function (d) {
            PF.estado($b, null);
            if (!d.itens.length) { PF.estado($b, 'vazio', 'Nenhum pagamento neste mês.'); return; }
            var rot = function (i) { return i.automatico ? 'Retorno bancário (automático)' : (i.coletor === 'nao_informado' ? '(não informado)' : i.coletor); };
            PFG.barrasH('g-col', d.itens.map(function (i) {
                return { rotulo: rot(i), valor: i.valor, dica: PF.brl(i.valor) + ' · ' + PF.num(i.qtd) + ' baixa(s)' };
            }), PFG.COR.azul);
            var tot = d.itens.reduce(function (s, i) { return s + i.valor; }, 0);
            var auto = d.itens.filter(function (i) { return i.automatico; }).reduce(function (s, i) { return s + i.valor; }, 0);
            $('#n-col').text(tot ? PF.pct(100 * auto / tot) + ' do valor entrou por retorno bancário, sem digitação. O resto foi baixado à mão.' : '');
            PFG.tabela($('#t-col'), ['Baixa por', 'Valor', 'Baixas'], d.itens.map(function (i) { return [rot(i), PF.brl(i.valor), PF.num(i.qtd)]; }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarColetor); });
    }

    function abrirDia(dia, pagina) {
        $('#mp-titulo').text('Pagamentos de ' + PF.data(dia));
        $('#mp-linhas').html('<tr><td colspan="7" class="lc-loading">Carregando...</td></tr>');
        PF.abrirModal('pf-modal-pag');
        PF.api('rec.pagamentos', { dia: dia, pagina: pagina }).then(function (d) {
            $('#mp-titulo').text('Pagamentos de ' + PF.data(dia) + ' — ' + PF.brl(d.soma));
            $('#mp-linhas').html(d.linhas.length ? d.linhas.map(function (l) {
                var dias = +l.dias;
                return '<tr><td>' + PF.linkCliente(l.uuid_cliente, l.nome || l.login) + '<div class="pf-sub">' + PF.esc(l.login) + (l.tipo !== 'mensalidade' ? ' · ' + PF.esc(l.tipo) : '') + '</div></td>' +
                    '<td class="prio-6">' + PF.data(l.datavenc) + '</td><td class="num">' + PF.brl(+l.valor) + '</td><td class="num"><b>' + PF.brl(+l.valorpag) + '</b></td>' +
                    '<td><span class="pf-tag" style="background:' + (PFG.FORMA[l.forma] || '#e87ba4') + '22;color:#111827">' + PF.esc(nomeForma(l.forma)) + '</span></td>' +
                    '<td class="prio-7">' + PF.esc(l.coletor === 'arq.retorno' ? 'retorno bancário' : l.coletor) + '</td>' +
                    '<td class="num">' + (dias > 0 ? '<span class="pf-dias f0">+' + dias + '</span>' : (dias < 0 ? dias : 'em dia')) + '</td></tr>';
            }).join('') : '<tr><td colspan="7" class="lc-empty">Nenhum pagamento.</td></tr>');
            PF.paginacao($('#mp-pag'), d.pagina, d.por_pagina, d.total, function (p) { abrirDia(dia, p); });
        }).catch(function (e) { $('#mp-linhas').html('<tr><td colspan="7">' + PF.esc(e.mensagem) + '</td></tr>'); });
    }

    function carregarTudo() { carregarKpis(); carregarDia(); carregarMensal(); carregarColetor(); }

    $(function () {
        PF.api('inicio.estado').then(function (d) {
            $('#f-mes').val(d.hoje.slice(0, 7)).attr('max', d.hoje.slice(0, 7));
            d.dias_venc.forEach(function (n) { $('#f-dv').append('<option value="' + n + '">Dia ' + n + '</option>'); });
            carregarTudo();
        }).catch(PF.erro);
        $('#f-mes, #f-dv').on('change', carregarTudo);
        $('#btn-atualizar').on('click', carregarTudo);
        $('[data-modo]').on('click', function () {
            MODO = $(this).attr('data-modo');
            $('[data-modo]').removeClass('ativo').attr('aria-pressed', 'false');
            $(this).addClass('ativo').attr('aria-pressed', 'true');
            if (ULT_MENSAL) desenharMensal();
        });
    });
})();
</script>
</body>
</html>
