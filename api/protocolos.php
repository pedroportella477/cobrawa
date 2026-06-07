<?php
// ===== protocolos.php =====
require_once __DIR__ . '/../includes/bootstrap.php';
$user = auth();
$rows = DB::fetchAll('
    SELECT p.codigo, p.created_at, c.status,
           cl.nome as cliente, u.nome as operador
    FROM protocolos p
    LEFT JOIN conversas c  ON c.id  = p.conversa_id
    LEFT JOIN clientes  cl ON cl.id = p.cliente_id
    LEFT JOIN usuarios  u  ON u.id  = p.operador_id
    ORDER BY p.id DESC LIMIT 200
');
foreach ($rows as &$r) $r['created_at'] = date('d/m/Y H:i', strtotime($r['created_at']));
jsonOk($rows);
