<?php
/**
 * Suite 02 :: validacao de entrada, filtros e regras de calendario.
 */
T::suite('Validar');

T::igual('mes valido', '2026-09', Validar::mes('2026-09'));
T::recusa('mes 13', fn() => Validar::mes('2026-13'), 'PF-VAL-001');
T::recusa('mes com SQL', fn() => Validar::mes("2026-09' OR 1=1"), 'PF-VAL-001');
T::igual('faixas normalizadas', '5,15,30', Validar::faixas(' 5, 15 ,30 '));
T::recusa('faixas fora de ordem', fn() => Validar::faixas('15,5'), 'PF-VAL-011');
T::recusa('faixas com texto', fn() => Validar::faixas('5,abc'), 'PF-VAL-011');
T::igual('lista de tipos', 'mensalidade,servicos', Validar::listaPalavras('Mensalidade, servicos'));
T::recusa('tipo com aspas (injecao)', fn() => Validar::listaPalavras("mensalidade','x"), 'PF-VAL-012');
T::recusa('opcao fora da lista', fn() => Validar::umDe('drop', ['ativos', 'todos']), 'PF-VAL-010');

T::suite('Filtro');

$f = Filtro::ler(['situacao' => 'desativados', 'plano' => "x' OR '1'='1", 'venc' => '10', 'busca' => '50%_']);
[$sql, $p] = Filtro::sqlCliente($f);
T::certo('valores viram parametros, nunca SQL', !str_contains($sql, "OR '1'") && in_array("x' OR '1'='1", $p, true));
T::certo('busca escapa curinga do LIKE', in_array('%50\\%\\_%', $p, true));
T::certo('venc compara como numero', str_contains($sql, 'CAST(c.venc AS UNSIGNED)') && in_array(10, $p, true));
T::recusa('situacao desconhecida', fn() => Filtro::ler(['situacao' => 'x']), 'PF-VAL-010');
T::recusa('venc nao numerico', fn() => Filtro::ler(['venc' => 'dez']), 'PF-VAL-007');

T::suite('Calendario');

// 2026-08-20 e quinta: corte bruto 20 + 15 + 1 = 05/09 (sabado) -> segunda 07/09. O MK-AUTH
// NAO pula feriado: 07/09 continua sendo a data, mesmo sendo feriado nacional.
T::igual('corte em sabado vai para segunda (sem pular feriado)', '2026-09-07', Calendario::dataCorte('2026-08-20', 15, [1, 2, 3, 4, 5]));
// 2026-10-10 e sabado: o boleto vence na segunda 12/10; corte 12 + 16 = 28/10 (quarta)
T::igual('vencimento em sabado conta a partir da segunda', '2026-10-28', Calendario::dataCorte('2026-10-10', 15, [1, 2, 3, 4, 5]));
T::igual('dias_de_corte com "Ped" ignora o item desconhecido', [1, 2, 3, 4, 5], Parametros::diasSemanaCorte());
T::igual('dias de vencimento lidos do sis_opcao', [10, 20], Parametros::diasVencimento());
T::igual('carencia padrao lida do sis_opcao', 15, Parametros::diasCortePadrao());
T::igual('limites do mes', ['2026-02-01', '2026-03-01'], Calendario::limitesMes('2026-02'));
$ef = Db::valor('SELECT ' . Calendario::sqlVencEf("'2026-10-10 00:00:00'"));
T::igual('vencimento efetivo de sabado no SQL e a segunda', '2026-10-12', $ef);
T::igual('faixas de aging pelo padrao', ['1-5', '6-15', '16-30', '31-60', '61-90', '+90'], array_column(Visao::faixas(), 'rotulo'));

// O MK-AUTH grava o datavenc ja empurrado para o dia util: a segunda volta para o dia do grupo.
$dv = fn(string $d) => (int) Db::valor('SELECT ' . Calendario::sqlDiaVenc("'$d 00:00:00'"));
T::igual('datavenc gravado na segunda 12/10 e do grupo dia 10 (sabado)', 10, $dv('2026-10-12'));
T::igual('datavenc gravado na segunda 21/09 e do grupo dia 20 (domingo)', 20, $dv('2026-09-21'));
T::igual('dia normal fica como esta', 20, $dv('2026-10-20'));
T::igual('dia que nao e de vencimento fica como esta', 13, $dv('2026-10-13'));
