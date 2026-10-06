<?php
/**
 * painel_financeiro :: elementos fixos de toda pagina — loading, toasts e o modal de confirmacao.
 *
 * Fica DEPOIS do baixo.php, como filho direto do <body>: dentro do wrapper do addon, um
 * stacking context prende o modal abaixo do #sistema-rodape e o botao para de responder
 * ao clique (addon-mkauth-anatomia).
 */
?>
<div class="lc-loading-overlay" id="pf-loading">
    <div class="lc-loading-box">
        <div class="lc-loading-spinner"></div>
        <div class="lc-loading-text" id="pf-loading-texto">Carregando...</div>
    </div>
</div>

<div id="pf-toasts" class="pf-toasts"></div>

<div class="lc-overlay" id="pf-modal-confirma" onclick="PF.fecharSeFora(event, 'pf-modal-confirma')">
    <div class="lc-modal-box">
        <div class="lc-modal-header" id="pf-confirma-cab">
            <span class="lc-modal-title" id="pf-confirma-titulo">Confirmar</span>
            <button type="button" class="lc-modal-close" onclick="PF.fecharModal('pf-modal-confirma')">&times;</button>
        </div>
        <div class="lc-modal-body" style="text-align:center">
            <div class="lc-confirm-icon fechar" id="pf-confirma-icone"><i class="bi bi-question-lg"></i></div>
            <div class="lc-confirm-msg" id="pf-confirma-msg"></div>
            <p class="lc-confirm-sub" id="pf-confirma-sub"></p>
            <div id="pf-confirma-digitar" style="display:none;margin-top:12px;text-align:left">
                <label class="lc-label" id="pf-confirma-digitar-rotulo"></label>
                <input type="text" class="lc-input" id="pf-confirma-digitar-input" autocomplete="off">
            </div>
        </div>
        <div class="lc-modal-footer">
            <button type="button" class="lc-btn-cancel" onclick="PF.fecharModal('pf-modal-confirma')">Cancelar</button>
            <button type="button" class="lc-btn-confirm" id="pf-confirma-ok">Confirmar</button>
        </div>
    </div>
</div>

<div style="position: fixed; bottom: 10px; right: 15px; font-size: 11px; color: #9ca3af; z-index: 9999; font-family: 'Inter', sans-serif; pointer-events: none;">
    By Marcelo Silvestro
</div>
