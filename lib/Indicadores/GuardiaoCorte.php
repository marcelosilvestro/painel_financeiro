<?php
/**
 * painel_financeiro :: guardiao de feriado do corte.
 *
 * O MK-AUTH nao conhece feriado: corta quem passou de vencimento + dias_corte em qualquer dia
 * da semana de sis_opcao.dias_de_corte. O corte dele e CUMULATIVO (cada rodada seleciona todo
 * cliente com TO_DAYS(NOW()) - TO_DAYS(datavenc) > dias_corte) e le sis_opcao.auto_corte antes
 * de selecionar qualquer cliente — conferido pelas consultas que o corte.php envia ao banco.
 *
 * Entao, em vez de mexer no dias_corte de cada cliente, o guardiao so:
 *   - num dia de feriado (tab_feriados), desliga o auto_corte do MK-AUTH;
 *   - no primeiro dia que nao e feriado, religa — e o MK-AUTH corta quem ficou para tras.
 * Ele so religa o que ELE desligou: se o provedor deixou o corte desligado, nao toca.
 *
 * Escrita no MK-AUTH: uma opcao (sis_opcao.auto_corte), com auditoria. Roda pelo cron a cada
 * 5 minutos (cli/guardiao.php), antes da janela do corte do MK-AUTH (08h-22h).
 */
final class GuardiaoCorte
{
    public static function ligado(): bool
    {
        return Config::ligado('guardiao_feriado');
    }

    public static function feriadoHoje(): ?array
    {
        if (!Db::tabelaExiste('tab_feriados')) {
            return null;
        }
        return Db::um("SELECT data, nome, abrangencia FROM tab_feriados WHERE tipo = 'feriado' AND data = CURDATE()");
    }

    private static function autoCorte(): string
    {
        return strtolower(trim((string) Db::valor("SELECT valor FROM sis_opcao WHERE nome = 'auto_corte' LIMIT 1")));
    }

    private static function gravarAutoCorte(string $valor): void
    {
        Db::exec("UPDATE sis_opcao SET valor = ? WHERE nome = 'auto_corte'", [$valor]);
        Parametros::esquecer();
    }

    /**
     * Um ciclo do guardiao. Idempotente: rodar varias vezes no mesmo estado nao faz nada.
     * @return array{acao:string,motivo:string}
     */
    public static function executar(string $usuario = 'cron'): array
    {
        if (!Db::travar('painel_financeiro.guardiao', 0)) {
            return ['acao' => 'nenhuma', 'motivo' => 'outra execucao em andamento'];
        }
        try {
            Config::set('guardiao_visto_em', (string) Db::valor('SELECT NOW()'), $usuario, true);
            $desligouEm = Config::get('guardiao_desligou_em');
            $auto = self::autoCorte();
            $feriado = self::feriadoHoje();

            // 1. Guardiao ligado e hoje e feriado: suspende o corte (se estava ligado).
            if (self::ligado() && $feriado) {
                if ($auto === 'sim') {
                    self::gravarAutoCorte('nao');
                    Config::set('guardiao_desligou_em', $feriado['data'], $usuario, true);
                    Auditoria::registrar('guardiao_suspender', 'auto_corte', null, ['auto_corte' => 'sim'],
                        ['auto_corte' => 'nao', 'feriado' => $feriado['nome'], 'data' => $feriado['data']]);
                    Log::info('guardiao.suspendeu', ['feriado' => $feriado['nome']]);
                    return ['acao' => 'suspendeu', 'motivo' => 'feriado: ' . $feriado['nome']];
                }
                return ['acao' => 'nenhuma', 'motivo' => $desligouEm !== '' ? 'corte ja suspenso pelo guardiao' : 'corte ja estava desligado pelo provedor'];
            }

            // 2. Nao e feriado (ou o guardiao foi desligado): devolve o que ELE desligou.
            if ($desligouEm !== '') {
                if ($auto === 'nao') {
                    self::gravarAutoCorte('sim');
                }
                Config::set('guardiao_desligou_em', '', $usuario, true);
                Auditoria::registrar('guardiao_religar', 'auto_corte', null, ['auto_corte' => $auto],
                    ['auto_corte' => 'sim', 'suspenso_desde' => $desligouEm]);
                Log::info('guardiao.religou', ['suspenso_desde' => $desligouEm]);
                return ['acao' => 'religou', 'motivo' => 'fim do feriado de ' . $desligouEm];
            }
            return ['acao' => 'nenhuma', 'motivo' => $feriado ? 'guardiao desligado' : 'dia normal'];
        } finally {
            Db::destravar('painel_financeiro.guardiao');
        }
    }

    /** Situacao para a tela, o diagnostico e os alertas. */
    public static function estado(): array
    {
        $visto = Config::get('guardiao_visto_em');
        $idadeMin = $visto !== '' ? (int) Db::valor('SELECT TIMESTAMPDIFF(MINUTE, ?, NOW())', [$visto]) : null;
        $desligouEm = Config::get('guardiao_desligou_em');
        $feriado = self::feriadoHoje();
        $auto = self::autoCorte();
        $ligado = self::ligado();
        $problema = null;
        if ($ligado && ($idadeMin === null || $idadeMin > 20)) {
            $problema = 'O guardião não roda há ' . ($idadeMin === null ? 'muito tempo' : $idadeMin . ' min') . ': confira o cron do addon.';
        } elseif ($desligouEm !== '' && !$feriado) {
            $problema = 'O corte do MK-AUTH continua suspenso pelo guardião fora de feriado (desde ' . $desligouEm . ').';
        } elseif ($ligado && $feriado && $auto === 'sim' && $desligouEm !== '') {
            $problema = 'Hoje é feriado, mas o corte do MK-AUTH foi religado (alguém salvou as Opções?). O guardião suspende de novo em até 5 min.';
        }
        return ['ligado' => $ligado, 'auto_corte' => $auto, 'feriado_hoje' => $feriado, 'suspenso_desde' => $desligouEm ?: null,
                'visto_em' => $visto ?: null, 'idade_min' => $idadeMin, 'problema' => $problema];
    }

    /** Datas em que o corte NAO roda (para a projecao), quando o guardiao esta ligado. @return string[] */
    public static function datasSemCorte(string $de, string $ate): array
    {
        if (!self::ligado()) {
            return [];
        }
        return array_keys(Agenda::feriadosEntre($de, $ate));
    }
}
