<?php
/**
 * painel_financeiro :: bootstrap web do addon.
 *
 * Ordem obrigatoria (addon-mkauth-anatomia):
 *   config.php -> (ajax.php responde e sai) -> nav/header.php -> ../../topo.php
 *
 * Addon AUTOSSUFICIENTE: nada de outro addon e incluido. Do core do MK-AUTH vem apenas
 * topo.php, baixo.php, menu.js e scripts/jquery.js.
 *
 * SOMENTE LEITURA nas tabelas do MK-AUTH, com UMA excecao explicita e auditada: a opcao
 * sis_opcao.auto_corte pelo guardiao de feriado (lib/Indicadores/GuardiaoCorte.php).
 * Fora isso, o addon so escreve nas proprias tab_pfin_* e na tabela compartilhada tab_feriados.
 */
include('addons.class.php');

$pf_ajax = defined('PF_AJAX');

// ---------------------------------------------------------------- sessao do painel
if (!file_exists(__DIR__ . '/../../login.hhvm')) {
    $ext_mk = '.php';
    session_name('mka');
    if (!isset($_SESSION)) session_start();
    if (!isset($_SESSION['mka_logado'])) {
        if ($pf_ajax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false, 'data' => null, 'warnings' => [],
                'errors' => [['code' => 'PF-AUTH-001', 'message' => 'Sessão expirada.', 'details' => []]],
                'sessao_expirada' => true,
            ]);
            exit;
        }
        exit('Acesso negado. <a href="/admin/login.php">Fazer Login</a>');
    }
} else {
    $ext_mk = '.hhvm';
    // Sessao gerenciada pelo MK-AUTH em modo HHVM
}

require_once __DIR__ . '/lib/Core/carregar.php';

// ---------------------------------------------------------------- banco
$PF_DB = Credenciais::descobrir();
if ($PF_DB === null) {
    if ($pf_ajax) {
        Resultado::erro('PF-SYS-005')->enviar(500);
    }
    exit(htmlspecialchars(Credenciais::comoResolver()));
}

// mysqli exigido pelo topo.php do MK-AUTH
$link = @mysqli_connect($PF_DB['host'], $PF_DB['user'], $PF_DB['pass'], $PF_DB['name'], $PF_DB['port']);
if (!$link) {
    exit('Falha na conexao com o banco de dados MySQL.');
}

try {
    $pdo = Db::conectar($PF_DB);
} catch (PDOException $e) {
    Log::excecao('config.conectar', $e);
    if ($pf_ajax) {
        Resultado::erro('PF-SYS-001')->enviar(500);
    }
    exit('Erro de conexao com o banco de dados.');
}
unset($PF_DB);

$usuario_logado = (string) ($_SESSION['MKA_Usuario'] ?? $_SESSION['MM_Usuario'] ?? 'sistema');

Log::configurar(PF_DIR_LOGS, $usuario_logado);
Auditoria::configurar($usuario_logado, $_SERVER['REMOTE_ADDR'] ?? null);
Permissao::configurar($usuario_logado);

// O schema minimo para qualquer tela: sem ele, nem a permissao pode ser checada.
$pf_schema_ok = Db::tabelaExiste('tab_pfin_permissao') && Db::tabelaExiste('tab_pfin_config')
             && Db::tabelaExiste('tab_pfin_fato_safra');
if (!$pf_schema_ok && $pf_ajax) {
    Resultado::erro('PF-SYS-006')->enviar(500);
}
if ($pf_schema_ok) {
    Cache::configurar(PF_DIR_DADOS . '/cache', Config::int('cache_min'));
    // Teto por consulta: um filtro largo demais vira erro na tela, nao carga presa no MK-AUTH.
    Db::limitarTempo(Config::int('limite_consulta_s'));
}

// ---------------------------------------------------------------- CSRF
if (empty($_SESSION['pf_csrf'])) {
    $_SESSION['pf_csrf'] = bin2hex(random_bytes(16));
}
$pf_csrf = (string) $_SESSION['pf_csrf'];

// AJAX so LE a sessao daqui em diante: uma consulta lenta nao prende o usuario no painel.
if ($pf_ajax && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

/** Escapa para HTML. */
function pf_h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
