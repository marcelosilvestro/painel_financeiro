<?php
/**
 * painel_financeiro :: catalogo de codigos de erro estaveis.
 *
 * O codigo e contrato: interface, logs e testes dependem dele, nunca do texto.
 * A mensagem e a que o USUARIO ve — sem SQL, sem nome de tabela, sem senha, sem stack trace.
 */
final class Erros
{
    public const MENSAGENS = [
        // Sessao e acesso
        'PF-AUTH-001' => 'Sessão expirada.',
        'PF-AUTH-002' => 'Você não tem permissão para esta operação.',
        'PF-AUTH-003' => 'Requisição inválida (token de segurança).',
        'PF-AUTH-004' => 'O administrador do addon já foi definido.',
        'PF-AUTH-005' => 'O addon precisa ter ao menos um administrador.',
        'PF-AUTH-006' => 'Login não encontrado entre os usuários do MK-AUTH.',

        // Validacao de entrada
        'PF-VAL-001' => 'Mês de referência inválido (use AAAA-MM).',
        'PF-VAL-002' => 'Data inválida (use AAAA-MM-DD).',
        'PF-VAL-005' => 'Login inválido.',
        'PF-VAL-007' => 'Valor numérico fora da faixa permitida.',
        'PF-VAL-008' => 'Campo obrigatório não preenchido.',
        'PF-VAL-009' => 'Texto contém caracteres não permitidos.',
        'PF-VAL-010' => 'Filtro desconhecido.',
        'PF-VAL-011' => 'Lista de faixas inválida: use números crescentes separados por vírgula (ex.: 5,15,30,60,90).',
        'PF-VAL-012' => 'Lista inválida: use palavras separadas por vírgula.',

        // Configuracao
        'PF-CFG-001' => 'Configuração desconhecida.',
        'PF-CFG-002' => 'Esta configuração é interna e não pode ser alterada pela interface.',

        // Agregador
        'PF-AGR-001' => 'O processamento dos indicadores já está rodando. Aguarde terminar.',
        'PF-AGR-002' => 'O processamento dos indicadores falhou. Veja o log do addon.',

        // Sistema
        'PF-SYS-001' => 'Erro interno. A ocorrência foi registrada no log do addon.',
        'PF-SYS-002' => 'Valor inválido.',
        'PF-SYS-003' => 'Operação desconhecida.',
        'PF-SYS-004' => 'Método HTTP não permitido para esta operação.',
        'PF-SYS-005' => 'O addon não encontrou a configuração de acesso ao banco. Rode o instalador.',
        'PF-SYS-006' => 'O banco do addon não está instalado. Rode o instalador.',
        'PF-SYS-007' => 'A consulta demorou demais e foi interrompida. Tente um filtro menor.',
    ];

    public static function mensagem(string $code): string
    {
        return self::MENSAGENS[$code] ?? 'Erro.';
    }

    public static function existe(string $code): bool
    {
        return isset(self::MENSAGENS[$code]);
    }
}
