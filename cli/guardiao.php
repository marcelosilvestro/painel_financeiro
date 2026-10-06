<?php
/**
 * painel_financeiro :: guardiao de feriado do corte (cron a cada 5 minutos).
 *
 *   php cli/guardiao.php [--conf=ARQ]
 *
 * So age com a configuracao "Guardiao de feriado" ligada: num feriado da tab_feriados desliga o
 * auto_corte do MK-AUTH; no primeiro dia que nao e feriado, religa o que ele mesmo desligou.
 * Silencioso quando nao faz nada. Saida: 0 ok, 1 falha, 3 sem configuracao de banco.
 */
require_once __DIR__ . '/bootstrap.php';

$args = pf_cli_args($argv);
pf_cli_conectar($args);
try {
    $r = GuardiaoCorte::executar(pf_usuario_cli());
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' guardiao ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}
if ($r['acao'] !== 'nenhuma') {
    echo date('c') . " guardiao: {$r['acao']} ({$r['motivo']})\n";
}
exit(0);
