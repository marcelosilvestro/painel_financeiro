-- painel_financeiro :: consultas de AUDITORIA dos indicadores.
--
-- Escritas de forma independente do codigo do addon, para conferir os numeros da tela direto
-- no banco. Todas sao SOMENTE LEITURA. Troque as datas do mes e, se o provedor usa outros tipos
-- de titulo na receita recorrente (Configuracoes > Tipos de titulo), troque 'mensalidade'.
--
--   mysql -u root -p mkradius < sql/auditoria/indicadores.sql
--
-- "Vencimento efetivo" = datavenc empurrado do sabado/domingo para a segunda (sem feriados).

SET @ini = '2026-09-01', @fim = '2026-10-01';

-- Faturamento previsto (competencia) e quanto dele ja foi pago
SELECT 'previsto' AS indicador, COUNT(*) AS titulos, ROUND(SUM(valor + 0), 2) AS valor,
       ROUND(SUM(IF(status = 'pago', valorpag + 0, 0)), 2) AS recebido
  FROM sis_lanc
 WHERE deltitulo = 0 AND tipo = 'mensalidade' AND datavenc >= @ini AND datavenc < @fim;

-- Recebido no mes (caixa): tudo o que foi pago no periodo, qualquer vencimento e tipo
SELECT 'caixa' AS indicador, COUNT(*) AS titulos, ROUND(SUM(valorpag + 0), 2) AS valor,
       ROUND(SUM(GREATEST(valorpag - valor, 0)), 2) AS juros, ROUND(SUM(GREATEST(valor - valorpag, 0)), 2) AS desconto
  FROM sis_lanc
 WHERE deltitulo = 0 AND status = 'pago' AND datapag >= @ini AND datapag < @fim;

-- Pago em dia na safra (titulos com vencimento no mes)
SELECT 'pago_em_dia' AS indicador, COUNT(*) AS titulos,
       SUM(status = 'pago' AND DATE(datapag) <= DATE(datavenc)
           + INTERVAL (CASE DAYOFWEEK(datavenc) WHEN 7 THEN 2 WHEN 1 THEN 1 ELSE 0 END) DAY) AS em_dia
  FROM sis_lanc
 WHERE deltitulo = 0 AND tipo = 'mensalidade' AND datavenc >= @ini AND datavenc < @fim;

-- Em atraso hoje, clientes ativos
SELECT 'em_atraso_ativos' AS indicador, COUNT(*) AS titulos, COUNT(DISTINCT l.login) AS clientes, ROUND(SUM(l.valor + 0), 2) AS valor
  FROM sis_lanc l JOIN sis_cliente c ON c.login = l.login
 WHERE l.deltitulo = 0 AND l.tipo = 'mensalidade' AND l.status <> 'pago' AND c.cli_ativado = 's'
   AND DATE(l.datavenc) + INTERVAL (CASE DAYOFWEEK(l.datavenc) WHEN 7 THEN 2 WHEN 1 THEN 1 ELSE 0 END) DAY < CURDATE();

-- Bloqueados (clientes ativos)
SELECT 'bloqueados' AS indicador, COUNT(*) AS clientes FROM sis_cliente WHERE cli_ativado = 's' AND bloqueado = 'sim';

-- Recuperacao de credito: desativados, so titulos vencidos ATE a desativacao
SELECT 'recuperavel' AS indicador, COUNT(*) AS titulos, COUNT(DISTINCT l.login) AS clientes, ROUND(SUM(l.valor + 0), 2) AS valor
  FROM sis_lanc l JOIN sis_cliente c ON c.login = l.login
 WHERE l.deltitulo = 0 AND l.tipo = 'mensalidade' AND l.status <> 'pago' AND c.cli_ativado = 'n' AND l.datavenc < CURDATE()
   AND (c.data_desativacao IS NULL OR DATE(l.datavenc) <= DATE(c.data_desativacao));

-- Titulos gerados DEPOIS da desativacao (nao e divida: cadastro a limpar)
SELECT 'pos_desativacao' AS indicador, COUNT(*) AS titulos, COUNT(DISTINCT l.login) AS clientes, ROUND(SUM(l.valor + 0), 2) AS valor
  FROM sis_lanc l JOIN sis_cliente c ON c.login = l.login
 WHERE l.deltitulo = 0 AND l.tipo = 'mensalidade' AND l.status <> 'pago' AND c.cli_ativado = 'n' AND l.datavenc < CURDATE()
   AND DATE(l.datavenc) > DATE(c.data_desativacao);
