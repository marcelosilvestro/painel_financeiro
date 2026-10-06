-- painel_financeiro :: schema completo, idempotente.
--
-- Roda inteiro a cada instalacao/atualizacao (lib/Core/Schema.php): o que ja existe fica,
-- o que falta e criado. Mudanca de coluna futura entra aqui como ALTER condicional, nunca
-- como arquivo incremental solto.
--
-- Regras de escrita deste arquivo (o separador de comandos e simples):
--   * todo comando termina com ";" no FIM da linha
--   * comentario so em linha propria comecando com "--", nunca depois do ";"
--
-- As tabelas nativas do MK-AUTH (sis_lanc, sis_cliente, sis_opcao, sis_plano, sis_logs,
-- sis_enviadas, radacct) sao SOMENTE LEITURA e nao aparecem aqui. O addon so escreve nas
-- tab_pfin_* abaixo.

CREATE TABLE IF NOT EXISTS `tab_pfin_migration` (
  `migration`   VARCHAR(100) NOT NULL,
  `checksum`    CHAR(64)     NOT NULL,
  `versao`      VARCHAR(20)  NOT NULL DEFAULT '0',
  `executed_at` DATETIME     NOT NULL,
  `executed_by` VARCHAR(60)  NULL,
  `duracao_ms`  INT UNSIGNED NULL,
  `resultado`   VARCHAR(10)  NOT NULL DEFAULT 'ok',
  `erro`        TEXT         NULL,
  PRIMARY KEY (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuracao chave/valor. Os padroes vivem em lib/Core/Config.php: aqui so fica o que o
-- administrador alterou e os valores internos.
CREATE TABLE IF NOT EXISTS `tab_pfin_config` (
  `chave`        VARCHAR(64) NOT NULL,
  `valor`        TEXT        NULL,
  `alterado_por` VARCHAR(60) NULL,
  `alterado_em`  DATETIME    NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Papeis por login do MK-AUTH (sis_acesso.login).
CREATE TABLE IF NOT EXISTS `tab_pfin_permissao` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login`      VARCHAR(60)  NOT NULL,
  `papel`      VARCHAR(40)  NOT NULL,
  `criado_por` VARCHAR(60)  NULL,
  `criado_em`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login_papel` (`login`, `papel`),
  KEY `ix_papel` (`papel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem fez o que: configuracao, permissoes, exportacoes de lista, reprocessamentos.
CREATE TABLE IF NOT EXISTS `tab_pfin_auditoria` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `criado_em`   DATETIME        NOT NULL,
  `usuario`     VARCHAR(60)     NOT NULL,
  `ip`          VARCHAR(45)     NULL,
  `acao`        VARCHAR(60)     NOT NULL,
  `entidade`    VARCHAR(40)     NOT NULL,
  `entidade_id` BIGINT UNSIGNED NULL,
  `antes`       MEDIUMTEXT      NULL,
  `depois`      MEDIUMTEXT      NULL,
  `correlacao`  VARCHAR(64)     NULL,
  `request_id`  VARCHAR(32)     NULL,
  PRIMARY KEY (`id`),
  KEY `ix_criado` (`criado_em`),
  KEY `ix_entidade` (`entidade`, `entidade_id`),
  KEY `ix_usuario` (`usuario`),
  KEY `ix_correlacao` (`correlacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada execucao do agregador (cron noturno ou "processar agora").
CREATE TABLE IF NOT EXISTS `tab_pfin_execucao` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job`        VARCHAR(40)  NOT NULL,
  `inicio`     DATETIME     NOT NULL,
  `fim`        DATETIME     NULL,
  `duracao_ms` INT UNSIGNED NULL,
  `resultado`  VARCHAR(10)  NOT NULL DEFAULT 'rodando',
  `linhas`     INT UNSIGNED NULL,
  `detalhe`    TEXT         NULL,
  `usuario`    VARCHAR(60)  NULL,
  PRIMARY KEY (`id`),
  KEY `ix_job_inicio` (`job`, `inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fato de CAIXA: o que entrou por dia de pagamento (datapag), forma e dia de vencimento.
-- Existe porque sis_lanc.datapag nao tem indice: somar recebimentos direto na tabela nativa
-- varre todos os titulos pagos da historia a cada abertura da tela.
CREATE TABLE IF NOT EXISTS `tab_pfin_fato_dia` (
  `dia`          DATE             NOT NULL,
  `dia_venc`     TINYINT UNSIGNED NOT NULL,
  `forma`        VARCHAR(20)      NOT NULL,
  `recorrente`   TINYINT(1)       NOT NULL,
  `qtd`          INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_pago`   DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `valor_titulo` DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `juros`        DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `desconto`     DECIMAL(14,2)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`dia`, `dia_venc`, `forma`, `recorrente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fato de COMPETENCIA: cada mes de vencimento (safra) e o que aconteceu com os titulos dele.
-- So titulos da receita recorrente (config tipos_receita). "maduro" = prazo (vencimento
-- efetivo + tolerancia) ja passou; d30/d90 = 30/90 dias depois do vencimento efetivo.
CREATE TABLE IF NOT EXISTS `tab_pfin_fato_safra` (
  `mes`                  DATE             NOT NULL,
  `dia_venc`             TINYINT UNSIGNED NOT NULL,
  `qtd`                  INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor`                DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `qtd_em_dia`           INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_em_dia`         DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `qtd_atraso`           INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_atraso`         DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `qtd_vencido`          INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_vencido`        DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `qtd_a_vencer`         INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_a_vencer`       DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `qtd_maduro`           INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_maduro`         DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `valor_maduro_d30`     DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `valor_nao_pago_d30`   DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `valor_maduro_d90`     DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `valor_nao_pago_d90`   DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `qtd_baixa`            INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor_baixa`          DECIMAL(14,2)    NOT NULL DEFAULT 0,
  `atualizado_em`        DATETIME         NOT NULL,
  PRIMARY KEY (`mes`, `dia_venc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Distribuicao do atraso: titulos pagos por dias apos o vencimento efetivo
-- (0 = em dia ou antecipado, 1..30, 31 = 31 ou mais).
CREATE TABLE IF NOT EXISTS `tab_pfin_fato_atraso` (
  `mes`      DATE             NOT NULL,
  `dia_venc` TINYINT UNSIGNED NOT NULL,
  `dias`     TINYINT UNSIGNED NOT NULL,
  `qtd`      INT UNSIGNED     NOT NULL DEFAULT 0,
  `valor`    DECIMAL(14,2)    NOT NULL DEFAULT 0,
  PRIMARY KEY (`mes`, `dia_venc`, `dias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================================
-- CONTRATO COMPARTILHADO: tab_feriados
--
-- Uma tabela so para todos os addons (calendario, painel_financeiro, livro_caixa). Quem nao a
-- encontra cria com ESTE MESMO DDL — o bloco abaixo e copiado igual no baseline do
-- painel_financeiro. Mudou aqui, muda la.
--
--   data         dia (chave)
--   nome         nome do feriado ou motivo
--   tipo         'feriado' = dia nao util. ('dia_util' existe por compatibilidade, sem uso.)
--   abrangencia  nacional | estadual | municipal | outro
--   origem       quem gravou: brasilapi | manual | painel_financeiro | livro_caixa ...
--   alterado_*   ultima alteracao (vazio nas linhas antigas do Livro Caixa)
--
-- Regra de leitura em todos os addons: dia nao util = sabado, domingo ou tipo = 'feriado'.
-- A tabela pode ja existir no formato antigo (data, nome, tipo), criada pelo Livro Caixa: as
-- colunas novas entram com valor padrao, sem perder nada.
-- ============================================================================================
CREATE TABLE IF NOT EXISTS `tab_feriados` (
  `data` DATE         NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `tipo` ENUM('feriado','dia_util') NOT NULL DEFAULT 'feriado',
  PRIMARY KEY (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tab_feriados`
  ADD COLUMN IF NOT EXISTS `abrangencia` ENUM('nacional','estadual','municipal','outro') NOT NULL DEFAULT 'nacional',
  ADD COLUMN IF NOT EXISTS `origem` VARCHAR(20) NOT NULL DEFAULT 'manual',
  ADD COLUMN IF NOT EXISTS `alterado_por` VARCHAR(60) NULL,
  ADD COLUMN IF NOT EXISTS `alterado_em` DATETIME NULL;

-- Caixa por quem deu a baixa (coletor): "arq.retorno" e o retorno bancario automatico; os
-- demais sao logins de operador. Mesma origem e mesmo recorte do fato_dia.
CREATE TABLE IF NOT EXISTS `tab_pfin_fato_coletor` (
  `dia`        DATE          NOT NULL,
  `coletor`    VARCHAR(20)   NOT NULL,
  `qtd`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `valor_pago` DECIMAL(14,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`dia`, `coletor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eventos de bloqueio/desbloqueio lidos do sis_logs (texto). O MK-AUTH apaga log antigo e
-- reloga o mesmo bloqueio todo dia enquanto o cliente segue bloqueado: aqui fica cada evento
-- uma vez, para sempre, e os episodios sao montados na leitura.
CREATE TABLE IF NOT EXISTS `tab_pfin_evento_bloqueio` (
  `log_id`  BIGINT UNSIGNED NOT NULL,
  `data`    DATETIME        NOT NULL,
  `login`   VARCHAR(64)     NOT NULL,
  `tipo`    VARCHAR(12)     NOT NULL,
  `titulo`  BIGINT UNSIGNED NULL,
  `origem`  VARCHAR(12)     NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `ix_login_data` (`login`, `data`),
  KEY `ix_data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
