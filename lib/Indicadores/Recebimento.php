<?php
/**
 * painel_financeiro :: indicadores da tela Recebimentos (regime de CAIXA).
 *
 * Tudo aqui e "o que entrou", pela data do pagamento. Dias ja processados vem dos fatos
 * (tab_pfin_fato_dia / _coletor); o que o agregador ainda nao cobriu (hoje, ou tudo se ele nunca
 * rodou) e lido ao vivo — sis_lanc.datapag nao tem indice, por isso o resultado vai para o cache.
 */
final class Recebimento
{
    /** Formas com cor e rotulo proprios; o resto e somado em "outras". */
    public const FORMAS = ['pix', 'boleto', 'dinheiro', 'cartao'];

    public static function formaGrupo(string $f): string
    {
        return in_array($f, self::FORMAS, true) ? $f : 'outras';
    }

    /** Data limite do fato e inicio do trecho ao vivo dentro de [ini, fim). */
    private static function corte(string $ini, string $fim): array
    {
        $ate = Agregador::fatoCobreAte();
        $vivoDesde = ($ate !== '' && $ate >= $ini) ? max($ini, (new DateTimeImmutable($ate))->modify('+1 day')->format('Y-m-d')) : $ini;
        $hojeMais1 = (new DateTimeImmutable(Calendario::hoje()))->modify('+1 day')->format('Y-m-d');
        return [$vivoDesde, min($fim, $hojeMais1)];
    }

    /**
     * Pagamentos de [ini, fim) por dia, forma, coletor e recorrente — fato + ao vivo.
     * @return array<int,array{dia:string,forma:string,coletor:?string,qtd:int,valor:float,juros:float,desconto:float}>
     */
    public static function linhas(string $ini, string $fim, ?int $dv, bool $comColetor = false): array
    {
        [$vivoDesde, $vivoAte] = self::corte($ini, $fim);
        $saida = [];
        $fDv = $dv !== null ? ' AND dia_venc = ?' : '';
        $fatoAte = min($vivoDesde, $fim);
        $p = $dv !== null ? [$ini, $fatoAte, $dv] : [$ini, $fatoAte];
        if ($vivoDesde > $ini) {
            if ($comColetor) {
                // o fato por coletor nao tem dia de vencimento: com filtro de vencimento, ao vivo
                foreach (Db::todos("SELECT dia, coletor, SUM(qtd) qtd, SUM(valor_pago) valor FROM tab_pfin_fato_coletor
                                     WHERE dia >= ? AND dia < ? GROUP BY dia, coletor", [$ini, $fatoAte]) as $r) {
                    $saida[] = ['dia' => $r['dia'], 'forma' => null, 'coletor' => $r['coletor'], 'qtd' => (int) $r['qtd'],
                                'valor' => (float) $r['valor'], 'juros' => 0.0, 'desconto' => 0.0];
                }
            } else {
                foreach (Db::todos("SELECT dia, forma, SUM(qtd) qtd, SUM(valor_pago) valor, SUM(juros) juros, SUM(desconto) desconto
                                      FROM tab_pfin_fato_dia WHERE dia >= ? AND dia < ? $fDv GROUP BY dia, forma", $p) as $r) {
                    $saida[] = ['dia' => $r['dia'], 'forma' => $r['forma'], 'coletor' => null, 'qtd' => (int) $r['qtd'],
                                'valor' => (float) $r['valor'], 'juros' => (float) $r['juros'], 'desconto' => (float) $r['desconto']];
                }
            }
        }
        if ($vivoDesde < $vivoAte) {
            $pv = [$vivoDesde, $vivoAte];
            $fDvL = '';
            if ($dv !== null && !$comColetor) {
                $fDvL = ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
                $pv[] = $dv;
            }
            $vp = 'CAST(l.valorpag AS DECIMAL(12,2))';
            $v = 'CAST(l.valor AS DECIMAL(12,2))';
            foreach (Db::todos(
                "SELECT DATE(l.datapag) AS dia, " . Agregador::sqlForma('l.formapag') . " AS forma, "
                . Agregador::sqlColetor('l.coletor') . " AS coletor, COUNT(*) AS qtd, SUM($vp) AS valor,
                        SUM(GREATEST($vp - $v, 0)) AS juros, SUM(GREATEST($v - $vp, 0)) AS desconto
                   FROM sis_lanc l
                  WHERE l.deltitulo = 0 AND l.status = 'pago' AND l.datapag >= ? AND l.datapag < ? $fDvL
                  GROUP BY 1, 2, 3", $pv) as $r) {
                $saida[] = ['dia' => $r['dia'], 'forma' => $comColetor ? null : $r['forma'], 'coletor' => $comColetor ? $r['coletor'] : null,
                            'qtd' => (int) $r['qtd'], 'valor' => (float) $r['valor'], 'juros' => (float) $r['juros'], 'desconto' => (float) $r['desconto']];
            }
        }
        return $saida;
    }

    /** Cartoes da tela. */
    public static function kpis(string $mes, ?int $dv): array
    {
        [$v, $em] = Cache::lembrar('rec.kpis', ['mes' => $mes, 'dv' => $dv], function () use ($mes, $dv) {
            $caixa = Visao::recebidoCaixa($mes, $dv);
            $ant = Visao::recebidoCaixa(Calendario::mesAnterior($mes), $dv);
            $anoAnt = Visao::recebidoCaixa((new DateTimeImmutable($mes . '-01'))->modify('-12 month')->format('Y-m'), $dv);
            return ['caixa' => $caixa, 'caixa_ant' => $ant['valor'], 'caixa_ano_ant' => $anoAnt['valor'],
                    'em_aberto' => self::emAbertoDoMes($mes, $dv)];
        });
        $v['calculado_em'] = $em;
        return $v;
    }

    /**
     * O que falta entrar dos titulos que vencem no mes: a vencer (prazo ainda nao chegou) e
     * vencido (prazo passou e nao pagou). Competencia, receita recorrente.
     */
    public static function emAbertoDoMes(string $mes, ?int $dv): array
    {
        [$ini, $fim] = Calendario::limitesMes($mes);
        $prazo = Calendario::sqlPrazo('l.datavenc');
        $v = 'CAST(l.valor AS DECIMAL(12,2))';
        $p = [$ini, $fim];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND ' . Calendario::sqlDiaVenc('l.datavenc') . ' = ?';
            $p[] = $dv;
        }
        $r = Db::um("SELECT IFNULL(SUM(IF($prazo >= CURDATE(), $v, 0)), 0) AS a_vencer, SUM($prazo >= CURDATE()) AS qtd_a_vencer,
                            IFNULL(SUM(IF($prazo < CURDATE(), $v, 0)), 0) AS vencido, SUM($prazo < CURDATE()) AS qtd_vencido
                       FROM sis_lanc l
                      WHERE l.datavenc >= ? AND l.datavenc < ? AND l.deltitulo = 0 AND l.status <> 'pago'
                        AND l.tipo IN " . Calendario::sqlTiposReceita() . " $fDv", $p);
        return ['a_vencer' => (float) $r['a_vencer'], 'qtd_a_vencer' => (int) $r['qtd_a_vencer'],
                'vencido' => (float) $r['vencido'], 'qtd_vencido' => (int) $r['qtd_vencido']];
    }

    /** Recebido por dia do mes, empilhado por forma. */
    public static function porDia(string $mes, ?int $dv): array
    {
        [$saida, $em] = Cache::lembrar('rec.dia', ['mes' => $mes, 'dv' => $dv], function () use ($mes, $dv) {
            [$ini, $fim] = Calendario::limitesMes($mes);
            $n = (int) (new DateTimeImmutable($ini))->format('t');
            $series = [];
            foreach (array_merge(self::FORMAS, ['outras']) as $f) {
                $series[$f] = array_fill(0, $n, 0.0);
            }
            $qtd = array_fill(0, $n, 0);
            foreach (self::linhas($ini, $fim, $dv) as $r) {
                $i = (int) substr($r['dia'], 8, 2) - 1;
                $series[self::formaGrupo((string) $r['forma'])][$i] += $r['valor'];
                $qtd[$i] += $r['qtd'];
            }
            foreach ($series as &$s) {
                $s = array_map(fn($x) => round($x, 2), $s);
            }
            unset($s);
            return ['dias' => range(1, $n), 'series' => $series, 'qtd' => $qtd, 'mes' => $mes];
        });
        $saida['calculado_em'] = $em;
        return $saida;
    }

    /**
     * Ultimos 12 meses ate $mes: recebido (caixa) por forma e previsto (competencia, do fato de
     * safra). Dois numeros em R$ no mesmo eixo, sem eixo duplo.
     */
    public static function mensal(string $mes, ?int $dv): array
    {
        [$saida, $em] = Cache::lembrar('rec.mensal', ['mes' => $mes, 'dv' => $dv], function () use ($mes, $dv) {
            $ini = (new DateTimeImmutable($mes . '-01'))->modify('-11 month')->format('Y-m-d');
            $fim = (new DateTimeImmutable($mes . '-01'))->modify('+1 month')->format('Y-m-d');
            $meses = [];
            for ($d = new DateTimeImmutable($ini); $d->format('Y-m-d') < $fim; $d = $d->modify('+1 month')) {
                $meses[] = $d->format('Y-m');
            }
            $idx = array_flip($meses);
            $series = [];
            foreach (array_merge(self::FORMAS, ['outras']) as $f) {
                $series[$f] = array_fill(0, count($meses), 0.0);
            }
            $juros = array_fill(0, count($meses), 0.0);
            $desc = array_fill(0, count($meses), 0.0);
            foreach (self::linhas($ini, $fim, $dv) as $r) {
                $i = $idx[substr($r['dia'], 0, 7)] ?? null;
                if ($i === null) {
                    continue;
                }
                $series[self::formaGrupo((string) $r['forma'])][$i] += $r['valor'];
                $juros[$i] += $r['juros'];
                $desc[$i] += $r['desconto'];
            }
            $prev = array_fill(0, count($meses), null);
            $p = [$ini, $mes . '-01'];
            $fDv = '';
            if ($dv !== null) {
                $fDv = ' AND dia_venc = ?';
                $p[] = $dv;
            }
            foreach (Db::todos("SELECT DATE_FORMAT(mes, '%Y-%m') m, SUM(valor) v FROM tab_pfin_fato_safra
                                 WHERE mes >= ? AND mes <= ? $fDv GROUP BY mes", $p) as $r) {
                if (isset($idx[$r['m']])) {
                    $prev[$idx[$r['m']]] = round((float) $r['v'], 2);
                }
            }
            $total = [];
            foreach ($meses as $i => $m) {
                $t = 0.0;
                foreach ($series as $s) {
                    $t += $s[$i];
                }
                $total[] = round($t, 2);
            }
            foreach ($series as &$s) {
                $s = array_map(fn($x) => round($x, 2), $s);
            }
            unset($s);
            return ['meses' => $meses, 'series' => $series, 'total' => $total, 'previsto' => $prev,
                    'juros' => array_map(fn($x) => round($x, 2), $juros), 'desconto' => array_map(fn($x) => round($x, 2), $desc)];
        });
        $saida['calculado_em'] = $em;
        return $saida;
    }

    /** Quem deu baixa no mes (coletor). "arq.retorno" e o retorno bancario automatico. */
    public static function coletores(string $mes): array
    {
        [$saida] = Cache::lembrar('rec.coletor', ['mes' => $mes], function () use ($mes) {
            [$ini, $fim] = Calendario::limitesMes($mes);
            $g = [];
            foreach (self::linhas($ini, $fim, null, true) as $r) {
                $c = (string) $r['coletor'];
                $g[$c] ??= ['coletor' => $c, 'qtd' => 0, 'valor' => 0.0];
                $g[$c]['qtd'] += $r['qtd'];
                $g[$c]['valor'] += $r['valor'];
            }
            usort($g, fn($a, $b) => $b['valor'] <=> $a['valor']);
            foreach ($g as &$x) {
                $x['valor'] = round($x['valor'], 2);
                $x['automatico'] = $x['coletor'] === 'arq.retorno';
            }
            unset($x);
            return ['itens' => array_values($g)];
        });
        return $saida;
    }

    /** Pagamentos de um dia (lista nominal, paginada). */
    public static function pagamentosDia(string $dia, int $pagina): array
    {
        $fim = (new DateTimeImmutable($dia))->modify('+1 day')->format('Y-m-d');
        $ef = Calendario::sqlVencEf('l.datavenc');
        $rows = Db::todos(
            "SELECT l.id, l.login, c.nome, c.uuid_cliente, DATE(l.datavenc) AS datavenc, l.datapag,
                    CAST(l.valor AS DECIMAL(12,2)) AS valor, CAST(l.valorpag AS DECIMAL(12,2)) AS valorpag,
                    " . Agregador::sqlForma('l.formapag') . " AS forma, " . Agregador::sqlColetor('l.coletor') . " AS coletor,
                    l.tipo, DATEDIFF(DATE(l.datapag), $ef) AS dias
               FROM sis_lanc l LEFT JOIN sis_cliente c ON c.login = l.login
              WHERE l.deltitulo = 0 AND l.status = 'pago' AND l.datapag >= ? AND l.datapag < ?
              ORDER BY l.datapag, c.nome", [$dia, $fim]);
        $por = Config::int('linhas_por_pagina');
        $total = count($rows);
        $pagina = max(1, min($pagina, max(1, (int) ceil($total / $por))));
        $soma = 0.0;
        foreach ($rows as $r) {
            $soma += (float) $r['valorpag'];
        }
        return ['dia' => $dia, 'total' => $total, 'soma' => round($soma, 2), 'pagina' => $pagina, 'por_pagina' => $por,
                'linhas' => array_slice($rows, ($pagina - 1) * $por, $por)];
    }
}
