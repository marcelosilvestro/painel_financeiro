<?php
/**
 * painel_financeiro :: indicadores da tela Inadimplencia.
 *
 * Base de tudo: os titulos vencidos hoje (Visao::titulosVencidos) agrupados por cliente. O
 * conjunto e pequeno por natureza (so quem deve), entao filtro, ordenacao e paginacao rodam no
 * PHP sobre ele — e o resultado vai para o cache por combinacao de filtros.
 */
final class Inadimplencia
{
    public const ORDENS = ['valor', 'dias', 'nome', 'titulos', 'atrasos'];

    /** Clientes com titulo vencido, ja filtrados. @return array<int,array> */
    public static function clientes(array $f): array
    {
        [$lista] = Cache::lembrar('inad.clientes', Filtro::chave($f), function () use ($f) {
            [$cond, $p] = Filtro::sqlCliente($f);
            $por = [];
            foreach (Visao::titulosVencidos($cond, $p) as $t) {
                $l = $t['login'];
                if (!isset($por[$l])) {
                    $por[$l] = [
                        'login' => $l, 'nome' => $t['nome'], 'uuid' => $t['uuid_cliente'], 'plano' => $t['plano'],
                        'bairro' => $t['bairro'], 'cidade' => $t['cidade'], 'vendedor' => $t['vendedor'],
                        'venc' => $t['venc'] === null ? null : (int) $t['venc'], 'ativo' => $t['cli_ativado'] === 's',
                        'bloqueado' => $t['bloqueado'] === 'sim', 'data_bloq' => $t['bloqueado'] === 'sim' ? $t['data_bloq'] : null,
                        'titulos' => 0, 'valor' => 0.0, 'dias' => 0, 'mais_antigo' => $t['datavenc'],
                    ];
                }
                $por[$l]['titulos']++;
                $por[$l]['valor'] += (float) $t['valor'];
                $por[$l]['dias'] = max($por[$l]['dias'], (int) $t['dias']);
                if ($t['datavenc'] < $por[$l]['mais_antigo']) {
                    $por[$l]['mais_antigo'] = $t['datavenc'];
                }
            }
            $atrasos = self::atrasosRecentes(array_keys($por));
            $n = Config::int('reincidencia_n');
            foreach ($por as $l => &$c) {
                $c['valor'] = round($c['valor'], 2);
                $c['atrasos'] = $atrasos[$l] ?? 0;
                $c['reincidente'] = $c['atrasos'] >= $n;
            }
            unset($c);
            return array_values($por);
        });

        return array_values(array_filter($lista, function ($c) use ($f) {
            if ($f['dias_min'] !== null && $c['dias'] < $f['dias_min']) {
                return false;
            }
            if ($f['dias_max'] !== null && $c['dias'] > $f['dias_max']) {
                return false;
            }
            return !$f['reincidente'] || $c['reincidente'];
        }));
    }

    /**
     * Reincidencia: entre os ultimos M titulos ja vencidos (prazo passado) de cada login,
     * quantos foram pagos depois do prazo ou seguem sem pagamento.
     *
     * @param string[] $logins
     * @return array<string,int>
     */
    public static function atrasosRecentes(array $logins): array
    {
        if (!$logins) {
            return [];
        }
        $m = Config::int('reincidencia_m');
        $tipos = Calendario::sqlTiposReceita();
        $prazo = Calendario::sqlPrazo('l.datavenc');
        // janela folgada: M titulos mensais cabem em M+3 meses mesmo com um titulo extra no meio
        $meses = $m + 3;
        $atrasou = "(l.status <> 'pago' OR DATE(l.datapag) > $prazo)";
        $contagem = [];

        if (count($logins) > 300) {
            // Base grande: UMA passada pela janela de datas (indice datavenc) com ROW_NUMBER por
            // cliente, em vez de centenas de buscas por login. Teste de carga, 30 mil clientes.
            $quero = array_flip($logins);
            $rows = Db::todos(
                "SELECT x.login, SUM(x.atrasou) AS n FROM (
                     SELECT l.login, $atrasou AS atrasou,
                            ROW_NUMBER() OVER (PARTITION BY l.login ORDER BY l.datavenc DESC) AS rn
                       FROM sis_lanc l " . Db::dicaIndice('sis_lanc', 'datavenc') . "
                      WHERE l.datavenc >= CURDATE() - INTERVAL $meses MONTH AND l.datavenc < CURDATE()
                        AND l.deltitulo = 0 AND l.tipo IN $tipos AND $prazo < CURDATE()
                 ) x WHERE x.rn <= $m GROUP BY x.login HAVING n > 0");
            foreach ($rows as $r) {
                if (isset($quero[$r['login']])) {
                    $contagem[$r['login']] = (int) $r['n'];
                }
            }
            return $contagem;
        }

        foreach (array_chunk($logins, 300) as $lote) {
            $in = implode(',', array_fill(0, count($lote), '?'));
            $rows = Db::todos(
                "SELECT x.login, SUM(x.atrasou) AS n FROM (
                     SELECT l.login, $atrasou AS atrasou,
                            ROW_NUMBER() OVER (PARTITION BY l.login ORDER BY l.datavenc DESC) AS rn
                       FROM sis_lanc l " . Db::dicaIndice('sis_lanc', 'login') . "
                      WHERE l.login IN ($in) AND l.deltitulo = 0 AND l.tipo IN $tipos
                        AND l.datavenc >= CURDATE() - INTERVAL $meses MONTH AND $prazo < CURDATE()
                 ) x WHERE x.rn <= $m GROUP BY x.login HAVING n > 0", $lote);
            foreach ($rows as $r) {
                $contagem[$r['login']] = (int) $r['n'];
            }
        }
        return $contagem;
    }

    public static function resumo(array $f): array
    {
        $cs = self::clientes($f);
        $r = ['clientes' => count($cs), 'valor' => 0.0, 'titulos' => 0, 'reincidentes' => 0, 'bloqueados' => 0,
              'dias_mediana' => null];
        $dias = [];
        foreach ($cs as $c) {
            $r['valor'] += $c['valor'];
            $r['titulos'] += $c['titulos'];
            $r['reincidentes'] += $c['reincidente'] ? 1 : 0;
            $r['bloqueados'] += $c['bloqueado'] ? 1 : 0;
            $dias[] = $c['dias'];
        }
        if ($dias) {
            sort($dias);
            $meio = intdiv(count($dias), 2);
            $r['dias_mediana'] = count($dias) % 2 ? $dias[$meio] : intdiv($dias[$meio - 1] + $dias[$meio], 2);
        }
        $r['valor'] = round($r['valor'], 2);
        $r['reincidencia'] = ['n' => Config::int('reincidencia_n'), 'm' => Config::int('reincidencia_m')];
        return $r;
    }

    /** Pagina da lista nominal, ordenada. */
    public static function lista(array $f, int $pagina, string $ordem, string $dir): array
    {
        $cs = self::ordenar(self::clientes($f), $ordem, $dir);
        $por = Config::int('linhas_por_pagina');
        $total = count($cs);
        $pagina = max(1, min($pagina, max(1, (int) ceil($total / $por))));
        $linhas = array_slice($cs, ($pagina - 1) * $por, $por);

        $ult = self::ultimosPagamentos(array_column($linhas, 'login'));
        foreach ($linhas as &$c) {
            $c['ultimo_pagamento'] = $ult[$c['login']] ?? null;
        }
        unset($c);
        return ['total' => $total, 'pagina' => $pagina, 'por_pagina' => $por, 'linhas' => $linhas];
    }

    public static function ordenar(array $cs, string $ordem, string $dir): array
    {
        $mult = $dir === 'asc' ? 1 : -1;
        usort($cs, function ($a, $b) use ($ordem, $mult) {
            $x = $ordem === 'nome' ? strcasecmp((string) $a['nome'], (string) $b['nome'])
                                   : ($a[$ordem] <=> $b[$ordem]);
            return $x !== 0 ? $x * $mult : strcasecmp((string) $a['nome'], (string) $b['nome']);
        });
        return $cs;
    }

    /** Ultimo pagamento de cada login (indice por login). @return array<string,string> */
    private static function ultimosPagamentos(array $logins): array
    {
        if (!$logins) {
            return [];
        }
        $in = implode(',', array_fill(0, count($logins), '?'));
        $saida = [];
        foreach (Db::todos("SELECT login, MAX(datapag) AS ult FROM sis_lanc
                             WHERE login IN ($in) AND deltitulo = 0 AND status = 'pago' GROUP BY login", $logins) as $r) {
            $saida[$r['login']] = substr((string) $r['ult'], 0, 10);
        }
        return $saida;
    }

    /** Onde o atraso se concentra: top 12 de uma dimensao por valor. */
    public static function dimensao(array $f, string $dim): array
    {
        if (!isset(Filtro::DIMENSOES[$dim])) {
            throw new PfErro('PF-VAL-010');
        }
        $g = [];
        foreach (self::clientes($f) as $c) {
            $k = $c[$dim];
            $k = ($k === null || $k === '') ? '(sem)' : ($dim === 'venc' ? 'dia ' . $k : (string) $k);
            $g[$k] ??= ['rotulo' => $k, 'valor' => 0.0, 'clientes' => 0];
            $g[$k]['valor'] += $c['valor'];
            $g[$k]['clientes']++;
        }
        usort($g, fn($a, $b) => $b['valor'] <=> $a['valor']);
        $top = array_slice($g, 0, 12);
        foreach ($top as &$x) {
            $x['valor'] = round($x['valor'], 2);
        }
        unset($x);
        return ['dimensao' => $dim, 'itens' => $top, 'outros' => max(0, count($g) - 12)];
    }

    /**
     * Distribuicao do atraso (safras dos ultimos N meses ate $mes): titulos pagos por dias
     * depois do vencimento efetivo. Marca o dia do corte padrao do provedor.
     */
    public static function distribuicao(string $mes, ?int $dv, int $meses = 6): array
    {
        $ini = (new DateTimeImmutable($mes . '-01'))->modify('-' . ($meses - 1) . ' month')->format('Y-m-d');
        $p = [$ini, $mes . '-01'];
        $fDv = '';
        if ($dv !== null) {
            $fDv = ' AND dia_venc = ?';
            $p[] = $dv;
        }
        $rows = Db::todos("SELECT dias, SUM(qtd) qtd, SUM(valor) valor FROM tab_pfin_fato_atraso
                            WHERE mes >= ? AND mes <= ? $fDv GROUP BY dias ORDER BY dias", $p);
        $qtd = array_fill(0, 32, 0);
        foreach ($rows as $r) {
            $qtd[(int) $r['dias']] = (int) $r['qtd'];
        }
        $total = array_sum($qtd);
        $acum = [];
        $s = 0;
        foreach ($qtd as $q) {
            $s += $q;
            $acum[] = $total > 0 ? round(100 * $s / $total, 1) : null;
        }
        return ['dias' => range(0, 31), 'qtd' => $qtd, 'acumulado_pct' => $acum, 'total' => $total,
                'corte_dia' => Parametros::diasCortePadrao() + 1, 'meses' => $meses, 'desde' => substr($ini, 0, 7)];
    }

    /** Valores possiveis dos filtros (contagens agregadas, sem nome de cliente). */
    public static function opcoes(): array
    {
        $col = function (string $c) {
            return array_column(Db::todos(
                "SELECT DISTINCT $c AS v FROM sis_cliente WHERE $c IS NOT NULL AND $c <> '' ORDER BY $c LIMIT 500"), 'v');
        };
        return [
            'planos'     => $col('plano'),
            'cidades'    => $col('cidade'),
            'bairros'    => $col('bairro'),
            'vendedores' => $col('vendedor'),
            'dias_venc'  => Parametros::diasVencimento(),
        ];
    }

    /**
     * CSV da lista filtrada, em streaming. Celulas que comecam com = + - @ ganham um apostrofo
     * na frente: abrir o arquivo no Excel nunca executa formula vinda de um cadastro.
     */
    public static function exportarCsv(array $f, string $ordem, string $dir): int
    {
        $cs = self::ordenar(self::clientes($f), $ordem, $dir);
        $ult = [];
        foreach (array_chunk(array_column($cs, 'login'), 500) as $lote) {
            $ult += self::ultimosPagamentos($lote);
        }
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="inadimplentes-' . date('Ymd-His') . '.csv"');
            header('Cache-Control: no-store');
        }
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Nome', 'Login', 'Plano', 'Bairro', 'Cidade', 'Vencimento', 'Situação', 'Bloqueado',
                       'Títulos vencidos', 'Valor (R$)', 'Dias de atraso', 'Mais antigo', 'Último pagamento', 'Atrasos recentes'], ';');
        $seguro = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;
        foreach ($cs as $c) {
            fputcsv($out, array_map($seguro, [
                (string) $c['nome'], (string) $c['login'], (string) $c['plano'], (string) $c['bairro'], (string) $c['cidade'],
                $c['venc'] === null ? '' : (string) $c['venc'], $c['ativo'] ? 'ativo' : 'desativado', $c['bloqueado'] ? 'sim' : 'não',
                (string) $c['titulos'], number_format($c['valor'], 2, ',', ''), (string) $c['dias'],
                (string) $c['mais_antigo'], (string) ($ult[$c['login']] ?? ''), (string) $c['atrasos'],
            ]), ';');
        }
        fclose($out);
        return count($cs);
    }
}
