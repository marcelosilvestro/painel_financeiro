<?php
/**
 * Suite 07 :: guardiao de feriado do corte e a regra real do corte do MK-AUTH.
 */
T::suite('Calendario: corte pulando feriado');

// 28/10/2026 (quarta) feriado: o corte anda para quinta 29/10
T::igual('sem pular: corte no feriado', '2026-10-28', Calendario::dataCorte('2026-10-12', 15, [1, 2, 3, 4, 5]));
T::igual('pulando o feriado: proximo dia de corte', '2026-10-29', Calendario::dataCorte('2026-10-12', 15, [1, 2, 3, 4, 5], ['2026-10-28']));
T::igual('feriado na sexta: vai para segunda', '2026-11-02', Calendario::dataCorte('2026-10-14', 15, [1, 2, 3, 4, 5], ['2026-10-30']));

T::suite('Guardiao de feriado');

$hoje = Calendario::hoje();
$auto = fn() => (string) Db::valor("SELECT valor FROM sis_opcao WHERE nome = 'auto_corte'");
Db::exec("UPDATE sis_opcao SET valor = 'sim' WHERE nome = 'auto_corte'");
Db::exec('DELETE FROM tab_feriados WHERE data = ?', [$hoje]);
Config::set('guardiao_feriado', '0', 'teste');
Config::set('guardiao_desligou_em', '', 'teste', true);

Db::exec("INSERT INTO tab_feriados (data, nome, tipo, abrangencia, origem) VALUES (?, 'Feriado de hoje', 'feriado', 'municipal', 'manual')", [$hoje]);
T::igual('guardiao desligado: nao toca no MK-AUTH', 'nenhuma', GuardiaoCorte::executar('teste')['acao']);
T::igual('auto_corte intocado', 'sim', $auto());

Config::set('guardiao_feriado', '1', 'teste');
T::igual('feriado hoje: suspende o corte', 'suspendeu', GuardiaoCorte::executar('teste')['acao']);
T::igual('auto_corte desligado', 'nao', $auto());
T::igual('idempotente: segunda rodada nao faz nada', 'nenhuma', GuardiaoCorte::executar('teste')['acao']);
T::certo('suspensao na auditoria', (bool) Db::valor("SELECT COUNT(*) FROM tab_pfin_auditoria WHERE acao = 'guardiao_suspender'"));
T::igual('estado: suspenso desde hoje, sem problema', [$hoje, null], [GuardiaoCorte::estado()['suspenso_desde'], GuardiaoCorte::estado()['problema']]);

Db::exec("UPDATE sis_opcao SET valor = 'sim' WHERE nome = 'auto_corte'");   // alguem salvou as Opcoes no feriado
T::certo('estado avisa que religaram no feriado', str_contains((string) GuardiaoCorte::estado()['problema'], 'religado'));
T::igual('e o guardiao suspende de novo', 'suspendeu', GuardiaoCorte::executar('teste')['acao']);

Db::exec('DELETE FROM tab_feriados WHERE data = ?', [$hoje]);
T::certo('fora do feriado ainda suspenso vira problema (alerta)', GuardiaoCorte::estado()['problema'] !== null);
T::certo('o problema aparece nos alertas da Visao Geral', in_array('guardiao', array_column(Visao::alertas()['alertas'], 'id'), true));
T::igual('dia normal: religa o que ele desligou', 'religou', GuardiaoCorte::executar('teste')['acao']);
T::igual('auto_corte de volta', 'sim', $auto());
T::igual('sem pendencia', null, GuardiaoCorte::estado()['suspenso_desde']);

// o provedor desligou o corte por conta propria: o guardiao nao assume
Db::exec("UPDATE sis_opcao SET valor = 'nao' WHERE nome = 'auto_corte'");
Db::exec("INSERT INTO tab_feriados (data, nome, tipo, abrangencia, origem) VALUES (?, 'Feriado de hoje', 'feriado', 'municipal', 'manual')", [$hoje]);
T::igual('corte desligado pelo provedor: nao assume', 'nenhuma', GuardiaoCorte::executar('teste')['acao']);
Db::exec('DELETE FROM tab_feriados WHERE data = ?', [$hoje]);
T::igual('e nao liga depois do feriado', 'nenhuma', GuardiaoCorte::executar('teste')['acao']);
T::igual('auto_corte continua como o provedor deixou', 'nao', $auto());

// desligar o guardiao com o corte suspenso devolve o corte
Db::exec("UPDATE sis_opcao SET valor = 'sim' WHERE nome = 'auto_corte'");
Db::exec("INSERT INTO tab_feriados (data, nome, tipo, abrangencia, origem) VALUES (?, 'Feriado de hoje', 'feriado', 'municipal', 'manual')", [$hoje]);
GuardiaoCorte::executar('teste');
Config::set('guardiao_feriado', '0', 'teste');
T::igual('guardiao desligado com corte suspenso: religa', 'religou', GuardiaoCorte::executar('teste')['acao']);
T::igual('auto_corte religado', 'sim', $auto());
Db::exec('DELETE FROM tab_feriados WHERE data = ?', [$hoje]);

T::suite('Agenda com o guardiao');

Config::set('guardiao_feriado', '1', 'teste');
Calendario::fixarHoje('2026-09-30');
$m = Agenda::mes(2026, 10);   // 28/10 continua feriado (suite 06)
Calendario::fixarHoje(null);
$c = null;
foreach ($m['dias'][28]['eventos'] as $e) { if ($e['tipo'] === 'corte' && $e['venc'] === 10) { $c = $e; } }
T::igual('corte V10 sai do feriado 28/10 e vai para 29/10', '2026-10-28', $c['adiado_de'] ?? null);
T::igual('e nao e mais conflito', [true, null], [is_array($c) && array_key_exists('conflito', $c), $c['conflito'] ?? null]);
$tem28 = false;
foreach ($m['dias'][27]['eventos'] as $e) { if ($e['tipo'] === 'corte' && $e['venc'] === 10) { $tem28 = true; } }
T::igual('28/10 fica sem corte', false, $tem28);
Config::set('guardiao_feriado', '0', 'teste');

T::suite('Regra real do corte do MK-AUTH');

Db::exec("UPDATE sis_cliente SET observacao = 'sim' WHERE login = 'c3'");
T::igual('cliente em observacao nao e candidato', false, isset(Agenda::candidatosCorte($hoje)['c3']));
Db::exec("UPDATE sis_cliente SET observacao = 'nao' WHERE login = 'c3'");
T::igual('so titulo mensalidade conta (c7 e servico)', false, isset(Agenda::candidatosCorte($hoje)['c7']));
Db::exec("UPDATE sis_cliente SET dias_corte = NULL WHERE login = 'c3'");
T::igual('dias_corte vazio: o MK-AUTH nunca corta', false, isset(Agenda::candidatosCorte($hoje)['c3']));
Db::exec("UPDATE sis_cliente SET dias_corte = 15 WHERE login = 'c3'");
T::igual('POST e escrita do guardiao: nenhuma rota escreve auto_corte pela tela', true,
    !array_filter(Rotas::MAPA, fn($r) => $r[2][1] === 'executar'));
