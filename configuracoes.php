<?php
require_once __DIR__ . '/config.php';
$pf_pagina = 'configuracoes';
$pf_perm_pagina = 'ver';
include('nav/header.php');
$pf_admin = $pf_schema_ok && Permissao::tem('admin');
?>
<body data-ext="<?= pf_h($ext_mk) ?>">
<?php include('../../topo.php'); ?>

<div class="container-fluid px-3 py-0 pf-wrap">
<?php include('nav/abas.php'); ?>
<?php if (!$pf_bloqueado): ?>

    <?php if (!$pf_admin): ?>
    <div class="pf-aviso info"><i class="bi bi-info-circle"></i>
        <div>Somente o administrador do addon altera configurações, permissões e dispara o processamento. Você está vendo os valores em vigor.</div></div>
    <?php endif; ?>

    <div class="pf-g11">
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-cpu"></i> Processamento dos indicadores</span>
                <?php if ($pf_admin): ?>
                <span class="pf-cab-acoes">
                    <label class="pf-chk" style="font-weight:500;font-size:12px"><input type="checkbox" id="ag-completo"> refazer a história inteira</label>
                    <button type="button" class="lc-btn-black" id="btn-processar"><i class="bi bi-play-fill"></i> Processar agora</button>
                </span>
                <?php endif; ?>
            </div>
            <div id="pf-agregador" style="padding:12px 16px"><div class="lc-loading">Carregando...</div></div>
        </div>
        <div class="lc-section-panel">
            <div class="lc-section-header">
                <span class="lc-section-title"><i class="bi bi-box-arrow-in-down"></i> Lido do MK-AUTH</span>
                <span class="pf-sub">somente leitura</span>
            </div>
            <div id="pf-mkauth" style="padding:12px 16px"><div class="lc-loading">Carregando...</div></div>
        </div>
    </div>

    <div class="lc-section-panel pf-painel">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-sliders"></i> Configurações</span>
            <?php if ($pf_admin): ?>
            <button type="button" class="lc-btn-black" id="btn-salvar"><i class="bi bi-check2"></i> Salvar</button>
            <?php endif; ?>
        </div>
        <div id="pf-config"><div class="lc-loading">Carregando...</div></div>
    </div>

    <?php if ($pf_admin): ?>
    <div class="lc-section-panel pf-painel">
        <div class="lc-section-header">
            <span class="lc-section-title"><i class="bi bi-people"></i> Permissões</span>
            <button type="button" class="lc-btn-black" id="btn-novo-usuario"><i class="bi bi-person-plus"></i> Conceder acesso</button>
        </div>
        <div class="lc-table-wrap">
            <table class="lc-table">
                <thead><tr><th>Login</th><th class="prio-6">Nome</th><th>Papéis</th><th></th></tr></thead>
                <tbody id="pf-perms"><tr><td colspan="4" class="lc-loading">Carregando...</td></tr></tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
</div>

<?php include('../../baixo.php'); ?>
<?php include('nav/comum.php'); ?>

<?php if ($pf_admin): ?>
<div class="lc-overlay" id="modal-perm">
    <div class="lc-modal-box lg">
        <div class="lc-modal-header">
            <span class="lc-modal-title">Permissões do usuário</span>
            <button type="button" class="lc-modal-close" aria-label="Fechar" onclick="PF.fecharModal('modal-perm')">&times;</button>
        </div>
        <div class="lc-modal-body">
            <label class="lc-label" for="perm-login">Usuário do MK-AUTH</label>
            <select class="lc-input" id="perm-login"></select>
            <div class="lc-input-hint" id="perm-login-dica"></div>
            <label class="lc-label pf-mt">Papéis</label>
            <div class="pf-papeis-grid" id="perm-papeis"></div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="PF.fecharModal('modal-perm')">Cancelar</button>
            <button type="button" class="lc-btn-confirm" id="perm-salvar">Salvar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../../menu.js<?= $ext_mk ?>"></script>
<script>
(function () {
    var ADMIN = <?= $pf_admin ? 'true' : 'false' ?>;
    var itens = [];
    var permDados = null;
    var DIAS_SEMANA = ['', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];

    // ------------------------------------------------------------ processamento
    function renderAgregador(e) {
        var ok = e.ultima_ok, ult = e.ultima, det = null;
        try { det = ok && ult && ult.detalhe ? JSON.parse(ult.detalhe) : null; } catch (x) { det = null; }
        var linhas = [];
        if (e.rodando) {
            linhas.push('<span class="pf-res pendente">Processando agora</span>');
        } else if (e.nunca_rodou) {
            linhas.push('<span class="pf-res erro">Nunca processado</span> <span class="pf-sub">safras, pontualidade e recebimentos passados ficam vazios até o primeiro processamento.</span>');
        } else {
            linhas.push('<span class="pf-res ' + (e.desatualizado ? 'aviso' : 'ok') + '">' + (e.desatualizado ? 'Atrasado' : 'Em dia') + '</span> ' +
                        'último processamento em <b>' + PF.dataHora(ok.fim) + '</b> (' + PF.num(ok.duracao_ms) + ' ms)');
        }
        if (ult && ult.resultado === 'erro') {
            linhas.push('<span class="pf-res erro">A última tentativa falhou</span> <span class="pf-sub">' + PF.esc(ult.detalhe || '') + '</span>');
        }
        if (e.fato_dia_ate) linhas.push('Recebimentos agregados até <b>' + PF.data(e.fato_dia_ate) + '</b>; depois disso a tela lê ao vivo.');
        linhas.push('<span class="pf-sub">O processamento roda sozinho toda madrugada (cron instalado pelo instalador). Ele só LÊ as tabelas do MK-AUTH.</span>');
        $('#pf-agregador').html(linhas.map(function (l) { return '<div style="margin-bottom:6px;font-size:13px">' + l + '</div>'; }).join(''));
    }

    function carregarAgregador() {
        PF.api('agregador.estado').then(renderAgregador).catch(PF.erro);
    }

    function processar() {
        var completo = $('#ag-completo').is(':checked');
        PF.loading(completo ? 'Reprocessando toda a história...' : 'Processando...');
        PF.api('agregador.processar', { completo: completo ? '1' : '0' }, 'POST').then(function (r) {
            PF.toast('ok', 'Indicadores processados em ' + PF.num(r.ms) + ' ms.',
                     PF.num(r.linhas_dia) + ' dias de caixa, ' + PF.num(r.linhas_safra) + ' linhas de safra');
            renderAgregador(r.estado);
        }).catch(PF.erro).finally(PF.fimLoading);
    }

    // ------------------------------------------------------------ lido do MK-AUTH
    function renderMkauth(m) {
        var linhas = [
            ['Dias de vencimento', m.dias_venc.length ? m.dias_venc.join(', ') : '—'],
            ['Carência até o corte (padrão)', m.dias_corte + ' dias (corte no dia ' + (m.dias_corte + 1) + ' após o vencimento)'],
            ['Dias da semana com corte', m.dias_semana_corte.map(function (d) { return DIAS_SEMANA[d]; }).join(', ')],
            ['Corte automático', m.corte_automatico ? 'ligado' : 'desligado'],
            ['Forma de bloqueio', m.modo_bloqueio || '—']
        ];
        $('#pf-mkauth').html('<table class="lc-table" style="font-size:13px"><tbody>' + linhas.map(function (l) {
            return '<tr><td class="pf-sub" style="width:45%">' + PF.esc(l[0]) + '</td><td><b>' + PF.esc(l[1]) + '</b></td></tr>';
        }).join('') + '</tbody></table><div class="pf-sub" style="margin-top:8px">Esses valores vêm de Provedor › Opções do MK-AUTH. Para mudar, altere lá.</div>');
    }

    // ------------------------------------------------------------ configuracoes
    function campo(i) {
        var id = 'cfg-' + i.chave, dis = ADMIN ? '' : ' disabled', html;
        if (i.tipo === 'bool') {
            html = '<label class="pf-chk"><input type="checkbox" id="' + id + '"' + (i.valor === '1' ? ' checked' : '') + dis + '> ' + PF.esc(i.rotulo) + '</label>';
        } else if (i.tipo === 'enum') {
            html = '<label class="lc-label" for="' + id + '">' + PF.esc(i.rotulo) + '</label><select class="lc-input" id="' + id + '"' + dis + '>' +
                i.opcoes.map(function (o) { return '<option' + (o === i.valor ? ' selected' : '') + '>' + PF.esc(o) + '</option>'; }).join('') + '</select>';
        } else if (i.tipo === 'int') {
            html = '<label class="lc-label" for="' + id + '">' + PF.esc(i.rotulo) + '</label><input type="number" class="lc-input" id="' + id + '" value="' + PF.esc(i.valor) + '"' +
                (i.min !== null ? ' min="' + i.min + '"' : '') + (i.max !== null ? ' max="' + i.max + '"' : '') + dis + '>';
        } else {
            html = '<label class="lc-label" for="' + id + '">' + PF.esc(i.rotulo) + '</label><input type="text" class="lc-input" id="' + id + '" value="' + PF.esc(i.valor) + '"' + dis + '>';
        }
        return '<div class="pf-form-item">' + html + '<div class="lc-input-hint">' + PF.esc(i.ajuda) + '</div></div>';
    }

    function renderConfig(d) {
        itens = d.itens;
        var grupos = {}, ordem = [];
        itens.forEach(function (i) { if (!grupos[i.grupo]) { grupos[i.grupo] = []; ordem.push(i.grupo); } grupos[i.grupo].push(i); });
        $('#pf-config').html(ordem.map(function (g) {
            return '<div class="lc-label" style="padding:12px 16px 0">' + PF.esc(g) + '</div><div class="pf-form-grid">' + grupos[g].map(campo).join('') + '</div>';
        }).join(''));
    }

    function salvarConfig() {
        var v = {};
        itens.forEach(function (i) {
            var $c = $('#cfg-' + i.chave);
            v[i.chave] = i.tipo === 'bool' ? ($c.is(':checked') ? '1' : '0') : $c.val();
        });
        PF.loading('Salvando...');
        PF.api('config.salvar', { valores: v }, 'POST').then(function (d) {
            renderConfig({ itens: d.itens });
            if (!d.alteradas.length) { PF.toast('info', 'Nada foi alterado.'); return; }
            if (d.reprocessar) {
                PF.toast('avis', 'Configurações salvas. Elas mudam a regra dos indicadores.',
                         'Marque "refazer a história inteira" e clique em Processar agora para recalcular o passado.');
                $('#ag-completo').prop('checked', true);
            } else {
                PF.toast('ok', 'Configurações salvas.');
            }
        }).catch(PF.erro).finally(PF.fimLoading);
    }

    // ------------------------------------------------------------ permissoes
    var NOMES_PAPEL = { admin: 'Administrador', ver: 'Ver indicadores', nominal: 'Ver listas com nome', exportar: 'Exportar CSV' };

    function nomeDe(login) {
        var u = (permDados.usuarios || []).filter(function (x) { return x.login === login; })[0];
        return u ? u.nome : '';
    }

    function renderPerms(d) {
        permDados = d;
        if (!d.permissoes.length) {
            $('#pf-perms').html('<tr><td colspan="4" class="lc-empty">Nenhum usuário com acesso.</td></tr>');
            return;
        }
        $('#pf-perms').html(d.permissoes.map(function (p) {
            return '<tr><td><strong>' + PF.esc(p.login) + '</strong>' + (p.login === d.eu ? ' <span class="pf-sub">(você)</span>' : '') + '</td>' +
                '<td class="prio-6">' + PF.esc(nomeDe(p.login)) + '</td>' +
                '<td class="pf-quebra">' + p.papeis.map(function (x) { return '<span class="pf-papel' + (x === 'admin' ? ' admin' : '') + '">' + PF.esc(NOMES_PAPEL[x] || x) + '</span>'; }).join('') + '</td>' +
                '<td style="text-align:right"><button type="button" class="lc-btn-outline" data-login="' + PF.esc(p.login) + '"><i class="bi bi-pencil"></i> Editar</button></td></tr>';
        }).join(''));
    }

    function carregarPerms() {
        if (!ADMIN) return;
        PF.api('permissao.listar').then(renderPerms).catch(PF.erro);
    }

    function abrirPerm(login) {
        var atuais = [];
        (permDados.permissoes || []).forEach(function (p) { if (p.login === login) atuais = p.papeis; });
        var $sel = $('#perm-login').empty();
        if (login) {
            $sel.append($('<option>').val(login).text(login + (nomeDe(login) ? ' — ' + nomeDe(login) : ''))).prop('disabled', true);
        } else {
            $sel.prop('disabled', false).append('<option value="">Selecione...</option>');
            var comAcesso = {};
            permDados.permissoes.forEach(function (p) { comAcesso[p.login] = 1; });
            permDados.usuarios.forEach(function (u) {
                if (!comAcesso[u.login]) $sel.append($('<option>').val(u.login).text(u.login + (u.nome ? ' — ' + u.nome : '') + (u.ativo ? '' : ' (inativo)')));
            });
        }
        $('#perm-login-dica').text(permDados.usuarios.length ? '' : 'Não consegui ler os usuários do MK-AUTH.');
        $('#perm-papeis').html(permDados.papeis.map(function (p) {
            return '<label><input type="checkbox" value="' + PF.esc(p.id) + '"' + (atuais.indexOf(p.id) !== -1 ? ' checked' : '') + '>' +
                   '<span><strong>' + PF.esc(NOMES_PAPEL[p.id] || p.id) + '</strong><small>' + PF.esc(p.descricao) + '</small></span></label>';
        }).join(''));
        PF.abrirModal('modal-perm');
    }

    function salvarPerm() {
        var login = $('#perm-login').val();
        if (!login) { PF.toast('avis', 'Selecione o usuário.'); return; }
        var papeis = $('#perm-papeis input:checked').map(function () { return this.value; }).get();
        PF.loading('Salvando...');
        PF.api('permissao.definir', { login: login, papeis: papeis }, 'POST')
            .then(function () { PF.fecharModal('modal-perm'); PF.toast('ok', 'Permissões de ' + login + ' salvas.'); carregarPerms(); })
            .catch(PF.erro)
            .finally(PF.fimLoading);
    }

    $(function () {
        PF.api('config.listar').then(function (d) { renderConfig(d); renderMkauth(d.mkauth); }).catch(PF.erro);
        carregarAgregador();
        carregarPerms();
        $('#btn-processar').on('click', processar);
        $('#btn-salvar').on('click', salvarConfig);
        $('#btn-novo-usuario').on('click', function () { abrirPerm(''); });
        $('#pf-perms').on('click', 'button[data-login]', function () { abrirPerm($(this).attr('data-login')); });
        $('#perm-salvar').on('click', salvarPerm);
    });
})();
</script>
</body>
</html>
