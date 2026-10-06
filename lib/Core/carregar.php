<?php
/**
 * painel_financeiro :: carrega o nucleo. Web (config.php), CLI (cli/bootstrap.php) e testes incluem so isto.
 */
require_once __DIR__ . '/Erros.php';
require_once __DIR__ . '/PfErro.php';
require_once __DIR__ . '/Resultado.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Validar.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Permissao.php';
require_once __DIR__ . '/Credenciais.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/../Regras/Parametros.php';
require_once __DIR__ . '/../Regras/Calendario.php';
require_once __DIR__ . '/../Regras/Filtro.php';
require_once __DIR__ . '/../Agregador/Agregador.php';
require_once __DIR__ . '/../Indicadores/Visao.php';
require_once __DIR__ . '/../Indicadores/Inadimplencia.php';
require_once __DIR__ . '/../Indicadores/Recebimento.php';
require_once __DIR__ . '/../Indicadores/Carteira.php';
require_once __DIR__ . '/../Indicadores/Agenda.php';
require_once __DIR__ . '/../Indicadores/GuardiaoCorte.php';

if (!defined('PF_DIR_DADOS')) {
    define('PF_DIR_DADOS', '/opt/mk-auth/dados/painel_financeiro');
}
if (!defined('PF_DIR_LOGS')) {
    define('PF_DIR_LOGS', '/opt/mk-auth/log/painel_financeiro');
}

/** Versao declarada no manifest.json. */
function pf_versao(): string
{
    static $v = null;
    if ($v === null) {
        $m = @json_decode((string) @file_get_contents(__DIR__ . '/../../manifest.json'), true);
        $v = isset($m['version']) ? (string) $m['version'] : '0';
    }
    return $v;
}
