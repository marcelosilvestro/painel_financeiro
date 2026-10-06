# Painel Financeiro — addon para MK-AUTH

Dashboard de **inadimplência, pontualidade e recebimentos** para provedores que usam o MK-AUTH.
Ele transforma os títulos do MK-AUTH em respostas rápidas:

- Quanto já entrou do que estava previsto para o mês, e se o ritmo está normal?
- Quanto está em atraso hoje, e esse atraso está envelhecendo?
- Que fatia dos clientes paga em dia, e a pontualidade está melhorando?
- Onde o atraso se concentra (plano, bairro, cidade, vendedor, dia de vencimento)?
- Quanto dá para recuperar de clientes já desativados?
- Há bloqueios e cortes fora do lugar?

O addon **só lê** as tabelas do MK-AUTH, com **uma exceção explícita e auditada**: o guardião de
feriado liga e desliga a opção `auto_corte` (veja abaixo).
Fora isso, ele grava apenas nas próprias tabelas (`tab_pfin_*`) e na tabela compartilhada de
feriados (`tab_feriados`).

## Instalação

No servidor do MK-AUTH, como root:

```sh
wget -O - https://raw.githubusercontent.com/marcelosilvestro/painel_financeiro/main/instalar.sh | bash
```

Sem internet no servidor, gere ou baixe o pacote `painel_financeiro-X.Y.Z.tar.gz` e rode:

```sh
bash instalar.sh --pacote=painel_financeiro-X.Y.Z.tar.gz
```

O mesmo comando instala, atualiza e repara. O instalador:

1. acha o acesso ao banco (variáveis `PF_DB_USER`/`PF_DB_PASS`, instalação anterior,
   `/opt/mk-auth/conf/secrets.php` ou o padrão de fábrica) e grava em
   `/opt/mk-auth/conf/painel_financeiro.php` (640, fora do webroot);
2. faz backup das tabelas do addon e dos arquivos antes de atualizar;
3. cria as tabelas `tab_pfin_*`, as pastas de dados/log e o item no menu **Financeiro › Relatórios**;
4. instala o cron noturno (`/etc/cron.d/painel_financeiro`, 03:40) e faz o primeiro processamento;
5. roda o diagnóstico (`bash instalar.sh --diagnostico` repete só essa parte).

Depois disso, abra o addon, **assuma a administração** (aviso no topo da Visão Geral) e, em
**Configurações › Permissões**, libere os outros usuários.

Requisitos: MK-AUTH com MariaDB, PHP 8.0+ com `pdo_mysql`, `mbstring` e `json`.

## Telas

| Tela | O que mostra |
|---|---|
| **Visão Geral** | 5 indicadores (recebido do previsto, em atraso, pago em dia, bloqueados, recuperação), curva de recebimento do mês, atraso por faixa, safras de 12 meses e alertas |
| **Inadimplência** | lista paginada de quem deve (com filtros, ordenação e exportação CSV), concentração por grupo e distribuição dos dias de atraso |
| **Recebimentos** | o que entrou no mês e a comparação com o mês anterior e o mesmo mês do ano passado, juros e descontos, o que falta entrar, recebido por dia e por forma (clique: pagamentos do dia), 12 meses com o previsto e quem deu a baixa (retorno bancário × operador) |
| **Carteira** | clientes ativos, MRR, ARPU, churn, entradas × saídas, primeira fatura dos clientes novos, bloqueios e desbloqueios, MRR por plano e recuperação de crédito por ano de desativação, com a lista dos devedores |
| **Agenda** | calendário do mês de cobrança: vencimentos com % pago, avisos com entregues e falhas, cortes previstos (nos próximos 7 dias, quantos serão cortados de verdade) e o alerta de corte em feriado. Clique no dia para ver o detalhe e quem será cortado. **Só para consulta**: feriados se cadastram no addon Calendário de Feriados, e o corte em feriado é resolvido pelo guardião. Na lateral: regras do MK-AUTH e estado do guardião, comunicações do mês, efetividade da régua e próximos feriados |
| **Configurações** | processamento, parâmetros lidos do MK-AUTH, regras do addon e permissões |

## Como cada número é calculado

Todo indicador declara o **regime**:

- **competência**: pelo mês de vencimento (`datavenc`), ou seja, o que era para entrar;
- **caixa**: pelo dia do pagamento (`datapag`), ou seja, o que entrou;
- **hoje**: a foto do momento, sem período.

Convenções que valem para todos:

- Título válido é `deltitulo = 0`. Título excluído **depois** de vencer conta como **baixa sem
  pagamento** (renegociação ou perdão). Ele aparece separado e fica **fora** da inadimplência.
- A receita recorrente usa os tipos de título configurados (padrão: `mensalidade`). Serviços e
  outros ficam fora de faturamento, safra e inadimplência.
- O **vencimento efetivo** empurra sábado e domingo para a segunda-feira, como faz o boleto do
  MK-AUTH. Feriados entram só se a opção for ligada.
- Juros recebidos = `valorpag − valor`. Desconto concedido = `valor − valorpag`. Os campos
  `valormulta` e `valormora` **não** são usados: eles guardam o que vai impresso no boleto, não
  o que foi cobrado.

| Indicador | Definição |
|---|---|
| Faturamento previsto | soma dos títulos com vencimento no mês (competência) |
| Recebido do previsto | quanto desses títulos já foi pago, com qualquer data de pagamento |
| Caixa do mês | tudo o que foi pago no mês, de qualquer vencimento e tipo (caixa) |
| Em atraso | títulos não pagos cujo vencimento efetivo já passou, de clientes ativos (hoje) |
| Pago em dia | na safra mais recente em que **todos** os títulos já venceram: pagos até o vencimento efetivo (+ tolerância) ÷ títulos da safra |
| Inadimplência D+30 / D+90 | valor não pago até 30/90 dias depois do vencimento ÷ valor da safra. Só aparece quando a safra inteira passou do marco |
| Atraso por faixa | títulos vencidos por dias desde o vencimento efetivo. Cada cliente é contado uma vez, na faixa do título mais antigo |
| Reincidente | cliente com N ou mais atrasos (pago depois do prazo ou não pago) entre os últimos M títulos vencidos (padrão: 3 de 6) |
| Recuperação de crédito | dívida de clientes desativados, **só** de títulos que venceram até a data da desativação |
| Bloqueados | clientes ativos com `bloqueado = 'sim'` |
| MRR | soma do valor do plano dos clientes ativos não isentos, menos o desconto e mais o acréscimo do cadastro |
| Churn | clientes desativados no mês ÷ clientes ativos no início do mês |
| Primeira fatura | para cada cliente novo, o primeiro título com vencimento a partir da instalação: pago em dia, pago com atraso, não pago ou ainda não venceu |
| Bloqueios | episódios montados do log do MK-AUTH: um bloqueio começa no primeiro registro depois de um desbloqueio. O MK-AUTH relança o mesmo bloqueio todo dia, e isso não conta como novo |
| Corte previsto | vencimento (fim de semana vai para segunda) + carência do cliente + 1, só nos dias da semana com corte. Feriado **não** é pulado, como no MK-AUTH; a Agenda avisa quando o corte cai num feriado |
| Efetividade do aviso | entre os avisos com título entregues, quantos títulos foram pagos em até 3 dias. Falha de entrega = o gateway devolveu erro. *Experimental: depende do texto gravado em `sis_enviadas`* |

O arquivo [`sql/auditoria/indicadores.sql`](sql/auditoria/indicadores.sql) tem consultas
independentes para conferir cada número direto no banco.

### O que vem do MK-AUTH (não é configurado no addon)

Os dias de vencimento (`diaNN`), a carência de corte (`climk_dias_corte` ou o `dias_corte` do
cliente), os dias da semana com corte (`dias_de_corte`) e se o corte é automático (`auto_corte`)
são lidos de **Provedor › Opções**. O addon não tem nenhuma regra fixa de uma empresa.

## Desempenho

`sis_lanc.datapag` não tem índice no MK-AUTH. Por isso, somar pagamentos direto na tabela varre
todo o histórico. O addon resolve isso **sem alterar o MK-AUTH**:

- um processamento noturno (03:40) monta tabelas de fato (`tab_pfin_fato_*`) com caixa por dia,
  safras, distribuição do atraso e eventos de bloqueio;
- um cron leve, a cada 10 minutos, agrega só o caixa do dia corrente. Se ele parar, a tela volta
  a ler o dia ao vivo sozinha;
- consultas de títulos vencidos usam dica de índice (`status`/`datavenc`), aplicada só quando o
  índice existe na instalação;
- toda consulta da tela tem um tempo máximo (`max_statement_time`, padrão de 15 s), e os
  indicadores ao vivo ficam em cache (padrão de 5 minutos);
- as listas são sempre paginadas no servidor.

Teste de carga (`tests/carga.php`), com 30 mil clientes, 1,7 milhão de títulos e TokuDB, numa VM
com 4 vCPU:

| Etapa | Tempo |
|---|---|
| Processamento completo (toda a história) | ~20 s |
| Processamento noturno | ~10 s |
| Caixa do dia | ~3 s |
| Indicadores da Visão Geral, sem cache | < 1 s cada |
| Inadimplência, sem cache | ~1,5 s |
| Recebimentos, Carteira e Agenda | < 0,5 s |
| Lista de pagamentos de um dia | ~2 s (é a única leitura que varre `datapag`) |

## Feriados

Os feriados ficam na tabela **compartilhada** `tab_feriados`, a mesma do Livro Caixa e do addon
[**Calendário de Feriados**](https://github.com/marcelosilvestro/calendario), que é onde eles são cadastrados e sincronizados com a BrasilAPI. O
painel lê essa tabela e, se ela não existir, cria com o mesmo DDL (está em `sql/baseline.sql`).

## Guardião de feriado do corte (recomendado)

O corte do MK-AUTH (`/opt/mk-auth/scripts/corte.php`, de 15 em 15 minutos, das 8h às 22h) não
conhece feriado. Observando as consultas que ele envia ao banco:

- antes de selecionar qualquer cliente, ele lê `dias_de_corte` e `auto_corte` em `sis_opcao`;
- ele seleciona **todo** cliente ativo, não bloqueado e **não em observação** com título
  `mensalidade` não pago em que `TO_DAYS(hoje) − TO_DAYS(datavenc) > dias_corte`. Por isso o corte é
  **cumulativo**: quem não foi cortado num dia é cortado na rodada válida seguinte.

Com **Configurações › Cobrança › Guardião de feriado** ligado, um cron do addon (a cada 5 minutos):

1. num dia que é feriado na tabela `tab_feriados` (addon Calendário de Feriados), **desliga o
   "corte automático"** do MK-AUTH;
2. no primeiro dia que não é feriado, **religa**, e o MK-AUTH corta quem ficou para trás.

Ninguém precisa mexer no `dias_corte` dos clientes nem lembrar de voltar ao padrão. O guardião só
religa o que **ele** desligou: se você deixou o corte desligado, ele não toca. Toda ação fica na
auditoria. Se alguém salvar as Opções do MK-AUTH durante o feriado e religar o corte, o guardião
desliga de novo em até 5 minutos. Se o cron dele parar, ou o corte ficar suspenso fora de um
feriado, aparece um alerta. A Agenda mostra o corte adiado (selo com escudo) no dia em que ele
vai acontecer.

Limite: um feriado marcado depois das 8h do próprio dia não desfaz os cortes que já aconteceram.

## Segurança

- A sessão é a do próprio MK-AUTH. O addon não tem um login próprio.
- Papéis do addon:
  - **Ver indicadores**: só números agregados;
  - **Ver listas com nome**: listas com os clientes;
  - **Exportar CSV**: cada exportação fica registrada na auditoria;
  - **Administrador**.
- Toda operação passa por um roteador único: rota conhecida → método HTTP → token CSRF nas
  escritas → papel exigido.
- Filtros só aceitam valores, nunca nomes de coluna. Todo SQL usa parâmetros.
- O CSV neutraliza fórmulas (`= + - @`) vindas do cadastro.
- `lib/`, `cli/` e `sql/` são bloqueadas por `.htaccess`. A pasta `tests/` não entra no pacote.

## Desenvolvimento

```sh
bash empacotar.sh                    # gera pacote/painel_financeiro-X.Y.Z.tar.gz
php tests/run.php --conf=/opt/mk-auth/conf/painel_financeiro.php
```

A suíte cria e apaga um banco próprio (`mkradius_pfin_test`). Ela se recusa a rodar com um
banco cujo nome não contenha `test` ou a partir de `/opt/mk-auth`.

Teste de carga, com um banco próprio que é apagado no fim:

```sh
php -d memory_limit=1024M tests/carga.php --conf=/opt/mk-auth/conf/painel_financeiro.php --clientes=30000 --meses=60
```

Bibliotecas de terceiros: [Chart.js](https://www.chartjs.org) 4.4.1 (MIT), empacotada em
`js/vendor/`. O addon funciona sem internet.

## Licença

MIT. Veja [LICENSE](LICENSE).
