<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
if ($body) $action = $body['action'] ?? $action;

switch ($action) {
    case 'list':
        if (!nivel($user, ['MASTER','ADMIN'])) jsonError('Sem permissão', 403);
        $rows = DB::fetchAll('SELECT id, login, nome, email, nivel, setor, status_operador, ultimo_acesso, ativo FROM usuarios ORDER BY nivel, nome');
        jsonOk($rows);

    case 'operadores':
        $rows = DB::fetchAll('SELECT id, nome, nivel, status_operador FROM usuarios WHERE ativo=1 ORDER BY nome');
        jsonOk($rows);

    case 'create':
        if (!nivel($user, ['MASTER','ADMIN'])) jsonError('Sem permissão', 403);
        $login = trim($body['login'] ?? '');
        $nome  = trim($body['nome']  ?? '');
        $senha = $body['senha'] ?? '';
        if (!$login || !$nome || !$senha) jsonError('Preencha login, nome e senha');
        $exists = DB::fetchOne('SELECT id FROM usuarios WHERE login=?', [$login]);
        if ($exists) jsonError('Login já existe');
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $id   = DB::insert('INSERT INTO usuarios (login, senha, nome, email, nivel, setor) VALUES (?,?,?,?,?,?)',
            [$login, $hash, $nome, $body['email'] ?? null, $body['nivel'] ?? 'OPERADOR', $body['setor'] ?? null]);
        logAudit($user['id'], $user['login'], 'Criou usuário', "$login / $nome");
        jsonOk(['id' => $id]);

    case 'update':
        if (!nivel($user, ['MASTER','ADMIN'])) jsonError('Sem permissão', 403);
        $id   = (int)($body['id'] ?? 0);
        if (!$id) jsonError('ID inválido');
        $sets = ['nome=?','email=?','nivel=?','setor=?'];
        $params = [trim($body['nome']), $body['email'] ?? null, $body['nivel'] ?? 'OPERADOR', $body['setor'] ?? null];
        if (!empty($body['senha'])) {
            $sets[]   = 'senha=?';
            $params[] = password_hash($body['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        $params[] = $id;
        DB::query('UPDATE usuarios SET ' . implode(',', $sets) . ' WHERE id=?', $params);
        logAudit($user['id'], $user['login'], 'Editou usuário', "ID $id");
        jsonOk();

    case 'delete':
        if (!nivel($user, ['MASTER'])) jsonError('Sem permissão', 403);
        $id = (int)($body['id'] ?? 0);
        $u  = DB::fetchOne('SELECT nivel FROM usuarios WHERE id=?', [$id]);
        if ($u && $u['nivel'] === 'MASTER') jsonError('Não pode excluir o MASTER');
        DB::query('UPDATE usuarios SET ativo=0 WHERE id=?', [$id]);
        logAudit($user['id'], $user['login'], 'Desativou usuário', "ID $id");
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
