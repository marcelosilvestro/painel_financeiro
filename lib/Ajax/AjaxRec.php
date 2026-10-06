<?php
/**
 * painel_financeiro :: tela Recebimentos.
 */
final class AjaxRec
{
    public static function kpis(array $e): array
    {
        [$mes, $dv] = AjaxVisao::periodo($e);
        return Recebimento::kpis($mes, $dv);
    }

    public static function dia(array $e): array
    {
        [$mes, $dv] = AjaxVisao::periodo($e);
        return Recebimento::porDia($mes, $dv);
    }

    public static function mensal(array $e): array
    {
        [$mes, $dv] = AjaxVisao::periodo($e);
        return Recebimento::mensal($mes, $dv);
    }

    public static function coletor(array $e): array
    {
        [$mes] = AjaxVisao::periodo($e);
        return Recebimento::coletores($mes);
    }

    public static function pagamentos(array $e): array
    {
        $dia = Validar::data($e['dia'] ?? '');
        return Recebimento::pagamentosDia($dia, Validar::inteiroOpc($e['pagina'] ?? null, 1, 100000, 1));
    }
}
