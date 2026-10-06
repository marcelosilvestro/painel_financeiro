<?php
/**
 * painel_financeiro :: estado inicial de qualquer tela.
 */
final class AjaxInicio
{
    public static function estado(array $e): array
    {
        $papeis = Permissao::papeis();
        return [
            'versao'      => pf_versao(),
            'ha_admin'    => Permissao::haAdmin(),
            'pode_ver'    => Permissao::tem('ver'),
            'papeis'      => $papeis,
            'nominal'     => Permissao::tem('nominal'),
            'exportar'    => Permissao::tem('exportar'),
            'admin'       => Permissao::tem('admin'),
            'hoje'        => Calendario::hoje(),
            'dias_venc'   => Parametros::diasVencimento(),
            'agregador'   => Permissao::tem('ver') ? Agregador::estado() : null,
        ];
    }
}
