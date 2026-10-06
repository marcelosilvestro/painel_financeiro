<?php
/**
 * painel_financeiro :: processamento dos indicadores.
 */
final class AjaxAgregador
{
    public static function estado(array $e): array
    {
        return Agregador::estado();
    }

    /** "Processar agora": o mesmo que o cron faz, disparado pelo administrador. */
    public static function processar(array $e): array
    {
        @set_time_limit(600);
        // A tela tem teto de tempo por consulta; o processamento nao.
        Db::limitarTempo(600);
        $completo = Validar::bool($e['completo'] ?? '0');
        $r = Agregador::executar(Permissao::login(), $completo);
        Auditoria::registrar('processar', 'agregador', null, null, $r);
        return $r + ['estado' => Agregador::estado()];
    }
}
