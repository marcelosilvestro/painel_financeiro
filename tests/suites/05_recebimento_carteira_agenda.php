<?php
/**
 * Suite 05 :: Recebimentos, Carteira e Agenda sobre a mesma base sintetica da suite 04
 * (ela continua valendo: $hoje, $B, $X, $d, $cli e $tit vem de la).
 */
T::suite('Recebimentos');

$mesX = $X->format('Y-m');
// no mes de X entraram: c1 (segunda apos o sabado) 100 + c2 (X+1) 102 + c4 (X-5) 95 = 297
$d1 = Recebimento::porDia($mesX, null);
$somaDia = 0.0;
foreach ($d1['series'] as $s) { $somaDia += array_sum($s); }
T::igual('recebido por dia soma o caixa do mes', 297.0, round($somaDia, 2));
T::igual('PIX e Pix contam na mesma serie (c1 + c2)', 202.0, array_sum($d1['series']['pix']));
T::igual('caixa por dia bate com o KPI de caixa', round($somaDia, 2), Visao::recebidoCaixa($mesX, null)['valor']);
$m = Recebimento::mensal($mesX, null);
T::igual('12 meses: ultimo mes = caixa do mes', 297.0, end($m['total']));
T::igual('12 meses: previsto vem da safra', 600.0, end($m['previsto']));
$p = Recebimento::pagamentosDia(substr($d($X, 1), 0, 10), 1);
T::igual('pagamentos do dia X+1: so c2', ['c2'], array_column($p['linhas'], 'login'));
T::igual('soma dos pagamentos do dia', 102.0, $p['soma']);
$ab = Recebimento::emAbertoDoMes($mesX, null);
T::igual('falta entrar do mes: vencido c3 + c8', [200.0, 0.0], [$ab['vencido'], $ab['a_vencer']]);

// baixa por retorno bancario: um pagamento novo no mes de X, reprocessado
$cli('c20');
Db::exec("INSERT INTO sis_lanc (login, datavenc, datapag, status, tipo, valor, valorpag, formapag, coletor)
          VALUES ('c20', ?, ?, 'pago', 'mensalidade', '60.00', '60.00', 'boleto', 'arq.retorno')", [$d($X), $d($X, 2)]);
Agregador::executar('teste', true);
$col = [];
foreach (Recebimento::coletores($mesX)['itens'] as $i) { $col[$i['coletor']] = $i; }
T::igual('coletor: retorno bancario separado e marcado automatico', [60.0, true], [$col['arq.retorno']['valor'] ?? null, $col['arq.retorno']['automatico'] ?? null]);
T::igual('coletor vazio vira nao_informado', 297.0, $col['nao_informado']['valor'] ?? null);

T::suite('Carteira');

$antigo = $B->modify('-1 year')->format('Y-m-d 00:00:00');
Db::exec('UPDATE sis_cliente SET data_ins = ?', [$antigo]);
Db::exec("UPDATE sis_cliente SET data_ins = ? WHERE login = 'c11'", [(new DateTimeImmutable($hoje))->modify('-10 day')->format('Y-m-d 08:00:00')]);
Db::exec("INSERT INTO sis_plano (nome, valor) VALUES ('300mbps', '100.00')");
Db::exec("UPDATE sis_cliente SET desconto = 10 WHERE login = 'c1'");
$mrr = Carteira::mrr();
// ativos: todos menos c8 = 13 (c1..c7, c9..c12, c20 — c8 desativado). MRR = 13 x 100 - 10 de desconto
$ativos = (int) Db::valor("SELECT COUNT(*) FROM sis_cliente WHERE cli_ativado = 's'");
T::igual('MRR = plano dos ativos - desconto', round($ativos * 100 - 10, 2), $mrr['mrr']);
T::igual('ARPU = MRR / ativos pagantes', round(($ativos * 100 - 10) / $ativos, 2), $mrr['arpu']);
$mDes = $X->modify('+20 day');
[$ini, $fim] = Calendario::limitesMes($mDes->format('Y-m'));
$mv = Carteira::movimentoMes($ini, $fim);
T::igual('mes da desativacao de c8: 1 saida, saiu devendo', [1, 1], [$mv['saidas'], $mv['saidas_com_divida']]);
T::igual('churn = saidas / ativos no inicio do mes', round(100 / $mv['inicio'], 2), $mv['churn']);
$rec = Carteira::recuperacao();
T::igual('recuperacao: ano da desativacao de c8, R$ 100', [[$mDes->format('Y'), 100.0]], array_map(fn($a) => [$a['ano'], $a['valor']], $rec['anos']));
$rl = Carteira::recuperacaoLista(1, 'valor', null);
T::igual('lista de recuperacao: c8', ['c8'], array_column($rl['linhas'], 'login'));
$pf = Carteira::primeiraFatura(substr($hoje, 0, 7));
T::igual('primeira fatura: c11 (novo) pagou em dia', 1, array_sum(array_column($pf['meses'], 'em_dia')));

// log do MK-AUTH: c9 ja estava bloqueado quando o historico comeca (nao e episodio novo);
// c3 bloqueia (texto com o NOME, login vem do titulo), e relogado no dia seguinte, desbloqueia em
// 5 dias e bloqueia de novo.
$tituloC3 = (int) Db::valor("SELECT id FROM sis_lanc WHERE login = 'c3' LIMIT 1");
// titulo inexistente: o login sai do proprio texto (caminho alternativo)
$tituloC9 = 999999;
$base = (new DateTimeImmutable(substr($hoje, 0, 7) . '-01'))->modify('-2 month')->modify('+1 day');
$log = function (DateTimeImmutable $q, string $txt) {
    Db::exec("INSERT INTO sis_logs (registro, data, login, tipo) VALUES (?, ?, 'mk-bot', 'admin')", [$txt, $q->format('d/m/Y H:i:s')]);
};
$log($base->setTime(8, 0), "cliente c9 bloqueado por atraso no titulo $tituloC9 vencido em 01/01/26");
$log($base->modify('+3 day')->setTime(9, 0), 'fez desbloqueio do cliente Fulano observacao c9');
$log($base->modify('+10 day')->setTime(8, 0), "cliente ana devedora bloqueado por atraso no titulo $tituloC3 vencido em 01/01/26");
$log($base->modify('+11 day')->setTime(8, 0), "cliente ana devedora bloqueado por atraso no titulo $tituloC3 vencido em 01/01/26");
$log($base->modify('+15 day')->setTime(8, 0), 'fez o desbloqueio do cliente c3 por nao ter titulos vencidos');
$log($base->modify('+18 day')->setTime(8, 0), "cliente c3 bloqueado por atraso no titulo $tituloC3 vencido em 01/01/26");
$log($base->modify('+19 day')->setTime(8, 0), 'cliente c3 foi removido da observacao');
$r = Agregador::executar('teste');
T::igual('eventos lidos do log (a linha que nao e bloqueio fica de fora)', 6, $r['eventos_bloqueio']);
T::igual('incremental: segunda leitura nao duplica', 0, Agregador::executar('teste')['eventos_bloqueio']);
$bl = null;
foreach (Carteira::bloqueios(substr($hoje, 0, 7))['meses'] as $x) {
    if ($x['mes'] === $base->format('Y-m')) { $bl = $x; }
}
T::igual('bloqueios: 2 episodios de c3 (relog e herdado de c9 nao contam)', 2, $bl['bloqueios']);
T::igual('desbloqueios: 1 automatico e 1 manual', [1, 1], [$bl['desbloqueios_auto'], $bl['desbloqueios_manual']]);
T::igual('tempo bloqueado: 5 dias (desbloqueio herdado nao tem duracao)', 5.0, $bl['mediana_dias']);

T::suite('Agenda');

// c13: venceu ha 14 dias e segue sem pagar -> o corte cai nos proximos dias
$v13 = (new DateTimeImmutable($hoje))->modify('-14 day');
$cli('c13');
$tit('c13', $d($v13), '90.00', 'vencido');
$corte13 = Calendario::dataCorte($v13->format('Y-m-d'), 15, Parametros::diasSemanaCorte());
Db::exec("INSERT INTO tab_feriados (data, nome) VALUES (?, 'Feriado de teste')", [$corte13]);
Cache::limpar();
$dia = null;
foreach (Agenda::cortes(7)['dias'] as $x) {
    if (in_array('c13', array_column($x['lista'], 'login'), true)) { $dia = $x; }
}
T::certo('c13 aparece no dia de corte previsto', $dia !== null && $dia['data'] === $corte13, 'previsto ' . $corte13);
T::igual('corte em feriado e sinalizado (o MK-AUTH nao pula feriado)', 'Feriado de teste', $dia['feriado'] ?? null);
Db::exec('DELETE FROM tab_feriados WHERE data = ?', [$corte13]);
Cache::limpar();

$v14 = (new DateTimeImmutable($hoje))->modify('+3 day');
$cli('c14');
$tit('c14', $d($v14), '70.00', 'aberto');
$ven = Agenda::vencimentos(7);
T::certo('vencimento a pagar aparece na agenda', array_sum(array_column($ven['dias'], 'valor')) >= 70.0);

Db::exec("INSERT INTO sis_configmsg (item, valor) VALUES ('wappmsg10depois', '{\"tipo_01\":\"1\",\"tipo_02\":\"0\",\"tipo_03\":\"0\"}'),
          ('wappmsg5antes', '{\"tipo_01\":\"0\"}'), ('wappmsg0', '{\"tipo_01\":\"0\"}'), ('wappmsganiv', '{\"tipo_01\":\"2\"}')");
$re = Agenda::regua();
T::igual('regua: so itens de vencimento (aniversario fica fora)', 3, count($re['avisos']));
T::igual('regua: ativo D+10', [10], array_column(array_filter($re['avisos'], fn($a) => $a['ativo']), 'dias'));

// c15 pagou 1 dia depois do aviso de D+10; o aviso de c13 falhou no gateway
$v15 = (new DateTimeImmutable($hoje))->modify('-12 day');
$cli('c15');
$tit('c15', $d($v15), '80.00', 'pago', $d(new DateTimeImmutable($hoje), -1), '80.00');
$id15 = (int) Db::valor("SELECT id FROM sis_lanc WHERE login = 'c15'");
$id13 = (int) Db::valor("SELECT id FROM sis_lanc WHERE login = 'c13'");
Db::exec("INSERT INTO sis_enviadas (login, data, tipo, mensagem) VALUES
          ('c15', ?, 'app', ?), ('c13', ?, 'app', ?), ('c1', ?, 'app', 'legenda img Feliz aniversario')",
    [$v15->modify('+10 day')->format('Y-m-d 08:00:00'), "[Titulo: $id15] Ola, C15 Nao identificamos o pagamento",
     $v13->modify('+10 day')->format('Y-m-d 08:00:00'), "[Titulo: $id13] codigo (500) {\"status\":500,\"error\":\"Internal Server Error\"}",
     $v15->format('Y-m-d 08:00:00')]);
$ef = Agenda::efetividade(90)['grupos'];
$g = $ef[count($ef) - 1];
T::igual('efetividade: 2 avisos com titulo, 1 falha, 1 pagou em 3 dias', [2, 1, 1], [$g['enviados'], $g['falhas'], $g['pagos_3d']]);
T::igual('efetividade: % pagou sobre os entregues', 100.0, $g['pct_pagou']);

T::suite('Permissao das rotas novas');

foreach (['rec.pagamentos', 'cart.recuperacao_lista'] as $a) {
    T::igual("rota $a exige lista nominal", 'nominal', Rotas::MAPA[$a][1]);
}
$escritaAgenda = array_keys(array_filter(Rotas::MAPA, fn($r, $k) => str_starts_with($k, 'ag.') && $r[0] === 'POST', ARRAY_FILTER_USE_BOTH));
T::igual('a Agenda e so para consulta: nenhuma rota de escrita', [], $escritaAgenda);
Permissao::definir('operador', ['ver'], 'teste');
Permissao::configurar('operador');
$c = AjaxAgenda::cortes(['dias' => '7']);
T::certo('sem papel nominal, a agenda de cortes vem sem nomes', !empty($c['sem_nomes']) && array_sum(array_map(fn($x) => count($x['lista']), $c['dias'])) === 0);
Permissao::configurar('teste');

T::suite('Caixa do dia (cron de 10 min)');

$antes = Visao::recebidoCaixa(substr($hoje, 0, 7), null);
T::certo('sem o cron do dia, hoje e lido ao vivo', $antes['ao_vivo_desde'] === $hoje);
$r = Agregador::caixaHoje();
T::igual('cron do dia grava o caixa de hoje no fato', true, (int) Db::valor('SELECT COUNT(*) FROM tab_pfin_fato_dia WHERE dia = ?', [$hoje]) > 0);
T::igual('com o cron recente, o fato cobre ate hoje', $hoje, Agregador::fatoCobreAte());
$depois = Visao::recebidoCaixa(substr($hoje, 0, 7), null);
T::igual('a tela para de ler ao vivo', null, $depois['ao_vivo_desde']);
T::igual('e o valor e o mesmo', $antes['valor'], $depois['valor']);
Agregador::executar('teste');
T::igual('o processamento noturno nao apaga o dia corrente', true, (int) Db::valor('SELECT COUNT(*) FROM tab_pfin_fato_dia WHERE dia = ?', [$hoje]) > 0);
Config::set('fato_hoje_em', (new DateTimeImmutable($hoje))->modify('-1 day')->format('Y-m-d 23:59:00'), 'teste', true);
T::igual('cron do dia parado: volta a ler ao vivo sozinho', (new DateTimeImmutable($hoje))->modify('-1 day')->format('Y-m-d'), Agregador::fatoCobreAte());
