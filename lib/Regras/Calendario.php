<?php
/**
 * painel_financeiro :: datas de cobranca.
 *
 * Vencimento efetivo: o MK-AUTH emite o boleto de um vencimento em sabado/domingo para o
 * proximo dia util, entao pagar na segunda e pagar em dia. Feriado so entra quando o
 * administrador liga "considerar_feriados"; os feriados vem da tabela COMPARTILHADA tab_feriados
 * (addon Calendario de Feriados, a mesma que o Livro Caixa usa).
 *
 * Corte: o MK-AUTH corta em vencimento + dias_corte + 1 (dias_corte e carencia), somente nos
 * dias da semana de sis_opcao.dias_de_corte, e NAO conhece feriado.
 */
final class Calendario
{
    /** Expressao SQL: DATE + deslocamento de fim de semana. */
    private static function sqlFimDeSemana(string $data): string
    {
        return "($data + INTERVAL (CASE DAYOFWEEK($data) WHEN 7 THEN 2 WHEN 1 THEN 1 ELSE 0 END) DAY)";
    }

    /**
     * Expressao SQL do vencimento efetivo para uma coluna DATETIME/DATE (ex.: 'l.datavenc').
     * Com feriados ligados, ate dois feriados seguidos sao pulados (Natal/Ano-Novo nao encostam).
     */
    public static function sqlVencEf(string $coluna): string
    {
        $e = self::sqlFimDeSemana("DATE($coluna)");
        $feriados = self::feriados();
        if (!$feriados) {
            return $e;
        }
        $lista = implode(',', array_map(fn($d) => "'" . $d . "'", $feriados));
        for ($i = 0; $i < 2; $i++) {
            $e = "IF($e IN ($lista), " . self::sqlFimDeSemana("($e + INTERVAL 1 DAY)") . ", $e)";
        }
        return $e;
    }

    /**
     * Dia de vencimento (do grupo de clientes) de uma coluna datavenc. O MK-AUTH GRAVA o
     * datavenc ja empurrado para o dia util: o titulo do "dia 20" que caiu num domingo fica com
     * datavenc na segunda, dia 21 (conferido na base real: quase nenhum titulo em sab/dom). Para
     * filtrar e agrupar por dia de vencimento, a segunda-feira volta para o sabado/domingo quando
     * ESSE e um dia de vencimento do provedor (sis_opcao.diaNN).
     */
    public static function sqlDiaVenc(string $coluna): string
    {
        $dias = Parametros::diasVencimento();
        if (!$dias) {
            return "IFNULL(DAY($coluna), 0)";
        }
        $in = '(' . implode(',', array_map('intval', $dias)) . ')';
        return "IFNULL(CASE WHEN DAY($coluna) IN $in THEN DAY($coluna)
                    WHEN DAYOFWEEK($coluna) = 2 AND DAY($coluna - INTERVAL 1 DAY) IN $in THEN DAY($coluna - INTERVAL 1 DAY)
                    WHEN DAYOFWEEK($coluna) = 2 AND DAY($coluna - INTERVAL 2 DAY) IN $in THEN DAY($coluna - INTERVAL 2 DAY)
                    ELSE DAY($coluna) END, 0)";
    }

    /** Ultimo dia em que o pagamento ainda conta como em dia (vencimento efetivo + tolerancia). */
    public static function sqlPrazo(string $coluna): string
    {
        $tol = Config::int('tolerancia_em_dia');
        $ef = self::sqlVencEf($coluna);
        return $tol > 0 ? "($ef + INTERVAL $tol DAY)" : $ef;
    }

    /**
     * Lista SQL literal dos tipos de titulo da receita recorrente: ('mensalidade','servicos').
     * Os valores ja foram validados como [a-z0-9_] na configuracao; o quote e cinto extra.
     */
    public static function sqlTiposReceita(): string
    {
        $tipos = array_filter(explode(',', Config::get('tipos_receita')));
        if (!$tipos) {
            $tipos = ['mensalidade'];
        }
        return '(' . implode(',', array_map(fn($t) => Db::pdo()->quote($t), $tipos)) . ')';
    }

    private static ?array $feriados = null;

    /** Datas 'AAAA-MM-DD' dos feriados cadastrados, se a regra estiver ligada. @return string[] */
    public static function feriados(): array
    {
        if (self::$feriados !== null) {
            return self::$feriados;
        }
        self::$feriados = [];
        if (!Config::ligado('considerar_feriados') || !Db::tabelaExiste('tab_feriados')) {
            return self::$feriados;
        }
        foreach (Db::todos("SELECT data FROM tab_feriados WHERE tipo = 'feriado' ORDER BY data") as $r) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['data'])) {
                self::$feriados[] = (string) $r['data'];
            }
        }
        return self::$feriados;
    }

    /** Esquece o cache da requisicao (depois de mudar a configuracao ou os feriados). */
    public static function esquecer(): void
    {
        self::$feriados = null;
    }

    /**
     * Data em que o MK-AUTH corta um titulo: vencimento (empurrado do fim de semana) + carencia
     * + 1, avancando ate um dia da semana permitido em dias_de_corte.
     */
    public static function dataCorte(string $vencimento, int $diasCorte, array $diasSemana, array $pular = []): string
    {
        $d = new DateTimeImmutable(substr($vencimento, 0, 10));
        $dow = (int) $d->format('N');
        if ($dow === 6) {
            $d = $d->modify('+2 day');
        } elseif ($dow === 7) {
            $d = $d->modify('+1 day');
        }
        $d = $d->modify('+' . ($diasCorte + 1) . ' day');
        // $pular: datas em que o corte NAO roda (feriados, quando o guardiao esta ligado). O corte
        // do MK-AUTH e cumulativo: quem passou do prazo e cortado na proxima rodada valida.
        $pular = array_flip($pular);
        for ($i = 0; $i < 40 && (!in_array((int) $d->format('N'), $diasSemana, true) || isset($pular[$d->format('Y-m-d')])); $i++) {
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    /**
     * "Hoje" pelo relogio do BANCO, nao do PHP: as consultas usam CURDATE(), e um PHP com fuso
     * diferente do MySQL faria a tela e o SQL discordarem sobre o que e hoje perto da meia-noite.
     */
    public static function hoje(): string
    {
        static $h = null;
        return $h ??= (string) Db::valor('SELECT CURDATE()');
    }

    /** [primeiro dia, primeiro dia do mes seguinte] de um 'AAAA-MM'. */
    public static function limitesMes(string $mes): array
    {
        $ini = new DateTimeImmutable($mes . '-01');
        return [$ini->format('Y-m-d'), $ini->modify('+1 month')->format('Y-m-d')];
    }

    public static function mesAnterior(string $mes): string
    {
        return (new DateTimeImmutable($mes . '-01'))->modify('-1 month')->format('Y-m');
    }
}
