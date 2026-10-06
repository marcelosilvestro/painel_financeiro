/*
 * painel_financeiro :: graficos (Chart.js 4, empacotado em js/vendor — nada de CDN).
 *
 * Regras que valem para todos: um eixo so (nunca dois eixos y), marcas finas com ponta
 * arredondada, grade discreta, tooltip em toda marca, cor so para ESTADO (verde em dia, ambar
 * atrasado, vermelho vencido, cinza baixa, azul previsto) e sempre com legenda/rotulo — a cor
 * nunca carrega a informacao sozinha. Cada grafico tem uma tabela equivalente ("ver tabela").
 */
var PFG = (function () {
    'use strict';

    var COR = {
        azul: '#3266ad', azulClaro: '#9db5dc', verde: '#1d9e75', ambar: '#e0a100',
        vermelho: '#e74c3c', cinza: '#9aa3b2', grade: '#eef0f4', eixo: '#6b7280', tinta: '#111827'
    };
    // Formas de pagamento: categorias (identidade, nao estado), cor FIXA por forma e na ordem da
    // paleta validada para daltonismo. Uma forma nunca troca de cor quando outra some do filtro.
    var FORMA = { pix: '#2a78d6', boleto: '#eb6834', dinheiro: '#1baf7a', cartao: '#eda100', outras: '#e87ba4' };
    var FORMA_ROTULO = { pix: 'PIX', boleto: 'Boleto', dinheiro: 'Dinheiro', cartao: 'Cartão', outras: 'Outras' };
    var graficos = {};

    // Este arquivo carrega no <head>, antes do <body> existir: a fonte do painel so pode ser
    // lida no primeiro desenho (ver desenhar()).
    var fonteAplicada = false;
    function aplicarFonte() {
        if (fonteAplicada || !window.Chart || !document.body) return;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || 'sans-serif';
        fonteAplicada = true;
    }

    if (window.Chart) {
        Chart.defaults.font.size = 11;
        Chart.defaults.color = COR.eixo;
        Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 300;
        Chart.defaults.plugins.legend.display = false;   // a legenda e HTML, ao lado do grafico
        Chart.defaults.plugins.tooltip.backgroundColor = '#111827';
        Chart.defaults.plugins.tooltip.padding = 8;
        Chart.defaults.plugins.tooltip.cornerRadius = 6;
        Chart.defaults.plugins.tooltip.boxPadding = 4;
        Chart.defaults.maintainAspectRatio = false;
    }

    function eixoValor(extra) {
        return Object.assign({
            beginAtZero: true, border: { display: false },
            grid: { color: COR.grade, drawTicks: false },
            ticks: { padding: 6, callback: function (v) { return PF.brlCurto(v); } }
        }, extra || {});
    }
    function eixoCategoria(extra) {
        return Object.assign({ grid: { display: false }, border: { color: '#d1d5db' }, ticks: { padding: 4 } }, extra || {});
    }

    /** Cria (ou recria) o grafico de um canvas. O canvas recebe aria-label com o resumo. */
    function desenhar(id, config, resumo) {
        var el = document.getElementById(id);
        if (!el || !window.Chart) return null;
        aplicarFonte();
        if (graficos[id]) { graficos[id].destroy(); }
        el.setAttribute('role', 'img');
        el.setAttribute('aria-label', resumo || '');
        graficos[id] = new Chart(el.getContext('2d'), config);
        return graficos[id];
    }

    /** Linha vertical "hoje" sobre o indice x informado. */
    var pluginHoje = {
        id: 'pfHoje',
        afterDatasetsDraw: function (chart, args, opts) {
            if (opts === undefined || opts.indice === undefined || opts.indice === null || opts.indice < 0) return;
            var x = chart.scales.x.getPixelForValue(opts.indice);
            var ctx = chart.ctx, area = chart.chartArea;
            ctx.save();
            ctx.strokeStyle = '#9ca3af'; ctx.lineWidth = 1; ctx.setLineDash([3, 3]);
            ctx.beginPath(); ctx.moveTo(x, area.top); ctx.lineTo(x, area.bottom); ctx.stroke();
            ctx.setLineDash([]); ctx.fillStyle = '#6b7280'; ctx.font = '600 10px sans-serif'; ctx.textAlign = 'left';
            ctx.fillText(opts.rotulo || 'hoje', Math.min(x + 4, area.right - 28), area.top + 10);
            ctx.restore();
        }
    };

    /** Curva do mes: previsto acumulado (tracejado) x recebido acumulado (solido). */
    function curva(id, d, onClickDia) {
        var hojeIdx = d.dia_hoje > 0 ? d.dia_hoje - 1 : null;
        return desenhar(id, {
            type: 'line',
            data: {
                labels: d.dias,
                datasets: [
                    { label: 'Previsto acumulado', data: d.previsto, borderColor: COR.azul, borderWidth: 2, borderDash: [5, 4],
                      pointRadius: 0, pointHoverRadius: 4, tension: 0, fill: false, stepped: 'before' },
                    { label: 'Recebido acumulado', data: d.recebido, borderColor: COR.verde, backgroundColor: 'rgba(29,158,117,.08)',
                      borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0, fill: 'origin', spanGaps: false }
                ]
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                scales: { x: eixoCategoria({ ticks: { autoSkip: true, maxTicksLimit: 16 } }), y: eixoValor() },
                plugins: {
                    pfHoje: { indice: hojeIdx },
                    tooltip: { callbacks: {
                        title: function (it) { return 'Dia ' + it[0].label; },
                        label: function (c) { return c.dataset.label + ': ' + (c.raw === null ? '—' : PF.brl(c.raw)); },
                        afterBody: function (it) {
                            var p = it[0] && d.previsto[it[0].dataIndex], r = it[0] && d.recebido[it[0].dataIndex];
                            return (p > 0 && r !== null && r !== undefined) ? ['', 'Realizado: ' + PF.pct(100 * r / p)] : [];
                        }
                    } }
                },
                onClick: onClickDia ? function (e, els) { if (els.length) onClickDia(els[0].index + 1); } : undefined
            },
            plugins: [pluginHoje]
        }, 'Curva do mês: previsto acumulado ' + PF.brl(d.previsto[d.previsto.length - 1]) +
           ', recebido até hoje ' + PF.brl(hojeIdx !== null ? d.recebido[hojeIdx] : null));
    }

    /** Barras horizontais de uma serie (aging, dimensoes). itens: [{rotulo, valor, dica}] */
    function barrasH(id, itens, cor, onClick) {
        return desenhar(id, {
            type: 'bar',
            data: { labels: itens.map(function (i) { return i.rotulo; }),
                    datasets: [{ data: itens.map(function (i) { return i.valor; }), backgroundColor: cor || COR.azul,
                                 borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22, categoryPercentage: .8, barPercentage: .9 }] },
            options: {
                indexAxis: 'y',
                scales: { x: eixoValor(), y: eixoCategoria({ border: { display: false } }) },
                plugins: { tooltip: { callbacks: {
                    label: function (c) { var it = itens[c.dataIndex]; return it.dica || PF.brl(c.raw); }
                } } },
                onClick: onClick ? function (e, els) { if (els.length) onClick(itens[els[0].index], els[0].index); } : undefined,
                onHover: function (e, els) { e.native.target.style.cursor = (onClick && els.length) ? 'pointer' : 'default'; }
            }
        }, itens.map(function (i) { return i.rotulo + ': ' + PF.brl(i.valor); }).join('; '));
    }

    /**
     * Colunas empilhadas. series: [{rotulo, cor, valores[]}]. modo '100' normaliza cada coluna
     * em percentual (a dica mostra o R$ real). Um respiro branco de 2px separa os segmentos.
     */
    function empilhado(id, rotulos, series, modo, onClick, dicaExtra, linha) {
        var totais = rotulos.map(function (_, i) {
            return series.reduce(function (s, x) { return s + (x.valores[i] || 0); }, 0);
        });
        var datasets = series.map(function (s) {
            return {
                label: s.rotulo, backgroundColor: s.cor, borderColor: '#fff', borderWidth: { top: 2 }, borderSkipped: false,
                borderRadius: 0, maxBarThickness: 34,
                data: s.valores.map(function (v, i) { return modo === '100' ? (totais[i] > 0 ? 100 * (v || 0) / totais[i] : 0) : (v || 0); }),
                reais: s.valores, order: 1
            };
        });
        // Linha de referencia (ex.: previsto) no MESMO eixo em R$ — nunca um segundo eixo.
        if (linha && modo !== '100') {
            datasets.push({ type: 'line', label: linha.rotulo, data: linha.valores, reais: linha.valores, stack: 'linha', order: 0,
                            borderColor: linha.cor || COR.tinta, borderWidth: 2, borderDash: [5, 4], pointRadius: 3,
                            pointBackgroundColor: linha.cor || COR.tinta, pointHoverRadius: 5, fill: false, tension: 0, spanGaps: true });
        }
        return desenhar(id, {
            type: 'bar',
            data: { labels: rotulos, datasets: datasets },
            options: {
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: eixoCategoria({ stacked: true }),
                    y: modo === '100'
                        ? { stacked: true, max: 100, beginAtZero: true, border: { display: false }, grid: { color: COR.grade, drawTicks: false },
                            ticks: { padding: 6, callback: function (v) { return v + '%'; } } }
                        : eixoValor({ stacked: true })
                },
                plugins: { tooltip: { callbacks: {
                    label: function (c) {
                        var real = c.dataset.reais[c.dataIndex];
                        if (real === null || real === undefined) return c.dataset.label + ': —';
                        return c.dataset.label + ': ' + PF.brl(real) + (modo === '100' ? ' (' + PF.pct(c.raw) + ')' : '');
                    },
                    footer: function (it) { return dicaExtra ? dicaExtra(it[0].dataIndex) : ''; }
                } } },
                onClick: onClick ? function (e, els) { if (els.length) onClick(els[0].index); } : undefined,
                onHover: function (e, els) { e.native.target.style.cursor = (onClick && els.length) ? 'pointer' : 'default'; }
            }
        }, rotulos.map(function (r, i) { return r + ' ' + PF.brl(totais[i]); }).join('; '));
    }

    /** Colunas de uma serie com linhas-marco verticais (ex.: dia do corte). */
    function colunas(id, rotulos, valores, cor, marcos, dica) {
        var pluginMarcos = {
            id: 'pfMarcos',
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx, area = chart.chartArea;
                (marcos || []).forEach(function (m) {
                    var x = chart.scales.x.getPixelForValue(m.indice);
                    if (isNaN(x)) return;
                    ctx.save(); ctx.strokeStyle = m.cor || COR.vermelho; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
                    ctx.beginPath(); ctx.moveTo(x, area.top); ctx.lineTo(x, area.bottom); ctx.stroke();
                    ctx.setLineDash([]); ctx.fillStyle = m.cor || COR.vermelho; ctx.font = '600 10px sans-serif';
                    ctx.textAlign = 'right'; ctx.fillText(m.rotulo, x - 4, area.top + 10); ctx.restore();
                });
            }
        };
        return desenhar(id, {
            type: 'bar',
            data: { labels: rotulos, datasets: [{ data: valores, backgroundColor: cor || COR.azul, borderRadius: 4,
                    borderSkipped: 'start', categoryPercentage: .85, barPercentage: .9 }] },
            options: {
                scales: { x: eixoCategoria({ ticks: { autoSkip: true, maxTicksLimit: 16 } }),
                          y: { beginAtZero: true, border: { display: false }, grid: { color: COR.grade, drawTicks: false }, ticks: { padding: 6 } } },
                plugins: { tooltip: { callbacks: { label: function (c) { return dica ? dica(c.dataIndex) : String(c.raw); } } } }
            },
            plugins: [pluginMarcos]
        }, 'Distribuição: ' + rotulos.length + ' colunas');
    }

    /** Tabela alternativa: cab = ['Mes', 'Em dia', ...], linhas = [[...], ...] ja formatadas. */
    function tabela($alvo, cab, linhas) {
        var h = '<table><thead><tr>' + cab.map(function (c) { return '<th scope="col">' + PF.esc(c) + '</th>'; }).join('') +
                '</tr></thead><tbody>' + linhas.map(function (l) {
                    return '<tr>' + l.map(function (c) { return '<td>' + PF.esc(c) + '</td>'; }).join('') + '</tr>';
                }).join('') + '</tbody></table>';
        $alvo.html(h);
    }

    /** Liga o botao "ver tabela" de um painel. */
    function alternarTabela(botao, alvoId) {
        var $b = jQuery(botao), $t = jQuery('#' + alvoId);
        var ativo = !$t.hasClass('ativo');
        $t.toggleClass('ativo', ativo);
        $b.toggleClass('ativo', ativo).attr('aria-pressed', ativo ? 'true' : 'false');
    }

    /** Colunas lado a lado (ex.: entradas x saidas). series: [{rotulo, cor, valores[]}]; fmt formata o valor. */
    function agrupado(id, rotulos, series, fmt) {
        fmt = fmt || function (v) { return PF.num(v); };
        return desenhar(id, {
            type: 'bar',
            data: { labels: rotulos, datasets: series.map(function (s) {
                return { label: s.rotulo, data: s.valores, backgroundColor: s.cor, borderRadius: 4, borderSkipped: 'start',
                         borderColor: '#fff', borderWidth: 1, maxBarThickness: 18, categoryPercentage: .7, barPercentage: .9 };
            }) },
            options: {
                interaction: { mode: 'index', intersect: false },
                scales: { x: eixoCategoria(), y: { beginAtZero: true, border: { display: false }, grid: { color: COR.grade, drawTicks: false },
                          ticks: { padding: 6, precision: 0 } } },
                plugins: { tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fmt(c.raw); } } } }
            }
        }, series.map(function (s) { return s.rotulo; }).join(' x '));
    }

    return { COR: COR, FORMA: FORMA, FORMA_ROTULO: FORMA_ROTULO, agrupado: agrupado, curva: curva, barrasH: barrasH, empilhado: empilhado, colunas: colunas,
             tabela: tabela, alternarTabela: alternarTabela };
})();
