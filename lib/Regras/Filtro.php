<?php
/**
 * painel_financeiro :: filtros de cliente vindos da tela.
 *
 * Lista fechada de campos: o request escolhe VALORES, nunca colunas. Todo valor vai para o SQL
 * como parametro.
 */
final class Filtro
{
    /** Dimensoes que a tela pode agrupar/filtrar => coluna de sis_cliente. */
    public const DIMENSOES = [
        'plano'    => 'c.plano',
        'cidade'   => 'c.cidade',
        'bairro'   => 'c.bairro',
        'vendedor' => 'c.vendedor',
        'venc'     => 'c.venc',
    ];

    /** Normaliza os filtros do request. */
    public static function ler(array $e): array
    {
        $f = [
            'situacao'   => Validar::umDe($e['situacao'] ?? '', ['ativos', 'desativados', 'todos'], 'ativos'),
            'dias_min'   => Validar::inteiroOpc($e['dias_min'] ?? null, 0, 100000),
            'dias_max'   => Validar::inteiroOpc($e['dias_max'] ?? null, 0, 100000),
            'busca'      => Validar::texto($e['busca'] ?? '', 60),
            'reincidente' => Validar::bool($e['reincidente'] ?? '0'),
            'bloqueado'  => Validar::umDe($e['bloqueado'] ?? '', ['', 'sim', 'nao'], ''),
        ];
        foreach (array_keys(self::DIMENSOES) as $d) {
            $f[$d] = Validar::texto($e[$d] ?? '', 64);
        }
        if ($f['venc'] !== '' && !ctype_digit($f['venc'])) {
            throw new PfErro('PF-VAL-007', ['campo' => 'venc']);
        }
        return $f;
    }

    /**
     * Condicoes sobre sis_cliente (alias c).
     * @return array{0:string,1:array} fragmento "AND ..." e parametros
     */
    public static function sqlCliente(array $f): array
    {
        $sql = '';
        $p = [];
        if ($f['situacao'] === 'ativos') {
            $sql .= " AND c.cli_ativado = 's'";
        } elseif ($f['situacao'] === 'desativados') {
            $sql .= " AND c.cli_ativado = 'n'";
        }
        foreach (self::DIMENSOES as $d => $col) {
            if (($f[$d] ?? '') !== '') {
                // venc e VARCHAR(2) no MK-AUTH ("5" ou "05"): compara como numero.
                $sql .= $d === 'venc' ? ' AND CAST(c.venc AS UNSIGNED) = ?' : " AND $col = ?";
                $p[] = $d === 'venc' ? (int) $f[$d] : $f[$d];
            }
        }
        if (($f['bloqueado'] ?? '') !== '') {
            $sql .= ' AND c.bloqueado = ?';
            $p[] = $f['bloqueado'];
        }
        if (($f['busca'] ?? '') !== '') {
            $sql .= ' AND (c.nome LIKE ? OR c.login LIKE ?)';
            $termo = '%' . addcslashes($f['busca'], '%_\\') . '%';
            $p[] = $termo;
            $p[] = $termo;
        }
        return [$sql, $p];
    }

    /** Chave estavel dos filtros (cache). */
    public static function chave(array $f): array
    {
        ksort($f);
        return $f;
    }
}
