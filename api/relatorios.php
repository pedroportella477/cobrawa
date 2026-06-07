<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
$action = $_GET['action'] ?? 'produtividade';

switch ($action) {
    case 'produtividade':
        $rows = DB::fetchAll('
            SELECT u.nome as operador,
                   COUNT(DISTINCT c.id)  as atendimentos,
                   COUNT(m.id)           as mensagens,
                   SUM(cl.status_cobranca="Pago") as acordos,
                   SUM(CASE WHEN cl.status_cobranca="Pago" THEN cl.valor_divida ELSE 0 END) as valor
            FROM usuarios u
            LEFT JOIN conversas c  ON c.operador_id  = u.id
            LEFT JOIN mensagens m  ON m.conversa_id  = c.id AND m.direcao="enviada"
            LEFT JOIN clientes  cl ON cl.id           = c.cliente_id
            WHERE u.ativo=1
            GROUP BY u.id, u.nome
            ORDER BY atendimentos DESC
        ');
        jsonOk($rows);

    case 'clientes':
        $rows = DB::fetchAll('SELECT status_cobranca, COUNT(*) total, SUM(valor_divida) valor FROM clientes GROUP BY status_cobranca');
        jsonOk($rows);

    case 'operadores':
        $rows = DB::fetchAll('SELECT status_operador, COUNT(*) total FROM usuarios WHERE ativo=1 GROUP BY status_operador');
        jsonOk($rows);

    case 'cobrancas':
        $rows = DB::fetchAll('SELECT DATE(created_at) dia, COUNT(*) total FROM mensagens WHERE direcao="enviada" GROUP BY dia ORDER BY dia DESC LIMIT 30');
        jsonOk($rows);

    case 'conversas':
        $rows = DB::fetchAll('SELECT status, COUNT(*) total FROM conversas GROUP BY status');
        jsonOk($rows);

    default:
        jsonError('Tipo inválido');
}
