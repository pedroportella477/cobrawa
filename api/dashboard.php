<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = auth();
$action = $_GET['action'] ?? 'full';

if ($action === 'badges') {
    $unread = DB::fetchOne('SELECT COUNT(*) as n FROM mensagens WHERE lido=0 AND direcao="recebida"')['n'] ?? 0;
    $alerts = DB::fetchOne('SELECT COUNT(*) as n FROM alertas WHERE lido=0 AND (destinatario_id=? OR destinatario_id IS NULL)', [$user['id']])['n'] ?? 0;
    jsonOk(['unread' => $unread, 'alerts' => $alerts]);
}

$total_clientes  = DB::fetchOne('SELECT COUNT(*) n FROM clientes WHERE status_cobranca != "Pago"')['n'] ?? 0;
$conv_abertas    = DB::fetchOne('SELECT COUNT(*) n FROM conversas WHERE status="aberta"')['n'] ?? 0;
$ops_online      = DB::fetchOne('SELECT COUNT(*) n FROM usuarios WHERE status_operador="online" AND ativo=1')['n'] ?? 0;
$msgs_enviadas   = DB::fetchOne('SELECT COUNT(*) n FROM mensagens WHERE direcao="enviada" AND DATE(created_at)=CURDATE()')['n'] ?? 0;
$msgs_recebidas  = DB::fetchOne('SELECT COUNT(*) n FROM mensagens WHERE direcao="recebida" AND DATE(created_at)=CURDATE()')['n'] ?? 0;
$acordos         = DB::fetchOne('SELECT COUNT(*) n FROM clientes WHERE status_cobranca="Pago" AND DATE(updated_at)=CURDATE()')['n'] ?? 0;

$status_counts = DB::fetchAll('SELECT status_cobranca, COUNT(*) total FROM clientes GROUP BY status_cobranca ORDER BY total DESC');
$ops_status    = DB::fetchAll('SELECT status_operador, COUNT(*) qtd FROM usuarios WHERE ativo=1 GROUP BY status_operador');

$atividade = DB::fetchAll('
    SELECT l.created_at, l.acao, l.usuario_login as operador,
           COALESCE(cl.nome, "") as cliente,
           COALESCE(c.protocolo, "") as protocolo
    FROM logs l
    LEFT JOIN conversas c ON c.id = (SELECT id FROM conversas ORDER BY id DESC LIMIT 1)
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    ORDER BY l.id DESC LIMIT 10
');

jsonOk([
    'total_clientes'  => $total_clientes,
    'conversas_abertas' => $conv_abertas,
    'operadores_online' => $ops_online,
    'msgs_enviadas'   => $msgs_enviadas,
    'msgs_recebidas'  => $msgs_recebidas,
    'acordos'         => $acordos,
    'status_counts'   => $status_counts,
    'ops_status'      => $ops_status,
    'atividade'       => array_map(function($a) {
        $a['created_at'] = date('d/m H:i', strtotime($a['created_at']));
        return $a;
    }, $atividade),
]);
