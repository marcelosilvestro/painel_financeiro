<?php
/**
 * painel_financeiro :: processamento dos indicadores (cron).
 *
 *   php cli/agregar.php [--completo] [--conf=ARQ]   noturno: historico (fatos de caixa, safra, atraso, bloqueios)
 *   php cli/agregar.php --hoje [--conf=ARQ]         a cada 10 min: so o caixa do dia corrente
 *
 * So LE as tabelas do MK-AUTH; grava apenas nas tab_pfin_*. Uma trava no banco (GET_LOCK)
 * impede duas execucoes do mesmo tipo ao mesmo tempo — a segunda sai com codigo 2.
 * Saida: 0 ok, 1 falha, 2 ja rodando, 3 sem configuracao de banco.
 */
require_once __DIR__ . '/bootstrap.php';

$args = pf_cli_args($argv);
pf_cli_conectar($args);
Cache::configurar(PF_DIR_DADOS . '/cache', Config::int('cache_min'));

try {
    if (isset($args['hoje'])) {
        $r = Agregador::caixaHoje();
        // silencioso quando da certo: roda 144 vezes por dia e o log do cron nao precisa disso
        exit(0);
    }
    $r = Agregador::executar(pf_usuario_cli(), isset($args['completo']));
} catch (PfErro $e) {
    fwrite(STDERR, date('c') . ' ' . $e->getMessage() . "\n");
    exit($e->codigo() === 'PF-AGR-001' ? 2 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, date('c') . ' ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}
printf("%s ok: %d dias de caixa, %d linhas de safra, %d de atraso, %d eventos de bloqueio em %d ms%s\n", date('c'),
    $r['linhas_dia'], $r['linhas_safra'], $r['linhas_atraso'], $r['eventos_bloqueio'], $r['ms'], $r['completo'] ? ' (completo)' : '');
exit(0);
