<?php
/**
 * Suite 01 :: schema do addon e as tabelas nativas minimas do MK-AUTH para os testes.
 */
T::suite('Schema');

$schema = new Schema(__DIR__ . '/../../sql');
$r = $schema->aplicar('teste', pf_versao());
T::igual('baseline cria todas as tabelas do addon', count(Schema::TABELAS), $r['tabelas']);
$r2 = $schema->aplicar('teste', pf_versao());
T::igual('baseline e idempotente (segunda aplicacao)', count(Schema::TABELAS), $r2['tabelas']);
$est = $schema->estado();
T::certo('estado: instalado e em dia', $est['instalado'] && !$est['desatualizado']);
Db::tabelaExiste(Db::ESQUECER);

// Tabelas nativas do MK-AUTH, so com as colunas que o addon le. Tipos iguais aos de producao
// (valor e valorpag VARCHAR, venc VARCHAR(2), datas DATETIME).
Db::pdo()->exec("CREATE TABLE sis_lanc (
    id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(255), datavenc DATETIME NULL, datapag DATETIME NULL,
    status VARCHAR(255) DEFAULT 'aberto', tipo VARCHAR(255), valor VARCHAR(50), valorpag VARCHAR(50),
    formapag VARCHAR(100), coletor VARCHAR(20), deltitulo TINYINT(1) DEFAULT 0, datadel DATETIME NULL,
    alt_venc TINYINT(1) DEFAULT 0, tarifa_paga DECIMAL(12,2) DEFAULT 0,
    KEY (login), KEY (datavenc), KEY (status), KEY (deltitulo)) DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(64) UNIQUE, nome VARCHAR(255), uuid_cliente VARCHAR(48),
    plano VARCHAR(64), bairro VARCHAR(255), cidade VARCHAR(255), vendedor VARCHAR(255), venc VARCHAR(2),
    cli_ativado ENUM('s','n') DEFAULT 's', bloqueado ENUM('sim','nao') DEFAULT 'nao', data_bloq DATETIME NULL, data_desbloq DATETIME NULL,
    data_desativacao DATETIME NULL, isento VARCHAR(3) DEFAULT 'nao', dias_corte INT(3) NULL, data_ins DATETIME NULL,
    desconto DECIMAL(12,2) DEFAULT 0, acrescimo DECIMAL(12,2) DEFAULT 0, observacao ENUM('sim','nao') DEFAULT 'nao') DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_logs (id INT AUTO_INCREMENT PRIMARY KEY, registro TEXT, data VARCHAR(30), login VARCHAR(64), tipo VARCHAR(20), operacao VARCHAR(20)) DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_enviadas (id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(255), data DATETIME, tipo ENUM('sms','web','app'), mensagem LONGTEXT, uuid VARCHAR(50), xto VARCHAR(64)) DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_configmsg (item VARCHAR(64) PRIMARY KEY, valor LONGTEXT) DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_opcao (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(255) UNIQUE, valor TEXT) DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_plano (nome VARCHAR(255) PRIMARY KEY, valor VARCHAR(255)) DEFAULT CHARSET=latin1");
Db::pdo()->exec("CREATE TABLE sis_acesso (id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(60), nome VARCHAR(255), ativo VARCHAR(3) DEFAULT 'sim') DEFAULT CHARSET=latin1");
Db::exec("INSERT INTO sis_acesso (login, nome) VALUES ('teste', 'Teste'), ('operador', 'Operador')");
Db::exec("INSERT INTO sis_opcao (nome, valor) VALUES ('dia10','sim'), ('dia20','sim'), ('dia05','nao'),
          ('climk_dias_corte','15'), ('dias_de_corte','Mon,Tue,Wed,Thu,Fri,Ped'), ('auto_corte','sim'), ('tbloqradius','pool')");
Db::tabelaExiste(Db::ESQUECER);
T::certo('tabelas nativas de teste criadas', Db::tabelaExiste('sis_lanc') && Db::tabelaExiste('sis_cliente'));
