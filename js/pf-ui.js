/*
 * painel_financeiro :: componentes de tela reutilizaveis.
 *
 * Vanilla + jQuery do core, sem framework (padrao do Livro Caixa). Toda chamada ao servidor
 * passa por PF.api(): ela poe o token CSRF, trata sessao expirada e devolve sempre o
 * envelope { ok, data, errors, request_id }.
 */
var PF = (function ($) {
    'use strict';

    var URL_LOGIN = '/admin/';

    function csrf() {
        var m = document.querySelector('meta[name="pf-csrf"]');
        return m ? m.getAttribute('content') : '';
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function pareceLogin(texto) {
        return typeof texto === 'string' &&
            (texto.indexOf('Acesso negado') !== -1 || texto.indexOf('login.hhvm') !== -1);
    }

    /**
     * Chama ajax.php?acao=...  Devolve uma Promise que resolve com `data` e rejeita com
     * { mensagem, codigo, resp }. A mensagem ja vem pronta para mostrar ao usuario.
     */
    function api(acao, dados, metodo) {
        metodo = metodo || 'GET';
        if (dados && Object.prototype.hasOwnProperty.call(dados, 'acao')) {
            return Promise.reject({ mensagem: 'Erro interno da tela: parâmetro "acao" reservado (' + acao + ').', codigo: null, resp: null });
        }
        return new Promise(function (resolve, reject) {
            $.ajax({
                url: 'ajax.php?acao=' + encodeURIComponent(acao),
                method: metodo,
                data: dados || {},
                dataType: 'text',
                headers: metodo === 'POST' ? { 'X-CSRF-Token': csrf() } : {}
            }).always(function (a, status) {
                var texto = (status === 'success') ? a : (a && a.responseText);
                if (pareceLogin(texto)) { window.location.href = URL_LOGIN; return; }
                var resp;
                try { resp = JSON.parse(texto); } catch (e) {
                    var trecho = String(texto || '').replace(/<[^>]*>/g, ' ').trim().slice(0, 300);
                    reject({ mensagem: 'Resposta inesperada do servidor.' + (trecho ? ' ' + trecho : ''), codigo: null, resp: null });
                    return;
                }
                if (resp.sessao_expirada) { window.location.href = URL_LOGIN; return; }
                if (resp.ok) { resolve(resp.data); return; }
                var err = (resp.errors && resp.errors[0]) || {};
                reject({ mensagem: (err.message || 'Falha na operação.') + (resp.request_id ? ' (' + resp.request_id + ')' : ''),
                         codigo: err.code || null, detalhes: err.details || {}, resp: resp });
            });
        });
    }

    /** Download por POST (CSV): formulario oculto com o token, o navegador baixa o arquivo. */
    function baixar(acao, dados) {
        var $f = $('<form method="post" style="display:none"></form>').attr('action', 'ajax.php?acao=' + encodeURIComponent(acao));
        $f.append($('<input type="hidden" name="csrf">').val(csrf()));
        Object.keys(dados || {}).forEach(function (k) {
            if (dados[k] !== null && dados[k] !== undefined && dados[k] !== '') {
                $f.append($('<input type="hidden">').attr('name', k).val(dados[k]));
            }
        });
        $('body').append($f);
        $f.trigger('submit');
        setTimeout(function () { $f.remove(); }, 1000);
    }

    // ------------------------------------------------------------ loading e toast
    function loading(texto) {
        $('#pf-loading-texto').text(texto || 'Carregando...');
        $('#pf-loading').addClass('ativo');
    }
    function fimLoading() { $('#pf-loading').removeClass('ativo'); }

    /** tipo: ok | info | avis | erro */
    function toast(tipo, texto, sub) {
        var icones = { ok: 'bi-check-circle-fill', info: 'bi-info-circle-fill',
                       avis: 'bi-exclamation-triangle-fill', erro: 'bi-x-octagon-fill' };
        var $t = $('<div class="pf-toast ' + esc(tipo) + '" role="status"><i class="bi ' + (icones[tipo] || icones.info) + '"></i><div>' +
                   esc(texto) + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</div></div>');
        $t.on('click', function () { $t.remove(); });
        $('#pf-toasts').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, tipo === 'erro' ? 9000 : 4500);
    }

    function erro(e) { toast('erro', (e && e.mensagem) || String(e)); }

    // ------------------------------------------------------------ modais
    function abrirModal(id)  { $('#' + id).addClass('ativo'); }
    function fecharModal(id) { $('#' + id).removeClass('ativo'); }
    // Modal NAO fecha com clique fora (decisao de 02/10 nos addons): so pelo X ou pela acao.
    function fecharSeFora(ev, id) { }

    /** Confirmacao. opts: { titulo, msg, sub, perigo, textoOk } */
    function confirmar(opts) {
        return new Promise(function (resolve) {
            var perigo = !!opts.perigo;
            $('#pf-confirma-titulo').text(opts.titulo || 'Confirmar');
            $('#pf-confirma-cab').toggleClass('lc-modal-header-danger', perigo);
            $('#pf-confirma-icone').attr('class', 'lc-confirm-icon ' + (perigo ? 'excluir' : 'fechar'))
                .html('<i class="bi ' + (perigo ? 'bi-exclamation-triangle' : 'bi-question-lg') + '"></i>');
            $('#pf-confirma-msg').text(opts.msg || '');
            $('#pf-confirma-sub').text(opts.sub || '');
            $('#pf-confirma-digitar').hide();
            var $ok = $('#pf-confirma-ok').text(opts.textoOk || 'Confirmar').toggleClass('danger', perigo).prop('disabled', false);
            $ok.off('click').on('click', function () { fecharModal('pf-modal-confirma'); resolve(true); });
            $('#pf-modal-confirma .lc-btn-cancel, #pf-modal-confirma .lc-modal-close')
                .off('click.pf').on('click.pf', function () { resolve(false); });
            abrirModal('pf-modal-confirma');
        });
    }

    // ------------------------------------------------------------ formatacao
    var fmtBrl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    var fmtNum = new Intl.NumberFormat('pt-BR');
    var MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    function brl(v) { return v === null || v === undefined ? '—' : fmtBrl.format(v); }
    /** R$ compacto para eixos e cartoes estreitos: R$ 81,0 mil */
    function brlCurto(v) {
        if (v === null || v === undefined) return '—';
        var a = Math.abs(v);
        if (a >= 1e6) return 'R$ ' + (v / 1e6).toFixed(1).replace('.', ',') + ' mi';
        if (a >= 1e3) return 'R$ ' + (v / 1e3).toFixed(1).replace('.', ',') + ' mil';
        return fmtBrl.format(v);
    }
    function num(v) { return v === null || v === undefined ? '—' : fmtNum.format(v); }
    function pct(v, casas) {
        return v === null || v === undefined ? '—' : Number(v).toFixed(casas === undefined ? 1 : casas).replace('.', ',') + '%';
    }
    /** '2026-09' -> 'set/26' */
    function mesCurto(m) { var p = String(m).split('-'); return MESES[+p[1] - 1] + '/' + p[0].slice(2); }
    function data(iso) {
        if (!iso) return '—';
        var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? m[3] + '/' + m[2] + '/' + m[1] : esc(iso);
    }
    function dataHora(iso) {
        if (!iso) return '—';
        var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : esc(iso);
    }

    /** Barra de paginacao. onIr(pagina) e chamado no clique. */
    function paginacao($alvo, pagina, porPagina, total, onIr) {
        var paginas = Math.max(1, Math.ceil(total / porPagina));
        $alvo.html(
            '<span>' + num(total) + ' registro(s) — página ' + pagina + ' de ' + paginas + '</span>' +
            '<button type="button" class="lc-btn-outline" aria-label="Página anterior" data-p="' + (pagina - 1) + '"' + (pagina <= 1 ? ' disabled' : '') + '>&#10094;</button>' +
            '<button type="button" class="lc-btn-outline" aria-label="Próxima página" data-p="' + (pagina + 1) + '"' + (pagina >= paginas ? ' disabled' : '') + '>&#10095;</button>'
        );
        $alvo.find('button').on('click', function () { onIr(parseInt($(this).attr('data-p'), 10)); });
    }

    // ------------------------------------------------------------ estados de bloco
    /** Estado de um bloco grafico: 'carregando' | 'vazio' | 'erro'. */
    function estado($alvo, tipo, texto, onTentar) {
        $alvo.find('.pf-esqueleto, .pf-estado').remove();
        if (tipo === 'carregando') { $alvo.append('<div class="pf-esqueleto" aria-hidden="true"></div>'); return; }
        if (!tipo) return;
        var ico = tipo === 'erro' ? 'bi-exclamation-octagon' : 'bi-inbox';
        var $e = $('<div class="pf-estado ' + tipo + '"><i class="bi ' + ico + '"></i><div>' + esc(texto) + '</div></div>');
        if (onTentar) {
            $('<button type="button" class="lc-btn-outline"><i class="bi bi-arrow-clockwise"></i> Tentar de novo</button>')
                .on('click', onTentar).appendTo($e);
        }
        $alvo.append($e);
    }

    /** Link para a ficha do cliente no proprio MK-AUTH. */
    function linkCliente(uuid, nome) {
        var ext = document.body.getAttribute('data-ext') || '.hhvm';
        if (!uuid) return '<span class="pf-cli">' + esc(nome) + '</span>';
        return '<a class="pf-cli" target="_blank" rel="noopener" href="../../cliente_det' + esc(ext) + '?uuid=' +
               encodeURIComponent(uuid) + '">' + esc(nome) + '</a>';
    }

    return {
        api: api, baixar: baixar, esc: esc, loading: loading, fimLoading: fimLoading, toast: toast, erro: erro,
        abrirModal: abrirModal, fecharModal: fecharModal, fecharSeFora: fecharSeFora, confirmar: confirmar,
        brl: brl, brlCurto: brlCurto, num: num, pct: pct, mesCurto: mesCurto, data: data, dataHora: dataHora,
        paginacao: paginacao, estado: estado, linkCliente: linkCliente
    };
})(jQuery);
