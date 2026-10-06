<?php
require_once __DIR__ . '/config.php';
$pf_pagina = 'agenda';
$pf_perm_pagina = 'ver';
include('nav/header.php');
?>
<body data-ext="<?= pf_h($ext_mk) ?>">
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 pf-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$pf_bloqueado): ?>

    <div class="pf-ag-topo">
        <div class="pf-ag-mes" role="group" aria-label="Mês">
            <button type="button" id="mes-ant" aria-label="Mês anterior">&#10094;</button>
            <span id="mes-rot">—</span>
            <button type="button" id="mes-prox" aria-label="Próximo mês">&#10095;</button>
        </div>
        <button type="button" class="lc-btn-outline" id="btn-hoje"><i class="bi bi-calendar-check"></i> Hoje</button>
        <span class="pf-ag-legenda" aria-hidden="true">
            <span class="pf-ev corte mini"><i class="bi bi-scissors"></i> corte</span>
            <span class="pf-ev conflito mini"><i class="bi bi-exclamation-triangle-fill"></i> corte em feriado</span>
            <span class="pf-ev venc mini"><i class="bi bi-check-circle-fill"></i> vencimento</span>
            <span class="pf-ev aviso mini"><i class="bi bi-envelope-fill"></i> aviso</span>
            <span class="pf-ev corte mini"><i class="bi bi-shield-check"></i> adiado pelo guardião</span>
        </span>
        <a class="lc-btn-outline" id="btn-feriados" href="#" style="margin-left:auto"><i class="bi bi-calendar-x"></i> Gerenciar feriados</a>
    </div>

    <div id="a-aviso-corte" class="pf-aviso" style="display:none"><i class="bi bi-info-circle"></i>
        <div>O corte automático está <b>desligado</b> no MK-AUTH (Provedor › Opções). Os cortes abaixo mostram quando ele cortaria se estivesse ligado.</div></div>

    <div class="pf-kpis" id="pf-kpis">
        <div class="pf-kpi" id="k-corte-hoje"></div>
        <div class="pf-kpi" id="k-corte-7"></div>
        <div class="pf-kpi" id="k-venc-7"></div>
        <div class="pf-kpi" id="k-regua"></div>
        <div class="pf-kpi" id="k-feriado"></div>
    </div>

    <div class="pf-ag-corpo">
        <div class="lc-section-panel pf-ag-cal">
            <div class="pf-ag-sem" aria-hidden="true"><span class="fds">Dom</span><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span class="fds">Sáb</span></div>
            <div class="pf-ag-grade" id="ag-grade"><div class="lc-loading" style="grid-column:1/-1">Carregando...</div></div>
            <ul class="pf-ag-lista" id="ag-lista"></ul>
            <div class="pf-nota" style="padding-top:8px">Projeção pela regra do MK-AUTH: vencimento no fim de semana vai para a segunda; corte = vencimento + carência + 1, só nos dias da semana com corte, <b>sem pular feriado</b>. Nos próximos 7 dias, o corte mostra quantos clientes serão cortados de verdade (têm título vencido). Clique num dia para ver o detalhe.</div>
        </div>

        <aside class="pf-ag-lado">
            <div class="lc-section-panel">
                <div class="lc-section-header"><span class="lc-section-title"><i class="bi bi-sliders"></i> Regras do MK-AUTH</span></div>
                <div id="lado-param" class="pf-ag-bloco"></div>
            </div>
            <div class="lc-section-panel">
                <div class="lc-section-header"><span class="lc-section-title"><i class="bi bi-whatsapp"></i> Comunicações do mês</span>
                    <span class="lc-badge-count" id="lado-com-tot">–</span></div>
                <ul class="pf-ag-itens" id="lado-com"></ul>
            </div>
            <div class="lc-section-panel">
                <div class="lc-section-header"><span class="lc-section-title"><i class="bi bi-chat-dots"></i> Efetividade da régua</span>
                    <span class="pf-res experimental">90 dias</span></div>
                <div class="lc-table-wrap"><table class="lc-table pf-tabela"><thead><tr>
                    <th scope="col">Aviso</th><th class="num" scope="col">Enviados</th><th class="num" scope="col">Falhas</th>
                    <th class="num" scope="col" title="Entre os entregues, títulos pagos em até 3 dias">Pagaram 3d</th>
                </tr></thead><tbody id="lado-regua"></tbody></table></div>
            </div>
            <div class="lc-section-panel">
                <div class="lc-section-header"><span class="lc-section-title"><i class="bi bi-calendar-x"></i> Próximos feriados</span></div>
                <ul class="pf-ag-itens" id="lado-fer"></ul>
            </div>
        </aside>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<div class="lc-overlay" id="pf-modal-dia">
    <div class="lc-modal-box" style="max-width:640px">
        <div class="lc-modal-header">
            <span class="lc-modal-title" id="md-titulo">Dia</span>
            <button type="button" class="lc-modal-close" aria-label="Fechar" onclick="PF.fecharModal('pf-modal-dia')">&times;</button>
        </div>
        <div class="lc-modal-body" id="md-corpo"></div>
        <div class="lc-modal-footer"><button type="button" class="lc-btn-cancel" onclick="PF.fecharModal('pf-modal-dia')">Fechar</button></div>
    </div>
</div>

<div class="lc-overlay" id="pf-modal-corte">
    <div class="lc-modal-box" style="max-width:720px">
        <div class="lc-modal-header">
            <span class="lc-modal-title" id="mc-titulo">Cortes</span>
            <button type="button" class="lc-modal-close" aria-label="Fechar" onclick="PF.fecharModal('pf-modal-corte')">&times;</button>
        </div>
        <div class="lc-modal-body" style="padding:0">
            <div class="lc-table-wrap" style="max-height:60vh;overflow:auto">
                <table class="lc-table pf-tabela"><thead><tr><th scope="col">Cliente</th><th scope="col">Venceu</th><th class="num" scope="col">Títulos</th><th class="num" scope="col">Valor</th></tr></thead>
                <tbody id="mc-linhas"></tbody></table>
            </div>
        </div>
        <div class="lc-modal-footer"><button type="button" class="lc-btn-cancel" onclick="PF.fecharModal('pf-modal-corte')">Fechar</button></div>
    </div>
</div>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    var SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    var SEM_ABREV = ['', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
    var MES = null, DADOS = null, CORTES = null, HOJE = null;

    function kpi(id, ico, rotulo, valor, sub) {
        $('#' + id).html('<div class="pf-kpi-rotulo"><i class="bi ' + ico + '"></i> ' + PF.esc(rotulo) + '</div>' +
            '<div class="pf-kpi-valor">' + PF.esc(valor) + '</div>' + (sub ? '<div class="pf-kpi-sub">' + PF.esc(sub) + '</div>' : ''));
    }
    function rotDias(d) { return d > 0 ? 'D+' + d : (d < 0 ? 'D' + d : 'D0'); }
    function dmy(iso) { return PF.data(iso).slice(0, 5); }
    function dowDe(iso) { return new Date(iso + 'T12:00:00').getDay(); }

    // ------------------------------------------------------------------ selos
    function selo(e) {
        if (e.tipo === 'corte') {
            var cls = e.conflito ? 'conflito' : 'corte';
            var ico = e.conflito ? 'bi-exclamation-triangle-fill pf-pulsa' : 'bi-scissors';
            var sub = e.real !== null && e.real !== undefined ? (e.real + ' serão cortados') : (e.clientes + ' cli · projeção');
            if (e.adiado_de) { ico = 'bi-shield-check'; sub = 'adiado de ' + dmy(e.adiado_de) + ' · ' + sub; }
            return '<span class="pf-ev ' + cls + '"' + (e.adiado_de ? ' title="O guardião suspende o corte do MK-AUTH no feriado ' + PF.esc(e.adiado_por) + '"' : '') +
                   '><i class="bi ' + ico + '"></i> Corte V' + e.venc + ' · ' + e.dias_corte + 'd<small>' +
                   (e.conflito ? 'feriado! ' : '') + sub + '</small></span>';
        }
        if (e.tipo === 'venc') {
            var s = e.titulos ? (PF.brlCurto(e.valor) + (e.pct_pago !== null ? ' · ' + PF.pct(e.pct_pago, 0) + ' pago' : '')) : (e.clientes + ' cli');
            return '<span class="pf-ev venc"><i class="bi bi-check-circle-fill"></i> Venc. dia ' + e.venc + ' · ' + (e.titulos || e.clientes) + '<small>' + s + '</small></span>';
        }
        var env = e.envio ? ('<small>✓' + (e.envio.enviados - e.envio.falhas) + (e.envio.falhas ? ' ✗' + e.envio.falhas : '') + '</small>') : '';
        return '<span class="pf-ev aviso"><i class="bi bi-envelope-fill"></i> Aviso ' + rotDias(e.dias) + ' (V' + e.venc + ')' + env + '</span>';
    }

    function desenhar(d) {
        DADOS = d;
        var primeiro = d.dias[0].dow, html = '', lista = '';
        for (var i = 0; i < primeiro; i++) html += '<div class="pf-ag-dia vazio"></div>';
        d.dias.forEach(function (x, idx) {
            var cls = ['pf-ag-dia'];
            if (x.fds) cls.push('fds');
            if (x.feriado) cls.push('feriado');
            if (x.data === d.hoje) cls.push('hoje');
            if (x.data < d.hoje) cls.push('passado');
            var aria = x.dia + ' de ' + MESES[d.mes - 1] + ', ' + SEMANA[x.dow] + (x.feriado ? ', feriado ' + x.feriado.nome : '') +
                       (x.eventos.length ? ', ' + x.eventos.length + ' evento(s)' : '');
            html += '<div class="' + cls.join(' ') + '" role="button" tabindex="0" data-i="' + idx + '" aria-label="' + PF.esc(aria) + '">' +
                '<div class="pf-ag-dia-cab"><b>' + x.dia + '</b>' + (x.feriado ? '<span>' + PF.esc(x.feriado.nome) + '</span>' : '') + '</div>' +
                x.eventos.map(selo).join('') + '</div>';
            if (x.eventos.length || x.feriado) {
                lista += '<li data-i="' + idx + '" tabindex="0"><div class="pf-ag-lista-dia"><b>' + dmy(x.data) + '</b> ' + SEMANA[x.dow] +
                    (x.feriado ? ' <span class="pf-tag reinc">' + PF.esc(x.feriado.nome) + '</span>' : '') + '</div>' + x.eventos.map(selo).join('') + '</li>';
            }
        });
        $('#ag-grade').html(html);
        $('#ag-lista').html(lista || '<li class="lc-empty">Nenhum evento neste mês.</li>');
        $('#mes-rot').text(MESES[d.mes - 1] + ' ' + d.ano);

        var p = d.parametros;
        $('#a-aviso-corte').toggle(!p.corte_automatico);
        $('#lado-param').html('<div><span class="pf-sub">Carência padrão</span><b>' + p.corte_padrao + ' dias</b></div>' +
            '<div><span class="pf-sub">Corta em</span><b>' + p.dias_semana_corte.map(function (n) { return SEM_ABREV[n]; }).join(', ') + '</b></div>' +
            '<div><span class="pf-sub">Corte automático</span><b>' + (p.corte_automatico ? 'ligado' : 'desligado') + '</b></div>' +
            guardiaoHtml(d.guardiao));

        var com = [], tot = 0;
        d.dias.forEach(function (x) {
            var av = x.eventos.filter(function (e) { return e.tipo === 'aviso'; });
            if (!av.length) return;
            tot += av.length;
            var ok = 0, err = 0, passou = false;
            av.forEach(function (e) { if (e.envio) { passou = true; ok += e.envio.enviados - e.envio.falhas; err += e.envio.falhas; } });
            com.push('<li><div style="flex:1"><b>' + dmy(x.data) + '</b> <span class="pf-sub">' + av.map(function (e) { return rotDias(e.dias) + ' V' + e.venc; }).join(', ') + '</span></div>' +
                (passou ? '<span class="pf-res ok">✓ ' + ok + '</span>' + (err ? ' <span class="pf-res erro">✗ ' + err + '</span>' : '') : '<span class="pf-res pendente">agendado</span>') + '</li>');
        });
        $('#lado-com').html(com.join('') || '<li class="pf-sub">Nenhum aviso neste mês.</li>');
        $('#lado-com-tot').text(tot);

        $('#lado-fer').html(d.proximos_feriados.length ? d.proximos_feriados.map(function (f) {
            return '<li><span class="pf-ag-fer-dia">' + dmy(f.data) + '</span><div style="flex:1">' + PF.esc(f.nome) +
                   '<div class="pf-sub">' + SEMANA[dowDe(f.data)] + ' · ' + PF.esc(f.abrangencia) + '</div></div></li>';
        }).join('') : '<li class="pf-sub">Nenhum feriado futuro cadastrado.</li>');
        var prox = d.proximos_feriados[0];
        kpi('k-feriado', 'bi-calendar-x', 'Próximo feriado', prox ? dmy(prox.data) : '—', prox ? prox.nome : 'nenhum cadastrado');

        var $f = $('#btn-feriados');
        if (d.calendario_instalado) {
            $f.attr('href', '../calendario/index.php?voltar=' + encodeURIComponent('../painel_financeiro/agenda.php?mes=' + MES)).removeClass('pf-desab').removeAttr('title');
        } else {
            $f.attr('href', '#').addClass('pf-desab').attr('title', 'Instale o addon Calendário de Feriados para gerenciar os feriados');
        }
    }

    function guardiaoHtml(g) {
        if (!g) return '';
        var h = '<div><span class="pf-sub">Guardião de feriado</span><b>' + (g.ligado ? 'ligado' : 'desligado') + '</b></div>';
        if (g.ligado && g.suspenso_desde) h += '<div class="pf-sub" style="color:#3266ad"><i class="bi bi-shield-check"></i> Corte do MK-AUTH suspenso hoje' + (g.feriado_hoje ? ' (' + PF.esc(g.feriado_hoje.nome) + ')' : '') + '.</div>';
        if (!g.ligado) h += '<div class="pf-sub">Ligue em Configurações › Cobrança para o corte nunca cair num feriado, sem mexer na carência dos clientes.</div>';
        if (g.problema) h += '<div class="pf-ag-alerta"><i class="bi bi-exclamation-triangle-fill"></i> ' + PF.esc(g.problema) + '</div>';
        return h;
    }

    function carregarMes() {
        $('#ag-grade').html('<div class="lc-loading" style="grid-column:1/-1">Carregando...</div>');
        history.replaceState(null, '', 'agenda.php?mes=' + MES);
        return PF.api('ag.mes', { mes: MES }).then(desenhar).catch(function (e) {
            $('#ag-grade').html('<div class="pf-aviso erro" style="grid-column:1/-1;margin:12px"><i class="bi bi-x-octagon"></i><div>' + PF.esc(e.mensagem) + '</div></div>');
        });
    }

    function andarMes(n) {
        var p = MES.split('-'), d = new Date(+p[0], +p[1] - 1 + n, 1);
        MES = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        carregarMes();
    }

    // ------------------------------------------------------------------ KPIs operacionais
    function carregarKpis() {
        PF.api('ag.cortes', { dias: 7 }).then(function (d) {
            CORTES = d;
            var hoje = d.dias[0], tot = 0, val = 0;
            d.dias.forEach(function (x) { tot += x.clientes; val += x.valor; });
            kpi('k-corte-hoje', 'bi-scissors', 'Corte hoje', PF.num(hoje.clientes), hoje.clientes ? PF.brl(hoje.valor) + ' em atraso' : 'ninguém');
            kpi('k-corte-7', 'bi-calendar-week', 'Cortes em 7 dias', PF.num(tot), tot ? PF.brl(val) + ' em atraso' : 'ninguém');
        }).catch(PF.erro);
        PF.api('ag.vencimentos', { dias: 7 }).then(function (d) {
            var t = 0, q = 0;
            d.dias.forEach(function (x) { t += x.valor; q += x.qtd; });
            kpi('k-venc-7', 'bi-calendar-event', 'Vence em 7 dias', PF.brl(t), PF.num(q) + ' título(s)');
        }).catch(PF.erro);
        PF.api('ag.regua', { dias: 90 }).then(function (d) {
            var at = d.avisos.filter(function (a) { return a.ativo; });
            kpi('k-regua', 'bi-chat-dots', 'Avisos ativos', PF.num(at.length), at.map(function (a) { return rotDias(a.dias); }).join(', ') || 'nenhum');
            var g = d.efetividade.grupos;
            $('#lado-regua').html(g.length ? g.map(function (x) {
                return '<tr><td>' + (x.dias === null ? 'Outros' : rotDias(x.dias)) + '</td><td class="num">' + PF.num(x.enviados) + '</td>' +
                    '<td class="num">' + PF.pct(x.pct_falha) + '</td><td class="num"><b>' + PF.pct(x.pct_pagou) + '</b></td></tr>';
            }).join('') : '<tr><td colspan="4" class="lc-empty">Sem avisos com título nos últimos 90 dias.</td></tr>');
        }).catch(PF.erro);
    }

    // ------------------------------------------------------------------ detalhe do dia
    function abrirDia(i) {
        var x = DADOS.dias[i], pode = DADOS.pode, h = '';
        $('#md-titulo').text(PF.data(x.data) + ' — ' + SEMANA[x.dow]);
        if (x.feriado) {
            h += '<div class="pf-aviso" style="margin-bottom:10px"><i class="bi bi-calendar-x"></i><div><b>Feriado ' + PF.esc(x.feriado.abrangencia) + ':</b> ' + PF.esc(x.feriado.nome) + '</div></div>';
        }
        if (!x.corta && !x.fds) h += '<div class="pf-sub" style="margin-bottom:8px">O MK-AUTH não corta neste dia da semana.</div>';
        if (!x.eventos.length) h += '<div class="lc-empty">Nenhum vencimento, aviso ou corte neste dia.</div>';
        x.eventos.forEach(function (e, j) {
            h += '<div class="pf-ag-det">' + selo(e) + '<div class="pf-ag-det-txt">';
            if (e.tipo === 'corte') {
                h += 'Clientes com vencimento dia ' + e.venc + ' e ' + e.dias_corte + ' dias de carência (vencimento de ' + PF.data(e.nominal) + '). ' +
                     (e.real !== null && e.real !== undefined ? '<b>' + e.real + '</b> têm título vencido e serão cortados.' : e.clientes + ' clientes no grupo (projeção).');
                if (e.conflito) h += '<div class="pf-ag-alerta"><i class="bi bi-exclamation-triangle-fill"></i> O corte cai no feriado <b>' + PF.esc(e.conflito) + '</b>. O MK-AUTH corta mesmo assim: ligue o guardião de feriado em Configurações › Cobrança.</div>';
                if (e.adiado_de) h += '<div class="pf-sub" style="margin-top:4px"><i class="bi bi-shield-check"></i> Cairia em ' + PF.data(e.adiado_de) + ' (' + PF.esc(e.adiado_por) + '): o guardião suspende o corte do MK-AUTH nesse dia, e ele corta aqui.</div>';
                h += '<div class="pf-ag-acoes">';
                if (pode.nominal && e.real) h += '<button type="button" class="lc-btn-outline" data-ver-corte="' + j + '">Ver clientes</button>';
                h += '</div>';
            } else if (e.tipo === 'venc') {
                h += e.clientes + ' clientes ativos vencem no dia ' + e.venc + (e.nominal !== x.data ? ' (o dia ' + dmy(e.nominal) + ' caiu no fim de semana)' : '') + '.' +
                     (e.titulos ? ' ' + PF.num(e.titulos) + ' títulos, ' + PF.brl(e.valor) + (e.pct_pago !== null ? ', ' + PF.pct(e.pct_pago) + ' já pagos.' : '.') : '');
            } else {
                h += 'Aviso automático ' + rotDias(e.dias) + ' para quem vence no dia ' + e.venc + ' (' + PF.esc(e.canal) + ').' +
                     (e.envio ? ' Enviados ' + e.envio.enviados + ', falharam ' + e.envio.falhas + '.' : ' Ainda não enviado.');
            }
            h += '</div></div>';
        });
        h += '<div class="pf-nota" style="padding:10px 0 0">Esta agenda é só para consulta. Feriados se cadastram no Calendário Geral (botão "Gerenciar feriados").</div>';
        $('#md-corpo').html(h).data('dia', i);
        PF.abrirModal('pf-modal-dia');
    }

    function verCorte(e, data) {
        var dia = (CORTES && CORTES.dias || []).filter(function (c) { return c.data === data; })[0];
        var lista = dia ? dia.lista.filter(function (c) { return c.venc === e.venc; }) : [];
        $('#mc-titulo').text('Corte em ' + PF.data(data) + ' — vencimento dia ' + e.venc + ' (' + lista.length + ')');
        $('#mc-linhas').html(lista.map(function (c) {
            return '<tr><td>' + PF.linkCliente(c.uuid, c.nome) + '<div class="pf-sub">' + PF.esc(c.login) + '</div></td><td>' + PF.data(c.vencimento) +
                   '</td><td class="num">' + c.titulos + '</td><td class="num"><b>' + PF.brl(c.valor) + '</b></td></tr>';
        }).join('') || '<tr><td colspan="4" class="lc-empty">Nenhum cliente.</td></tr>');
        PF.abrirModal('pf-modal-corte');
    }

    $(function () {
        var q = new URLSearchParams(window.location.search);
        PF.api('inicio.estado').then(function (d) {
            HOJE = d.hoje;
            MES = /^\d{4}-\d{2}$/.test(q.get('mes') || '') ? q.get('mes') : d.hoje.slice(0, 7);
            carregarMes(); carregarKpis();
        }).catch(PF.erro);
        $('#mes-ant').on('click', function () { andarMes(-1); });
        $('#mes-prox').on('click', function () { andarMes(1); });
        $('#btn-hoje').on('click', function () { MES = HOJE.slice(0, 7); carregarMes(); });
        $('#btn-feriados').on('click', function (ev) { if ($(this).hasClass('pf-desab')) { ev.preventDefault(); PF.toast('info', $(this).attr('title')); } });
        $('#ag-grade, #ag-lista').on('click keydown', '[data-i]', function (ev) {
            if (ev.type === 'keydown' && ev.key !== 'Enter' && ev.key !== ' ') return;
            ev.preventDefault();
            abrirDia(+$(this).attr('data-i'));
        });
        $('#md-corpo').on('click', '[data-ver-corte]', function () {
            var x = DADOS.dias[$('#md-corpo').data('dia')];
            verCorte(x.eventos[+$(this).attr('data-ver-corte')], x.data);
        });
    });
})();
</script>
</body>
</html>
