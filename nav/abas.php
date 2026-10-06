<?php
/**
 * painel_financeiro :: barra de navegacao do addon + guarda de acesso da pagina.
 *
 * Antes de incluir, a pagina define:
 *   $pf_pagina       id da aba atual
 *   $pf_perm_pagina  papel exigido ('logado' = qualquer usuario do painel)
 *
 * Depois do include, $pf_bloqueado diz se o conteudo pode ser mostrado. A guarda aqui e de
 * conveniencia: cada operacao AJAX confere a permissao de novo no servidor.
 */
$pf_abas = [
    'visao'         => ['index.php',         'bi-speedometer2',         'Visão Geral'],
    'inadimplencia' => ['inadimplencia.php', 'bi-exclamation-diamond',  'Inadimplência'],
    'recebimentos'  => ['recebimentos.php',  'bi-cash-coin',            'Recebimentos'],
    'carteira'      => ['carteira.php',      'bi-people',               'Carteira'],
    'agenda'        => ['agenda.php',        'bi-calendar-week',        'Agenda'],
    'configuracoes' => ['configuracoes.php', 'bi-gear',                 'Configurações'],
];
// Telas ja entregues; as demais aparecem apagadas, com "em breve".
$pf_abas_prontas = array_keys($pf_abas);

$pf_bloqueado = false;
$pf_motivo = '';
if (!$pf_schema_ok) {
    $pf_bloqueado = true;
    $pf_motivo = 'schema';
} elseif (($pf_perm_pagina ?? 'ver') !== 'logado' && !Permissao::tem($pf_perm_pagina ?? 'ver')) {
    $pf_bloqueado = true;
    $pf_motivo = 'permissao';
}
?>
<nav class="pf-abas" aria-label="Seções do painel financeiro">
    <span class="pf-marca"><i class="bi bi-graph-up-arrow"></i> Painel Financeiro</span>
    <?php foreach ($pf_abas as $id => [$arq, $ico, $rot]): ?>
        <?php if (in_array($id, $pf_abas_prontas, true)): ?>
            <a href="<?= $arq ?>" class="pf-aba<?= ($pf_pagina ?? '') === $id ? ' ativa' : '' ?>"<?= ($pf_pagina ?? '') === $id ? ' aria-current="page"' : '' ?>><i class="bi <?= $ico ?>"></i> <?= $rot ?></a>
        <?php else: ?>
            <span class="pf-aba em-breve" title="<?= $rot ?> — disponível numa próxima versão"><i class="bi <?= $ico ?>"></i> <?= $rot ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php if ($pf_motivo === 'schema'): ?>
    <div class="pf-aviso erro">
        <i class="bi bi-database-exclamation"></i>
        <div><strong>O banco do addon não está instalado.</strong>
            Rode o instalador no terminal do servidor (como root) para criar as tabelas do addon.</div>
    </div>
<?php elseif ($pf_motivo === 'permissao'): ?>
    <div class="pf-aviso">
        <i class="bi bi-shield-lock"></i>
        <div><strong>Você não tem acesso a esta tela.</strong>
            Peça ao administrador do addon para liberar o seu login (<?= pf_h(Permissao::login()) ?>) em Configurações › Permissões.</div>
    </div>
<?php endif; ?>
