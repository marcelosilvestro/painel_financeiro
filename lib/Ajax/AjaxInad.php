<?php
/**
 * painel_financeiro :: tela Inadimplencia.
 */
final class AjaxInad
{
    public static function resumo(array $e): array
    {
        return Inadimplencia::resumo(self::filtros($e));
    }

    public static function lista(array $e): array
    {
        $f = Filtro::ler($e);
        $pagina = Validar::inteiroOpc($e['pagina'] ?? null, 1, 100000, 1);
        $ordem = Validar::umDe($e['ordem'] ?? '', Inadimplencia::ORDENS, 'valor');
        $dir = Validar::umDe($e['dir'] ?? '', ['asc', 'desc'], 'desc');
        return Inadimplencia::lista($f, $pagina, $ordem, $dir);
    }

    public static function dimensao(array $e): array
    {
        $dim = Validar::umDe($e['dim'] ?? '', array_keys(Filtro::DIMENSOES), 'plano');
        return Inadimplencia::dimensao(self::filtros($e), $dim);
    }

    public static function distribuicao(array $e): array
    {
        [$mes, $dv] = AjaxVisao::periodo($e);
        return Inadimplencia::distribuicao($mes, $dv);
    }

    public static function opcoes(array $e): array
    {
        return Inadimplencia::opcoes();
    }

    /** Responde com o arquivo CSV, nao com JSON. A exportacao fica na auditoria. */
    public static function exportar(array $e): void
    {
        $f = Filtro::ler($e);
        $ordem = Validar::umDe($e['ordem'] ?? '', Inadimplencia::ORDENS, 'valor');
        $dir = Validar::umDe($e['dir'] ?? '', ['asc', 'desc'], 'desc');
        $n = Inadimplencia::exportarCsv($f, $ordem, $dir);
        Auditoria::registrar('exportar_csv', 'inadimplentes', null, null, ['filtros' => $f, 'linhas' => $n]);
        Log::info('inad.exportar', ['linhas' => $n]);
        exit;
    }

    /**
     * Filtros para as operacoes AGREGADAS: sem o papel nominal, a busca por nome e ignorada —
     * senao "quanto deve quem se chama X" vazaria por um grafico.
     */
    private static function filtros(array $e): array
    {
        $f = Filtro::ler($e);
        if (!Permissao::tem('nominal')) {
            $f['busca'] = '';
        }
        return $f;
    }
}
