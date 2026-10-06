<?php
/**
 * painel_financeiro :: indicadores da tela Carteira.
 *
 * Base de clientes, receita recorrente contratada (MRR), entradas e saidas, divida de
 * desativados, comportamento da primeira fatura e historico de bloqueios.
 */
final class Carteira
{
    /** Cartoes da tela. */
    public static function kpis(string $mes): array
    {
        [$v, $em] = Cache::lembrar('cart.kpis', ['mes' => $mes], function () use ($mes) {
            $mrr = self::mrr();
            [$ini, $fim] = Calendario::limitesMes($mes);
            $mov = self::movimentoMes($ini, $fim);
            $iniBaixa = (new DateTimeImmutable($mes . '-01'))->modify('-11 month')->format('Y-m-d');
            $baixa = Db::um('SELECT IFNULL(SUM(valor_baixa), 0) v, IFNULL(SUM(qtd_baixa), 0) q FROM tab_pfin_fato_safra
                              WHERE mes >= ? AND mes <= ?', [$iniBaixa, $mes . '-01']);
            return ['mrr' => $mrr, 'movimento' => $mov, 'recuperavel' => Visao::recuperavel(),
                    'baixa_12m' => ['valor' => (float) $baixa['v'], 'qtd' => (int) $baixa['q']]];
        });
        $v['calculado_em'] = $em;
        return $v;
    }

    /**
     * MRR: soma do valor do plano dos clientes ativos nao isentos, menos desconto e mais
     * acrescimo do cadastro. Cliente sem plano cadastrado entra com zero (e e contado a parte).
     */
    public static function mrr(): array
    {
        $r = Db::um(
            "SELECT COUNT(*) AS ativos,
                    SUM(IFNULL(c.isento, 'nao') = 'sim') AS isentos,
                    SUM(IFNULL(c.isento, 'nao') <> 'sim' AND p.nome IS NULL) AS sem_plano,
                    IFNULL(SUM(IF(IFNULL(c.isento, 'nao') <> 'sim',
                        IFNULL(CAST(p.valor AS DECIMAL(12,2)), 0) - IFNULL(c.desconto, 0) + IFNULL(c.acrescimo, 0), 0)), 0) AS mrr
               FROM sis_cliente c LEFT JOIN sis_plano p ON p.nome = c.plano
              WHERE c.cli_ativado = 's'");
        $pagantes = (int) $r['ativos'] - (int) $r['isentos'];
        return ['ativos' => (int) $r['ativos'], 'isentos' => (int) $r['isentos'], 'sem_plano' => (int) $r['sem_plano'],
                'mrr' => round((float) $r['mrr'], 2), 'arpu' => $pagantes > 0 ? round((float) $r['mrr'] / $pagantes, 2) : null];
    }

    /** MRR por plano (ativos nao isentos). */
    public static function mrrPorPlano(): array
    {
        $rows = Db::todos(
            "SELECT IFNULL(c.plano, '(sem plano)') AS plano, COUNT(*) AS clientes,
                    SUM(IFNULL(CAST(p.valor AS DECIMAL(12,2)), 0) - IFNULL(c.desconto, 0) + IFNULL(c.acrescimo, 0)) AS mrr
               FROM sis_cliente c LEFT JOIN sis_plano p ON p.nome = c.plano
              WHERE c.cli_ativado = 's' AND IFNULL(c.isento, 'nao') <> 'sim'
              GROUP BY 1 ORDER BY mrr DESC");
        return ['itens' => array_map(fn($r) => ['plano' => $r['plano'], 'clientes' => (int) $r['clientes'],
                                                'mrr' => round((float) $r['mrr'], 2)], $rows)];
    }

    /**
     * Movimento de um mes: ativos no inicio, novos (data_ins), desativados (data_desativacao) e
     * churn = desativados / ativos no inicio. "Com divida" = desativado com titulo vencido ate a
     * data da desativacao ainda em aberto.
     */
    public static function movimentoMes(string $ini, string $fim): array
    {
        $r = Db::um(
            "SELECT SUM(c.data_ins < ? AND (c.data_desativacao IS NULL OR c.data_desativacao >= ?)) AS inicio,
                    SUM(c.data_ins >= ? AND c.data_ins < ?) AS novos,
                    SUM(c.data_desativacao >= ? AND c.data_desativacao < ?) AS saidas
               FROM sis_cliente c", [$ini, $ini, $ini, $fim, $ini, $fim]);
        $comDivida = (int) Db::valor(
            "SELECT COUNT(DISTINCT c.login) FROM sis_cliente c JOIN sis_lanc l ON l.login = c.login
              WHERE c.data_desativacao >= ? AND c.data_desativacao < ? AND l.deltitulo = 0 AND l.status <> 'pago'
                AND l.datavenc <= c.data_desativacao AND l.tipo IN " . Calendario::sqlTiposReceita(), [$ini, $fim]);
        $inicio = (int) $r['inicio'];
        return ['inicio' => $inicio, 'novos' => (int) $r['novos'], 'saidas' => (int) $r['saidas'], 'saidas_com_divida' => $comDivida,
                'churn' => $inicio > 0 ? round(100 * (int) $r['saidas'] / $inicio, 2) : null];
    }

    /** Entradas, saidas, base e churn dos ultimos 12 meses. */
    public static function movimento(string $mes): array
    {
        [$v] = Cache::lembrar('cart.mov', ['mes' => $mes], function () use ($mes) {
            $saida = ['meses' => [], 'novos' => [], 'saidas' => [], 'saidas_com_divida' => [], 'base' => [], 'churn' => []];
            for ($i = 11; $i >= 0; $i--) {
                $m = (new DateTimeImmutable($mes . '-01'))->modify("-$i month");
                $mv = self::movimentoMes($m->format('Y-m-d'), $m->modify('+1 month')->format('Y-m-d'));
                $saida['meses'][] = $m->format('Y-m');
                $saida['novos'][] = $mv['novos'];
                $saida['saidas'][] = $mv['saidas'];
                $saida['saidas_com_divida'][] = $mv['saidas_com_divida'];
                $saida['base'][] = $mv['inicio'] + $mv['novos'] - $mv['saidas'];
                $saida['churn'][] = $mv['churn'];
            }
            return $saida;
        });
        return $v;
    }

    /**
     * Recuperacao de credito por ano de desativacao. Titulo vencido ha mais de 5 anos e marcado
     * como possivelmente prescrito (Codigo Civil, art. 206, par. 5o, I) — quem decide e o juridico.
     */
    public static function recuperacao(): array
    {
        [$v] = Cache::lembrar('cart.recup', [], function () {
            $hoje = Calendario::hoje();
            $limitePrescricao = (new DateTimeImmutable($hoje))->modify('-5 year')->format('Y-m-d');
            $anos = [];
            foreach (self::titulosRecuperaveis() as $t) {
                $a = $t['data_desativacao'] ? substr((string) $t['data_desativacao'], 0, 4) : 'sem data';
                $anos[$a] ??= ['ano' => $a, 'valor' => 0.0, 'titulos' => 0, 'clientes' => [], 'prescrito' => 0.0];
                $anos[$a]['valor'] += (float) $t['valor'];
                $anos[$a]['titulos']++;
                $anos[$a]['clientes'][$t['login']] = true;
                if ($t['datavenc'] < $limitePrescricao) {
                    $anos[$a]['prescrito'] += (float) $t['valor'];
                }
            }
            krsort($anos);
            return ['anos' => array_values(array_map(fn($x) => ['ano' => $x['ano'], 'valor' => round($x['valor'], 2),
                'titulos' => $x['titulos'], 'clientes' => count($x['clientes']), 'prescrito' => round($x['prescrito'], 2)], $anos))];
        });
        return $v;
    }

    /** Titulos de desativados vencidos ATE a data da desativacao (a divida de verdade). */
    private static function titulosRecuperaveis(): array
    {
        return array_values(array_filter(Visao::titulosVencidos(" AND c.cli_ativado = 'n'"), function ($t) {
            return !$t['data_desativacao'] || $t['datavenc'] <= substr((string) $t['data_desativacao'], 0, 10);
        }));
    }

    /** Lista nominal da recuperacao, por cliente, paginada. */
    public static function recuperacaoLista(int $pagina, string $ordem, ?string $ano): array
    {
        $por = [];
        foreach (self::titulosRecuperaveis() as $t) {
            $a = $t['data_desativacao'] ? substr((string) $t['data_desativacao'], 0, 4) : 'sem data';
            if ($ano !== null && $a !== $ano) {
                continue;
            }
            $l = $t['login'];
            $por[$l] ??= ['login' => $l, 'nome' => $t['nome'], 'uuid' => $t['uuid_cliente'], 'cidade' => $t['cidade'],
                          'desativado' => $t['data_desativacao'] ? substr((string) $t['data_desativacao'], 0, 10) : null,
                          'titulos' => 0, 'valor' => 0.0, 'mais_antigo' => $t['datavenc']];
            $por[$l]['titulos']++;
            $por[$l]['valor'] += (float) $t['valor'];
            if ($t['datavenc'] < $por[$l]['mais_antigo']) {
                $por[$l]['mais_antigo'] = $t['datavenc'];
            }
        }
        $lista = array_values($por);
        usort($lista, function ($a, $b) use ($ordem) {
            if ($ordem === 'nome') {
                return strcasecmp((string) $a['nome'], (string) $b['nome']);
            }
            if ($ordem === 'desativado') {
                return strcmp((string) $b['desativado'], (string) $a['desativado']);
            }
            return $b['valor'] <=> $a['valor'];
        });
        $porPag = Config::int('linhas_por_pagina');
        $total = count($lista);
        $pagina = max(1, min($pagina, max(1, (int) ceil($total / $porPag))));
        $linhas = array_slice($lista, ($pagina - 1) * $porPag, $porPag);
        foreach ($linhas as &$x) {
            $x['valor'] = round($x['valor'], 2);
        }
        unset($x);
        return ['total' => $total, 'pagina' => $pagina, 'por_pagina' => $porPag, 'linhas' => $linhas];
    }

    /**
     * Primeira fatura dos clientes novos, por mes de instalacao (ultimos 12 meses): pagou em
     * dia, pagou com atraso, nao pagou (prazo passou) ou ainda nao venceu. Cliente que nao paga
     * a primeira fatura e sinal de venda ruim ou golpe.
     */
    public static function primeiraFatura(string $mes): array
    {
        [$v] = Cache::lembrar('cart.prim', ['mes' => $mes], function () use ($mes) {
            $ini = (new DateTimeImmutable($mes . '-01'))->modify('-11 month')->format('Y-m-d');
            $fim = (new DateTimeImmutable($mes . '-01'))->modify('+1 month')->format('Y-m-d');
            $prazo = Calendario::sqlPrazo('l.datavenc');
            $rows = Db::todos(
                "SELECT c.login, DATE_FORMAT(c.data_ins, '%Y-%m') AS m, l.status, DATE(l.datapag) AS pag, $prazo AS prazo, l.datavenc
                   FROM sis_cliente c
                   JOIN sis_lanc l ON l.login = c.login AND l.deltitulo = 0 AND l.tipo IN " . Calendario::sqlTiposReceita() . "
                                  AND l.datavenc >= DATE(c.data_ins)
                  WHERE c.data_ins >= ? AND c.data_ins < ?
                  ORDER BY c.login, l.datavenc", [$ini, $fim]);
            $primeira = [];
            foreach ($rows as $r) {
                $primeira[$r['login']] ??= $r;
            }
            $meses = [];
            for ($d = new DateTimeImmutable($ini); $d->format('Y-m-d') < $fim; $d = $d->modify('+1 month')) {
                $meses[$d->format('Y-m')] = ['mes' => $d->format('Y-m'), 'em_dia' => 0, 'atraso' => 0, 'nao_pagou' => 0, 'a_vencer' => 0];
            }
            $hoje = Calendario::hoje();
            foreach ($primeira as $r) {
                if (!isset($meses[$r['m']])) {
                    continue;
                }
                if ($r['status'] === 'pago') {
                    $k = ($r['pag'] !== null && $r['pag'] <= $r['prazo']) ? 'em_dia' : 'atraso';
                } else {
                    $k = $r['prazo'] < $hoje ? 'nao_pagou' : 'a_vencer';
                }
                $meses[$r['m']][$k]++;
            }
            return ['meses' => array_values($meses)];
        });
        return $v;
    }

    /**
     * Bloqueios e desbloqueios por mes, montados em EPISODIOS a partir dos eventos do sis_logs
     * (o MK-AUTH reloga o bloqueio todo dia enquanto o cliente segue bloqueado): um episodio
     * comeca no primeiro bloqueio depois de um desbloqueio e termina no desbloqueio seguinte.
     */
    public static function bloqueios(string $mes): array
    {
        [$v] = Cache::lembrar('cart.bloq', ['mes' => $mes], function () use ($mes) {
            $ini = (new DateTimeImmutable($mes . '-01'))->modify('-11 month')->format('Y-m-d');
            $fim = (new DateTimeImmutable($mes . '-01'))->modify('+1 month')->format('Y-m-d');
            $desde = Db::valor('SELECT MIN(data) FROM tab_pfin_evento_bloqueio');
            $meses = [];
            for ($d = new DateTimeImmutable($ini); $d->format('Y-m-d') < $fim; $d = $d->modify('+1 month')) {
                $meses[$d->format('Y-m')] = ['mes' => $d->format('Y-m'), 'bloqueios' => 0, 'desbloqueios_auto' => 0,
                                             'desbloqueios_manual' => 0, 'duracoes' => []];
            }
            // Quem ja estava bloqueado quando o historico do log comeca aparece nos primeiros dias
            // como "bloqueio" (o MK-AUTH reloga todo dia): isso nao e episodio novo.
            $carencia = $desde ? date('Y-m-d H:i:s', strtotime((string) $desde) + 2 * 86400) : null;
            $estado = [];
            $inicio = [];
            foreach (Db::todos('SELECT login, data, tipo, origem FROM tab_pfin_evento_bloqueio WHERE data < ? ORDER BY login, data, log_id', [$fim]) as $e) {
                $l = $e['login'];
                $m = substr((string) $e['data'], 0, 7);
                if ($e['tipo'] === 'bloqueio') {
                    if (empty($estado[$l])) {
                        $estado[$l] = true;
                        $herdado = !isset($inicio[$l]) && $carencia !== null && (string) $e['data'] < $carencia;
                        $inicio[$l] = $herdado ? null : (string) $e['data'];
                        if (!$herdado && isset($meses[$m])) {
                            $meses[$m]['bloqueios']++;
                        }
                    }
                } elseif (!empty($estado[$l])) {
                    $estado[$l] = false;
                    if (isset($meses[$m])) {
                        $meses[$m][$e['origem'] === 'manual' ? 'desbloqueios_manual' : 'desbloqueios_auto']++;
                        if ($inicio[$l] !== null) {
                            $meses[$m]['duracoes'][] = (strtotime((string) $e['data']) - strtotime($inicio[$l])) / 86400;
                        }
                    }
                }
            }
            foreach ($meses as &$x) {
                $d = $x['duracoes'];
                sort($d);
                $n = count($d);
                $x['mediana_dias'] = $n ? round($n % 2 ? $d[intdiv($n, 2)] : ($d[$n / 2 - 1] + $d[$n / 2]) / 2, 1) : null;
                unset($x['duracoes']);
            }
            unset($x);
            return ['meses' => array_values($meses), 'historico_desde' => $desde ? substr((string) $desde, 0, 10) : null];
        });
        return $v;
    }
}
