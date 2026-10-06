<?php
/**
 * painel_financeiro :: Visao Geral (Home).
 */
final class AjaxVisao
{
    /** Mes de referencia (padrao: mes corrente do banco) e dia de vencimento opcional. */
    public static function periodo(array $e): array
    {
        $mes = isset($e['mes']) && $e['mes'] !== '' ? Validar::mes($e['mes']) : substr(Calendario::hoje(), 0, 7);
        $dv = Validar::inteiroOpc($e['dv'] ?? null, 1, 31);
        return [$mes, $dv];
    }

    public static function kpis(array $e): array
    {
        [$mes, $dv] = self::periodo($e);
        return Visao::kpis($mes, $dv);
    }

    public static function curva(array $e): array
    {
        [$mes, $dv] = self::periodo($e);
        return Visao::curva($mes, $dv);
    }

    public static function aging(array $e): array
    {
        [, $dv] = self::periodo($e);
        $sit = Validar::umDe($e['situacao'] ?? '', ['ativos', 'desativados', 'todos'], 'ativos');
        return Visao::aging($sit, $dv);
    }

    public static function safra(array $e): array
    {
        [$mes, $dv] = self::periodo($e);
        return Visao::safra($mes, $dv);
    }

    public static function alertas(array $e): array
    {
        return Visao::alertas();
    }

    public static function alertaLista(array $e): array
    {
        $tipo = Validar::umDe($e['tipo'] ?? '', ['pago_bloqueado', 'sem_corte', 'pos_desativacao']);
        return ['tipo' => $tipo, 'linhas' => Visao::listaAlerta($tipo)];
    }
}
