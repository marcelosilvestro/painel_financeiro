<?php
/**
 * painel_financeiro :: configuracoes gerais.
 */
final class AjaxConfig
{
    /** Chaves que mudam o resultado do agregador: alterar pede reprocessamento completo. */
    public const PEDEM_REPROCESSAR = ['tipos_receita', 'tolerancia_em_dia', 'considerar_feriados'];

    public static function listar(array $e): array
    {
        return [
            'itens'       => Config::paraTela(),
            'pode_editar' => Permissao::tem('admin'),
            'mkauth'      => [
                'dias_venc'        => Parametros::diasVencimento(),
                'dias_corte'       => Parametros::diasCortePadrao(),
                'dias_semana_corte' => Parametros::diasSemanaCorte(),
                'corte_automatico' => Parametros::corteAutomatico(),
                'modo_bloqueio'    => Parametros::modoBloqueio(),
            ],
        ];
    }

    /** Grava varias chaves de uma vez, tudo ou nada. */
    public static function salvar(array $e): array
    {
        $valores = isset($e['valores']) && is_array($e['valores']) ? $e['valores'] : [];
        $usuario = Permissao::login();

        $antes = [];
        $depois = [];
        try {
            Db::transacao(function () use ($valores, $usuario, &$antes, &$depois) {
                foreach ($valores as $chave => $valor) {
                    $chave = (string) $chave;
                    try {
                        [$a, $d] = Config::set($chave, $valor, $usuario);
                    } catch (PfErro $ex) {
                        throw new PfErro($ex->codigo(), $ex->detalhes() + ['chave' => $chave],
                            (Config::DEFINICOES[$chave]['rotulo'] ?? $chave) . ': ' . $ex->getMessage());
                    }
                    if ($a !== $d) {
                        $antes[$chave] = $a;
                        $depois[$chave] = $d;
                    }
                }
            });
        } catch (Throwable $ex) {
            Config::limparCache();
            throw $ex;
        }

        if ($depois) {
            Auditoria::registrar('config_alterar', 'config', null, $antes, $depois);
            Log::info('config.alterar', ['chaves' => array_keys($depois)]);
            Cache::limpar();
        }
        return [
            'alteradas'  => array_keys($depois),
            'reprocessar' => (bool) array_intersect(array_keys($depois), self::PEDEM_REPROCESSAR),
            'itens'      => Config::paraTela(),
        ];
    }
}
