<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    case 'list':
        $rows = DB::fetchAll('
            SELECT a.*, r.nome as remetente, d.nome as destinatario
            FROM alertas a
            LEFT JOIN usuarios r ON r.id = a.remetente_id
            LEFT JOIN usuarios d ON d.id = a.destinatario_id
            ORDER BY a.id DESC LIMIT 50
        ');
        // Carregar operadores para o select
        $ops = DB::fetchAll('SELECT id, nome FROM usuarios WHERE ativo=1 AND nivel IN ("OPERADOR","SUPERVISOR") ORDER BY nome');
        $res = jsonOk($rows);
        // Retornar também operadores junto
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'data' => $rows, 'operadores' => $ops]);
        exit;

    case 'send':
        if (!nivel($user, ['MASTER','ADMIN','SUPERVISOR'])) jsonError('Sem permissão', 403);
        $msg   = trim($body['mensagem'] ?? '');
        $dest  = !empty($body['destinatario_id']) ? (int)$body['destinatario_id'] : null;
        $prior = $body['prioridade'] ?? 'Normal';
        if (!$msg) jsonError('Mensagem vazia');
        DB::insert('INSERT INTO alertas (remetente_id, destinatario_id, mensagem, prioridade) VALUES (?,?,?,?)',
            [$user['id'], $dest, $msg, $prior]);
        logAudit($user['id'], $user['login'], 'Alerta', "Para: " . ($dest ?: 'todos'));
        jsonOk();

    case 'unread':
        $rows = DB::fetchAll(
            'SELECT * FROM alertas WHERE lido=0 AND (destinatario_id=? OR destinatario_id IS NULL) ORDER BY id DESC LIMIT 1',
            [$user['id']]
        );
        jsonOk($rows);

    case 'mark_read':
        $id = (int)($body['id'] ?? 0);
        DB::query('UPDATE alertas SET lido=1 WHERE id=?', [$id]);
        jsonOk();

    default:
        jsonError('Ação inválida');
}
