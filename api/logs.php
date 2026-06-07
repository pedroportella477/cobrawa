<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
if (!nivel($user, ['MASTER','ADMIN','SUPERVISOR'])) jsonError('Sem permissão', 403);
$filter = $_GET['filter'] ?? '';
$sql    = 'SELECT * FROM logs WHERE 1=1';
$params = [];
if ($filter) { $sql .= ' AND acao LIKE ?'; $params[] = "%$filter%"; }
$sql .= ' ORDER BY id DESC LIMIT 300';
$rows = DB::fetchAll($sql, $params);
foreach ($rows as &$r) $r['created_at'] = date('d/m/Y H:i:s', strtotime($r['created_at']));
jsonOk($rows);
