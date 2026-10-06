<?php
/**
 * Suite 03 :: configuracao e permissoes.
 */
T::suite('Config');

Config::limparCache();
T::igual('padrao vem do codigo, sem seed', 'mensalidade', Config::get('tipos_receita'));
T::igual('tolerancia padrao', 0, Config::int('tolerancia_em_dia'));
[$a, $d] = Config::set('faixas_aging', '10,30,90', 'teste');
T::igual('set devolve antes e depois', ['5,15,30,60,90', '10,30,90'], [$a, $d]);
Config::limparCache();
T::igual('valor novo persiste', '10,30,90', Config::get('faixas_aging'));
Config::set('faixas_aging', '5,15,30,60,90', 'teste');
T::recusa('inteiro fora da faixa', fn() => Config::set('tolerancia_em_dia', '99', 'teste'), 'PF-VAL-007');
T::recusa('chave desconhecida', fn() => Config::set('rm_rf', '1', 'teste'), 'PF-CFG-001');
T::recusa('chave interna nao se altera pela interface', fn() => Config::set('fato_dia_ate', '2026-01-01', 'teste'), 'PF-CFG-002');

$r = AjaxConfig::salvar(['valores' => ['tolerancia_em_dia' => '0', 'cache_min' => '7']]);
T::igual('salvar informa so o que mudou', ['cache_min'], $r['alteradas']);
T::igual('cache nao pede reprocessar', false, $r['reprocessar']);
$r = AjaxConfig::salvar(['valores' => ['tolerancia_em_dia' => '2']]);
T::igual('mudar a tolerancia pede reprocessar', true, $r['reprocessar']);
AjaxConfig::salvar(['valores' => ['tolerancia_em_dia' => '0', 'cache_min' => '5']]);
T::recusa('lote com valor invalido nao grava nenhum',
    fn() => AjaxConfig::salvar(['valores' => ['cache_min' => '9', 'faixas_aging' => 'x']]), 'PF-VAL-011');
Config::limparCache();
T::igual('cache_min nao foi gravado', 5, Config::int('cache_min'));

T::suite('Permissao');

Permissao::esquecer();
T::igual('sem papel, nao ve nada', false, Permissao::tem('ver'));
Permissao::assumirAdmin('teste');
T::igual('primeiro admin', true, Permissao::tem('admin'));
T::recusa('segundo "primeiro admin" e recusado', fn() => Permissao::assumirAdmin('operador'), 'PF-AUTH-004');
Permissao::definir('operador', ['ver'], 'teste');
T::igual('papel ver: ve agregados', true, Permissao::tem('ver', 'operador'));
T::igual('papel ver: NAO ve nomes', false, Permissao::tem('nominal', 'operador'));
T::igual('papel ver: NAO exporta', false, Permissao::tem('exportar', 'operador'));
T::recusa('nao deixa o addon sem admin', fn() => Permissao::definir('teste', ['ver'], 'teste'), 'PF-AUTH-005');
T::igual('papel inventado e descartado', ['ver'], Permissao::definir('operador', ['ver', 'root'], 'teste')[1]);
foreach (Rotas::MAPA as $acao => [$metodo, $perm]) {
    if (in_array($acao, ['inad.lista', 'visao.alerta_lista'], true)) {
        T::igual("rota $acao exige lista nominal", 'nominal', $perm);
    }
    if ($metodo === 'POST' && $acao !== 'permissao.assumir_admin') {
        T::certo("rota de escrita $acao nao e aberta a qualquer logado", $perm !== 'logado' && $perm !== 'ver');
    }
}
