<?php
/**
 * painel_financeiro :: tabela de operacoes AJAX.
 *
 * Toda operacao do addon esta listada aqui com o metodo HTTP e a permissao exigida. O
 * roteador (ajax.php) recusa o que nao estiver na tabela.
 *
 * perm:
 *   'logado'  qualquer usuario logado no painel (estado inicial e assumir o PRIMEIRO admin)
 *   'ver'     qualquer papel do addon: numeros agregados, sem nome de cliente
 *   'nominal' listas com nome de cliente
 *   outro     um papel de Permissao::PAPEIS (admin sempre passa)
 */
final class Rotas
{
    public const MAPA = [
        'inicio.estado'           => ['GET',  'logado',   ['AjaxInicio', 'estado']],

        'permissao.assumir_admin' => ['POST', 'logado',   ['AjaxPermissao', 'assumirAdmin']],
        'permissao.listar'        => ['GET',  'admin',    ['AjaxPermissao', 'listar']],
        'permissao.definir'       => ['POST', 'admin',    ['AjaxPermissao', 'definir']],

        'config.listar'           => ['GET',  'ver',      ['AjaxConfig', 'listar']],
        'config.salvar'           => ['POST', 'admin',    ['AjaxConfig', 'salvar']],

        'agregador.estado'        => ['GET',  'ver',      ['AjaxAgregador', 'estado']],
        'agregador.processar'     => ['POST', 'admin',    ['AjaxAgregador', 'processar']],

        'visao.kpis'              => ['GET',  'ver',      ['AjaxVisao', 'kpis']],
        'visao.curva'             => ['GET',  'ver',      ['AjaxVisao', 'curva']],
        'visao.aging'             => ['GET',  'ver',      ['AjaxVisao', 'aging']],
        'visao.safra'             => ['GET',  'ver',      ['AjaxVisao', 'safra']],
        'visao.alertas'           => ['GET',  'ver',      ['AjaxVisao', 'alertas']],
        'visao.alerta_lista'      => ['GET',  'nominal',  ['AjaxVisao', 'alertaLista']],

        'inad.resumo'             => ['GET',  'ver',      ['AjaxInad', 'resumo']],
        'inad.lista'              => ['GET',  'nominal',  ['AjaxInad', 'lista']],
        'inad.dimensao'           => ['GET',  'ver',      ['AjaxInad', 'dimensao']],
        'inad.distribuicao'       => ['GET',  'ver',      ['AjaxInad', 'distribuicao']],
        'inad.opcoes'             => ['GET',  'ver',      ['AjaxInad', 'opcoes']],
        'inad.exportar'           => ['POST', 'exportar', ['AjaxInad', 'exportar']],

        'rec.kpis'                => ['GET',  'ver',      ['AjaxRec', 'kpis']],
        'rec.dia'                 => ['GET',  'ver',      ['AjaxRec', 'dia']],
        'rec.mensal'              => ['GET',  'ver',      ['AjaxRec', 'mensal']],
        'rec.coletor'             => ['GET',  'ver',      ['AjaxRec', 'coletor']],
        'rec.pagamentos'          => ['GET',  'nominal',  ['AjaxRec', 'pagamentos']],

        'cart.kpis'               => ['GET',  'ver',      ['AjaxCart', 'kpis']],
        'cart.movimento'          => ['GET',  'ver',      ['AjaxCart', 'movimento']],
        'cart.planos'             => ['GET',  'ver',      ['AjaxCart', 'planos']],
        'cart.recuperacao'        => ['GET',  'ver',      ['AjaxCart', 'recuperacao']],
        'cart.recuperacao_lista'  => ['GET',  'nominal',  ['AjaxCart', 'recuperacaoLista']],
        'cart.primeira_fatura'    => ['GET',  'ver',      ['AjaxCart', 'primeiraFatura']],
        'cart.bloqueios'          => ['GET',  'ver',      ['AjaxCart', 'bloqueios']],

        'ag.cortes'               => ['GET',  'ver',      ['AjaxAgenda', 'cortes']],
        'ag.vencimentos'          => ['GET',  'ver',      ['AjaxAgenda', 'vencimentos']],
        'ag.regua'                => ['GET',  'ver',      ['AjaxAgenda', 'regua']],
        'ag.mes'                  => ['GET',  'ver',      ['AjaxAgenda', 'mes']],
        'ag.guardiao'             => ['GET',  'ver',      ['AjaxAgenda', 'guardiao']],
    ];
}
