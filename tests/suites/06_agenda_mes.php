<?php
/**
 * Suite 06 :: Agenda (calendario do mes) e feriados compartilhados.
 *
 * Referencia: o Planejamento de Cobrancas do Livro Caixa em outubro/2026 (prints do Marcelo):
 * vencimento dia 10 -> 12/10 (o dia 10 e sabado), vencimento dia 20 -> 20/10; avisos D+10 e
 * D+15 em 01, 06, 22, 27 e 30/10; cortes em 07/10 (V20 de setembro) e 28/10 (V10 de outubro).
 */
T::suite('Agenda :: calendario do mes (igual ao Planejamento)');

// a projecao de outubro vale vista ANTES do mes (ate hoje a agenda mostra o corte realizado)
Calendario::fixarHoje('2026-09-30');

Db::exec("INSERT INTO sis_configmsg (item, valor) VALUES ('wappmsg15depois', '{\"tipo_01\":\"1\"}')
          ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
Db::exec("UPDATE sis_cliente SET dias_corte = 15 WHERE cli_ativado = 's'");
$m = Agenda::mes(2026, 10);
$quando = function (array $m, string $tipo) {
    $s = [];
    foreach ($m['dias'] as $d) {
        foreach ($d['eventos'] as $e) {
            if ($e['tipo'] === $tipo) {
                $s[] = substr($d['data'], 8, 2) . ($tipo === 'aviso' ? ':D+' . $e['dias'] : '') . ':V' . $e['venc'];
            }
        }
    }
    sort($s);
    return $s;
};
T::igual('vencimentos: 12/10 (dia 10 caiu no sabado) e 20/10', ['12:V10', '20:V20'], $quando($m, 'venc'));
T::igual('avisos D+10/D+15 nos mesmos dias do Planejamento',
    ['01:D+10:V20', '06:D+15:V20', '22:D+10:V10', '27:D+15:V10', '30:D+10:V20'], $quando($m, 'aviso'));
T::igual('cortes: 07/10 (V20 de setembro) e 28/10 (V10)', ['07:V20', '28:V10'], $quando($m, 'corte'));
T::igual('o mes tem 31 dias e comeca na quinta', [31, 4], [count($m['dias']), $m['dias'][0]['dow']]);

Db::exec("INSERT INTO tab_feriados (data, nome, tipo, abrangencia, origem) VALUES ('2026-10-28', 'Feriado municipal de teste', 'feriado', 'municipal', 'manual')");
$m = Agenda::mes(2026, 10);
$c28 = null;
foreach ($m['dias'][27]['eventos'] as $e) { if ($e['tipo'] === 'corte') { $c28 = $e; } }
T::igual('corte em feriado vira conflito (o MK-AUTH corta mesmo assim)', 'Feriado municipal de teste', $c28['conflito'] ?? null);
T::igual('o conflito e o primeiro selo do dia', 'corte', $m['dias'][27]['eventos'][0]['tipo']);
T::igual('o dia mostra o feriado', 'municipal', $m['dias'][27]['feriado']['abrangencia'] ?? null);

Db::exec("UPDATE sis_opcao SET valor = 'Tue,Wed,Thu,Fri' WHERE nome = 'dias_de_corte'");
Parametros::esquecer();
$m2 = Agenda::mes(2026, 10);
T::igual('dias de corte lidos do MK-AUTH: sem segunda, o corte de 07/10 (qua) fica', true, in_array('07:V20', $quando($m2, 'corte'), true));
Db::exec("UPDATE sis_opcao SET valor = 'Mon,Tue,Wed,Thu,Fri,Ped' WHERE nome = 'dias_de_corte'");
Parametros::esquecer();
Calendario::fixarHoje(null);

T::suite('Feriados: contrato compartilhado');

Db::exec("INSERT INTO tab_feriados (data, nome, tipo, abrangencia, origem) VALUES ('2026-08-06', 'Padroeira', 'feriado', 'municipal', 'manual')");
Config::set('considerar_feriados', '1', 'teste');
Config::limparCache();
Calendario::esquecer();
T::certo('vencimento efetivo le os feriados compartilhados', in_array('2026-08-06', Calendario::feriados(), true));
Config::set('considerar_feriados', '0', 'teste');
Calendario::esquecer();
