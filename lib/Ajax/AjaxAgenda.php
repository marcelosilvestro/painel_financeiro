<?php
/**
 * painel_financeiro :: tela Agenda de cobranca.
 */
final class AjaxAgenda
{
    public static function cortes(array $e): array
    {
        $r = Agenda::cortes(Validar::inteiroOpc($e['dias'] ?? null, 1, 31, 7));
        // quem nao tem o papel nominal ve so quantos e quanto, sem os nomes
        if (!Permissao::tem('nominal')) {
            foreach ($r['dias'] as &$d) {
                $d['lista'] = [];
            }
            unset($d);
            $r['sem_nomes'] = true;
        }
        return $r;
    }

    public static function vencimentos(array $e): array
    {
        return Agenda::vencimentos(Validar::inteiroOpc($e['dias'] ?? null, 1, 31, 7));
    }

    public static function regua(array $e): array
    {
        return Agenda::regua() + ['efetividade' => Agenda::efetividade(Validar::inteiroOpc($e['dias'] ?? null, 7, 365, 90))];
    }


    /** Calendario do mes. Sem o papel nominal, ninguem recebe nome de cliente (aqui nao ha). */
    public static function mes(array $e): array
    {
        [$mes] = AjaxVisao::periodo($e);
        [$a, $m] = array_map('intval', explode('-', $mes));
        $r = Agenda::mes($a, $m);
        $r['proximos_feriados'] = Agenda::proximosFeriados(6);
        $r['calendario_instalado'] = Agenda::calendarioInstalado();
        // A Agenda e so para consulta: feriado se muda no Calendario Geral, corte pelo guardiao.
        $r['pode'] = ['nominal' => Permissao::tem('nominal')];
        return $r;
    }

    public static function guardiao(array $e): array
    {
        return GuardiaoCorte::estado();
    }
}
