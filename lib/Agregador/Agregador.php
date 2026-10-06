<?php
/**
 * painel_financeiro :: agregador noturno (cron) e "processar agora".
 *
 * Monta as tabelas de fato a partir de sis_lanc:
 *   tab_pfin_fato_dia     caixa: recebido por dia de pagamento (ate ONTEM; hoje e ao vivo)
 *   tab_pfin_fato_safra   competencia: cada mes de vencimento e o destino dos titulos dele
 *   tab_pfin_fato_atraso  em quantos dias depois do vencimento os titulos foram pagos
 *
 * sis_lanc e lida com SELECT simples e a agregacao volta para o PHP antes de gravar. Nao ha
 * INSERT ... SELECT de proposito: ele pode travar linhas da tabela de origem enquanto roda, e
 * a origem aqui e a tabela onde o MK-AUTH da baixa nos pagamentos.
 */
final class Agregador
{
    public const TRAVA = 'painel_financeiro.agregador';

    /** Muda quando a REGRA dos fatos muda: a proxima execucao refaz a historia inteira sozinha. */
    public const VERSAO_FATOS = '2';

    /**
     * @param bool $completo refaz a historia inteira (instalacao nova ou correcao)
     * @return array resumo da execucao
     */
    public static function executar(string $usuario, bool $completo = false): array
    {
        if (!Db::travar(self::TRAVA, 0)) {
            throw new PfErro('PF-AGR-001', [], null, 409);
        }
        $ini = microtime(true);
        Db::exec("INSERT INTO tab_pfin_execucao (job, inicio, resultado, usuario) VALUES ('agregar', NOW(), 'rodando', ?)",
            [substr($usuario, 0, 60)]);
        $idExec = Db::ultimoId();

        try {
            // Primeira execucao (fato vazio) e sempre completa.
            $vazio = (int) Db::valor('SELECT COUNT(*) FROM tab_pfin_fato_dia') === 0;
            $completo = $completo || $vazio || Config::get('fato_versao') !== self::VERSAO_FATOS;

            $hoje = Calendario::hoje();
            $desdeCaixa = $completo ? '1970-01-01'
                : (new DateTimeImmutable($hoje))->modify('-' . Config::int('dias_recalculo_caixa') . ' day')->format('Y-m-d');
            $mesAtual = substr($hoje, 0, 7) . '-01';
            $desdeSafra = $completo ? '1970-01-01'
                : (new DateTimeImmutable($mesAtual))->modify('-' . Config::int('meses_recalculo_safra') . ' month')->format('Y-m-d');
            $ateSafra = (new DateTimeImmutable($mesAtual))->modify('+1 month')->format('Y-m-d');

            $nDia = self::fatoDia($desdeCaixa, $hoje);
            $nSafra = self::fatoSafra($desdeSafra, $ateSafra);
            $nAtraso = self::fatoAtraso($desdeSafra, $ateSafra);
            $nEventos = self::eventosBloqueio();

            $ontem = (new DateTimeImmutable($hoje))->modify('-1 day')->format('Y-m-d');
            Config::set('fato_dia_ate', $ontem, $usuario, true);
            if ($completo) {
                Config::set('fato_versao', self::VERSAO_FATOS, $usuario, true);
            }
            Cache::limpar();

            $ms = (int) ((microtime(true) - $ini) * 1000);
            $resumo = ['completo' => $completo, 'caixa_desde' => $desdeCaixa, 'safra_desde' => $desdeSafra,
                       'linhas_dia' => $nDia, 'linhas_safra' => $nSafra, 'linhas_atraso' => $nAtraso,
                       'eventos_bloqueio' => $nEventos, 'ms' => $ms];
            Db::exec("UPDATE tab_pfin_execucao SET fim = NOW(), duracao_ms = ?, resultado = 'ok', linhas = ?, detalhe = ? WHERE id = ?",
                [$ms, $nDia + $nSafra + $nAtraso, json_encode($resumo), $idExec]);
            Log::info('agregador.ok', $resumo);
            return $resumo;
        } catch (Throwable $e) {
            $ms = (int) ((microtime(true) - $ini) * 1000);
            try {
                Db::exec("UPDATE tab_pfin_execucao SET fim = NOW(), duracao_ms = ?, resultado = 'erro', detalhe = ? WHERE id = ?",
                    [$ms, substr($e->getMessage(), 0, 1000), $idExec]);
            } catch (Throwable $e2) {
                // o registro de execucao e secundario
            }
            Log::excecao('agregador.erro', $e);
            throw $e instanceof PfErro ? $e : new PfErro('PF-AGR-002', [], null, 500);
        } finally {
            Db::destravar(self::TRAVA);
        }
    }

    /**
     * Recebimentos por dia, de $desde ate ontem. UMA leitura de sis_lanc alimenta os dois fatos
     * de caixa (por forma/vencimento e por coletor): a varredura e o custo, nao a gravacao.
     */
    private static function fatoDia(string $desde, string $hoje): int
    {
        $rows = Db::todos(
            "SELECT DATE(l.datapag) AS dia, " . Calendario::sqlDiaVenc('l.datavenc') . " AS dia_venc,
                    " . self::sqlForma('l.formapag') . " AS forma,
                    IF(l.tipo IN " . Calendario::sqlTiposReceita() . ", 1, 0) AS recorrente,
                    " . self::sqlColetor('l.coletor') . " AS coletor,
                    COUNT(*) AS qtd,
                    SUM(CAST(l.valorpag AS DECIMAL(12,2))) AS valor_pago,
                    SUM(CAST(l.valor AS DECIMAL(12,2))) AS valor_titulo,
                    SUM(GREATEST(CAST(l.valorpag AS DECIMAL(12,2)) - CAST(l.valor AS DECIMAL(12,2)), 0)) AS juros,
                    SUM(GREATEST(CAST(l.valor AS DECIMAL(12,2)) - CAST(l.valorpag AS DECIMAL(12,2)), 0)) AS desconto
               FROM sis_lanc l
              WHERE l.deltitulo = 0 AND l.status = 'pago'
                AND l.datapag >= ? AND l.datapag < ?
              GROUP BY 1, 2, 3, 4, 5", [$desde, $hoje]);

        $dia = [];
        $col = [];
        foreach ($rows as $r) {
            $k = $r['dia'] . '|' . $r['dia_venc'] . '|' . $r['forma'] . '|' . $r['recorrente'];
            $dia[$k] ??= ['dia' => $r['dia'], 'dia_venc' => $r['dia_venc'], 'forma' => $r['forma'], 'recorrente' => $r['recorrente'],
                          'qtd' => 0, 'valor_pago' => 0.0, 'valor_titulo' => 0.0, 'juros' => 0.0, 'desconto' => 0.0];
            foreach (['qtd', 'valor_pago', 'valor_titulo', 'juros', 'desconto'] as $c) {
                $dia[$k][$c] += $r[$c] + 0;
            }
            $kc = $r['dia'] . '|' . $r['coletor'];
            $col[$kc] ??= ['dia' => $r['dia'], 'coletor' => $r['coletor'], 'qtd' => 0, 'valor_pago' => 0.0];
            $col[$kc]['qtd'] += (int) $r['qtd'];
            $col[$kc]['valor_pago'] += (float) $r['valor_pago'];
        }

        Db::transacao(function () use ($dia, $col, $desde, $hoje) {
            Db::exec('DELETE FROM tab_pfin_fato_dia WHERE dia >= ? AND dia < ?', [$desde, $hoje]);
            self::inserirEmLote('tab_pfin_fato_dia',
                ['dia', 'dia_venc', 'forma', 'recorrente', 'qtd', 'valor_pago', 'valor_titulo', 'juros', 'desconto'], array_values($dia));
            Db::exec('DELETE FROM tab_pfin_fato_coletor WHERE dia >= ? AND dia < ?', [$desde, $hoje]);
            self::inserirEmLote('tab_pfin_fato_coletor', ['dia', 'coletor', 'qtd', 'valor_pago'], array_values($col));
        });
        return count($dia);
    }

    /**
     * Caixa de HOJE (cron a cada 10 min): sis_lanc.datapag nao tem indice, entao somar os
     * pagamentos do dia e uma varredura. Feita aqui, em segundo plano, a tela nunca varre — so
     * le o fato. Se este cron parar, a tela volta a ler ao vivo sozinha (ver fatoCobreAte).
     */
    public static function caixaHoje(): array
    {
        if (!Db::travar(self::TRAVA . '.hoje', 0)) {
            throw new PfErro('PF-AGR-001', [], null, 409);
        }
        try {
            $ini = microtime(true);
            $hoje = Calendario::hoje();
            $amanha = (new DateTimeImmutable($hoje))->modify('+1 day')->format('Y-m-d');
            $n = self::fatoDia($hoje, $amanha);
            Config::set('fato_hoje_em', (string) Db::valor('SELECT NOW()'), 'cron', true);
            return ['dia' => $hoje, 'linhas_dia' => $n, 'ms' => (int) ((microtime(true) - $ini) * 1000)];
        } finally {
            Db::destravar(self::TRAVA . '.hoje');
        }
    }

    /**
     * Ultimo dia (inclusive) que os fatos de caixa cobrem por inteiro: ontem, depois do
     * processamento noturno; HOJE, quando o cron de 10 em 10 minutos rodou ha menos de 20 min.
     * Vazio = nunca processado (a tela le tudo ao vivo).
     */
    public static function fatoCobreAte(): string
    {
        $ate = Config::get('fato_dia_ate');
        $em = Config::get('fato_hoje_em');
        $hoje = Calendario::hoje();
        $ontem = (new DateTimeImmutable($hoje))->modify('-1 day')->format('Y-m-d');
        if ($ate === $ontem && $em !== '' && substr($em, 0, 10) === $hoje
            && (int) Db::valor('SELECT TIMESTAMPDIFF(MINUTE, ?, NOW())', [$em]) <= 20) {
            return $hoje;
        }
        return $ate;
    }

    /** Coletor normalizado: vazio vira "nao_informado". */
    public static function sqlColetor(string $col): string
    {
        return "IF(TRIM(IFNULL($col, '')) = '', 'nao_informado', LEFT(LOWER(TRIM($col)), 20))";
    }

    /**
     * Eventos de bloqueio/desbloqueio do sis_logs, incrementais pelo id do log. O texto e o unico
     * registro do MK-AUTH: "cliente X bloqueado por atraso no titulo N vencido em ..." e
     * "fez [o] desbloqueio do cliente X ...". No bloqueio o login vem do TITULO (o texto as vezes
     * traz o nome no lugar do login); no desbloqueio, de quem existir em sis_cliente.
     */
    private static function eventosBloqueio(): int
    {
        if (!Db::tabelaExiste('sis_logs')) {
            return 0;
        }
        $ultimo = (int) Db::valor('SELECT IFNULL(MAX(log_id), 0) FROM tab_pfin_evento_bloqueio');
        $total = 0;
        do {
            $rows = Db::todos(
                "SELECT id, STR_TO_DATE(data, '%d/%m/%Y %H:%i:%s') AS quando, registro FROM sis_logs
                  WHERE id > ? AND (registro LIKE '%bloqueado por atraso no titulo%' OR registro LIKE '%desbloqueio do cliente%')
                  ORDER BY id LIMIT 5000", [$ultimo]);
            if (!$rows) {
                break;
            }
            $titulos = [];
            $candidatos = [];
            foreach ($rows as $r) {
                if (preg_match('/bloqueado por atraso no titulo (\d+)/', (string) $r['registro'], $m)) {
                    $titulos[(int) $m[1]] = true;
                } elseif (preg_match_all('/(?:cliente|observacao) (\S+)/', (string) $r['registro'], $m)) {
                    foreach ($m[1] as $c) {
                        $candidatos[$c] = true;
                    }
                }
            }
            $loginDoTitulo = self::mapa('SELECT id AS k, login AS v FROM sis_lanc WHERE id IN', array_keys($titulos));
            $existe = self::mapa('SELECT login AS k, login AS v FROM sis_cliente WHERE login IN', array_keys($candidatos));

            $ev = [];
            foreach ($rows as $r) {
                $reg = (string) $r['registro'];
                $ultimo = (int) $r['id'];
                if ($r['quando'] === null) {
                    continue;
                }
                if (preg_match('/bloqueado por atraso no titulo (\d+)/', $reg, $m)) {
                    $login = $loginDoTitulo[(int) $m[1]] ?? null;
                    if ($login === null && preg_match('/^cliente (\S+) bloqueado/', $reg, $mm) && isset($existe[$mm[1]])) {
                        $login = $mm[1];
                    }
                    if ($login !== null) {
                        $ev[] = ['log_id' => $r['id'], 'data' => $r['quando'], 'login' => $login, 'tipo' => 'bloqueio',
                                 'titulo' => (int) $m[1], 'origem' => 'auto'];
                    }
                } elseif (preg_match_all('/(?:cliente|observacao) (\S+)/', $reg, $m)) {
                    $login = null;
                    foreach ($m[1] as $c) {
                        if (isset($existe[$c])) {
                            $login = $c;
                            break;
                        }
                    }
                    if ($login !== null) {
                        $ev[] = ['log_id' => $r['id'], 'data' => $r['quando'], 'login' => $login, 'tipo' => 'desbloqueio',
                                 'titulo' => null, 'origem' => str_contains($reg, 'por nao ter titulos vencidos') ? 'auto' : 'manual'];
                    }
                }
            }
            self::inserirEmLote('tab_pfin_evento_bloqueio', ['log_id', 'data', 'login', 'tipo', 'titulo', 'origem'], $ev);
            $total += count($ev);
        } while (count($rows) === 5000);
        return $total;
    }

    /** SELECT ... IN (lista) em lotes, devolvendo [k => v]. */
    private static function mapa(string $sqlPrefixo, array $chaves): array
    {
        $saida = [];
        foreach (array_chunk($chaves, 500) as $lote) {
            $in = implode(',', array_fill(0, count($lote), '?'));
            foreach (Db::todos("$sqlPrefixo ($in)", $lote) as $r) {
                $saida[$r['k']] = (string) $r['v'];
            }
        }
        return $saida;
    }

    /** Destino dos titulos por mes de vencimento, de $desde ate o fim do mes atual. */
    private static function fatoSafra(string $desde, string $ate): int
    {
        $tipos = Calendario::sqlTiposReceita();
        $ef    = Calendario::sqlVencEf('l.datavenc');
        $prazo = Calendario::sqlPrazo('l.datavenc');
        $v     = 'CAST(l.valor AS DECIMAL(12,2))';
        $ok    = '(l.deltitulo = 0)';
        $pago  = "($ok AND l.status = 'pago')";
        $npago = "($ok AND l.status <> 'pago')";
        $dp    = 'DATE(l.datapag)';

        $rows = Db::todos(
            "SELECT DATE_FORMAT(l.datavenc, '%Y-%m-01') AS mes, " . Calendario::sqlDiaVenc('l.datavenc') . " AS dia_venc,
                    SUM($ok) AS qtd,                                   SUM(IF($ok, $v, 0)) AS valor,
                    SUM($pago AND $dp <= $prazo) AS qtd_em_dia,        SUM(IF($pago AND $dp <= $prazo, $v, 0)) AS valor_em_dia,
                    SUM($pago AND $dp > $prazo) AS qtd_atraso,         SUM(IF($pago AND $dp > $prazo, $v, 0)) AS valor_atraso,
                    SUM($npago AND $prazo < CURDATE()) AS qtd_vencido, SUM(IF($npago AND $prazo < CURDATE(), $v, 0)) AS valor_vencido,
                    SUM($npago AND $prazo >= CURDATE()) AS qtd_a_vencer, SUM(IF($npago AND $prazo >= CURDATE(), $v, 0)) AS valor_a_vencer,
                    SUM($ok AND $prazo < CURDATE()) AS qtd_maduro,     SUM(IF($ok AND $prazo < CURDATE(), $v, 0)) AS valor_maduro,
                    SUM(IF($ok AND $ef + INTERVAL 30 DAY < CURDATE(), $v, 0)) AS valor_maduro_d30,
                    SUM(IF($ok AND $ef + INTERVAL 30 DAY < CURDATE()
                           AND ($npago OR $dp > $ef + INTERVAL 30 DAY), $v, 0)) AS valor_nao_pago_d30,
                    SUM(IF($ok AND $ef + INTERVAL 90 DAY < CURDATE(), $v, 0)) AS valor_maduro_d90,
                    SUM(IF($ok AND $ef + INTERVAL 90 DAY < CURDATE()
                           AND ($npago OR $dp > $ef + INTERVAL 90 DAY), $v, 0)) AS valor_nao_pago_d90,
                    SUM(l.deltitulo = 1 AND DATE(l.datadel) > $prazo) AS qtd_baixa,
                    SUM(IF(l.deltitulo = 1 AND DATE(l.datadel) > $prazo, $v, 0)) AS valor_baixa
               FROM sis_lanc l
              WHERE l.datavenc >= ? AND l.datavenc < ?
                AND l.tipo IN $tipos
                AND (l.deltitulo = 0 OR l.status <> 'pago')
              GROUP BY 1, 2", [$desde, $ate]);

        $agora = date('Y-m-d H:i:s');
        foreach ($rows as &$r) {
            $r['atualizado_em'] = $agora;
        }
        unset($r);

        Db::transacao(function () use ($rows, $desde) {
            Db::exec('DELETE FROM tab_pfin_fato_safra WHERE mes >= ?', [$desde]);
            self::inserirEmLote('tab_pfin_fato_safra', [
                'mes', 'dia_venc', 'qtd', 'valor', 'qtd_em_dia', 'valor_em_dia', 'qtd_atraso', 'valor_atraso',
                'qtd_vencido', 'valor_vencido', 'qtd_a_vencer', 'valor_a_vencer', 'qtd_maduro', 'valor_maduro',
                'valor_maduro_d30', 'valor_nao_pago_d30', 'valor_maduro_d90', 'valor_nao_pago_d90',
                'qtd_baixa', 'valor_baixa', 'atualizado_em'], $rows);
        });
        return count($rows);
    }

    /** Histograma: dias entre o vencimento efetivo e o pagamento (0 = em dia, 31 = 31+). */
    private static function fatoAtraso(string $desde, string $ate): int
    {
        $tipos = Calendario::sqlTiposReceita();
        $ef = Calendario::sqlVencEf('l.datavenc');
        $rows = Db::todos(
            "SELECT DATE_FORMAT(l.datavenc, '%Y-%m-01') AS mes, " . Calendario::sqlDiaVenc('l.datavenc') . " AS dia_venc,
                    LEAST(GREATEST(DATEDIFF(DATE(l.datapag), $ef), 0), 31) AS dias,
                    COUNT(*) AS qtd, SUM(CAST(l.valorpag AS DECIMAL(12,2))) AS valor
               FROM sis_lanc l
              WHERE l.datavenc >= ? AND l.datavenc < ?
                AND l.tipo IN $tipos AND l.deltitulo = 0 AND l.status = 'pago' AND l.datapag IS NOT NULL
              GROUP BY 1, 2, 3", [$desde, $ate]);

        Db::transacao(function () use ($rows, $desde) {
            Db::exec('DELETE FROM tab_pfin_fato_atraso WHERE mes >= ?', [$desde]);
            self::inserirEmLote('tab_pfin_fato_atraso', ['mes', 'dia_venc', 'dias', 'qtd', 'valor'], $rows);
        });
        return count($rows);
    }

    /** Forma de pagamento normalizada: "PIX" e "Pix" sao a mesma coisa. */
    public static function sqlForma(string $col): string
    {
        return "IF(TRIM(IFNULL($col, '')) = '', 'nao_informado', LEFT(LOWER(TRIM($col)), 20))";
    }

    private static function inserirEmLote(string $tabela, array $colunas, array $rows): void
    {
        $lote = 400;
        $cols = '`' . implode('`,`', $colunas) . '`';
        $um = '(' . implode(',', array_fill(0, count($colunas), '?')) . ')';
        for ($i = 0; $i < count($rows); $i += $lote) {
            $pedaco = array_slice($rows, $i, $lote);
            $params = [];
            foreach ($pedaco as $r) {
                foreach ($colunas as $c) {
                    $params[] = array_key_exists($c, $r) ? $r[$c] : 0;
                }
            }
            Db::exec("INSERT INTO $tabela ($cols) VALUES " . implode(',', array_fill(0, count($pedaco), $um)), $params);
        }
    }

    /** Situacao do processamento, para o selo da tela e o diagnostico. */
    public static function estado(): array
    {
        $ult = Db::um("SELECT id, inicio, fim, duracao_ms, resultado, detalhe FROM tab_pfin_execucao
                        WHERE job = 'agregar' ORDER BY id DESC LIMIT 1");
        $ok = Db::um("SELECT inicio, fim, duracao_ms FROM tab_pfin_execucao
                       WHERE job = 'agregar' AND resultado = 'ok' ORDER BY id DESC LIMIT 1");
        $idadeH = $ok ? (int) Db::valor('SELECT TIMESTAMPDIFF(HOUR, ?, NOW())', [$ok['fim']]) : null;
        // Uma execucao "rodando" ha mais de 2 h morreu sem se registrar (processo derrubado).
        $rodando = $ult && $ult['resultado'] === 'rodando'
            && (int) Db::valor('SELECT TIMESTAMPDIFF(MINUTE, ?, NOW())', [$ult['inicio']]) < 120;
        return [
            'ultima'       => $ult,
            'ultima_ok'    => $ok,
            'idade_h'      => $idadeH,
            'rodando'      => $rodando,
            'nunca_rodou'  => $ok === null,
            'desatualizado' => $ok === null || $idadeH > 26,
            'fato_dia_ate' => Config::get('fato_dia_ate') ?: null,
        ];
    }
}
