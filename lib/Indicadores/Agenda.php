<?php
/**
 * painel_financeiro :: Agenda de cobranca — o que vai acontecer nos proximos dias.
 *
 * Cortes previstos seguem a regra do MK-AUTH: vencimento (empurrado do fim de semana) +
 * carencia + 1, so nos dias da semana de sis_opcao.dias_de_corte, e SEM pular feriado. Quando
 * o corte cai num feriado cadastrado, a agenda SINALIZA o conflito; com o guardiao de feriado
 * ligado, mostra o corte adiado para o proximo dia valido.
 */
final class Agenda
{
    /**
     * Quem o MK-AUTH vai cortar e quando, pela regra REAL do corte.php (consultas observadas no
     * banco): cliente ativo, nao bloqueado, NAO em observacao, com titulo 'mensalidade' nao pago
     * e nao excluido; corta quando TO_DAYS(hoje) - TO_DAYS(datavenc) > dias_corte DO CLIENTE
     * (dias_corte vazio = nunca corta), nos dias da semana de dias_de_corte. Com o guardiao de
     * feriado ligado, o feriado tambem e pulado (o corte e cumulativo: cai no proximo dia valido).
     *
     * @return array<string,array{t:array,valor:float,titulos:int,corte:string}> por login
     */
    public static function candidatosCorte(string $ate): array
    {
        $semana = Parametros::diasSemanaCorte();
        $hoje = Calendario::hoje();
        $pular = GuardiaoCorte::datasSemCorte((new DateTimeImmutable($hoje))->modify('-60 day')->format('Y-m-d'), $ate);
        $sel = "SELECT l.login, DATE(l.datavenc) AS datavenc, CAST(l.valor AS DECIMAL(12,2)) AS valor,
                       c.nome, c.uuid_cliente, c.venc, c.dias_corte
                  FROM sis_lanc l %s JOIN sis_cliente c ON c.login = l.login";
        $cond = " AND l.deltitulo = 0 AND l.tipo = 'mensalidade'
                  AND c.cli_ativado = 's' AND c.bloqueado = 'nao' AND IFNULL(c.observacao, 'nao') <> 'sim'";
        $rows = Db::todos(
            sprintf($sel, Db::dicaIndice('sis_lanc', 'status')) . " WHERE l.status = 'vencido' $cond
             UNION ALL
             " . sprintf($sel, Db::dicaIndice('sis_lanc', 'datavenc')) . " WHERE l.status = 'aberto' AND l.datavenc >= CURDATE() - INTERVAL 60 DAY AND l.datavenc <= ? $cond",
            [$ate]);
        $cli = [];
        foreach ($rows as $t) {
            $l = $t['login'];
            $cli[$l] ??= ['t' => $t, 'valor' => 0.0, 'titulos' => 0];
            $cli[$l]['valor'] += (float) $t['valor'];
            $cli[$l]['titulos']++;
            if ($t['datavenc'] < $cli[$l]['t']['datavenc']) {
                $cli[$l]['t'] = $t;
            }
        }
        foreach ($cli as $l => &$c) {
            if ($c['t']['dias_corte'] === null || $c['t']['dias_corte'] === '') {
                unset($cli[$l]);
                continue;
            }
            $c['corte'] = Calendario::dataCorte($c['t']['datavenc'], (int) $c['t']['dias_corte'], $semana, $pular);
        }
        unset($c);
        return $cli;
    }

    /** Cortes previstos entre hoje e hoje + $dias, por dia. */
    public static function cortes(int $dias): array
    {
        [$v] = Cache::lembrar('ag.cortes', ['dias' => $dias], function () use ($dias) {
            $hoje = Calendario::hoje();
            $ate = (new DateTimeImmutable($hoje))->modify("+$dias day")->format('Y-m-d');
            $semana = Parametros::diasSemanaCorte();
            $feriados = self::feriadosEntre($hoje, $ate);
            $guardiao = GuardiaoCorte::ligado();
            $cli = self::candidatosCorte($ate);
            $porDia = [];
            for ($d = new DateTimeImmutable($hoje); $d->format('Y-m-d') <= $ate; $d = $d->modify('+1 day')) {
                $k = $d->format('Y-m-d');
                $porDia[$k] = ['data' => $k, 'dow' => (int) $d->format('N'), 'corta' => in_array((int) $d->format('N'), $semana, true),
                               'feriado' => $feriados[$k]['nome'] ?? null, 'guardiao' => $guardiao && isset($feriados[$k]),
                               'clientes' => 0, 'valor' => 0.0, 'lista' => []];
            }
            foreach ($cli as $l => $c) {
                $t = $c['t'];
                $corte = $c['corte'];
                if (!isset($porDia[$corte])) {
                    continue;
                }
                $porDia[$corte]['clientes']++;
                $porDia[$corte]['valor'] += $c['valor'];
                $porDia[$corte]['lista'][] = ['login' => $l, 'nome' => $t['nome'], 'uuid' => $t['uuid_cliente'],
                                              'valor' => round($c['valor'], 2), 'titulos' => $c['titulos'], 'vencimento' => $t['datavenc'],
                                              'venc' => $t['venc'] === null ? null : (int) $t['venc']];
            }
            foreach ($porDia as &$x) {
                $x['valor'] = round($x['valor'], 2);
                usort($x['lista'], fn($a, $b) => strcasecmp((string) $a['nome'], (string) $b['nome']));
            }
            unset($x);
            return ['dias' => array_values($porDia), 'corte_automatico' => Parametros::corteAutomatico(), 'guardiao' => $guardiao];
        });
        // o que o MK-AUTH ja cortou hoje (quem foi cortado sai dos candidatos acima). FORA do cache:
        // o cron de 10 min nao limpa o cache, e o card ficaria atrasado (ou zerado apos atualizar).
        $hoje = $v['dias'][0]['data'];
        $feito = self::cortados($hoje, $hoje)[$hoje] ?? null;
        $v['dias'][0]['cortados'] = $feito['clientes'] ?? 0;
        $v['dias'][0]['religados'] = $feito['religados'] ?? 0;
        $v['dias'][0]['valor_cortados'] = $feito['valor'] ?? 0.0;
        return $v;
    }

    /**
     * Cortes que JA aconteceram, por dia, a partir do historico de bloqueios (tab_pfin_evento_bloqueio,
     * lido do sis_logs; o cron de 10 min mantem o dia corrente em dia). Cada "bloqueado por atraso"
     * e um corte de verdade: o corte so pega quem esta desbloqueado (observado na producao: nao ha
     * relog diario). O desbloqueio e que NEM SEMPRE vai para o log, entao "religado" vem do log, de
     * um corte posterior (teve de ser religado no meio) ou, no ultimo corte, do sis_cliente de agora.
     * sis_cliente.data_bloq nao serve para contar: o MK-AUTH limpa o campo no desbloqueio.
     *
     * @return array<string,array{data:string,clientes:int,religados:int,valor:float,lista:array}> por dia
     */
    public static function cortados(string $de, string $ate): array
    {
        if (!Db::tabelaExiste('tab_pfin_evento_bloqueio')) {
            return [];
        }
        $ep = [];
        foreach (Db::todos('SELECT login, data, tipo, titulo FROM tab_pfin_evento_bloqueio WHERE data >= ? ORDER BY login, data, log_id', [$de]) as $e) {
            $l = $e['login'];
            $d = substr((string) $e['data'], 0, 10);
            $n = isset($ep[$l]) ? count($ep[$l]) - 1 : -1;
            if ($e['tipo'] === 'bloqueio') {
                if ($n >= 0 && $ep[$l][$n]['data'] === $d) {
                    continue;   // o mesmo corte duas vezes no dia conta uma
                }
                if ($n >= 0) {
                    $ep[$l][$n]['religado'] = true;
                }
                $ep[$l][] = ['data' => $d, 'quando' => (string) $e['data'], 'hora' => substr((string) $e['data'], 11, 5),
                             'titulo' => (int) $e['titulo'], 'religado' => false, 'religado_em' => null];
            } elseif ($n >= 0 && !$ep[$l][$n]['religado']) {
                $ep[$l][$n]['religado'] = true;
                $ep[$l][$n]['religado_em'] = substr((string) $e['data'], 0, 16);
            }
        }
        foreach ($ep as $l => $lista) {
            $ep[$l] = array_values(array_filter($lista, fn($x) => $x['data'] <= $ate));
            if (!$ep[$l]) {
                unset($ep[$l]);
            }
        }
        if (!$ep) {
            return [];
        }
        $titulos = [];
        foreach (array_chunk(array_values(array_unique(array_merge(...array_map(fn($x) => array_column($x, 'titulo'), array_values($ep))))), 500) as $lote) {
            $in = implode(',', array_fill(0, count($lote), '?'));
            foreach (Db::todos("SELECT id, DATE(datavenc) AS v, CAST(valor AS DECIMAL(12,2)) AS valor FROM sis_lanc WHERE id IN ($in)", $lote) as $t) {
                $titulos[(int) $t['id']] = $t;
            }
        }
        $clientes = [];
        foreach (array_chunk(array_keys($ep), 500) as $lote) {
            $in = implode(',', array_fill(0, count($lote), '?'));
            foreach (Db::todos("SELECT login, nome, uuid_cliente, venc, bloqueado, data_desbloq FROM sis_cliente WHERE login IN ($in)", $lote) as $c) {
                $clientes[$c['login']] = $c;
            }
        }
        $dias = [];
        foreach ($ep as $l => $lista) {
            $c = $clientes[$l] ?? null;
            foreach ($lista as $x) {
                if (!$x['religado'] && ($c['bloqueado'] ?? 'sim') === 'nao') {
                    $x['religado'] = true;
                    $x['religado_em'] = !empty($c['data_desbloq']) && (string) $c['data_desbloq'] >= $x['quando'] ? substr((string) $c['data_desbloq'], 0, 16) : null;
                }
                $t = $titulos[$x['titulo']] ?? null;
                $dias[$x['data']] ??= ['data' => $x['data'], 'clientes' => 0, 'religados' => 0, 'valor' => 0.0, 'lista' => []];
                $dia = &$dias[$x['data']];
                $dia['clientes']++;
                $dia['religados'] += $x['religado'] ? 1 : 0;
                $dia['valor'] += $t ? (float) $t['valor'] : 0.0;
                $dia['lista'][] = ['login' => $l, 'nome' => $c['nome'] ?? $l, 'uuid' => $c['uuid_cliente'] ?? null,
                                   'venc' => isset($c['venc']) ? (int) $c['venc'] : null, 'hora' => $x['hora'],
                                   'vencimento' => $t['v'] ?? null, 'valor' => $t ? round((float) $t['valor'], 2) : null,
                                   'religado' => $x['religado'], 'religado_em' => $x['religado_em']];
                unset($dia);
            }
        }
        ksort($dias);
        foreach ($dias as &$x) {
            $x['valor'] = round($x['valor'], 2);
            usort($x['lista'], fn($a, $b) => strcasecmp((string) $a['nome'], (string) $b['nome']));
        }
        unset($x);
        return $dias;
    }

    /** Titulos a vencer (receita recorrente) por vencimento efetivo, de hoje a hoje + $dias. */
    public static function vencimentos(int $dias): array
    {
        $hoje = Calendario::hoje();
        $ate = (new DateTimeImmutable($hoje))->modify("+$dias day")->format('Y-m-d');
        $ef = Calendario::sqlVencEf('l.datavenc');
        // janela folgada no indice (o vencimento efetivo anda ate 2 dias para frente)
        $rows = Db::todos(
            "SELECT $ef AS d, COUNT(*) AS qtd, SUM(CAST(l.valor AS DECIMAL(12,2))) AS valor
               FROM sis_lanc l
              WHERE l.datavenc >= ? - INTERVAL 3 DAY AND l.datavenc < ? + INTERVAL 1 DAY
                AND l.deltitulo = 0 AND l.status <> 'pago' AND l.tipo IN " . Calendario::sqlTiposReceita() . "
              GROUP BY 1 HAVING d >= ? AND d <= ? ORDER BY 1", [$hoje, $ate, $hoje, $ate]);
        return ['dias' => array_map(fn($r) => ['data' => (string) $r['d'], 'qtd' => (int) $r['qtd'], 'valor' => round((float) $r['valor'], 2)], $rows)];
    }

    /**
     * Regua de avisos configurada no MK-AUTH (sis_configmsg): itens "<canal>msg<N><antes|depois>"
     * e "<canal>msg0" (no dia). Ativo quando algum tipo_0X do JSON e maior que zero.
     */
    public static function regua(): array
    {
        if (!Db::tabelaExiste('sis_configmsg')) {
            return ['avisos' => []];
        }
        $avisos = [];
        foreach (Db::todos('SELECT item, valor FROM sis_configmsg') as $r) {
            if (!preg_match('/^([a-z]+)msg(\d+)(antes|depois)?$/', (string) $r['item'], $m)) {
                continue;
            }
            $j = json_decode((string) $r['valor'], true);
            $ativo = false;
            foreach (['tipo_01', 'tipo_02', 'tipo_03'] as $k) {
                if (is_array($j) && (int) ($j[$k] ?? 0) > 0) {
                    $ativo = true;
                }
            }
            $n = (int) $m[2];
            $quando = $m[3] ?? '';
            $avisos[] = ['item' => $r['item'], 'canal' => $m[1], 'dias' => $quando === 'antes' ? -$n : $n,
                         'rotulo' => $n === 0 ? 'No dia do vencimento' : ($n . ' dia(s) ' . ($quando === 'antes' ? 'antes' : 'depois')),
                         'ativo' => $ativo];
        }
        usort($avisos, fn($a, $b) => $a['dias'] <=> $b['dias']);
        return ['avisos' => $avisos];
    }

    /**
     * Efetividade dos avisos com titulo ("[Titulo: N]") nos ultimos $dias: enviados, falhas de
     * entrega (o gateway devolveu erro) e quantos titulos foram pagos em ate 3 dias depois do
     * aviso. Agrupado pela distancia entre o envio e o vencimento (ex.: D+10, D+15).
     * Experimental: depende do texto que o MK-AUTH grava em sis_enviadas.
     */
    public static function efetividade(int $dias): array
    {
        if (!Db::tabelaExiste('sis_enviadas')) {
            return ['grupos' => [], 'experimental' => true];
        }
        [$v] = Cache::lembrar('ag.efet', ['dias' => $dias], function () use ($dias) {
            $rows = Db::todos(
                "SELECT e.data, e.mensagem FROM sis_enviadas e
                  WHERE e.data >= CURDATE() - INTERVAL ? DAY AND e.mensagem LIKE '[Titulo: %'", [$dias]);
            $porTitulo = [];
            foreach ($rows as $r) {
                if (!preg_match('/^\[Titulo: (\d+)\]/', (string) $r['mensagem'], $m)) {
                    continue;
                }
                $falhou = (bool) preg_match('/^\[Titulo: \d+\]\s*codigo \(\d+\)/', (string) $r['mensagem'])
                       || str_contains((string) $r['mensagem'], '"error"');
                $porTitulo[] = ['titulo' => (int) $m[1], 'data' => substr((string) $r['data'], 0, 10), 'falhou' => $falhou];
            }
            $titulos = [];
            foreach (array_chunk(array_unique(array_column($porTitulo, 'titulo')), 500) as $lote) {
                $in = implode(',', array_fill(0, count($lote), '?'));
                foreach (Db::todos("SELECT id, DATE(datavenc) AS venc, status, DATE(datapag) AS pag FROM sis_lanc WHERE id IN ($in)", $lote) as $t) {
                    $titulos[(int) $t['id']] = $t;
                }
            }
            $g = [];
            foreach ($porTitulo as $a) {
                $t = $titulos[$a['titulo']] ?? null;
                if ($t === null || $t['venc'] === null) {
                    continue;
                }
                $off = (int) ((strtotime($a['data']) - strtotime($t['venc'])) / 86400);
                $k = $off;
                $g[$k] ??= ['dias' => $off, 'enviados' => 0, 'falhas' => 0, 'pagos_3d' => 0, 'ja_pagos' => 0];
                $g[$k]['enviados']++;
                if ($a['falhou']) {
                    $g[$k]['falhas']++;
                    continue;
                }
                if ($t['status'] === 'pago' && $t['pag'] !== null) {
                    if ($t['pag'] < $a['data']) {
                        $g[$k]['ja_pagos']++;
                    } elseif ((strtotime($t['pag']) - strtotime($a['data'])) / 86400 <= 3) {
                        $g[$k]['pagos_3d']++;
                    }
                }
            }
            ksort($g);
            // grupos com poucos envios (avisos avulsos) somam em "outros"
            $saida = [];
            $outros = ['dias' => null, 'enviados' => 0, 'falhas' => 0, 'pagos_3d' => 0, 'ja_pagos' => 0];
            foreach ($g as $x) {
                if ($x['enviados'] >= 5) {
                    $saida[] = $x;
                } else {
                    foreach (['enviados', 'falhas', 'pagos_3d', 'ja_pagos'] as $c) {
                        $outros[$c] += $x[$c];
                    }
                }
            }
            if ($outros['enviados'] > 0) {
                $saida[] = $outros;
            }
            foreach ($saida as &$x) {
                $entregues = $x['enviados'] - $x['falhas'];
                $x['pct_falha'] = $x['enviados'] ? round(100 * $x['falhas'] / $x['enviados'], 1) : null;
                $x['pct_pagou'] = $entregues ? round(100 * $x['pagos_3d'] / $entregues, 1) : null;
            }
            unset($x);
            return ['grupos' => $saida, 'dias' => $dias, 'experimental' => true];
        });
        return $v;
    }

    // ------------------------------------------------------------------ calendario do mes

    /**
     * Calendario do mes de cobranca: o que o MK-AUTH VAI fazer em cada dia (projecao a partir
     * dos dias de vencimento, da regua de avisos e do dias_corte de cada grupo de clientes) e o
     * que JA aconteceu (titulos pagos, avisos entregues/falhos). Porte do planejamento_cobranca
     * do Livro Caixa, com a regra de corte do proprio MK-AUTH (dias_de_corte) em vez de seg-sex fixo.
     *
     * Grupo = clientes ativos com o mesmo dia de vencimento e o mesmo dias_corte.
     */
    public static function mes(int $ano, int $mes): array
    {
        $ini = new DateTimeImmutable(sprintf('%04d-%02d-01', $ano, $mes));
        $fim = $ini->modify('+1 month');
        $hoje = Calendario::hoje();
        $padrao = Parametros::diasCortePadrao();
        $semana = Parametros::diasSemanaCorte();
        $feriados = self::feriadosEntre($ini->modify('-1 month')->format('Y-m-d'), $fim->modify('+1 month')->format('Y-m-d'));

        // grupos vencimento x dias_corte (clientes ativos)
        $grupos = [];
        foreach (Db::todos("SELECT CAST(venc AS UNSIGNED) AS v, IF(IFNULL(dias_corte, 0) > 0, dias_corte, ?) AS dc, COUNT(*) AS n
                              FROM sis_cliente WHERE cli_ativado = 's' GROUP BY 1, 2", [$padrao]) as $g) {
            if ((int) $g['v'] >= 1 && (int) $g['v'] <= 31) {
                $grupos[(int) $g['v']][(int) $g['dc']] = (int) $g['n'];
            }
        }
        // dias de vencimento: os ativos no MK-AUTH e qualquer outro que ainda tenha cliente
        $vencs = array_values(array_unique(array_merge(Parametros::diasVencimento(), array_keys($grupos))));
        sort($vencs);
        $avisos = array_values(array_filter(self::regua()['avisos'], fn($a) => $a['ativo']));
        $pular = GuardiaoCorte::datasSemCorte($ini->modify('-1 month')->format('Y-m-d'), $fim->modify('+1 month')->format('Y-m-d'));

        $dias = [];
        for ($d = $ini; $d < $fim; $d = $d->modify('+1 day')) {
            $k = $d->format('Y-m-d');
            $dow = (int) $d->format('N');
            $dias[$k] = ['data' => $k, 'dia' => (int) $d->format('j'), 'dow' => $dow % 7, 'fds' => $dow >= 6,
                         'feriado' => $feriados[$k] ?? null, 'corta' => in_array($dow, $semana, true), 'eventos' => []];
        }
        $add = function (string $data, array $ev) use (&$dias) {
            if (isset($dias[$data])) {
                $dias[$data]['eventos'][] = $ev;
            }
        };

        // ciclos: mes anterior, atual e seguinte (um aviso ou corte de um ciclo cai no mes vizinho)
        foreach ([-1, 0, 1] as $off) {
            $base = $ini->modify(($off >= 0 ? '+' : '') . $off . ' month');
            $nDias = (int) $base->format('t');
            foreach ($vencs as $v) {
                $nominal = $base->setDate((int) $base->format('Y'), (int) $base->format('n'), min($v, $nDias));
                $efetivo = $nominal;
                while ((int) $efetivo->format('N') >= 6) {
                    $efetivo = $efetivo->modify('+1 day');
                }
                $total = array_sum($grupos[$v] ?? []);
                $add($efetivo->format('Y-m-d'), ['tipo' => 'venc', 'venc' => $v, 'clientes' => $total,
                    'nominal' => $nominal->format('Y-m-d')]);
                foreach ($avisos as $a) {
                    $add($efetivo->modify(($a['dias'] >= 0 ? '+' : '') . $a['dias'] . ' day')->format('Y-m-d'),
                        ['tipo' => 'aviso', 'venc' => $v, 'dias' => $a['dias'], 'canal' => $a['canal'],
                         'efetivo' => $efetivo->format('Y-m-d'), 'nominal' => $nominal->format('Y-m-d')]);
                }
                foreach ($grupos[$v] ?? [] as $dc => $n) {
                    $bruto = Calendario::dataCorte($nominal->format('Y-m-d'), $dc, $semana);
                    $corte = $pular ? Calendario::dataCorte($nominal->format('Y-m-d'), $dc, $semana, $pular) : $bruto;
                    $ev = ['tipo' => 'corte', 'venc' => $v, 'dias_corte' => $dc, 'clientes' => $n,
                           'nominal' => $nominal->format('Y-m-d'), 'conflito' => $feriados[$corte]['nome'] ?? null,
                           'adiado_de' => $corte !== $bruto ? $bruto : null,
                           'adiado_por' => $corte !== $bruto ? ($feriados[$bruto]['nome'] ?? 'feriado') : null,
                           'real' => null];
                    $add($corte, $ev);
                }
            }
        }

        self::anexarReais($dias, $ini->format('Y-m-d'), $fim->format('Y-m-d'), $hoje);

        // ate hoje vale o que aconteceu: um selo com o corte realizado do dia (o MK-AUTH corta por
        // titulo vencido, todos os grupos juntos) no lugar da projecao por grupo. Hoje, a projecao
        // so fica se ainda ha quem cortar (o corte nao rodou ou rodou com o automatico desligado).
        $ultimo = $fim->modify('-1 day')->format('Y-m-d');
        $feitos = $ini->format('Y-m-d') <= $hoje ? self::cortados($ini->format('Y-m-d'), min($hoje, $ultimo)) : [];
        foreach ($dias as $data => &$x) {
            if ($data > $hoje) {
                continue;
            }
            $x['eventos'] = array_values(array_filter($x['eventos'],
                fn($e) => $e['tipo'] !== 'corte' || ($data === $hoje && !empty($e['real']))));
            if (isset($feitos[$data])) {
                $f = $feitos[$data];
                $x['eventos'][] = ['tipo' => 'cortado', 'venc' => 0, 'clientes' => $f['clientes'], 'religados' => $f['religados'],
                                   'valor' => $f['valor'], 'lista' => $f['lista']];
            }
        }
        unset($x);

        // ordem dos selos: conflito, corte, vencimento, aviso
        $peso = fn($e) => $e['tipo'] === 'cortado' ? 0 : ($e['tipo'] === 'corte' ? (!empty($e['conflito']) ? 0 : 1) : ($e['tipo'] === 'venc' ? 2 : 3));
        foreach ($dias as &$x) {
            usort($x['eventos'], fn($a, $b) => [$peso($a), $a['venc']] <=> [$peso($b), $b['venc']]);
        }
        unset($x);

        return [
            'ano' => $ano, 'mes' => $mes, 'hoje' => $hoje, 'dias' => array_values($dias),
            'parametros' => ['corte_padrao' => $padrao, 'dias_semana_corte' => $semana, 'corte_automatico' => Parametros::corteAutomatico()],
            'guardiao' => GuardiaoCorte::estado(),
        ];
    }

    /**
     * Dados reais sobre a projecao: titulos e pagamentos de cada vencimento, avisos entregues e
     * falhos por (dia do envio, grupo de vencimento) e, nos proximos 7 dias, quantos clientes
     * serao cortados de verdade (quem tem titulo vencido).
     */
    private static function anexarReais(array &$dias, string $ini, string $fim, string $hoje): void
    {
        // titulos por data de vencimento nominal (cobre o fim de semana que empurra para o mes)
        $tit = [];
        foreach (Db::todos("SELECT DATE(l.datavenc) AS d, COUNT(*) AS qtd, SUM(CAST(l.valor AS DECIMAL(12,2))) AS valor,
                                   SUM(l.status = 'pago') AS pagos
                              FROM sis_lanc l
                             WHERE l.datavenc >= ? - INTERVAL 3 DAY AND l.datavenc < ? AND l.deltitulo = 0
                               AND l.tipo IN " . Calendario::sqlTiposReceita() . " GROUP BY 1", [$ini, $fim]) as $r) {
            $tit[(string) $r['d']] = $r;
        }
        // avisos com titulo enviados no mes, por dia do envio e dia de vencimento do titulo
        $msg = [];
        if (Db::tabelaExiste('sis_enviadas')) {
            $rows = Db::todos("SELECT DATE(e.data) AS d, e.mensagem FROM sis_enviadas e
                                WHERE e.data >= ? AND e.data < ? AND e.mensagem LIKE '[Titulo: %'", [$ini, $fim]);
            $ids = [];
            foreach ($rows as $r) {
                if (preg_match('/^\[Titulo: (\d+)\]/', (string) $r['mensagem'], $m)) {
                    $ids[(int) $m[1]] = true;
                }
            }
            $vencDe = [];
            foreach (array_chunk(array_keys($ids), 500) as $lote) {
                $in = implode(',', array_fill(0, count($lote), '?'));
                foreach (Db::todos("SELECT id, DATE(datavenc) AS v FROM sis_lanc WHERE id IN ($in)", $lote) as $t) {
                    $vencDe[(int) $t['id']] = (string) $t['v'];
                }
            }
            foreach ($rows as $r) {
                if (!preg_match('/^\[Titulo: (\d+)\]/', (string) $r['mensagem'], $m) || !isset($vencDe[(int) $m[1]])) {
                    continue;
                }
                $k = $r['d'] . '|' . $vencDe[(int) $m[1]];
                $msg[$k] ??= ['enviados' => 0, 'falhas' => 0];
                $msg[$k]['enviados']++;
                if (preg_match('/^\[Titulo: \d+\]\s*codigo \(\d+\)/', (string) $r['mensagem']) || str_contains((string) $r['mensagem'], '"error"')) {
                    $msg[$k]['falhas']++;
                }
            }
        }
        // cortes reais dos proximos 7 dias, por dia e dia de vencimento do cliente
        $reais = [];
        foreach (self::cortes(7)['dias'] as $c) {
            foreach ($c['lista'] as $cli) {
                $k = $c['data'] . '|' . (int) $cli['venc'];
                $reais[$k] = ($reais[$k] ?? 0) + 1;
            }
        }
        $limiteReal = (new DateTimeImmutable($hoje))->modify('+7 day')->format('Y-m-d');

        foreach ($dias as $data => &$x) {
            foreach ($x['eventos'] as &$e) {
                if ($e['tipo'] === 'venc') {
                    $q = 0;
                    $v = 0.0;
                    $p = 0;
                    for ($d = new DateTimeImmutable($e['nominal']); $d->format('Y-m-d') <= $data; $d = $d->modify('+1 day')) {
                        $r = $tit[$d->format('Y-m-d')] ?? null;
                        if ($r) {
                            $q += (int) $r['qtd'];
                            $v += (float) $r['valor'];
                            $p += (int) $r['pagos'];
                        }
                    }
                    $e['titulos'] = $q;
                    $e['valor'] = round($v, 2);
                    $e['pct_pago'] = ($q > 0 && $data <= $hoje) ? round(100 * $p / $q, 1) : null;
                } elseif ($e['tipo'] === 'aviso') {
                    if ($data <= $hoje) {
                        // o MK-AUTH grava o vencimento ja empurrado; versoes antigas, o nominal: soma os dois
                        $a1 = $msg[$data . '|' . $e['efetivo']] ?? ['enviados' => 0, 'falhas' => 0];
                        $a2 = $e['nominal'] !== $e['efetivo'] ? ($msg[$data . '|' . $e['nominal']] ?? ['enviados' => 0, 'falhas' => 0]) : ['enviados' => 0, 'falhas' => 0];
                        $e['envio'] = ['enviados' => $a1['enviados'] + $a2['enviados'], 'falhas' => $a1['falhas'] + $a2['falhas']];
                    } else {
                        $e['envio'] = null;
                    }
                } elseif ($e['tipo'] === 'corte' && $data >= $hoje && $data <= $limiteReal) {
                    $e['real'] = $reais[$data . '|' . $e['venc']] ?? 0;
                }
            }
            unset($e);
        }
        unset($x);
    }

    // ------------------------------------------------------------------ feriados (tab_feriados compartilhada)

    /** [data => ['nome', 'abrangencia']] dos feriados no intervalo. */
    public static function feriadosEntre(string $de, string $ate): array
    {
        $saida = [];
        if (!Db::tabelaExiste('tab_feriados')) {
            return $saida;
        }
        foreach (Db::todos("SELECT data, nome, abrangencia FROM tab_feriados WHERE tipo = 'feriado' AND data >= ? AND data <= ? ORDER BY data",
                     [$de, $ate]) as $r) {
            $saida[(string) $r['data']] = ['nome' => (string) $r['nome'], 'abrangencia' => (string) $r['abrangencia']];
        }
        return $saida;
    }

    public static function proximosFeriados(int $n = 6): array
    {
        if (!Db::tabelaExiste('tab_feriados')) {
            return [];
        }
        return Db::todos("SELECT data, nome, abrangencia FROM tab_feriados WHERE tipo = 'feriado' AND data >= CURDATE() ORDER BY data LIMIT " . max(1, min(20, $n)));
    }

    /** O addon Calendario de Feriados esta instalado ao lado? (botao "Gerenciar feriados") */
    public static function calendarioInstalado(): bool
    {
        return is_file(__DIR__ . '/../../../calendario/manifest.json');
    }
}
