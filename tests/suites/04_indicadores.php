<?php
/**
 * Suite 04 :: indicadores com resultado conhecido.
 *
 * Base sintetica relativa a HOJE do banco: a safra de teste e a de 4 meses atras (madura ate
 * para D+90). Cada cliente e um caso extremo do plano; os valores esperados foram calculados a
 * mao e estao no comentario de cada um.
 */
T::suite('Indicadores :: base sintetica');

$hoje = Calendario::hoje();
$B = (new DateTimeImmutable(substr($hoje, 0, 7) . '-01'))->modify('-4 month');
// primeiro sabado a partir do dia 10 da safra
$sab = $B->modify('+9 day');
while ($sab->format('N') !== '6') {
    $sab = $sab->modify('+1 day');
}
$X = $sab->modify('+3 day'); // terca-feira seguinte: dia util, vencimento efetivo = ele mesmo
$d = fn(DateTimeImmutable $x, int $n = 0) => $x->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d 00:00:00');

$cli = function (string $login, array $extra = []) {
    $c = $extra + ['nome' => strtoupper($login), 'uuid_cliente' => 'uuid-' . $login, 'plano' => '300mbps', 'bairro' => 'Centro',
                   'cidade' => 'Cidade A', 'vendedor' => 'V1', 'venc' => '10', 'cli_ativado' => 's', 'bloqueado' => 'nao',
                   'data_bloq' => null, 'data_desativacao' => null, 'isento' => 'nao', 'dias_corte' => 15];
    Db::exec('INSERT INTO sis_cliente (login, nome, uuid_cliente, plano, bairro, cidade, vendedor, venc, cli_ativado, bloqueado,
              data_bloq, data_desativacao, isento, dias_corte) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$login, $c['nome'], $c['uuid_cliente'], $c['plano'], $c['bairro'], $c['cidade'], $c['vendedor'], $c['venc'],
         $c['cli_ativado'], $c['bloqueado'], $c['data_bloq'], $c['data_desativacao'], $c['isento'], $c['dias_corte']]);
};
$tit = function (string $login, string $venc, string $valor, string $status, ?string $pag = null, ?string $vp = null,
                 string $forma = 'boleto', string $tipo = 'mensalidade', int $del = 0, ?string $datadel = null) {
    Db::exec('INSERT INTO sis_lanc (login, datavenc, datapag, status, tipo, valor, valorpag, formapag, deltitulo, datadel)
              VALUES (?,?,?,?,?,?,?,?,?,?)', [$login, $venc, $pag, $status, $tipo, $valor, $vp, $forma, $del, $datadel]);
};

// c1: vence no SABADO, paga na segunda -> EM DIA (vencimento efetivo empurrado)
$cli('c1'); $tit('c1', $d($sab), '100.00', 'pago', $d($sab, 2), '100.00', 'PIX');
// c2: paga 1 dia depois, com R$ 2 de juros -> ATRASO; juros 2
$cli('c2'); $tit('c2', $d($X), '100.00', 'pago', $d($X, 1), '102.00', 'Pix');
// c3: nunca pagou -> VENCIDO (inadimplente em D+30 e D+90); ativo e nao bloqueado -> alerta de corte
$cli('c3', ['nome' => 'Ana Devedora']); $tit('c3', $d($X), '100.00', 'vencido');
// c4: pagou 5 dias ANTES, com R$ 5 de desconto -> EM DIA; desconto 5
$cli('c4'); $tit('c4', $d($X), '100.00', 'pago', $d($X, -5), '95.00', 'dinheiro');
// c5: excluido DEPOIS de vencer -> BAIXA SEM PAGAMENTO (fora da inadimplencia)
$cli('c5'); $tit('c5', $d($X), '100.00', 'vencido', null, null, 'boleto', 'mensalidade', 1, $d($X, 10));
// c6: excluido ANTES de vencer -> ignorado
$cli('c6'); $tit('c6', $d($X), '100.00', 'aberto', null, null, 'boleto', 'mensalidade', 1, $d($X, -3));
// c7: titulo de SERVICO nao pago -> fora da receita recorrente
$cli('c7'); $tit('c7', $d($X), '250.00', 'vencido', null, null, 'boleto', 'servicos');
// c8: DESATIVADO 20 dias depois de X: titulo de X e divida (recuperavel); o de X+30 e pos-desativacao
$cli('c8', ['cli_ativado' => 'n', 'data_desativacao' => $d($X, 20), 'nome' => '=HYPERLINK("x")']);
$tit('c8', $d($X), '100.00', 'vencido'); $tit('c8', $d($X, 30), '100.00', 'vencido');
// c9: ATIVO BLOQUEADO sem nada vencido -> alerta "bloqueado sem titulo vencido"
$cli('c9', ['bloqueado' => 'sim', 'data_bloq' => $d($X, 20)]);
// c10: pagou 40 dias depois -> ATRASO; nao pago em D+30, pago em D+90
$cli('c10'); $tit('c10', $d($X), '100.00', 'pago', $d($X, 40), '100.00');
// c11: pagou HOJE um titulo que vence daqui a 5 dias -> entra no caixa de hoje (ao vivo)
$cli('c11'); $tit('c11', (new DateTimeImmutable($hoje))->modify('+5 day')->format('Y-m-d 00:00:00'), '50.00', 'pago', $hoje . ' 10:00:00', '50.00', 'Dinheiro');
// c12: reincidente — 3 safras seguintes pagas 3 dias atrasado e a ultima vencida
$cli('c12', ['venc' => '20']);
for ($i = 1; $i <= 3; $i++) {
    $v = $X->modify("+$i month");
    while (in_array($v->format('N'), ['6', '7'], true)) { $v = $v->modify('+1 day'); }
    $tit('c12', $d($v), '80.00', 'pago', $d($v, 3), '80.00');
}
$tit('c12', $d($X->modify('+3 month')->modify('+10 day')), '80.00', 'vencido');

T::certo('base montada', (int) Db::valor('SELECT COUNT(*) FROM sis_lanc') === 15);

T::suite('Indicadores :: agregador');

Config::set('fato_dia_ate', '', 'teste', true);
$r = Agregador::executar('teste');
T::igual('primeira execucao e completa', true, $r['completo']);
// A trava vale entre CONEXOES (cron x tela): outra conexao segurando o lock barra a execucao.
$outra = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']}", $cfg['user'], $cfg['pass']);
$outra->query("SELECT GET_LOCK('" . Agregador::TRAVA . "', 0)")->fetchColumn();
T::recusa('trava impede duas execucoes ao mesmo tempo', fn() => Agregador::executar('teste'), 'PF-AGR-001');
$outra->query("SELECT RELEASE_LOCK('" . Agregador::TRAVA . "')")->fetchColumn();
$outra = null;
$ontem = (new DateTimeImmutable($hoje))->modify('-1 day')->format('Y-m-d');
T::igual('fato de caixa vai ate ontem', $ontem, Config::get('fato_dia_ate'));
$fd = Db::um('SELECT SUM(valor_pago) v, SUM(juros) j, SUM(desconto) d FROM tab_pfin_fato_dia');
T::igual('caixa agregado = pagamentos ate ontem', '637.00', $fd['v']);
T::igual('juros = valorpag - valor (nao valormulta)', '2.00', $fd['j']);
T::igual('desconto = valor - valorpag', '5.00', $fd['d']);
T::igual('PIX e Pix viram uma forma so', 1, (int) Db::valor("SELECT COUNT(DISTINCT forma) FROM tab_pfin_fato_dia WHERE forma = 'pix'"));
T::igual('pagamento de hoje fica fora do fato', 0, (int) Db::valor('SELECT COUNT(*) FROM tab_pfin_fato_dia WHERE dia >= ?', [$hoje]));

T::suite('Indicadores :: safra');

$mesB = $B->format('Y-m');
$s = null;
foreach (Visao::safra(substr($hoje, 0, 7), null)['safras'] as $x) {
    if ($x['mes'] === $mesB) { $s = $x; }
}
T::certo('safra de 4 meses atras existe', $s !== null);
// titulos validos da safra: c1 c2 c3 c4 c8 c10 = 6 x R$100
T::igual('previsto da safra (exclui servico e excluidos)', 600.0, $s['valor']);
T::igual('pago em dia: c1 (sabado->segunda) e c4 (antecipado)', 200.0, $s['em_dia']);
T::igual('pago com atraso: c2 e c10', 200.0, $s['atraso']);
T::igual('vencido em aberto: c3 e c8', 200.0, $s['vencido']);
T::igual('baixa sem pagamento: c5', 100.0, $s['baixa']);
T::igual('% pago em dia = 2/6', 33.3, $s['pct_em_dia']);
T::igual('inadimplencia D+30 = c3 + c8 + c10 (pagou em D+40)', 50.0, $s['inad_d30']);
T::igual('inadimplencia D+90 = c3 + c8', 33.33, $s['inad_d90']);
T::igual('safra madura', true, $s['madura']);

Config::set('tolerancia_em_dia', '1', 'teste');
Agregador::executar('teste', true);
foreach (Visao::safra(substr($hoje, 0, 7), null)['safras'] as $x) {
    if ($x['mes'] === $mesB) { $s = $x; }
}
T::igual('com 1 dia de tolerancia, c2 passa a contar em dia', 300.0, $s['em_dia']);
Config::set('tolerancia_em_dia', '0', 'teste');
Agregador::executar('teste', true);

T::suite('Indicadores :: foto de hoje');

$atr = Visao::emAtraso(null);
// ativos com mensalidade vencida: c3 (100) e c12 (80)
T::igual('em atraso (ativos): c3 + c12', ['valor' => 180.0, 'titulos' => 2, 'clientes' => 2], $atr);
$rec = Visao::recuperavel();
T::igual('recuperavel so ate a desativacao', 100.0, $rec['valor']);
T::igual('titulo pos-desativacao fica fora da divida', 100.0, $rec['pos_valor']);
$bl = Visao::bloqueados(null);
T::igual('bloqueados ativos', 1, $bl['clientes']);
$ag = Visao::aging('ativos', null);
T::igual('aging soma os titulos vencidos', 2, array_sum(array_column($ag['faixas'], 'titulos')));
T::igual('aging: c3 (4 meses) na faixa +90', 1, $ag['faixas'][5]['titulos']);
$cx = Visao::recebidoCaixa(substr($hoje, 0, 7), null);
T::certo('caixa do mes inclui o pagamento de hoje (ao vivo)', $cx['valor'] >= 50.0 && $cx['ao_vivo_desde'] !== null);
$cv = Visao::curva($mesB, null);
T::igual('curva: previsto acumulado fecha no previsto', 600.0, end($cv['previsto']));
T::igual('curva: recebido no mes (c1 + c2 + c4)', 297.0, end($cv['recebido']));
T::igual('curva: pago depois do mes (c10) fica de fora e e informado', 100.0, $cv['recebido_depois_do_mes']);

T::suite('Indicadores :: alertas');

$lst = Visao::listaAlerta('pago_bloqueado');
T::igual('bloqueado sem titulo vencido: c9', ['c9'], array_column($lst, 'login'));
$lst = Visao::listaAlerta('sem_corte');
// Regra real do corte.php: so titulo 'mensalidade' (c7 e servico: fica de fora); c12 venceu ha pouco.
T::igual('corte vencido e nao bloqueado: so c3 (servico nao corta no MK-AUTH)', ['c3'], array_column($lst, 'login'));
$lst = Visao::listaAlerta('pos_desativacao');
T::igual('titulos apos desativacao: c8', ['c8'], array_column($lst, 'login'));
T::recusa('tipo de alerta desconhecido', fn() => Visao::listaAlerta('drop'), 'PF-VAL-010');

T::suite('Indicadores :: inadimplencia');

$f = Filtro::ler([]);
$l = Inadimplencia::lista($f, 1, 'valor', 'desc');
T::igual('lista: ativos com titulo vencido', ['c3', 'c12'], array_column($l['linhas'], 'login'));
$c12 = $l['linhas'][1];
T::igual('c12: 3 pagos com atraso + 1 vencido = 4 atrasos', 4, $c12['atrasos']);
T::igual('c12 e reincidente', true, $c12['reincidente']);
T::igual('c3 nao e reincidente', false, $l['linhas'][0]['reincidente']);
T::certo('ultimo pagamento de c12 preenchido', $c12['ultimo_pagamento'] !== null);
T::igual('filtro reincidente', ['c12'], array_column(Inadimplencia::clientes(Filtro::ler(['reincidente' => '1'])), 'login'));
T::igual('filtro desativados', ['c8'], array_column(Inadimplencia::clientes(Filtro::ler(['situacao' => 'desativados'])), 'login'));
T::igual('filtro por venc do cliente', ['c12'], array_column(Inadimplencia::clientes(Filtro::ler(['venc' => '20'])), 'login'));
T::igual('filtro por dias minimos', ['c3'], array_column(Inadimplencia::clientes(Filtro::ler(['dias_min' => '100'])), 'login'));
$res = Inadimplencia::resumo($f);
T::igual('resumo: valor e clientes', [180.0, 2], [$res['valor'], $res['clientes']]);
$dim = Inadimplencia::dimensao($f, 'plano');
T::igual('dimensao por plano', [['rotulo' => '300mbps', 'valor' => 180.0, 'clientes' => 2]], $dim['itens']);
T::recusa('dimensao fora da lista branca', fn() => Inadimplencia::dimensao($f, 'senha'), 'PF-VAL-010');
$dist = Inadimplencia::distribuicao(substr($hoje, 0, 7), null);
T::igual('distribuicao: c1, c4 em dia (+ c11 nao e safra madura)', true, $dist['qtd'][0] >= 2);
T::igual('distribuicao: c10 em 31+', 1, $dist['qtd'][31]);

ob_start();
$n = Inadimplencia::exportarCsv(Filtro::ler(['situacao' => 'todos']), 'nome', 'asc');
$csv = ob_get_clean();
T::igual('CSV exporta todos os filtrados', 3, $n);
T::certo('CSV neutraliza formula vinda do cadastro', str_contains($csv, "'=HYPERLINK") && !str_contains($csv, ';=HYPERLINK'));
T::certo('CSV comeca com BOM UTF-8', str_starts_with($csv, "\xEF\xBB\xBF"));
