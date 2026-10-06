<?php
/**
 * painel_financeiro :: cache em arquivo dos indicadores ao vivo.
 *
 * Fica em /opt/mk-auth/dados/painel_financeiro/cache (a pasta do addon e somente leitura para o
 * PHP). Escrita atomica por arquivo temporario + rename, sem flock: o AppArmor do painel nega
 * lock em /opt/mk-auth e um flock negado derruba a escrita inteira.
 *
 * Cache e conveniencia: qualquer falha de disco cai para o calculo direto, nunca para erro.
 */
final class Cache
{
    private static ?string $dir = null;
    private static ?int $ttlS = null;

    public static function configurar(string $dir, int $ttlMin): void
    {
        self::$dir = rtrim($dir, '/');
        self::$ttlS = max(0, $ttlMin) * 60;
    }

    /**
     * Devolve o valor guardado para ($grupo, $params) ou calcula com $fn e guarda.
     * O segundo elemento diz quando o valor foi calculado (para o selo "atualizado ha X min").
     *
     * @return array{0:mixed,1:string}
     */
    public static function lembrar(string $grupo, array $params, callable $fn): array
    {
        $ttl = self::$ttlS ?? 0;
        if ($ttl <= 0 || self::$dir === null) {
            return [$fn(), date('Y-m-d H:i:s')];
        }
        ksort($params);
        $arq = self::$dir . '/' . preg_replace('/[^a-z0-9_.]/', '_', $grupo) . '-'
             . sha1(json_encode($params)) . '.json';

        $bruto = @file_get_contents($arq);
        if ($bruto !== false) {
            $j = json_decode($bruto, true);
            if (is_array($j) && isset($j['em'], $j['ts']) && (time() - (int) $j['ts']) < $ttl) {
                return [$j['v'] ?? null, (string) $j['em']];
            }
        }

        $v = $fn();
        $em = date('Y-m-d H:i:s');
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0770, true);
        }
        $tmp = $arq . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode(['ts' => time(), 'em' => $em, 'v' => $v], JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $arq);
        } else {
            @unlink($tmp);
        }
        return [$v, $em];
    }

    /** Apaga tudo (depois de reprocessar ou de mudar configuracao). */
    public static function limpar(): int
    {
        if (self::$dir === null || !is_dir(self::$dir)) {
            return 0;
        }
        $n = 0;
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
