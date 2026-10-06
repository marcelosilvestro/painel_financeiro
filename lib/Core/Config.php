<?php
/**
 * painel_financeiro :: configuracao geral, chave/valor com tipo e faixa.
 *
 * Os padroes vivem AQUI, nao no banco: uma instalacao nova funciona sem seed, e uma
 * atualizacao que muda um padrao vale para quem nunca alterou aquele valor.
 * tab_pfin_config guarda so o que o administrador mudou e os valores internos.
 */
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Validar.php';
require_once __DIR__ . '/PfErro.php';

final class Config
{
    /**
     * tipo: bool | int | enum | faixas | palavras | texto.  grupo: agrupamento na tela.
     * Chaves com 'interno' => true nao aparecem nem sao alteraveis pela interface.
     *
     * Nada aqui e regra de UMA empresa: dias de vencimento, dias de corte e forma de bloqueio
     * sao lidos do proprio MK-AUTH (sis_opcao) por Parametros. Aqui fica so o que o MK-AUTH
     * nao sabe responder — como cada provedor quer LER os proprios numeros.
     */
    public const DEFINICOES = [
        'tipos_receita' => ['tipo' => 'palavras', 'padrao' => 'mensalidade', 'grupo' => 'Financeiro',
            'rotulo' => 'Tipos de título da receita recorrente',
            'ajuda' => 'Valores de sis_lanc.tipo que entram em faturamento, inadimplência e safra. Os demais (serviços, outros) ficam fora desses indicadores.'],
        'tolerancia_em_dia' => ['tipo' => 'int', 'padrao' => '0', 'min' => 0, 'max' => 10, 'grupo' => 'Cobrança',
            'rotulo' => 'Tolerância para "pago em dia" (dias)',
            'ajuda' => 'Dias após o vencimento efetivo (já empurrado para o próximo dia útil) em que o pagamento ainda conta como em dia.'],
        'faixas_aging' => ['tipo' => 'faixas', 'padrao' => '5,15,30,60,90', 'grupo' => 'Cobrança',
            'rotulo' => 'Faixas de atraso (dias)',
            'ajuda' => 'Limites das faixas do gráfico de atraso. "5,15,30,60,90" gera 1-5, 6-15, 16-30, 31-60, 61-90 e +90.'],
        'reincidencia_n' => ['tipo' => 'int', 'padrao' => '3', 'min' => 2, 'max' => 12, 'grupo' => 'Cobrança',
            'rotulo' => 'Reincidente: atrasos',
            'ajuda' => 'Quantos títulos pagos com atraso (ou não pagos) marcam o cliente como reincidente.'],
        'reincidencia_m' => ['tipo' => 'int', 'padrao' => '6', 'min' => 3, 'max' => 24, 'grupo' => 'Cobrança',
            'rotulo' => 'Reincidente: janela (títulos)',
            'ajuda' => 'Entre os últimos N títulos vencidos do cliente.'],
        'guardiao_feriado' => ['tipo' => 'bool', 'padrao' => '0', 'grupo' => 'Cobrança',
            'rotulo' => 'Guardião de feriado: suspender o corte do MK-AUTH nos feriados',
            'ajuda' => 'Ligado: em dia de feriado (Calendário de Feriados) o addon desliga o "corte automático" do MK-AUTH e religa no dia seguinte, quando ele corta quem ficou para trás. Ninguém precisa mexer no dias_corte dos clientes. Grava só a opção auto_corte, com auditoria.'],
        'considerar_feriados' => ['tipo' => 'bool', 'padrao' => '0', 'grupo' => 'Cobrança',
            'rotulo' => 'Feriado empurra o vencimento efetivo',
            'ajuda' => 'Ligado: vencimento num feriado cadastrado conta como pago em dia no próximo dia útil (fim de semana sempre empurra).'],

        'dias_recalculo_caixa' => ['tipo' => 'int', 'padrao' => '400', 'min' => 31, 'max' => 3650, 'grupo' => 'Desempenho',
            'rotulo' => 'Recebimentos: dias recalculados por noite',
            'ajuda' => 'Quantos dias para trás o processamento noturno refaz. Pagamento lançado com data mais antiga que isso só entra num reprocessamento completo.'],
        'meses_recalculo_safra' => ['tipo' => 'int', 'padrao' => '15', 'min' => 3, 'max' => 60, 'grupo' => 'Desempenho',
            'rotulo' => 'Safras: meses recalculados por noite',
            'ajuda' => 'Meses de vencimento refeitos a cada processamento (o restante do histórico não muda, salvo exclusão de título).'],
        'cache_min' => ['tipo' => 'int', 'padrao' => '5', 'min' => 0, 'max' => 120, 'grupo' => 'Desempenho',
            'rotulo' => 'Cache dos indicadores ao vivo (min)',
            'ajuda' => '0 desliga. Indicadores que leem a base inteira são guardados por esse tempo.'],
        'limite_consulta_s' => ['tipo' => 'int', 'padrao' => '15', 'min' => 2, 'max' => 120, 'grupo' => 'Desempenho',
            'rotulo' => 'Tempo máximo de uma consulta (s)',
            'ajuda' => 'Consultas da tela que passarem disso são interrompidas para não pesar no MK-AUTH.'],

        'linhas_por_pagina' => ['tipo' => 'int', 'padrao' => '50', 'min' => 10, 'max' => 200, 'grupo' => 'Tela',
            'rotulo' => 'Linhas por página nas listas', 'ajuda' => 'Listas são sempre paginadas no servidor.'],

        // Internos
        'admin_definido_em' => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
        'fato_dia_ate'      => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
        'fato_hoje_em'      => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
        'fato_versao'       => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
        'guardiao_desligou_em' => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
        'guardiao_visto_em' => ['tipo' => 'texto', 'padrao' => '', 'interno' => true],
    ];

    private static array $cache = [];
    private static bool $carregado = false;

    public static function get(string $chave): string
    {
        if (!isset(self::DEFINICOES[$chave])) {
            throw new PfErro('PF-CFG-001', ['chave' => $chave]);
        }
        self::carregar();
        $v = self::$cache[$chave] ?? null;
        return ($v === null || $v === '') ? (string) self::DEFINICOES[$chave]['padrao'] : (string) $v;
    }

    public static function int(string $chave): int
    {
        return (int) self::get($chave);
    }

    public static function ligado(string $chave): bool
    {
        return self::get($chave) === '1';
    }

    /**
     * Grava um valor validado. Devolve [antes, depois] para a auditoria de quem chamou.
     * @return array{0:string,1:string}
     */
    public static function set(string $chave, $valor, string $usuario, bool $permitirInterno = false): array
    {
        $def = self::DEFINICOES[$chave] ?? null;
        if ($def === null) {
            throw new PfErro('PF-CFG-001', ['chave' => $chave]);
        }
        if (!empty($def['interno']) && !$permitirInterno) {
            throw new PfErro('PF-CFG-002', ['chave' => $chave]);
        }
        $novo = self::normalizar($def, $valor);
        $antes = self::get($chave);

        Db::exec(
            'INSERT INTO tab_pfin_config (chave, valor, alterado_por, alterado_em)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), alterado_por = VALUES(alterado_por), alterado_em = NOW()',
            [$chave, $novo, $usuario]
        );
        self::$cache[$chave] = $novo;
        return [$antes, $novo];
    }

    /** Tudo o que a tela de configuracoes mostra, agrupado, com o valor efetivo. */
    public static function paraTela(): array
    {
        $saida = [];
        foreach (self::DEFINICOES as $chave => $def) {
            if (!empty($def['interno'])) {
                continue;
            }
            $saida[] = [
                'chave'  => $chave,
                'grupo'  => $def['grupo'],
                'tipo'   => $def['tipo'],
                'rotulo' => $def['rotulo'],
                'ajuda'  => $def['ajuda'],
                'min'    => $def['min'] ?? null,
                'max'    => $def['max'] ?? null,
                'opcoes' => $def['opcoes'] ?? null,
                'padrao' => $def['padrao'],
                'valor'  => self::get($chave),
            ];
        }
        return $saida;
    }

    public static function limparCache(): void
    {
        self::$cache = [];
        self::$carregado = false;
    }

    private static function carregar(): void
    {
        if (self::$carregado) {
            return;
        }
        foreach (Db::todos('SELECT chave, valor FROM tab_pfin_config') as $r) {
            self::$cache[$r['chave']] = $r['valor'];
        }
        self::$carregado = true;
    }

    private static function normalizar(array $def, $valor): string
    {
        switch ($def['tipo']) {
            case 'bool':
                return Validar::bool($valor) ? '1' : '0';
            case 'int':
                return (string) Validar::inteiro($valor, (int) $def['min'], (int) $def['max']);
            case 'faixas':
                return Validar::faixas($valor);
            case 'palavras':
                return Validar::listaPalavras($valor);
            case 'enum':
                if (!in_array((string) $valor, $def['opcoes'], true)) {
                    throw new PfErro('PF-SYS-002', ['opcoes' => $def['opcoes']]);
                }
                return (string) $valor;
            default:
                return Validar::texto($valor, 255);
        }
    }
}
