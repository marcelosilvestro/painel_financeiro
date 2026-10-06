# Changelog

## 0.4.0 — 06/10/2026

Primeira versão pública.

- **Visão Geral:** recebido do previsto, em atraso, pago em dia (safra madura), bloqueados e
  recuperação de crédito; curva de recebimento do mês; atraso por faixa; safras de 12 meses com
  inadimplência D+30/D+90; alertas (bloqueado sem dívida, corte vencido sem bloqueio, títulos
  gerados após a desativação, processamento atrasado, guardião de feriado).
- **Inadimplência:** lista paginada com filtros, concentração por grupo, distribuição dos dias de
  atraso com o dia do corte e exportação CSV auditada.
- **Recebimentos:** o que entrou no mês (vs. mês anterior e mesmo mês do ano passado), juros e
  descontos, o que falta entrar, recebido por dia e por forma, 12 meses com o previsto e quem deu
  a baixa (retorno bancário × operadores).
- **Carteira:** clientes ativos, MRR, ARPU, churn, entradas × saídas, primeira fatura dos novos,
  bloqueios e desbloqueios, MRR por plano e recuperação de crédito por ano de desativação.
- **Agenda (só consulta):** calendário do mês de cobrança com vencimentos e % pago, avisos com
  entregues e falhas, cortes previstos pela regra real do MK-AUTH (nos próximos 7 dias, quantos
  serão cortados de verdade) e conflito de corte em feriado.
- **Guardião de feriado do corte:** em dia de feriado desliga o `auto_corte` do MK-AUTH e religa
  no dia seguinte, quando o corte (cumulativo) pega quem ficou para trás. Vem desligado; cron de
  5 minutos, auditoria e alertas. É a única escrita do addon numa tabela do MK-AUTH.
- Feriados lidos da tabela compartilhada `tab_feriados` (addon Calendário de Feriados).
- Processamento noturno em tabelas de fato próprias e cron de 10 minutos para o caixa do dia;
  testado com 30 mil clientes e 1,7 milhão de títulos.
- Papéis (ver, ver nomes, exportar, administrador), CSRF, auditoria, instalador, empacotador,
  workflow de release e suíte de testes com base sintética.
