<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user   = auth();
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
if ($body) $action = $body['action'] ?? $action;

switch ($action) {

    case 'list':
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $sql    = 'SELECT c.*, u.nome as operador_nome FROM clientes c LEFT JOIN usuarios u ON u.id=c.operador_id WHERE 1=1';
        $params = [];
        if ($search) { $sql .= ' AND (c.nome LIKE ? OR c.cpf_cnpj LIKE ? OR c.whatsapp LIKE ?)'; $p = "%$search%"; $params = array_merge($params, [$p, $p, $p]); }
        if ($status) { $sql .= ' AND c.status_cobranca=?'; $params[] = $status; }
        $sql .= ' ORDER BY c.id DESC LIMIT 200';
        $rows  = DB::fetchAll($sql, $params);
        $total = DB::fetchOne('SELECT COUNT(*) n FROM clientes')['n'] ?? 0;
        jsonOk(['clientes' => $rows, 'total' => $total]);

    case 'create':
        if (empty($body['nome']) || empty($body['whatsapp'])) jsonError('Nome e WhatsApp são obrigatórios');
        $id = DB::insert(
            'INSERT INTO clientes (nome,cpf_cnpj,telefone,whatsapp,email,produto,valor_divida,data_vencimento,cidade,estado,cep,endereco,status_cobranca,observacoes,operador_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $body['nome'], $body['cpf_cnpj'] ?? null, $body['telefone'] ?? null, $body['whatsapp'],
                $body['email'] ?? null, $body['produto'] ?? null,
                (float)($body['valor_divida'] ?? 0), $body['data_vencimento'] ?: null,
                $body['cidade'] ?? null, $body['estado'] ?? null, $body['cep'] ?? null, $body['endereco'] ?? null,
                $body['status_cobranca'] ?? 'Pendente', $body['observacoes'] ?? null, $user['id'],
            ]
        );
        logAudit($user['id'], $user['login'], 'Criou cliente', $body['nome']);
        jsonOk(['id' => $id]);

    case 'update':
        $id = (int)($body['id'] ?? 0);
        if (!$id) jsonError('ID inválido');
        DB::query(
            'UPDATE clientes SET nome=?,cpf_cnpj=?,telefone=?,whatsapp=?,email=?,produto=?,valor_divida=?,data_vencimento=?,cidade=?,estado=?,cep=?,endereco=?,status_cobranca=?,observacoes=? WHERE id=?',
            [
                $body['nome'], $body['cpf_cnpj'] ?? null, $body['telefone'] ?? null, $body['whatsapp'],
                $body['email'] ?? null, $body['produto'] ?? null,
                (float)($body['valor_divida'] ?? 0), $body['data_vencimento'] ?: null,
                $body['cidade'] ?? null, $body['estado'] ?? null, $body['cep'] ?? null, $body['endereco'] ?? null,
                $body['status_cobranca'] ?? 'Pendente', $body['observacoes'] ?? null, $id,
            ]
        );
        logAudit($user['id'], $user['login'], 'Editou cliente', "ID $id");
        jsonOk();

    case 'update_status':
        $id     = (int)($body['id'] ?? 0);
        $status = $body['status'] ?? '';
        $allowed = ['Pendente','Em negociacao','Pago','Judicial','Equipamento retirado','Sem Contato'];
        if (!$id || !in_array($status, $allowed)) jsonError('Dados inválidos');
        DB::query('UPDATE clientes SET status_cobranca=? WHERE id=?', [$status, $id]);
        logAudit($user['id'], $user['login'], 'Status cliente', "ID $id → $status");
        jsonOk();

    case 'delete':
        if (!nivel($user, ['MASTER','ADMIN'])) jsonError('Sem permissão', 403);
        $id = (int)($body['id'] ?? 0);
        DB::query('DELETE FROM clientes WHERE id=?', [$id]);
        logAudit($user['id'], $user['login'], 'Excluiu cliente', "ID $id");
        jsonOk();

    case 'import':
        $rows    = $body['rows'] ?? [];
        $imported = 0;
        foreach ($rows as $r) {
            if (empty($r['nome']) || empty($r['whatsapp'])) continue;
            $whats = preg_replace('/\D/', '', $r['whatsapp']);
            // Verificar se já existe
            $exists = DB::fetchOne('SELECT id FROM clientes WHERE whatsapp=?', [$whats]);
            if ($exists) continue;
            DB::insert(
                'INSERT INTO clientes (nome,whatsapp,cpf_cnpj,produto,valor_divida,data_vencimento,operador_id) VALUES (?,?,?,?,?,?,?)',
                [
                    $r['nome'], $whats, $r['cpf_cnpj'] ?? null, $r['produto'] ?? null,
                    (float)($r['valor_divida'] ?? 0), $r['data_vencimento'] ?: null, $user['id'],
                ]
            );
            $imported++;
        }
        logAudit($user['id'], $user['login'], 'Importou clientes', "$imported registros");
        jsonOk(['imported' => $imported]);

    default:
        jsonError('Ação inválida');
}
