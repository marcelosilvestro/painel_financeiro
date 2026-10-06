<?php
/**
 * painel_financeiro :: parametros de cobranca LIDOS DO MK-AUTH (sis_opcao, somente leitura).
 *
 * O addon vai para outras empresas: nenhum dia de vencimento, dia de corte ou forma de
 * bloqueio e fixo no codigo. Tudo sai daqui, que le o que o proprio provedor configurou.
 */
final class Parametros
{
    private static ?array $cache = null;

    /** Mapa nome => valor das opcoes que interessam ao addon. */
    public static function todos(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        if (!Db::tabelaExiste('sis_opcao')) {
            return self::$cache;
        }
        $rows = Db::todos(
            "SELECT nome, valor FROM sis_opcao
              WHERE nome REGEXP '^dia[0-9]{2}$'
                 OR nome IN ('climk_dias_corte','dias_de_corte','auto_corte','auto_desbloqueio','tbloqradius')");
        foreach ($rows as $r) {
            self::$cache[(string) $r['nome']] = (string) $r['valor'];
        }
        return self::$cache;
    }

    /** Dias do mes em que o provedor tem vencimento (sis_opcao.diaNN = 'sim'). @return int[] */
    public static function diasVencimento(): array
    {
        $dias = [];
        foreach (self::todos() as $nome => $valor) {
            if (preg_match('/^dia(\d{2})$/', $nome, $m) && strtolower(trim($valor)) === 'sim') {
                $dias[] = (int) $m[1];
            }
        }
        sort($dias);
        return $dias;
    }

    /** Carencia padrao do provedor (climk_dias_corte); o cliente pode ter a propria. */
    public static function diasCortePadrao(): int
    {
        $v = trim(self::todos()['climk_dias_corte'] ?? '');
        return ctype_digit($v) ? (int) $v : 15;
    }

    /**
     * Dias da semana em que o MK-AUTH executa o corte (sis_opcao.dias_de_corte, ex.:
     * "Mon,Tue,Wed,Thu,Fri,Ped"). Devolve numeros ISO 1=seg..7=dom. Itens que nao sao dia da
     * semana ("Ped") sao ignorados ate se saber o que significam.
     *
     * @return int[]
     */
    public static function diasSemanaCorte(): array
    {
        $mapa = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        $bruto = self::todos()['dias_de_corte'] ?? '';
        $dias = [];
        foreach (explode(',', strtolower($bruto)) as $p) {
            $p = trim($p);
            if (isset($mapa[$p])) {
                $dias[] = $mapa[$p];
            }
        }
        $dias = array_values(array_unique($dias));
        sort($dias);
        return $dias ?: [1, 2, 3, 4, 5];
    }

    public static function corteAutomatico(): bool
    {
        return strtolower(trim(self::todos()['auto_corte'] ?? 'sim')) === 'sim';
    }

    /** Como o MK-AUTH bloqueia: 'pool' (cliente bloqueado continua conectado) ou outro. */
    public static function modoBloqueio(): string
    {
        return strtolower(trim(self::todos()['tbloqradius'] ?? ''));
    }

    public static function esquecer(): void
    {
        self::$cache = null;
    }
}
