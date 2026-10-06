<?php
require_once __DIR__ . '/config.php';
$pf_pagina = 'visao';
// A Home abre para qualquer usuario do MK-AUTH: e nela que o primeiro administrador se
// apresenta. Os numeros so chegam por AJAX, que exige o papel 'ver'.
$pf_perm_pagina = 'logado';
include('nav/header.php');
?>
<body data-ext="<?= pf_h($ext_mk) ?>">
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 pf-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$pf_bloqueado): ?>

    <div id="pf-sem-admin" class="pf-aviso info" style="display:none">
        <i class="bi bi-person-badge"></i>
        <div style="flex:1">
            <strong>Este addon ainda não tem administrador.</strong>
            O administrador decide quem vê os números, quem vê o nome dos clientes e quem pode exportar listas.
        </div>
        <button type="button" class="lc-btn-black" id="btn-assumir"><i class="bi bi-shield-check"></i> Assumir administração</button>
    </div>
    <div id="pf-sem-acesso" class="pf-aviso" style="display:none">
        <i class="bi bi-shield-lock"></i>
        <div><strong>Você ainda não tem acesso a este addon.</strong>
            Peça ao administrador para liberar o seu login em Configurações › Permissões.</div>
    </div>

    <div id="pf-conteudo" style="display:none">
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
            <button type="button" class="pf-mini-btn" id="btn-atualizar" title="Recarregar os números"><i class="bi bi-arrow-clockwise"></i> Atualizar</button>
        </div>

        <div class="pf-kpis" id="pf-kpis">
            <div class="pf-kpi" id="k-recebido"></div>
            <div class="pf-kpi link" id="k-atraso" tabindex="0" data-href="inadimplencia.php"></div>
            <div class="pf-kpi" id="k-emdia"></div>
            <div class="pf-kpi link" id="k-bloq" tabindex="0" data-href="inadimplencia.php?bloqueado=sim"></div>
            <div class="pf-kpi link" id="k-recup" tabindex="0" data-href="inadimplencia.php?situacao=desativados"></div>
        </div>

        <div class="pf-g2">
            <div class="lc-section-panel">
                <div class="lc-section-header">
                    <span class="lc-section-title"><i class="bi bi-graph-up"></i> Recebimento do mês
                        <span class="pf-pergunta">estou recebendo no ritmo esperado?</span></span>
                    <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-curva')"><i class="bi bi-table"></i> Tabela</button></span>
                </div>
                <div class="pf-graf" id="b-curva"><canvas id="g-curva"></canvas></div>
                <div class="pf-legenda">
                    <span><i class="tracejada" style="border-color:#3266ad"></i> Previsto acumulado (pelo vencimento)</span>
                    <span><i class="linha" style="background:#1d9e75"></i> Recebido acumulado</span>
                </div>
                <div class="pf-nota" id="n-curva"></div>
                <div class="pf-tab-alt" id="t-curva"></div>
            </div>
            <div class="lc-section-panel">
                <div class="lc-section-header">
                    <span class="lc-section-title"><i class="bi bi-hourglass-split"></i> Atraso por faixa
                        <span class="pf-pergunta">o atraso está envelhecendo?</span></span>
                    <span class="pf-cab-acoes">
                        <button type="button" class="pf-mini-btn ativo" data-sit="ativos" aria-pressed="true">Ativos</button>
                        <button type="button" class="pf-mini-btn" data-sit="desativados" aria-pressed="false">Desativados</button>
                        <button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-aging')"><i class="bi bi-table"></i></button>
                    </span>
                </div>
                <div class="pf-graf" id="b-aging"><canvas id="g-aging"></canvas></div>
                <div class="pf-nota">Dias desde o vencimento efetivo. Clique numa faixa para ver os clientes.</div>
                <div class="pf-tab-alt" id="t-aging"></div>
            </div>
        </div>

        <div class="lc-section-panel pf-painel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-bar-chart-steps"></i> Safras: o que aconteceu com cada mês de vencimento
                    <span class="pf-pergunta">a pontualidade está melhorando?</span></span>
                <span class="pf-cab-acoes">
                    <button type="button" class="pf-mini-btn ativo" data-modo="100" aria-pressed="true">%</button>
                    <button type="button" class="pf-mini-btn" data-modo="valor" aria-pressed="false">R$</button>
                    <button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-safra')"><i class="bi bi-table"></i> Tabela</button>
                </span>
            </div>
            <div class="pf-graf baixo" id="b-safra"><canvas id="g-safra"></canvas></div>
            <div class="pf-legenda">
                <span><i style="background:#1d9e75"></i> Pago em dia</span>
                <span><i style="background:#e0a100"></i> Pago com atraso</span>
                <span><i style="background:#e74c3c"></i> Vencido em aberto</span>
                <span><i style="background:#9aa3b2"></i> Baixa sem pagamento</span>
                <span><i style="background:#9db5dc"></i> A vencer</span>
            </div>
            <div class="pf-nota">Receita recorrente, pelo valor do título. "Baixa sem pagamento" = título excluído depois de vencer (renegociação ou perdão) — fica fora da inadimplência.</div>
            <div class="pf-tab-alt" id="t-safra"></div>
        </div>

        <div class="lc-section-panel pf-painel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-bell"></i> Alertas</span>
                <span class="lc-badge-count" id="a-conta">–</span>
            </div>
            <div id="b-alertas"><div class="lc-loading" style="padding:14px 16px">Carregando...</div></div>
        </div>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<div class="lc-overlay" id="pf-modal-alerta">
    <div class="lc-modal-box" style="max-width:720px">
        <div class="lc-modal-header">
            <span class="lc-modal-title" id="ma-titulo">Alerta</span>
            <button type="button" class="lc-modal-close" aria-label="Fechar" onclick="PF.fecharModal('pf-modal-alerta')">&times;</button>
        </div>
        <div class="lc-modal-body" style="padding:0">
            <div class="lc-table-wrap" style="max-height:60vh;overflow:auto">
                <table class="lc-table pf-tabela"><thead><tr><th>Cliente</th><th>Detalhe</th></tr></thead><tbody id="ma-linhas"></tbody></table>
            </div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="PF.fecharModal('pf-modal-alerta')">Fechar</button>
        </div>
    </div>
</div>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var EST = null;          // estado inicial (papeis, dias de vencimento)
    var SIT_AGING = 'ativos';
    var MODO_SAFRA = '100';
    var ULT_SAFRA = null;

    function periodo() {
        return { mes: $('#f-mes').val() || '', dv: $('#f-dv').val() || '' };
    }

    // ------------------------------------------------------------------ KPIs
    function kpi(id, ico, rotulo, valor, sub, rodape) {
        $('#' + id).removeClass('erro').html(
            '<div class="pf-kpi-rotulo"><i class="bi ' + ico + '"></i> ' + PF.esc(rotulo) + '</div>' +
            '<div class="pf-kpi-valor" title="' + PF.esc(valor) + '">' + PF.esc(valor) + '</div>' +
            (sub ? '<div class="pf-kpi-sub">' + sub + '</div>' : '') +
            (rodape ? '<div class="pf-kpi-rodape">' + rodape + '</div>' : ''));
    }
    function kpisCarregando() {
        $('#pf-kpis .pf-kpi').each(function () { $(this).html('<div class="pf-esqueleto"></div><div class="pf-esqueleto" style="height:12px;width:50%"></div>'); });
    }
    function variacao(atual, anterior, maiorEhBom, sufixo) {
        if (atual === null || anterior === null || anterior === undefined) return '';
        var d = atual - anterior;
        if (Math.abs(d) < 0.05) return '<span class="pf-var neutro">= mês anterior</span>';
        var bom = maiorEhBom ? d > 0 : d < 0;
        return '<span class="pf-var ' + (bom ? 'bom' : 'ruim') + '">' + (d > 0 ? '▲ ' : '▼ ') +
               String(Math.abs(d).toFixed(1)).replace('.', ',') + (sufixo || '') + '</span> <span>vs. mês anterior</span>';
    }

    function renderKpis(k) {
        var p = k.previsto, pa = k.previsto_ant;
        var pctRec = p.valor > 0 ? 100 * p.recebido / p.valor : null;
        var pctEsp = p.valor > 0 ? 100 * p.valor_ate_hoje / p.valor : 0;
        var pctAnt = pa.valor > 0 ? 100 * pa.recebido / pa.valor : null;
        kpi('k-recebido', 'bi-cash-stack', 'Recebido do previsto', PF.brl(p.recebido),
            'de ' + PF.brl(p.valor) + ' (' + PF.pct(pctRec) + ')' +
            '<div class="pf-prog" title="Barra clara: o que já venceu até hoje. Barra azul: o que já foi pago.">' +
            '<span class="esperado" style="width:' + Math.min(100, pctEsp).toFixed(1) + '%"></span>' +
            '<span class="real" style="width:' + Math.min(100, pctRec || 0).toFixed(1) + '%"></span></div>',
            '<span class="pf-regime">competência</span> Caixa do mês: <b>' + PF.brl(k.caixa.valor) + '</b>');

        var a = k.em_atraso;
        kpi('k-atraso', 'bi-exclamation-diamond', 'Em atraso (ativos)', PF.brl(a.valor),
            PF.num(a.clientes) + ' cliente(s) · ' + PF.num(a.titulos) + ' título(s)',
            '<span class="pf-regime">hoje</span> ver lista <i class="bi bi-arrow-right"></i>');

        var e = k.pago_em_dia;
        if (e.atual) {
            var spark = '<span class="pf-spark" aria-hidden="true">' + e.tendencia.map(function (t) {
                return '<span style="height:' + Math.max(2, Math.round(t.pct * 0.18)) + 'px" title="' + PF.mesCurto(t.mes) + ': ' + PF.pct(t.pct) + '"></span>';
            }).join('') + '</span>';
            kpi('k-emdia', 'bi-calendar-check', 'Pago em dia', PF.pct(e.atual.pct),
                'safra de ' + PF.mesCurto(e.atual.mes) + ' · ' + PF.num(e.atual.qtd_em_dia) + ' de ' + PF.num(e.atual.qtd) + ' títulos',
                spark + ' ' + variacao(e.atual.pct, e.anterior ? e.anterior.pct : null, true, ' p.p.'));
        } else {
            kpi('k-emdia', 'bi-calendar-check', 'Pago em dia', '—', 'nenhuma safra completa processada',
                '<span class="pf-regime">competência</span>');
        }

        var b = k.bloqueados;
        kpi('k-bloq', 'bi-slash-circle', 'Bloqueados', PF.num(b.clientes),
            b.clientes ? 'devem ' + PF.brl(b.valor) : 'nenhum cliente ativo bloqueado',
            '<span class="pf-regime">hoje</span> clientes ativos');

        var r = k.recuperavel;
        kpi('k-recup', 'bi-arrow-repeat', 'Recuperação de crédito', PF.brl(r.valor),
            PF.num(r.clientes) + ' desativado(s) · ' + PF.num(r.titulos) + ' título(s)',
            r.pos_titulos ? '<span title="Títulos com vencimento depois da desativação não são dívida.">+ ' + PF.brlCurto(r.pos_valor) + ' gerados após desativar (fora)</span>'
                          : '<span class="pf-regime">hoje</span>');

        var est = EST.agregador;
        $('#pf-selo').html('Calculado ' + PF.dataHora(k.calculado_em).slice(-5) +
            (est && est.fato_dia_ate ? ' · processado até ' + PF.data(est.fato_dia_ate) : ' · <b>indicadores não processados</b>'));
    }

    function carregarKpis() {
        kpisCarregando();
        return PF.api('visao.kpis', periodo()).then(renderKpis).catch(function (e) {
            $('#pf-kpis .pf-kpi').addClass('erro').html('<div class="pf-kpi-valor">' + PF.esc(e.mensagem) + '</div>');
        });
    }

    // ------------------------------------------------------------------ graficos
    function carregarCurva() {
        var $b = $('#b-curva');
        PF.estado($b, 'carregando');
        PF.api('visao.curva', periodo()).then(function (d) {
            PF.estado($b, null);
            var total = d.previsto[d.previsto.length - 1] || 0;
            if (!total) { PF.estado($b, 'vazio', 'Nenhum título com vencimento neste mês.'); return; }
            PFG.curva('g-curva', d);
            $('#n-curva').text(d.recebido_depois_do_mes > 0
                ? PF.brl(d.recebido_depois_do_mes) + ' dos títulos deste mês foram pagos depois do fim do mês e não aparecem na curva.' : '');
            PFG.tabela($('#t-curva'), ['Dia', 'Previsto acumulado', 'Recebido acumulado'],
                d.dias.map(function (dia, i) { return [dia, PF.brl(d.previsto[i]), d.recebido[i] === null ? '—' : PF.brl(d.recebido[i])]; }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarCurva); });
    }

    function carregarAging() {
        var $b = $('#b-aging');
        PF.estado($b, 'carregando');
        var p = periodo(); p.situacao = SIT_AGING;
        PF.api('visao.aging', p).then(function (d) {
            PF.estado($b, null);
            var tot = d.faixas.reduce(function (s, f) { return s + f.titulos; }, 0);
            if (!tot) { PF.estado($b, 'vazio', 'Nenhum título vencido. Ótimo.'); PFG.tabela($('#t-aging'), ['Faixa'], []); return; }
            PFG.barrasH('g-aging', d.faixas.map(function (f) {
                return { rotulo: f.rotulo + ' dias', valor: f.valor, de: f.de, ate: f.ate,
                         dica: PF.brl(f.valor) + ' · ' + f.titulos + ' título(s) · ' + f.clientes + ' cliente(s)' };
            }), SIT_AGING === 'ativos' ? PFG.COR.vermelho : PFG.COR.cinza, function (it) {
                var q = 'situacao=' + SIT_AGING + '&dias_min=' + it.de + (it.ate !== null ? '&dias_max=' + it.ate : '');
                window.location.href = 'inadimplencia.php?' + q;
            });
            PFG.tabela($('#t-aging'), ['Faixa (dias)', 'Valor', 'Títulos', 'Clientes'],
                d.faixas.map(function (f) { return [f.rotulo, PF.brl(f.valor), f.titulos, f.clientes]; }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarAging); });
    }

    function desenharSafra() {
        var s = ULT_SAFRA;
        PFG.empilhado('g-safra', s.map(function (x) { return PF.mesCurto(x.mes); }), [
            { rotulo: 'Pago em dia', cor: PFG.COR.verde, valores: s.map(function (x) { return x.em_dia; }) },
            { rotulo: 'Pago com atraso', cor: PFG.COR.ambar, valores: s.map(function (x) { return x.atraso; }) },
            { rotulo: 'Vencido em aberto', cor: PFG.COR.vermelho, valores: s.map(function (x) { return x.vencido; }) },
            { rotulo: 'Baixa sem pagamento', cor: PFG.COR.cinza, valores: s.map(function (x) { return x.baixa; }) },
            { rotulo: 'A vencer', cor: PFG.COR.azulClaro, valores: s.map(function (x) { return x.a_vencer; }) }
        ], MODO_SAFRA, null, function (i) {
            var x = s[i];
            return (x.pct_em_dia !== null ? 'Pago em dia: ' + PF.pct(x.pct_em_dia) + (x.madura ? '' : ' (parcial)') : '') +
                   (x.inad_d30 !== null ? '\nInadimplência D+30: ' + PF.pct(x.inad_d30, 2) : '') +
                   (x.inad_d90 !== null ? '\nInadimplência D+90: ' + PF.pct(x.inad_d90, 2) : '');
        });
    }

    function carregarSafra() {
        var $b = $('#b-safra');
        PF.estado($b, 'carregando');
        PF.api('visao.safra', periodo()).then(function (d) {
            PF.estado($b, null);
            ULT_SAFRA = d.safras;
            if (!d.safras.length) {
                PF.estado($b, 'vazio', EST.agregador && EST.agregador.nunca_rodou
                    ? 'Os indicadores ainda não foram processados. Em Configurações, clique em "Processar agora".'
                    : 'Sem títulos nos últimos 12 meses.');
                return;
            }
            desenharSafra();
            PFG.tabela($('#t-safra'), ['Mês', 'Previsto', 'Em dia', 'Com atraso', 'Vencido', 'Baixa', 'A vencer', '% em dia', 'Inad. D+30', 'Inad. D+90'],
                d.safras.map(function (x) {
                    return [PF.mesCurto(x.mes), PF.brl(x.valor), PF.brl(x.em_dia), PF.brl(x.atraso), PF.brl(x.vencido), PF.brl(x.baixa),
                            PF.brl(x.a_vencer), PF.pct(x.pct_em_dia), PF.pct(x.inad_d30, 2), PF.pct(x.inad_d90, 2)];
                }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarSafra); });
    }

    // ------------------------------------------------------------------ alertas
    function carregarAlertas() {
        PF.api('visao.alertas').then(function (d) {
            var a = d.alertas;
            $('#a-conta').text(a.length);
            if (!a.length) {
                $('#b-alertas').html('<div class="pf-tudo-certo"><i class="bi bi-check-circle-fill"></i> Nenhum alerta. Bloqueios, cortes e cadastro coerentes.</div>');
                return;
            }
            var icones = { erro: 'bi-x-octagon-fill', aviso: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
            $('#b-alertas').html('<ul class="pf-alertas">' + a.map(function (x) {
                var ver = (x.qtd && EST.nominal && x.id !== 'agregador')
                    ? '<button type="button" class="lc-btn-outline" data-alerta="' + PF.esc(x.id) + '" data-titulo="' + PF.esc(x.titulo) + '">Ver</button>' : '';
                var link = x.id === 'agregador' && EST.admin ? '<a class="lc-btn-outline" href="configuracoes.php">Abrir</a>' : '';
                return '<li class="pf-alerta ' + PF.esc(x.nivel) + '"><i class="bi ' + icones[x.nivel] + '" aria-hidden="true"></i>' +
                       '<div class="pf-alerta-txt"><div class="pf-alerta-tit">' + PF.esc(x.titulo) + '</div>' +
                       '<div class="pf-alerta-desc">' + PF.esc(x.texto) + '</div></div>' +
                       (x.qtd !== null ? '<span class="pf-alerta-qtd">' + PF.num(x.qtd) + '</span>' : '') + ver + link + '</li>';
            }).join('') + '</ul>');
        }).catch(function (e) {
            $('#b-alertas').html('<div class="pf-aviso erro" style="margin:10px 16px"><i class="bi bi-x-octagon"></i><div>' + PF.esc(e.mensagem) + '</div></div>');
        });
    }

    function abrirAlerta(tipo, titulo) {
        $('#ma-titulo').text(titulo);
        $('#ma-linhas').html('<tr><td colspan="2" class="lc-loading">Carregando...</td></tr>');
        PF.abrirModal('pf-modal-alerta');
        PF.api('visao.alerta_lista', { tipo: tipo }).then(function (d) {
            $('#ma-linhas').html(d.linhas.length ? d.linhas.map(function (l) {
                return '<tr><td>' + PF.linkCliente(l.uuid, l.nome) + ' <span class="pf-sub">' + PF.esc(l.login) + '</span></td><td>' + PF.esc(l.detalhe) + '</td></tr>';
            }).join('') : '<tr><td colspan="2" class="lc-empty">Nenhum cliente.</td></tr>');
        }).catch(function (e) { $('#ma-linhas').html('<tr><td colspan="2">' + PF.esc(e.mensagem) + '</td></tr>'); });
    }

    // ------------------------------------------------------------------ montagem
    function carregarTudo() {
        carregarKpis();
        carregarCurva();
        carregarAging();
        carregarSafra();
        carregarAlertas();
    }

    function iniciar(d) {
        EST = d;
        $('#pf-sem-admin').toggle(!d.ha_admin);
        $('#pf-sem-acesso').toggle(d.ha_admin && !d.pode_ver);
        if (!d.pode_ver) return;
        $('#pf-conteudo').show();
        $('#f-mes').val(d.hoje.slice(0, 7)).attr('max', d.hoje.slice(0, 7));
        d.dias_venc.forEach(function (n) { $('#f-dv').append('<option value="' + n + '">Dia ' + n + '</option>'); });
        carregarTudo();
    }

    $(function () {
        PF.api('inicio.estado').then(iniciar).catch(PF.erro);

        $('#f-mes, #f-dv').on('change', function () { carregarKpis(); carregarCurva(); carregarAging(); carregarSafra(); });
        $('#btn-atualizar').on('click', carregarTudo);
        $('[data-sit]').on('click', function () {
            SIT_AGING = $(this).attr('data-sit');
            $('[data-sit]').removeClass('ativo').attr('aria-pressed', 'false');
            $(this).addClass('ativo').attr('aria-pressed', 'true');
            carregarAging();
        });
        $('[data-modo]').on('click', function () {
            MODO_SAFRA = $(this).attr('data-modo');
            $('[data-modo]').removeClass('ativo').attr('aria-pressed', 'false');
            $(this).addClass('ativo').attr('aria-pressed', 'true');
            if (ULT_SAFRA && ULT_SAFRA.length) desenharSafra();
        });
        $('#pf-kpis').on('click keydown', '.pf-kpi.link', function (ev) {
            if (ev.type === 'keydown' && ev.key !== 'Enter') return;
            var dv = $('#f-dv').val();
            var href = $(this).attr('data-href');
            window.location.href = href + (dv ? (href.indexOf('?') > 0 ? '&' : '?') + 'venc=' + dv : '');
        });
        $('#b-alertas').on('click', '[data-alerta]', function () { abrirAlerta($(this).attr('data-alerta'), $(this).attr('data-titulo')); });
        $('#btn-assumir').on('click', function () {
            PF.confirmar({ titulo: 'Assumir administração', msg: 'Você será o administrador deste addon.',
                           sub: 'Fica registrado na auditoria e só pode ser feito enquanto não houver administrador.', textoOk: 'Assumir'
            }).then(function (ok) {
                if (!ok) return;
                PF.loading('Gravando...');
                PF.api('permissao.assumir_admin', {}, 'POST')
                    .then(function () { PF.toast('ok', 'Você agora é o administrador do addon.'); return PF.api('inicio.estado').then(iniciar); })
                    .catch(PF.erro).finally(PF.fimLoading);
            });
        });
    });
})();
</script>
</body>
</html>
