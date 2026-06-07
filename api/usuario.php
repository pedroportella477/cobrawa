<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

switch ($action) {
    case 'update_profile':
        $nome   = trim($body['nome'] ?? '');
        $email  = trim($body['email'] ?? '');
        $status = $body['status_operador'] ?? 'online';
        $allowed = ['online','ausente','almoco','cafe','offline'];
        if (!in_array($status, $allowed)) $status = 'online';
        $sets   = ['nome=?', 'email=?', 'status_operador=?'];
        $params = [$nome, $email, $status];
        if (!empty($body['senha'])) {
            $sets[]   = 'senha=?';
            $params[] = password_hash($body['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        $params[] = $user['id'];
        DB::query('UPDATE usuarios SET ' . implode(',', $sets) . ' WHERE id=?', $params);
        logAudit($user['id'], $user['login'], 'Atualizou perfil', '');
        jsonOk();

    case 'set_status':
        $status  = $body['status'] ?? 'online';
        $allowed = ['online','ausente','almoco','cafe','offline'];
        if (!in_array($status, $allowed)) jsonError('Status inválido');
        DB::query('UPDATE usuarios SET status_operador=? WHERE id=?', [$status, $user['id']]);
        jsonOk();

    default:
        jsonError('Ação inválida');
}
