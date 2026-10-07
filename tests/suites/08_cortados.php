<?php
/**
 * Suite 08 :: corte realizado na Agenda (o que o MK-AUTH ja cortou), lido do historico de bloqueios.
 *
 * Caso real de 07/10/2026: o corte das 08:00 bloqueou 44, 11 pagaram e foram religados na mesma
 * manha; a Agenda mostrava "0 serao cortados", porque quem ja foi cortado sai dos candidatos.
 */
T::suite('Agenda :: cortes realizados');

$hoje = Calendario::hoje();
$hojeBr = (new DateTimeImmutable($hoje))->format('d/m/Y');
$titulo = fn(string $l) => (int) Db::valor('SELECT id FROM sis_lanc WHERE login = ? ORDER BY id LIMIT 1', [$l]);
$log = function (string $hora, string $txt) use ($hojeBr) {
    Db::exec("INSERT INTO sis_logs (registro, data, login, tipo) VALUES (?, ?, 'mk-bot', 'admin')", [$txt, "$hojeBr $hora"]);
};
// c4 cortado e religado (pagou); c5 cortado e segue bloqueado, com o log repetido no mesmo dia
$log('08:00:10', 'cliente c4 bloqueado por atraso no titulo ' . $titulo('c4') . ' vencido em 01/01/26');
$log('08:00:11', 'cliente c5 bloqueado por atraso no titulo ' . $titulo('c5') . ' vencido em 01/01/26');
$log('08:00:12', 'cliente c5 bloqueado por atraso no titulo ' . $titulo('c5') . ' vencido em 01/01/26');
$log('08:10:01', 'fez o desbloqueio do cliente c4 por nao ter titulos vencidos');
// c6 cortado e religado SEM log (caso real: o MK-AUTH nem sempre loga o desbloqueio)
$log('08:00:13', 'cliente c6 bloqueado por atraso no titulo ' . $titulo('c6') . ' vencido em 01/01/26');
// o estado em que o MK-AUTH deixa os clientes
Db::exec("UPDATE sis_cliente SET bloqueado = 'sim' WHERE login = 'c5'");
Db::exec("UPDATE sis_cliente SET bloqueado = 'nao', data_desbloq = ? WHERE login IN ('c4', 'c6')", [$hoje . ' 08:27:50']);

$r = Agregador::caixaHoje();
T::igual('o cron de 10 min le os bloqueios novos', 5, $r['eventos_bloqueio']);

$c = Agenda::cortados($hoje, $hoje)[$hoje] ?? null;
$por = $c ? array_column($c['lista'], null, 'login') : [];
ksort($por);
T::igual('cortados hoje: c4, c5 e c6 (o log repetido de c5 conta uma vez)', ['c4', 'c5', 'c6'], array_keys($por));
T::igual('2 religados no mesmo dia', 2, $c['religados'] ?? null);
T::igual('religado com a hora do log', $hoje . ' 08:10', $por['c4']['religado_em'] ?? null);
T::igual('religado sem log: hora do sis_cliente', [true, $hoje . ' 08:27'], [$por['c6']['religado'], $por['c6']['religado_em']]);
T::igual('c5 segue bloqueado, cortado as 08:00', [false, '08:00'], [$por['c5']['religado'], $por['c5']['hora']]);

Cache::limpar();
$k = Agenda::cortes(7)['dias'][0];
T::igual('KPI de hoje traz os cortados', [3, 2], [$k['cortados'], $k['religados']]);

[$a, $m] = array_map('intval', explode('-', substr($hoje, 0, 7)));
$mes = Agenda::mes($a, $m);
$evHoje = [];
$projPassada = 0;
foreach ($mes['dias'] as $d) {
    foreach ($d['eventos'] as $e) {
        if ($d['data'] === $hoje) {
            $evHoje[] = $e['tipo'];
        }
        if ($d['data'] < $hoje && $e['tipo'] === 'corte') {
            $projPassada++;
        }
    }
}
T::igual('o dia de hoje mostra o corte realizado primeiro', 'cortado', $evHoje[0] ?? null);
T::igual('dia que ja passou nao mostra projecao de corte', 0, $projPassada);
