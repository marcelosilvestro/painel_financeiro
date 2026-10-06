<?php
require_once __DIR__ . '/config.php';
$pf_pagina = 'inadimplencia';
$pf_perm_pagina = 'ver';
include('nav/header.php');
$pf_nominal = $pf_schema_ok && Permissao::tem('nominal');
$pf_exportar = $pf_schema_ok && Permissao::tem('exportar');
?>
<body data-ext="<?= pf_h($ext_mk) ?>">
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 pf-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$pf_bloqueado): ?>

    <form class="pf-filtros" id="pf-filtros" role="search" onsubmit="return false">
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-situacao">Clientes</label>
            <select class="lc-input-date" id="f-situacao" name="situacao">
                <option value="ativos">Ativos</option><option value="desativados">Desativados</option><option value="todos">Todos</option>
            </select>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-venc">Vencimento</label>
            <select class="lc-input-date" id="f-venc" name="venc"><option value="">Todos</option></select>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-plano">Plano</label>
            <select class="lc-input-date" id="f-plano" name="plano"><option value="">Todos</option></select>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-cidade">Cidade</label>
            <select class="lc-input-date" id="f-cidade" name="cidade"><option value="">Todas</option></select>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-bairro">Bairro</label>
            <select class="lc-input-date" id="f-bairro" name="bairro"><option value="">Todos</option></select>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-vendedor">Vendedor</label>
            <select class="lc-input-date" id="f-vendedor" name="vendedor"><option value="">Todos</option></select>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-dias_min">Atraso (dias)</label>
            <span style="display:flex;gap:4px">
                <input type="number" min="0" class="lc-input-date" id="f-dias_min" name="dias_min" placeholder="de" style="width:64px">
                <input type="number" min="0" class="lc-input-date" id="f-dias_max" name="dias_max" placeholder="até" style="width:64px" aria-label="Atraso até (dias)">
            </span>
        </div>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-bloqueado">Bloqueio</label>
            <select class="lc-input-date" id="f-bloqueado" name="bloqueado">
                <option value="">Todos</option><option value="sim">Bloqueados</option><option value="nao">Liberados</option>
            </select>
        </div>
        <?php if ($pf_nominal): ?>
        <div class="lc-filter-group">
            <label class="lc-filter-label" for="f-busca">Nome ou login</label>
            <input type="search" class="lc-input-date" id="f-busca" name="busca" maxlength="60" style="width:160px">
        </div>
        <?php endif; ?>
        <label class="pf-chk" style="align-self:center"><input type="checkbox" id="f-reincidente" name="reincidente" value="1"> Só reincidentes</label>
        <div class="pf-selo">
            <button type="button" class="lc-btn-outline" id="btn-limpar">Limpar</button>
            <?php if ($pf_exportar): ?>
            <button type="button" class="lc-btn-black" id="btn-exportar" title="Fica registrado na auditoria"><i class="bi bi-download"></i> Exportar CSV</button>
            <?php endif; ?>
        </div>
    </form>

    <div class="pf-kpis" id="pf-resumo">
        <div class="pf-kpi" id="r-valor"></div>
        <div class="pf-kpi" id="r-clientes"></div>
        <div class="pf-kpi" id="r-dias"></div>
        <div class="pf-kpi" id="r-reinc"></div>
        <div class="pf-kpi" id="r-bloq"></div>
    </div>

    <div class="pf-g11">
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-pin-map"></i> Onde se concentra
                    <span class="pf-pergunta">quais grupos devem mais?</span></span>
                <span class="pf-cab-acoes">
                    <select class="pf-mini-btn" id="f-dim" aria-label="Agrupar por">
                        <option value="plano">por plano</option><option value="bairro">por bairro</option><option value="cidade">por cidade</option>
                        <option value="vendedor">por vendedor</option><option value="venc">por dia de vencimento</option>
                    </select>
                    <button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-dim')"><i class="bi bi-table"></i></button>
                </span>
            </div>
            <div class="pf-graf" id="b-dim"><canvas id="g-dim"></canvas></div>
            <div class="pf-nota" id="n-dim">Clique numa barra para filtrar a lista.</div>
            <div class="pf-tab-alt" id="t-dim"></div>
        </div>
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-calendar2-range"></i> Quando os clientes pagam
                    <span class="pf-pergunta">a régua de cobrança está no ponto?</span></span>
                <span class="pf-cab-acoes"><button type="button" class="pf-mini-btn" aria-pressed="false" onclick="PFG.alternarTabela(this, 't-dist')"><i class="bi bi-table"></i></button></span>
            </div>
            <div class="pf-graf" id="b-dist"><canvas id="g-dist"></canvas></div>
            <div class="pf-nota" id="n-dist"></div>
            <div class="pf-tab-alt" id="t-dist"></div>
        </div>
    </div>

    <div class="lc-section-panel pf-painel">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-list-ul"></i> Clientes com título vencido</span>
            <span class="lc-badge-count" id="l-conta">–</span>
        </div>
        <?php if ($pf_nominal): ?>
        <div class="lc-table-wrap">
            <table class="lc-table pf-tabela">
                <thead><tr>
                    <th class="ord" data-ordem="nome" scope="col">Cliente</th>
                    <th class="prio-7" scope="col">Plano</th>
                    <th class="prio-6" scope="col">Bairro</th>
                    <th class="num prio-8" scope="col">Venc.</th>
                    <th class="num ord" data-ordem="titulos" scope="col">Títulos</th>
                    <th class="num ord" data-ordem="valor" scope="col">Valor</th>
                    <th class="num ord" data-ordem="dias" scope="col">Atraso</th>
                    <th class="num ord prio-7" data-ordem="atrasos" scope="col" title="Atrasos entre os últimos títulos vencidos">Atrasos</th>
                    <th class="prio-6" scope="col">Últ. pagamento</th>
                </tr></thead>
                <tbody id="l-linhas"><tr><td colspan="9" class="lc-loading">Carregando...</td></tr></tbody>
            </table>
        </div>
        <div class="pf-paginacao" id="l-pag"></div>
        <?php else: ?>
        <div class="pf-aviso" style="margin:12px 16px"><i class="bi bi-eye-slash"></i>
            <div>Você vê os números agregados. A lista com o nome dos clientes exige o papel <b>Ver listas com nome</b> — peça ao administrador do addon.</div></div>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>
<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var NOMINAL = <?= $pf_nominal ? 'true' : 'false' ?>;
    var ORDEM = 'valor', DIR = 'desc', PAGINA = 1;
    var CAMPOS = ['situacao', 'venc', 'plano', 'cidade', 'bairro', 'vendedor', 'dias_min', 'dias_max', 'bloqueado', 'busca'];

    function filtros() {
        var f = {};
        CAMPOS.forEach(function (c) { var v = $('#f-' + c).val(); if (v !== undefined && v !== '') f[c] = v; });
        if ($('#f-reincidente').is(':checked')) f.reincidente = '1';
        return f;
    }

    /** Filtros vindos da URL (links da Visão Geral). */
    function lerUrl() {
        var q = new URLSearchParams(window.location.search);
        CAMPOS.forEach(function (c) { if (q.has(c)) $('#f-' + c).val(q.get(c)); });
        if (q.get('reincidente') === '1') $('#f-reincidente').prop('checked', true);
    }
    function gravarUrl() {
        var f = filtros();
        var q = new URLSearchParams(f).toString();
        history.replaceState(null, '', 'inadimplencia.php' + (q ? '?' + q : ''));
    }

    function kpi(id, ico, rotulo, valor, sub) {
        $('#' + id).html('<div class="pf-kpi-rotulo"><i class="bi ' + ico + '"></i> ' + PF.esc(rotulo) + '</div>' +
            '<div class="pf-kpi-valor">' + PF.esc(valor) + '</div>' + (sub ? '<div class="pf-kpi-sub">' + PF.esc(sub) + '</div>' : ''));
    }

    function carregarResumo() {
        $('#pf-resumo .pf-kpi').html('<div class="pf-esqueleto"></div>');
        PF.api('inad.resumo', filtros()).then(function (r) {
            kpi('r-valor', 'bi-exclamation-diamond', 'Em atraso', PF.brl(r.valor), PF.num(r.titulos) + ' título(s) vencido(s)');
            kpi('r-clientes', 'bi-people', 'Clientes', PF.num(r.clientes), 'com ao menos um título vencido');
            kpi('r-dias', 'bi-hourglass-split', 'Atraso típico', r.dias_mediana === null ? '—' : r.dias_mediana + ' dias', 'mediana do título mais antigo');
            kpi('r-reinc', 'bi-arrow-repeat', 'Reincidentes', PF.num(r.reincidentes),
                r.reincidencia.n + '+ atrasos nos últimos ' + r.reincidencia.m + ' títulos');
            kpi('r-bloq', 'bi-slash-circle', 'Bloqueados', PF.num(r.bloqueados), 'entre os clientes da lista');
        }).catch(function (e) { $('#pf-resumo .pf-kpi').addClass('erro').html('<div class="pf-kpi-valor">' + PF.esc(e.mensagem) + '</div>'); });
    }

    function carregarDim() {
        var $b = $('#b-dim'), dim = $('#f-dim').val();
        PF.estado($b, 'carregando');
        var p = filtros(); p.dim = dim;
        PF.api('inad.dimensao', p).then(function (d) {
            PF.estado($b, null);
            if (!d.itens.length) { PF.estado($b, 'vazio', 'Nenhum cliente com título vencido neste filtro.'); PFG.tabela($('#t-dim'), [], []); return; }
            PFG.barrasH('g-dim', d.itens.map(function (i) {
                return { rotulo: i.rotulo, valor: i.valor, dica: PF.brl(i.valor) + ' · ' + i.clientes + ' cliente(s)' };
            }), PFG.COR.azul, function (it) {
                if (it.rotulo === '(sem)') return;
                $('#f-' + dim).val(dim === 'venc' ? it.rotulo.replace('dia ', '') : it.rotulo);
                aplicar();
            });
            $('#n-dim').text((d.outros ? 'Mostrando os 12 maiores; ' + d.outros + ' outro(s) grupo(s) somados fora. ' : '') + 'Clique numa barra para filtrar a lista.');
            PFG.tabela($('#t-dim'), ['Grupo', 'Valor', 'Clientes'], d.itens.map(function (i) { return [i.rotulo, PF.brl(i.valor), i.clientes]; }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarDim); });
    }

    function carregarDist() {
        var $b = $('#b-dist');
        PF.estado($b, 'carregando');
        var p = {}; if ($('#f-venc').val()) p.dv = $('#f-venc').val();
        PF.api('inad.distribuicao', p).then(function (d) {
            PF.estado($b, null);
            if (!d.total) { PF.estado($b, 'vazio', 'Sem pagamentos processados. Em Configurações, clique em "Processar agora".'); return; }
            var rot = d.dias.map(function (x) { return x === 0 ? 'em dia' : (x === 31 ? '31+' : String(x)); });
            var marcos = d.corte_dia <= 31 ? [{ indice: d.corte_dia, rotulo: 'corte', cor: PFG.COR.vermelho }] : [];
            PFG.colunas('g-dist', rot, d.qtd, PFG.COR.azul, marcos, function (i) {
                return PF.num(d.qtd[i]) + ' título(s) · ' + PF.pct(d.acumulado_pct[i]) + ' pagos até aqui';
            });
            var emDia = d.total ? 100 * d.qtd[0] / d.total : 0;
            var ateCorte = d.acumulado_pct[Math.min(31, d.corte_dia - 1)];
            $('#n-dist').text('Títulos pagos das safras desde ' + PF.mesCurto(d.desde) + ', por dias depois do vencimento efetivo. ' +
                PF.pct(emDia) + ' pagam em dia; ' + PF.pct(ateCorte) + ' pagam antes do dia do corte (D+' + d.corte_dia + ').');
            PFG.tabela($('#t-dist'), ['Dias após o vencimento', 'Títulos', '% acumulado'],
                d.dias.map(function (x, i) { return [rot[i], PF.num(d.qtd[i]), PF.pct(d.acumulado_pct[i])]; }));
        }).catch(function (e) { PF.estado($b, 'erro', e.mensagem, carregarDist); });
    }

    function classeDias(d) { return d > 60 ? 'f2' : (d > 15 ? 'f1' : 'f0'); }

    function carregarLista() {
        if (!NOMINAL) { PF.api('inad.resumo', filtros()).then(function (r) { $('#l-conta').text(r.clientes); }); return; }
        var p = filtros(); p.ordem = ORDEM; p.dir = DIR; p.pagina = PAGINA;
        $('#l-linhas').html('<tr><td colspan="9" class="lc-loading">Carregando...</td></tr>');
        PF.api('inad.lista', p).then(function (d) {
            $('#l-conta').text(d.total);
            PAGINA = d.pagina;
            $('th.ord').each(function () {
                var o = $(this).attr('data-ordem');
                $(this).attr('aria-sort', o === ORDEM ? (DIR === 'asc' ? 'ascending' : 'descending') : 'none')
                    .find('.bi').remove();
                if (o === ORDEM) $(this).append(' <i class="bi bi-caret-' + (DIR === 'asc' ? 'up' : 'down') + '-fill"></i>');
            });
            if (!d.linhas.length) {
                $('#l-linhas').html('<tr><td colspan="9" class="lc-empty">Nenhum cliente com título vencido neste filtro.</td></tr>');
            } else {
                $('#l-linhas').html(d.linhas.map(function (c) {
                    var tags = (c.bloqueado ? '<span class="pf-tag bloq" title="bloqueado desde ' + PF.esc(PF.data(c.data_bloq)) + '">bloqueado</span>' : '') +
                               (c.reincidente ? '<span class="pf-tag reinc">reincidente</span>' : '') +
                               (!c.ativo ? '<span class="pf-tag desat">desativado</span>' : '');
                    return '<tr><td>' + PF.linkCliente(c.uuid, c.nome) + tags + '<div class="pf-sub">' + PF.esc(c.login) + '</div></td>' +
                        '<td class="prio-7">' + PF.esc(c.plano) + '</td><td class="prio-6">' + PF.esc(c.bairro) + '</td>' +
                        '<td class="num prio-8">' + (c.venc === null ? '—' : c.venc) + '</td>' +
                        '<td class="num">' + c.titulos + '</td><td class="num"><b>' + PF.brl(c.valor) + '</b></td>' +
                        '<td class="num"><span class="pf-dias ' + classeDias(c.dias) + '">' + c.dias + ' d</span><div class="pf-sub">desde ' + PF.data(c.mais_antigo) + '</div></td>' +
                        '<td class="num prio-7">' + c.atrasos + '</td>' +
                        '<td class="prio-6">' + PF.data(c.ultimo_pagamento) + '</td></tr>';
                }).join(''));
            }
            PF.paginacao($('#l-pag'), d.pagina, d.por_pagina, d.total, function (pg) { PAGINA = pg; carregarLista(); });
        }).catch(function (e) {
            $('#l-linhas').html('<tr><td colspan="9"><div class="pf-aviso erro" style="margin:8px 0"><i class="bi bi-x-octagon"></i><div>' + PF.esc(e.mensagem) + '</div></div></td></tr>');
        });
    }

    function aplicar() {
        PAGINA = 1;
        gravarUrl();
        carregarResumo();
        carregarDim();
        carregarLista();
    }

    function opcoes() {
        return PF.api('inad.opcoes').then(function (o) {
            function encher(id, lista, fmt) {
                var $s = $('#f-' + id);
                lista.forEach(function (v) { $s.append($('<option>').val(v).text(fmt ? fmt(v) : v)); });
            }
            encher('venc', o.dias_venc, function (v) { return 'Dia ' + v; });
            encher('plano', o.planos); encher('cidade', o.cidades); encher('bairro', o.bairros); encher('vendedor', o.vendedores);
        });
    }

    $(function () {
        opcoes().catch(PF.erro).finally(function () {
            lerUrl();
            aplicar();
            carregarDist();
        });
        var espera = null;
        $('#pf-filtros').on('change', 'select, input[type=checkbox]', function () {
            if (this.id === 'f-venc') carregarDist();
            aplicar();
        });
        $('#pf-filtros').on('input', 'input[type=number], input[type=search]', function () {
            clearTimeout(espera); espera = setTimeout(aplicar, 450);
        });
        $('#f-dim').on('change', carregarDim);
        $('#btn-limpar').on('click', function () {
            $('#pf-filtros')[0].reset(); $('#f-situacao').val('ativos'); carregarDist(); aplicar();
        });
        $('th.ord').on('click', function () {
            var o = $(this).attr('data-ordem');
            if (o === ORDEM) { DIR = DIR === 'asc' ? 'desc' : 'asc'; } else { ORDEM = o; DIR = o === 'nome' ? 'asc' : 'desc'; }
            PAGINA = 1; carregarLista();
        });
        $('#btn-exportar').on('click', function () {
            var p = filtros(); p.ordem = ORDEM; p.dir = DIR;
            PF.baixar('inad.exportar', p);
            PF.toast('info', 'Gerando o arquivo...', 'A exportação fica registrada na auditoria do addon.');
        });
    });
})();
</script>
</body>
</html>
