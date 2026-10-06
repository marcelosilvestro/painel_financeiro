<?php
/**
 * painel_financeiro :: validacao de entrada.
 *
 * Tudo o que vem da interface passa por aqui antes de chegar a uma consulta. Cada metodo
 * devolve o valor NORMALIZADO ou lanca PfErro com o codigo do problema — nunca "corrige" em
 * silencio um valor perigoso.
 */
require_once __DIR__ . '/PfErro.php';

final class Validar
{
    public static function inteiro($v, int $min, int $max): int
    {
        if (!is_numeric($v) || (string) (int) $v !== trim((string) $v)) {
            throw new PfErro('PF-VAL-007', ['min' => $min, 'max' => $max]);
        }
        $n = (int) $v;
        if ($n < $min || $n > $max) {
            throw new PfErro('PF-VAL-007', ['min' => $min, 'max' => $max]);
        }
        return $n;
    }

    /** Inteiro opcional: vazio vira $padrao. */
    public static function inteiroOpc($v, int $min, int $max, ?int $padrao = null): ?int
    {
        if ($v === null || trim((string) $v) === '') {
            return $padrao;
        }
        return self::inteiro($v, $min, $max);
    }

    /** Mes de referencia "AAAA-MM". */
    public static function mes($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $v, $m) || (int) $m[2] < 1 || (int) $m[2] > 12
            || (int) $m[1] < 2000 || (int) $m[1] > 2100) {
            throw new PfErro('PF-VAL-001');
        }
        return $v;
    }

    public static function data($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new PfErro('PF-VAL-002');
        }
        return $v;
    }

    public static function login($v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^[A-Za-z0-9._@-]{1,60}$/', $v)) {
            throw new PfErro('PF-VAL-005');
        }
        return $v;
    }

    /** Texto livre: tira caracteres de controle, limita tamanho. Vazio permitido se !$obrigatorio. */
    public static function texto($v, int $max, bool $obrigatorio = false): string
    {
        $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $v));
        if ($obrigatorio && $v === '') {
            throw new PfErro('PF-VAL-008');
        }
        return mb_substr($v, 0, $max);
    }

    /** Valor que precisa estar numa lista fechada. */
    public static function umDe($v, array $opcoes, ?string $padrao = null): string
    {
        $v = trim((string) $v);
        if ($v === '' && $padrao !== null) {
            return $padrao;
        }
        if (!in_array($v, $opcoes, true)) {
            throw new PfErro('PF-VAL-010', ['opcoes' => $opcoes]);
        }
        return $v;
    }

    /** "5,15,30,60,90": inteiros positivos estritamente crescentes. */
    public static function faixas($v): string
    {
        $v = preg_replace('/\s+/', '', (string) $v);
        $partes = $v === '' ? [] : explode(',', $v);
        if (count($partes) < 1 || count($partes) > 10) {
            throw new PfErro('PF-VAL-011');
        }
        $ant = 0;
        foreach ($partes as $p) {
            if (!ctype_digit($p) || (int) $p <= $ant || (int) $p > 3650) {
                throw new PfErro('PF-VAL-011');
            }
            $ant = (int) $p;
        }
        return implode(',', array_map('intval', $partes));
    }

    /** "mensalidade,servicos": palavras simples separadas por virgula. */
    public static function listaPalavras($v): string
    {
        $v = strtolower(preg_replace('/\s+/', '', (string) $v));
        $partes = $v === '' ? [] : explode(',', $v);
        if (!$partes || count($partes) > 20) {
            throw new PfErro('PF-VAL-012');
        }
        foreach ($partes as $p) {
            if (!preg_match('/^[a-z0-9_]{1,30}$/', $p)) {
                throw new PfErro('PF-VAL-012');
            }
        }
        return implode(',', array_unique($partes));
    }

    public static function bool($v): bool
    {
        return in_array($v, [true, 1, '1', 'true', 'on', 'sim'], true);
    }
}
