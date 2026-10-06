<?php
/**
 * painel_financeiro :: teste de CARGA (nao entra no pacote).
 *
 *   php tests/carga.php --conf=/opt/mk-auth/conf/painel_financeiro.php [--clientes=30000] [--meses=60] [--manter]
 *
 * Cria um banco proprio (mkradius_pfin_carga_test) com tabelas nativas sinteticas no mesmo
 * motor da producao (TokuDB quando existe), gera clientes x meses de titulos com o perfil real
 * do provedor (~45% em dia, ~53% atrasado, ~2% calote) e mede o tempo de cada indicador.
 * Mesmas travas da suite: nome com 'test' e recusa rodar de dentro de /opt/mk-auth.
 */
declare(strict_types=1);

if (str_contains(str_replace('\\', '/', __DIR__), '/opt/mk-auth')) {
    fwrite(STDERR, "Recusado: o teste de carga nunca roda a partir de uma instalacao do MK-AUTH.\n");
    exit(2);
}
require_once __DIR__ . '/../lib/Core/carregar.php';

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z_]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}
$cfg = Credenciais::doArquivo((string) ($args['conf'] ?? '')) ?? exit("use --conf=ARQ\n");
$cfg['name'] = 'mkradius_pfin_carga_test';
$nCli = (int) ($args['clientes'] ?? 30000);
$nMes = (int) ($args['meses'] ?? 60);

$admin = new PDO("mysql:host={$cfg['host']};port={$cfg['port']}", $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec("DROP DATABASE IF EXISTS `{$cfg['name']}`");
$admin->exec("CREATE DATABASE `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
Db::conectar($cfg);
$tmp = sys_get_temp_dir() . '/pf_carga_' . getmypid();
@mkdir($tmp, 0700, true);
Log::configurar($tmp, 'carga');
Auditoria::configurar('carga', null);
Permissao::configurar('carga');
Cache::configurar($tmp . '/cache', 0);

$motor = (int) Db::valor("SELECT COUNT(*) FROM information_schema.ENGINES WHERE ENGINE = 'TokuDB' AND SUPPORT IN ('YES','DEFAULT')") ? 'TokuDB' : 'InnoDB';
$t = function (string $rotulo, callable $fn) {
    $i = microtime(true);
    $r = $fn();
    printf("  %-52s %8d ms\n", $rotulo, (int) ((microtime(true) - $i) * 1000));
    return $r;
};

echo "Banco {$cfg['name']} ($motor): $nCli clientes x $nMes meses\n";
(new Schema(__DIR__ . '/../sql'))->aplicar('carga', pf_versao());
$pdo = Db::pdo();
$pdo->exec("CREATE TABLE sis_lanc (id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(255), datavenc DATETIME, datapag DATETIME,
    status VARCHAR(255), tipo VARCHAR(255), valor VARCHAR(50), valorpag VARCHAR(50), formapag VARCHAR(100), coletor VARCHAR(20),
    deltitulo TINYINT(1) DEFAULT 0, datadel DATETIME, KEY(login), KEY(datavenc), KEY(status), KEY(deltitulo)) ENGINE=$motor DEFAULT CHARSET=latin1");
$pdo->exec("CREATE TABLE sis_cliente (id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(64) UNIQUE, nome VARCHAR(255), uuid_cliente VARCHAR(48),
    plano VARCHAR(64), bairro VARCHAR(255), cidade VARCHAR(255), vendedor VARCHAR(255), venc VARCHAR(2), cli_ativado ENUM('s','n'),
    bloqueado ENUM('sim','nao'), data_bloq DATETIME, data_desativacao DATETIME, isento VARCHAR(3) DEFAULT 'nao', dias_corte INT,
    data_ins DATETIME, desconto DECIMAL(12,2) DEFAULT 0, acrescimo DECIMAL(12,2) DEFAULT 0, observacao ENUM('sim','nao') DEFAULT 'nao', KEY(cli_ativado), KEY(bloqueado), KEY(plano)) ENGINE=$motor DEFAULT CHARSET=latin1");
$pdo->exec("CREATE TABLE sis_opcao (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) UNIQUE, valor TEXT)");
$pdo->exec("CREATE TABLE sis_plano (nome VARCHAR(255) PRIMARY KEY, valor VARCHAR(255))");
$pdo->exec("CREATE TABLE sis_logs (id INT AUTO_INCREMENT PRIMARY KEY, registro TEXT, data VARCHAR(30), login VARCHAR(64), tipo VARCHAR(20)) ENGINE=$motor DEFAULT CHARSET=latin1");
$pdo->exec("CREATE TABLE sis_enviadas (id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(255), data DATETIME, tipo VARCHAR(5), mensagem LONGTEXT) ENGINE=$motor DEFAULT CHARSET=latin1");
$pdo->exec("CREATE TABLE sis_configmsg (item VARCHAR(64) PRIMARY KEY, valor LONGTEXT)");
$pdo->exec("INSERT INTO sis_opcao (nome, valor) VALUES ('dia10','sim'),('dia20','sim'),('climk_dias_corte','15'),('dias_de_corte','Mon,Tue,Wed,Thu,Fri'),('auto_corte','sim')");
$pdo->exec("INSERT INTO sis_plano VALUES ('200mbps','89.90'),('300mbps','99.90'),('500mbps','139.90')");
$pdo->exec("INSERT INTO sis_configmsg VALUES ('wappmsg10depois','{\"tipo_01\":\"1\"}'),('wappmsg15depois','{\"tipo_01\":\"1\"}')");

echo "Gerando dados...\n";
$pdo->exec('CREATE TEMPORARY TABLE n (i INT PRIMARY KEY)');
$pdo->exec('INSERT INTO n (i) SELECT a.i + b.i * 10 + c.i * 100 + d.i * 1000 + e.i * 10000 FROM
    (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
    (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
    (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c,
    (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d,
    (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) e');
$ini = (new DateTimeImmutable(substr(Calendario::hoje(), 0, 7) . '-01'))->modify('-' . ($nMes - 2) . ' month')->format('Y-m-d');
$t('clientes', fn() => Db::exec("INSERT INTO sis_cliente (login, nome, uuid_cliente, plano, bairro, cidade, vendedor, venc, cli_ativado, bloqueado,
        data_desativacao, dias_corte, data_ins)
    SELECT CONCAT('u', i), CONCAT('Cliente ', i), MD5(i), ELT(1 + i % 3, '200mbps', '300mbps', '500mbps'), CONCAT('Bairro ', i % 40),
           CONCAT('Cidade ', i % 4), CONCAT('vend', i % 6), IF(i % 2, '10', '20'), IF(i % 10 = 0, 'n', 's'), IF(i % 97 = 0, 'sim', 'nao'),
           IF(i % 10 = 0, ? + INTERVAL (i % ?) MONTH, NULL), 15, ? - INTERVAL (i % 24) MONTH
      FROM n WHERE i < ?", [$ini, $nMes, $ini, $nCli]));
$pdo->exec('CREATE TEMPORARY TABLE mm (m INT PRIMARY KEY)');
$pdo->exec("INSERT INTO mm SELECT i FROM n WHERE i < $nMes");
$t('titulos (sis_lanc)', fn() => Db::exec("INSERT INTO sis_lanc (login, datavenc, datapag, status, tipo, valor, valorpag, formapag, coletor)
    SELECT x.login, x.v,
           CASE WHEN x.v >= CURDATE() OR x.r < 2 THEN NULL
                WHEN x.r < 47 THEN x.v - INTERVAL (x.r % 5) DAY
                WHEN x.v + INTERVAL (1 + x.r % 30) DAY > NOW() THEN NULL
                ELSE x.v + INTERVAL (1 + x.r % 30) DAY END,
           CASE WHEN x.v >= CURDATE() THEN 'aberto' WHEN x.r < 2 THEN 'vencido'
                WHEN x.r >= 47 AND x.v + INTERVAL (1 + x.r % 30) DAY > NOW() THEN 'vencido' ELSE 'pago' END,
           'mensalidade', '99.90', '99.90', ELT(1 + x.r % 4, 'pix', 'boleto', 'dinheiro', 'cartao'),
           IF(x.r % 4 = 1, 'arq.retorno', 'operador')
      FROM (SELECT c.login, c.data_ins, c.data_desativacao, (? + INTERVAL mm.m MONTH) AS mes,
                   (? + INTERVAL mm.m MONTH) + INTERVAL (c.venc - 1) DAY AS v,
                   CRC32(CONCAT(c.login, '-', mm.m)) % 100 AS r
              FROM sis_cliente c JOIN mm) x
     WHERE (x.data_desativacao IS NULL OR x.mes <= x.data_desativacao) AND x.mes >= DATE_FORMAT(x.data_ins, '%Y-%m-01')", [$ini, $ini]));
echo '  titulos gerados: ' . Db::valor('SELECT COUNT(*) FROM sis_lanc') . "\n";
// pagamento atrasado que ainda nao aconteceu fica como vencido sem data
Db::exec("UPDATE sis_lanc SET valorpag = NULL WHERE status <> 'pago'");
$t('log de bloqueios (sis_logs)', fn() => Db::exec("INSERT INTO sis_logs (registro, data, login, tipo)
    SELECT CONCAT('cliente ', login, ' bloqueado por atraso no titulo ', id, ' vencido em 01/01/26'),
           DATE_FORMAT(datavenc + INTERVAL 16 DAY, '%d/%m/%Y 08:00:00'), 'mk-bot', 'admin'
      FROM sis_lanc WHERE status = 'vencido' AND datavenc >= CURDATE() - INTERVAL 10 MONTH"));
$t('avisos (sis_enviadas)', fn() => Db::exec("INSERT INTO sis_enviadas (login, data, tipo, mensagem)
    SELECT login, datavenc + INTERVAL 10 DAY, 'app', CONCAT('[Titulo: ', id, '] Ola, nao identificamos o pagamento')
      FROM sis_lanc WHERE datavenc >= CURDATE() - INTERVAL 100 DAY AND datavenc < CURDATE() - INTERVAL 10 DAY
       AND (status <> 'pago' OR datapag > datavenc + INTERVAL 10 DAY)"));

echo "\nProcessamento (cron):\n";
$t('agregador completo (historia inteira)', fn() => Agregador::executar('carga', true));
$t('agregador incremental (noite seguinte)', fn() => Agregador::executar('carga'));
$t('caixa do dia (cron de 10 min)', fn() => Agregador::caixaHoje());

$mes = substr(Calendario::hoje(), 0, 7);
Db::limitarTempo(Config::int('limite_consulta_s'));
echo "\nTela (sem cache, teto de " . Config::int('limite_consulta_s') . " s por consulta):\n";
$f = Filtro::ler([]);
$t('Visao Geral: KPIs', fn() => Visao::kpis($mes, null));
$t('Visao Geral: curva do mes', fn() => Visao::curva($mes, null));
$t('Visao Geral: aging', fn() => Visao::aging('ativos', null));
$t('Visao Geral: safra 12m', fn() => Visao::safra($mes, null));
$t('Visao Geral: alertas', fn() => Visao::alertas());
$t('Inadimplencia: resumo (inclui reincidencia)', fn() => Inadimplencia::resumo($f));
$t('Inadimplencia: lista pagina 1', fn() => Inadimplencia::lista($f, 1, 'valor', 'desc'));
$t('Inadimplencia: por bairro', fn() => Inadimplencia::dimensao($f, 'bairro'));
$t('Inadimplencia: distribuicao', fn() => Inadimplencia::distribuicao($mes, null));
$t('Recebimentos: KPIs', fn() => Recebimento::kpis($mes, null));
$t('Recebimentos: por dia', fn() => Recebimento::porDia($mes, null));
$t('Recebimentos: 12 meses', fn() => Recebimento::mensal($mes, null));
$t('Recebimentos: coletores', fn() => Recebimento::coletores($mes));
$t('Recebimentos: pagamentos de um dia', fn() => Recebimento::pagamentosDia(Calendario::hoje(), 1));
$t('Carteira: KPIs', fn() => Carteira::kpis($mes));
$t('Carteira: movimento 12m', fn() => Carteira::movimento($mes));
$t('Carteira: recuperacao', fn() => Carteira::recuperacao());
$t('Carteira: primeira fatura', fn() => Carteira::primeiraFatura($mes));
$t('Carteira: bloqueios', fn() => Carteira::bloqueios($mes));
$t('Agenda: cortes 7 dias', fn() => Agenda::cortes(7));
$t('Agenda: vencimentos', fn() => Agenda::vencimentos(7));
$t('Agenda: efetividade 90 dias', fn() => Agenda::efetividade(90));
$t('Agenda: calendario do mes', fn() => Agenda::mes((int) substr($mes, 0, 4), (int) substr($mes, 5, 2)));
printf("\nMemoria de pico: %.1f MB\n", memory_get_peak_usage(true) / 1048576);

if (!isset($args['manter'])) {
    $admin->exec("DROP DATABASE IF EXISTS `{$cfg['name']}`");
    echo "Banco de carga apagado.\n";
}
