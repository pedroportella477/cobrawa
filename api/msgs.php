<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
if ($body) $action = $body['action'] ?? $action;

switch ($action) {
    case 'list':
        $rows = DB::fetchAll('SELECT m.*, u.nome as criador FROM msgs_prontas m LEFT JOIN usuarios u ON u.id=m.criado_por WHERE m.ativo=1 ORDER BY m.categoria, m.titulo');
        jsonOk($rows);

    case 'create':
        $titulo = trim($body['titulo'] ?? '');
        $corpo  = trim($body['corpo']  ?? '');
        if (!$titulo || !$corpo) jsonError('Preencha título e corpo');
        $id = DB::insert('INSERT INTO msgs_prontas (titulo, categoria, corpo, criado_por) VALUES (?,?,?,?)',
            [$titulo, $body['categoria'] ?? 'Geral', $corpo, $user['id']]);
        logAudit($user['id'], $user['login'], 'Criou msg pronta', $titulo);
        jsonOk(['id' => $id]);

    case 'update':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('ID inválido');
        DB::query('UPDATE msgs_prontas SET titulo=?, categoria=?, corpo=? WHERE id=?',
            [trim($body['titulo']), $body['categoria'] ?? 'Geral', trim($body['corpo']), $id]);
        logAudit($user['id'], $user['login'], 'Editou msg pronta', "ID $id");
        jsonOk();

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        DB::query('UPDATE msgs_prontas SET ativo=0 WHERE id=?', [$id]);
        logAudit($user['id'], $user['login'], 'Removeu msg pronta', "ID $id");
        jsonOk();

    default:
        jsonError('Ação inválida');
}
