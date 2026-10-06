<?php
/**
 * painel_financeiro :: diagnostico da instalacao (so le).
 *
 *   php cli/diagnostico.php [--conf=ARQ]
 */
require_once __DIR__ . '/bootstrap.php';

$args = pf_cli_args($argv);
pf_cli_conectar($args);

$ok = true;
$linha = function (bool $bom, string $txt) use (&$ok) {
    $ok = $ok && $bom;
    echo ($bom ? '  ok   ' : '  XX   ') . $txt . "\n";
};

echo "Painel Financeiro " . pf_versao() . " :: diagnostico\n";
$est = (new Schema())->estado();
$linha($est['instalado'], $est['instalado'] ? 'tabelas do addon presentes' : 'faltam tabelas: ' . implode(', ', $est['faltando']));
$linha(!$est['desatualizado'], $est['desatualizado'] ? 'schema desatualizado: rode o instalador' : 'schema em dia com o codigo');

foreach (['sis_lanc', 'sis_cliente', 'sis_opcao', 'sis_plano'] as $t) {
    $linha(Db::tabelaExiste($t), "tabela do MK-AUTH $t " . (Db::tabelaExiste($t) ? 'acessivel' : 'NAO encontrada'));
}
$linha(Db::tabelaExiste('sis_acesso'), 'usuarios do MK-AUTH (sis_acesso) ' . (Db::tabelaExiste('sis_acesso') ? 'acessiveis' : 'NAO encontrados'));

if ($est['instalado']) {
    $a = Agregador::estado();
    if ($a['nunca_rodou']) {
        echo "  ..   indicadores ainda nao processados (rode: php cli/agregar.php)\n";
    } else {
        $linha(!$a['desatualizado'], 'ultimo processamento: ' . $a['ultima_ok']['fim'] . ' (' . $a['idade_h'] . ' h atras)');
    }
    echo '  ..   dias de vencimento: ' . (implode(', ', Parametros::diasVencimento()) ?: '-')
       . ' | carencia de corte: ' . Parametros::diasCortePadrao() . " dias\n";
}
foreach ([PF_DIR_DADOS, PF_DIR_LOGS] as $dir) {
    $linha(is_dir($dir) && is_writable($dir), "pasta $dir " . (is_dir($dir) ? (is_writable($dir) ? 'gravavel' : 'sem permissao de escrita para ' . get_current_user()) : 'nao existe'));
}
exit($ok ? 0 : 1);
