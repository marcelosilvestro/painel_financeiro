<?php
/**
 * painel_financeiro :: tela Carteira.
 */
final class AjaxCart
{
    public static function kpis(array $e): array
    {
        [$mes] = AjaxVisao::periodo($e);
        return Carteira::kpis($mes);
    }

    public static function movimento(array $e): array
    {
        [$mes] = AjaxVisao::periodo($e);
        return Carteira::movimento($mes);
    }

    public static function planos(array $e): array
    {
        return Carteira::mrrPorPlano();
    }

    public static function recuperacao(array $e): array
    {
        return Carteira::recuperacao();
    }

    public static function recuperacaoLista(array $e): array
    {
        $ano = trim((string) ($e['ano'] ?? ''));
        if ($ano !== '' && $ano !== 'sem data' && !preg_match('/^\d{4}$/', $ano)) {
            throw new PfErro('PF-VAL-010');
        }
        return Carteira::recuperacaoLista(
            Validar::inteiroOpc($e['pagina'] ?? null, 1, 100000, 1),
            Validar::umDe($e['ordem'] ?? '', ['valor', 'nome', 'desativado'], 'valor'),
            $ano === '' ? null : $ano);
    }

    public static function primeiraFatura(array $e): array
    {
        [$mes] = AjaxVisao::periodo($e);
        return Carteira::primeiraFatura($mes);
    }

    public static function bloqueios(array $e): array
    {
        [$mes] = AjaxVisao::periodo($e);
        return Carteira::bloqueios($mes);
    }
}
