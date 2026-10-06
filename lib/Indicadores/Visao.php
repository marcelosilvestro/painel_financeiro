<?php
/**
 * painel_financeiro :: indicadores da Visao Geral.
 *
 * Cada metodo e um indicador documentado no README (definicao, regime e fonte). Regime:
 *   competencia  pelo mes de VENCIMENTO (datavenc) — "o que era para entrar"
 *   caixa        pelo dia de PAGAMENTO (datapag)   — "o que entrou"
 *   foto         o estado de hoje, sem periodo
 *
 * Convencoes: titulo valido = deltitulo = 0; valores com CAST(... AS DECIMAL) porque valor e
 * valorpag sao VARCHAR no MK-AUTH; valormulta/valormora NAO sao valores recebidos (sao os
 * parametros impressos no boleto) — juros = valorpag - valor.
 */
final class Visao
{
    // ------------------------------------------------------------------ KPIs

    public static function kpis(string $mes, ?int $dv): array
    {
        [$kpis, $em] = Cache::lembrar('visao.kpis', ['mes' => $mes, 'dv' => $dv], function () use ($mes, $dv) {
            $ant = Calendario::mesAnterior($mes);
            return [
                'previsto'      => self::previsto($mes, $dv),
                'previsto_ant'  => self::previsto($ant, $dv),
                'caixa'         => self::recebidoCaixa($mes, $dv),
                'caixa_ant'     => self::recebidoCaixa($ant, $dv),
                'em_atraso'     => self::emAtraso($dv),
                'pago_em_dia'   => self::pagoEmDia($mes, $dv),
                'bloqueados'    => self::bloqueados($dv),
                'recuperavel'   => self::recuperavel(),
            ];
        });
        $kpis['calculado_em'] = $em;
        $kpis['hoje'] = Calendario::hoje();
        $kpis['mes'] = $mes;
        return $kpis;
    }

    /**
     * Faturamento previsto (competencia): titulos da receita recorrente com vencimento no mes.
     * "ate_hoje" = parte cujo vencimento efetivo ja chegou — o que ja deveria ter entrado.
     */
    public static function previsto(string $mes, ?int $dv): array
    {
        [$ini, $fim] = Calendario::limitesMes($mes);
        $tipos = Calendario::sqlTiposReceita();
        $ef = Calendario::sqlVencEf('l.datavenc');
        $prazo = Calendario::sqlPrazo('l.datavenc');
        $v = 'CAST(l.valor AS DECIMAL(12,2))';
        $p = [$ini, $fim];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
            $p[] = $dv;
        }
        $r = Db::um(
            "SELECT COUNT(*) AS qtd, IFNULL(SUM($v), 0) AS valor,
                    IFNULL(SUM(IF($ef <= CURDATE(), $v, 0)), 0) AS valor_ate_hoje,
                    SUM(l.status = 'pago') AS qtd_pagos,
                    IFNULL(SUM(IF(l.status = 'pago', CAST(l.valorpag AS DECIMAL(12,2)), 0)), 0) AS recebido,
                    SUM(l.status = 'pago' AND DATE(l.datapag) <= $prazo) AS qtd_em_dia
               FROM sis_lanc l
              WHERE l.datavenc >= ? AND l.datavenc < ? AND l.deltitulo = 0 AND l.tipo IN $tipos $fDv", $p);
        return [
            'qtd' => (int) $r['qtd'], 'valor' => (float) $r['valor'], 'valor_ate_hoje' => (float) $r['valor_ate_hoje'],
            'qtd_pagos' => (int) $r['qtd_pagos'], 'recebido' => (float) $r['recebido'], 'qtd_em_dia' => (int) $r['qtd_em_dia'],
        ];
    }

    /**
     * Recebido no mes (caixa): tudo o que foi pago no mes, de qualquer vencimento e tipo.
     * Dias ate tab_pfin_fato_dia.ate vem do fato; o restante (hoje, ou tudo se o agregador
     * nunca rodou) e lido ao vivo — e e por isso que o resultado vai para o cache.
     */
    public static function recebidoCaixa(string $mes, ?int $dv): array
    {
        [$ini, $fim] = Calendario::limitesMes($mes);
        $ate = Agregador::fatoCobreAte();
        $porForma = [];
        $tot = ['valor' => 0.0, 'qtd' => 0, 'recorrente' => 0.0, 'juros' => 0.0, 'desconto' => 0.0];

        $somar = function (array $rows) use (&$porForma, &$tot) {
            foreach ($rows as $r) {
                $f = (string) $r['forma'];
                $porForma[$f] = ($porForma[$f] ?? 0) + (float) $r['valor_pago'];
                $tot['valor'] += (float) $r['valor_pago'];
                $tot['qtd'] += (int) $r['qtd'];
                $tot['juros'] += (float) $r['juros'];
                $tot['desconto'] += (float) $r['desconto'];
                if ((int) $r['recorrente'] === 1) {
                    $tot['recorrente'] += (float) $r['valor_pago'];
                }
            }
        };

        // 1. parte agregada
        $vivoDesde = $ini;
        if ($ate !== '' && $ate >= $ini) {
            $p = [$ini, min($fim, (new DateTimeImmutable($ate))->modify('+1 day')->format('Y-m-d'))];
            $fDv = '';
            if ($dv !== null) {
                $fDv = ' AND dia_venc = ?';
                $p[] = $dv;
            }
            $somar(Db::todos(
                "SELECT forma, recorrente, SUM(qtd) AS qtd, SUM(valor_pago) AS valor_pago, SUM(juros) AS juros, SUM(desconto) AS desconto
                   FROM tab_pfin_fato_dia WHERE dia >= ? AND dia < ? $fDv GROUP BY forma, recorrente", $p));
            $vivoDesde = (new DateTimeImmutable($ate))->modify('+1 day')->format('Y-m-d');
        }

        // 2. parte ao vivo (o que o agregador ainda nao cobriu, dentro do mes)
        $hojeMais1 = (new DateTimeImmutable(Calendario::hoje()))->modify('+1 day')->format('Y-m-d');
        $vivoAte = min($fim, $hojeMais1);
        if ($vivoDesde < $vivoAte) {
            $tipos = Calendario::sqlTiposReceita();
            $p = [$vivoDesde, $vivoAte];
            $fDv = '';
            if ($dv !== null) {
                $fDv = ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
                $p[] = $dv;
            }
            $somar(Db::todos(
                "SELECT " . Agregador::sqlForma('l.formapag') . " AS forma, IF(l.tipo IN $tipos, 1, 0) AS recorrente,
                        COUNT(*) AS qtd, SUM(CAST(l.valorpag AS DECIMAL(12,2))) AS valor_pago,
                        SUM(GREATEST(CAST(l.valorpag AS DECIMAL(12,2)) - CAST(l.valor AS DECIMAL(12,2)), 0)) AS juros,
                        SUM(GREATEST(CAST(l.valor AS DECIMAL(12,2)) - CAST(l.valorpag AS DECIMAL(12,2)), 0)) AS desconto
                   FROM sis_lanc l
                  WHERE l.deltitulo = 0 AND l.status = 'pago' AND l.datapag >= ? AND l.datapag < ? $fDv
                  GROUP BY 1, 2", $p));
        }

        arsort($porForma);
        $formas = [];
        foreach ($porForma as $f => $val) {
            $formas[] = ['forma' => $f, 'valor' => round($val, 2)];
        }
        return ['valor' => round($tot['valor'], 2), 'qtd' => $tot['qtd'], 'recorrente' => round($tot['recorrente'], 2),
                'juros' => round($tot['juros'], 2), 'desconto' => round($tot['desconto'], 2), 'formas' => $formas,
                'ao_vivo_desde' => $vivoDesde < $vivoAte ? $vivoDesde : null];
    }

    /**
     * Titulos vencidos e nao pagos HOJE (foto), da receita recorrente, com o cliente.
     * Dois ramos em UNION ALL para cada um usar o proprio indice: status='vencido' (o MK-AUTH
     * marca duas vezes por dia) e o 'aberto' que venceu desde a ultima marcacao.
     *
     * @param string $condCliente fragmento "AND ..." sobre sis_cliente c
     */
    public static function titulosVencidos(string $condCliente = '', array $paramsCliente = [], bool $todosTipos = false): array
    {
        $tipos = Calendario::sqlTiposReceita();
        $fTipo = $todosTipos ? '' : " AND l.tipo IN $tipos";
        $prazo = Calendario::sqlPrazo('l.datavenc');
        $ef = Calendario::sqlVencEf('l.datavenc');
        $sel = "SELECT l.id, l.login, DATE(l.datavenc) AS datavenc, DATEDIFF(CURDATE(), $ef) AS dias,
                       CAST(l.valor AS DECIMAL(12,2)) AS valor, l.tipo,
                       c.nome, c.uuid_cliente, c.plano, c.bairro, c.cidade, c.vendedor, c.venc, c.cli_ativado,
                       c.bloqueado, c.data_bloq, c.data_desativacao, c.isento, c.dias_corte
                  FROM sis_lanc l %s JOIN sis_cliente c ON c.login = l.login";
        $comum = " AND l.deltitulo = 0 $fTipo AND $prazo < CURDATE() $condCliente";
        // Dica de indice: sem ela o otimizador comeca pelos clientes e busca os titulos de cada um
        // (teste de carga, 30 mil clientes: 2,6 s contra 85 ms com a dica).
        return Db::todos(
            sprintf($sel, Db::dicaIndice('sis_lanc', 'status')) . " WHERE l.status = 'vencido' $comum
             UNION ALL
             " . sprintf($sel, Db::dicaIndice('sis_lanc', 'datavenc')) . " WHERE l.status = 'aberto' AND l.datavenc >= CURDATE() - INTERVAL 60 DAY AND l.datavenc < CURDATE() $comum",
            array_merge($paramsCliente, $paramsCliente));
    }

    /** Em atraso hoje, clientes ATIVOS: R$, titulos, clientes. */
    public static function emAtraso(?int $dv): array
    {
        $cond = " AND c.cli_ativado = 's'";
        $p = [];
        if ($dv !== null) {
            $cond .= ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
            $p[] = $dv;
        }
        $valor = 0.0;
        $logins = [];
        $rows = self::titulosVencidos($cond, $p);
        foreach ($rows as $r) {
            $valor += (float) $r['valor'];
            $logins[$r['login']] = true;
        }
        return ['valor' => round($valor, 2), 'titulos' => count($rows), 'clientes' => count($logins)];
    }

    /**
     * % pago em dia (competencia): na safra MADURA mais recente ate o mes escolhido (todos os
     * titulos ja passaram do prazo), titulos pagos ate o prazo / titulos da safra.
     * Tendencia: a mesma conta nas 6 safras maduras anteriores.
     */
    public static function pagoEmDia(string $mes, ?int $dv): array
    {
        $p = [$mes . '-01'];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND dia_venc = ?';
            $p[] = $dv;
        }
        $rows = Db::todos(
            "SELECT DATE_FORMAT(mes, '%Y-%m') AS mes, SUM(qtd) AS qtd, SUM(qtd_maduro) AS qtd_maduro, SUM(qtd_em_dia) AS qtd_em_dia
               FROM tab_pfin_fato_safra WHERE mes <= ? $fDv
              GROUP BY mes ORDER BY mes DESC LIMIT 12", $p);
        $maduras = [];
        foreach ($rows as $r) {
            if ((int) $r['qtd'] > 0 && (int) $r['qtd_maduro'] === (int) $r['qtd']) {
                $maduras[] = ['mes' => $r['mes'], 'pct' => round(100 * (int) $r['qtd_em_dia'] / (int) $r['qtd'], 1),
                              'qtd' => (int) $r['qtd'], 'qtd_em_dia' => (int) $r['qtd_em_dia']];
            }
        }
        $atual = $maduras[0] ?? null;
        return ['atual' => $atual, 'anterior' => $maduras[1] ?? null,
                'tendencia' => array_reverse(array_slice($maduras, 0, 6))];
    }

    /** Clientes ativos bloqueados agora e quanto devem. */
    public static function bloqueados(?int $dv): array
    {
        $p = [];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND CAST(c.venc AS UNSIGNED) = ?';
            $p[] = $dv;
        }
        $n = (int) Db::valor("SELECT COUNT(*) FROM sis_cliente c WHERE c.cli_ativado = 's' AND c.bloqueado = 'sim' $fDv", $p);
        $valor = 0.0;
        foreach (self::titulosVencidos(" AND c.cli_ativado = 's' AND c.bloqueado = 'sim' $fDv", $p) as $r) {
            $valor += (float) $r['valor'];
        }
        return ['clientes' => $n, 'valor' => round($valor, 2)];
    }

    /**
     * Recuperacao de credito: divida de clientes DESATIVADOS, so de titulos que venceram ATE a
     * desativacao. Titulo com vencimento depois da desativacao e mensalidade de servico nao
     * prestado — nao e divida, e sim cadastro a limpar (alerta "titulos apos desativacao").
     */
    public static function recuperavel(): array
    {
        $rows = self::titulosVencidos(" AND c.cli_ativado = 'n'");
        $r = ['valor' => 0.0, 'titulos' => 0, 'clientes' => 0, 'sem_data' => 0,
              'pos_valor' => 0.0, 'pos_titulos' => 0, 'pos_clientes' => 0];
        $cli = [];
        $pos = [];
        foreach ($rows as $t) {
            $dd = $t['data_desativacao'] ? substr((string) $t['data_desativacao'], 0, 10) : null;
            if ($dd !== null && $t['datavenc'] > $dd) {
                $r['pos_valor'] += (float) $t['valor'];
                $r['pos_titulos']++;
                $pos[$t['login']] = true;
                continue;
            }
            $r['valor'] += (float) $t['valor'];
            $r['titulos']++;
            if ($dd === null) {
                $r['sem_data']++;
            }
            $cli[$t['login']] = true;
        }
        $r['clientes'] = count($cli);
        $r['pos_clientes'] = count($pos);
        $r['valor'] = round($r['valor'], 2);
        $r['pos_valor'] = round($r['pos_valor'], 2);
        return $r;
    }

    // ------------------------------------------------------------------ graficos

    /**
     * Curva do mes (competencia): previsto acumulado pelo vencimento efetivo x recebido
     * acumulado pela data de pagamento, so dos titulos que vencem no mes. Pagamento antecipado
     * (antes do mes) entra no dia 1.
     */
    public static function curva(string $mes, ?int $dv): array
    {
        [$ini, $fim] = Calendario::limitesMes($mes);
        $tipos = Calendario::sqlTiposReceita();
        $ef = Calendario::sqlVencEf('l.datavenc');
        $p = [$ini, $fim];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
            $p[] = $dv;
        }
        $base = "FROM sis_lanc l WHERE l.datavenc >= ? AND l.datavenc < ? AND l.deltitulo = 0 AND l.tipo IN $tipos $fDv";
        $prev = Db::todos("SELECT $ef AS d, SUM(CAST(l.valor AS DECIMAL(12,2))) AS v $base GROUP BY 1", $p);
        $rec = Db::todos("SELECT DATE(l.datapag) AS d, SUM(CAST(l.valorpag AS DECIMAL(12,2))) AS v
                          $base AND l.status = 'pago' GROUP BY 1", $p);

        $nDias = (int) (new DateTimeImmutable($ini))->format('t');
        $diaDe = function (string $d) use ($ini, $fim, $nDias): ?int {
            if ($d < $ini) {
                return 1;
            }
            if ($d >= $fim) {
                return null;
            }
            return (int) substr($d, 8, 2);
        };
        $pd = array_fill(1, $nDias, 0.0);
        $rd = array_fill(1, $nDias, 0.0);
        $depois = 0.0;
        foreach ($prev as $r) {
            $i = $diaDe((string) $r['d']) ?? $nDias; // vencimento efetivo que escorrega para o mes seguinte
            $pd[$i] += (float) $r['v'];
        }
        foreach ($rec as $r) {
            $i = $diaDe((string) $r['d']);
            if ($i === null) {
                $depois += (float) $r['v'];
                continue;
            }
            $rd[$i] += (float) $r['v'];
        }
        $hoje = Calendario::hoje();
        $diaHoje = ($hoje >= $ini && $hoje < $fim) ? (int) substr($hoje, 8, 2) : ($hoje >= $fim ? $nDias : 0);
        $prevAc = [];
        $recAc = [];
        $sp = 0.0;
        $sr = 0.0;
        for ($i = 1; $i <= $nDias; $i++) {
            $sp += $pd[$i];
            $sr += $rd[$i];
            $prevAc[] = round($sp, 2);
            $recAc[] = $i <= $diaHoje ? round($sr, 2) : null;
        }
        return ['dias' => range(1, $nDias), 'previsto' => $prevAc, 'recebido' => $recAc, 'dia_hoje' => $diaHoje,
                'recebido_depois_do_mes' => round($depois, 2)];
    }

    /** Faixas de atraso a partir da configuracao "5,15,30,60,90". */
    public static function faixas(): array
    {
        $lim = array_map('intval', explode(',', Config::get('faixas_aging')));
        $faixas = [];
        $de = 1;
        foreach ($lim as $l) {
            $faixas[] = ['rotulo' => $de === $l ? (string) $l : "$de-$l", 'de' => $de, 'ate' => $l];
            $de = $l + 1;
        }
        $faixas[] = ['rotulo' => '+' . ($de - 1), 'de' => $de, 'ate' => null];
        return $faixas;
    }

    /**
     * Aging (foto): titulos vencidos por faixa de dias desde o vencimento efetivo. Clientes
     * contados UMA vez, na faixa do titulo mais antigo.
     */
    public static function aging(string $situacao, ?int $dv): array
    {
        $cond = $situacao === 'desativados' ? " AND c.cli_ativado = 'n'" : ($situacao === 'todos' ? '' : " AND c.cli_ativado = 's'");
        $p = [];
        if ($dv !== null) {
            $cond .= ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
            $p[] = $dv;
        }
        $faixas = self::faixas();
        foreach ($faixas as &$f) {
            $f += ['titulos' => 0, 'valor' => 0.0, 'clientes' => 0];
        }
        unset($f);
        $idx = function (int $dias) use ($faixas): int {
            foreach ($faixas as $i => $f) {
                if ($f['ate'] === null || $dias <= $f['ate']) {
                    return $i;
                }
            }
            return count($faixas) - 1;
        };
        $maxPorCliente = [];
        foreach (self::titulosVencidos($cond, $p) as $t) {
            $d = max(1, (int) $t['dias']);
            $i = $idx($d);
            $faixas[$i]['titulos']++;
            $faixas[$i]['valor'] += (float) $t['valor'];
            $maxPorCliente[$t['login']] = max($maxPorCliente[$t['login']] ?? 0, $d);
        }
        foreach ($maxPorCliente as $d) {
            $faixas[$idx($d)]['clientes']++;
        }
        foreach ($faixas as &$f) {
            $f['valor'] = round($f['valor'], 2);
        }
        unset($f);
        return ['faixas' => $faixas, 'situacao' => $situacao];
    }

    /** Safras dos ultimos 12 meses ate $mes (fato_safra). */
    public static function safra(string $mes, ?int $dv, int $n = 12): array
    {
        $ini = (new DateTimeImmutable($mes . '-01'))->modify('-' . ($n - 1) . ' month')->format('Y-m-d');
        $p = [$ini, $mes . '-01'];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND dia_venc = ?';
            $p[] = $dv;
        }
        $rows = Db::todos(
            "SELECT DATE_FORMAT(mes, '%Y-%m') AS mes,
                    SUM(qtd) qtd, SUM(valor) valor, SUM(qtd_em_dia) qtd_em_dia, SUM(valor_em_dia) valor_em_dia,
                    SUM(qtd_atraso) qtd_atraso, SUM(valor_atraso) valor_atraso,
                    SUM(qtd_vencido) qtd_vencido, SUM(valor_vencido) valor_vencido,
                    SUM(qtd_a_vencer) qtd_a_vencer, SUM(valor_a_vencer) valor_a_vencer,
                    SUM(qtd_maduro) qtd_maduro, SUM(valor_maduro_d30) valor_maduro_d30, SUM(valor_nao_pago_d30) valor_nao_pago_d30,
                    SUM(valor_maduro_d90) valor_maduro_d90, SUM(valor_nao_pago_d90) valor_nao_pago_d90,
                    SUM(qtd_baixa) qtd_baixa, SUM(valor_baixa) valor_baixa, MAX(atualizado_em) atualizado_em
               FROM tab_pfin_fato_safra WHERE mes >= ? AND mes <= ? $fDv GROUP BY mes ORDER BY mes", $p);
        $saida = [];
        foreach ($rows as $r) {
            $valor = (float) $r['valor'];
            $saida[] = [
                'mes' => $r['mes'], 'qtd' => (int) $r['qtd'], 'valor' => $valor,
                'em_dia' => (float) $r['valor_em_dia'], 'atraso' => (float) $r['valor_atraso'],
                'vencido' => (float) $r['valor_vencido'], 'a_vencer' => (float) $r['valor_a_vencer'],
                'baixa' => (float) $r['valor_baixa'], 'qtd_baixa' => (int) $r['qtd_baixa'],
                'qtd_em_dia' => (int) $r['qtd_em_dia'], 'qtd_atraso' => (int) $r['qtd_atraso'], 'qtd_vencido' => (int) $r['qtd_vencido'],
                'madura' => (int) $r['qtd'] > 0 && (int) $r['qtd_maduro'] === (int) $r['qtd'],
                'pct_em_dia' => (int) $r['qtd_maduro'] > 0 ? round(100 * (int) $r['qtd_em_dia'] / (int) $r['qtd_maduro'], 1) : null,
                // inadimplencia D+30/D+90 so quando a safra INTEIRA ja passou do marco
                'inad_d30' => $valor > 0 && abs((float) $r['valor_maduro_d30'] - $valor) < 0.005
                    ? round(100 * (float) $r['valor_nao_pago_d30'] / $valor, 2) : null,
                'inad_d90' => $valor > 0 && abs((float) $r['valor_maduro_d90'] - $valor) < 0.005
                    ? round(100 * (float) $r['valor_nao_pago_d90'] / $valor, 2) : null,
            ];
        }
        return ['safras' => $saida];
    }

    // ------------------------------------------------------------------ alertas

    /** Alertas acionaveis. So aparecem os que tem ocorrencia. */
    public static function alertas(): array
    {
        [$alertas, $em] = Cache::lembrar('visao.alertas', [], function () {
            $a = [];
            $est = Agregador::estado();
            if ($est['nunca_rodou']) {
                $a[] = ['id' => 'agregador', 'nivel' => 'erro', 'titulo' => 'Indicadores ainda não processados',
                        'texto' => 'Safras, pontualidade e recebimentos anteriores dependem do processamento. Em Configurações, clique em "Processar agora".',
                        'qtd' => null];
            } elseif ($est['desatualizado']) {
                $a[] = ['id' => 'agregador', 'nivel' => 'aviso', 'titulo' => 'Processamento atrasado',
                        'texto' => 'O último processamento completo foi há ' . $est['idade_h'] . ' h. Verifique o cron do addon.',
                        'qtd' => null];
            }
            $n = count(self::listaAlerta('pago_bloqueado'));
            if ($n > 0) {
                $a[] = ['id' => 'pago_bloqueado', 'nivel' => 'erro', 'titulo' => 'Bloqueado sem título vencido',
                        'texto' => 'Cliente ativo bloqueado que não deve nada: o desbloqueio automático não aconteceu.', 'qtd' => $n];
            }
            if (Parametros::corteAutomatico()) {
                $n = count(self::listaAlerta('sem_corte'));
                if ($n > 0) {
                    $a[] = ['id' => 'sem_corte', 'nivel' => 'aviso', 'titulo' => 'Vencido além do corte e não bloqueado',
                            'texto' => 'A data de corte já passou e o cliente segue liberado. Pode ser desbloqueio em confiança — confira.', 'qtd' => $n];
                }
            }
            $g = GuardiaoCorte::estado();
            if ($g['problema']) {
                $a[] = ['id' => 'guardiao', 'nivel' => 'erro', 'titulo' => 'Guardião de feriado do corte', 'texto' => $g['problema'], 'qtd' => null];
            }
            $rec = self::recuperavel();
            if ($rec['pos_titulos'] > 0) {
                $a[] = ['id' => 'pos_desativacao', 'nivel' => 'info', 'titulo' => 'Títulos gerados após a desativação',
                        'texto' => 'R$ ' . number_format($rec['pos_valor'], 2, ',', '.') . ' em ' . $rec['pos_titulos']
                                 . ' títulos de clientes já desativados. Não é dívida: é cadastro a limpar no MK-AUTH.',
                        'qtd' => $rec['pos_clientes']];
            }
            return $a;
        });
        return ['alertas' => $alertas, 'calculado_em' => $em];
    }

    /** Clientes de um alerta (lista nominal). */
    public static function listaAlerta(string $tipo): array
    {
        $saida = [];
        if ($tipo === 'pago_bloqueado') {
            $rows = Db::todos(
                "SELECT c.login, c.nome, c.uuid_cliente, c.plano, c.data_bloq
                   FROM sis_cliente c
                  WHERE c.cli_ativado = 's' AND c.bloqueado = 'sim'
                    AND NOT EXISTS (SELECT 1 FROM sis_lanc l
                                     WHERE l.login = c.login AND l.deltitulo = 0
                                       AND (l.status = 'vencido' OR (l.status = 'aberto' AND l.datavenc < CURDATE())))
                  ORDER BY c.nome");
            foreach ($rows as $r) {
                $saida[] = ['login' => $r['login'], 'nome' => $r['nome'], 'uuid' => $r['uuid_cliente'],
                            'detalhe' => 'bloqueado em ' . substr((string) $r['data_bloq'], 0, 10)];
            }
        } elseif ($tipo === 'sem_corte') {
            // mesma regra do corte do MK-AUTH (Agenda::candidatosCorte): o prazo ja passou e o
            // cliente segue liberado. Quem esta "em observacao" o MK-AUTH pula de proposito.
            $hoje = Calendario::hoje();
            foreach (Agenda::candidatosCorte($hoje) as $l => $c) {
                if ($c['corte'] < $hoje) {
                    $saida[] = ['login' => $l, 'nome' => $c['t']['nome'], 'uuid' => $c['t']['uuid_cliente'],
                                'detalhe' => 'venceu ' . self::br($c['t']['datavenc']) . ', corte previsto ' . self::br($c['corte'])];
                }
            }
            usort($saida, fn($a, $b) => strcmp((string) $a['nome'], (string) $b['nome']));
        } elseif ($tipo === 'pos_desativacao') {
            $por = [];
            foreach (self::titulosVencidos(" AND c.cli_ativado = 'n' AND c.data_desativacao IS NOT NULL") as $t) {
                if ($t['datavenc'] > substr((string) $t['data_desativacao'], 0, 10)) {
                    $l = $t['login'];
                    $por[$l] ??= ['login' => $l, 'nome' => $t['nome'], 'uuid' => $t['uuid_cliente'], 'n' => 0, 'v' => 0.0,
                                  'dd' => substr((string) $t['data_desativacao'], 0, 10)];
                    $por[$l]['n']++;
                    $por[$l]['v'] += (float) $t['valor'];
                }
            }
            foreach ($por as $x) {
                $saida[] = ['login' => $x['login'], 'nome' => $x['nome'], 'uuid' => $x['uuid'],
                            'detalhe' => $x['n'] . ' título(s), R$ ' . number_format($x['v'], 2, ',', '.') . ' — desativado em ' . self::br($x['dd'])];
            }
            usort($saida, fn($a, $b) => strcmp((string) $a['nome'], (string) $b['nome']));
        } else {
            throw new PfErro('PF-VAL-010');
        }
        return $saida;
    }

    private static function br(string $iso): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m) ? "$m[3]/$m[2]/$m[1]" : $iso;
    }
}
