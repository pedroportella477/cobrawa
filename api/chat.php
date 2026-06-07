<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/waha.php';
$user   = auth();
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
if ($body) $action = $body['action'] ?? $action;

function fmtMsg(array $m): array {
    $m['hora'] = date('H:i', strtotime($m['created_at']));
    return $m;
}

switch ($action) {

    case 'contacts':
        $search = $_GET['search'] ?? '';
        $sql = '
            SELECT cl.*, 
                   (SELECT conteudo FROM mensagens WHERE cliente_id=cl.id ORDER BY id DESC LIMIT 1) AS ultima_msg,
                   (SELECT DATE_FORMAT(created_at,"%H:%i") FROM mensagens WHERE cliente_id=cl.id ORDER BY id DESC LIMIT 1) AS ultima_hora,
                   (SELECT COUNT(*) FROM mensagens WHERE cliente_id=cl.id AND direcao="recebida" AND lido=0) AS nao_lidas
            FROM clientes cl
            WHERE 1=1
        ';
        $params = [];
        if ($search) { $sql .= ' AND (cl.nome LIKE ? OR cl.whatsapp LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= ' ORDER BY (SELECT id FROM mensagens WHERE cliente_id=cl.id ORDER BY id DESC LIMIT 1) DESC LIMIT 60';
        jsonOk(DB::fetchAll($sql, $params));

    case 'get_client':
        $id = (int)($_GET['id'] ?? 0);
        $c  = DB::fetchOne('SELECT * FROM clientes WHERE id=?', [$id]);
        if (!$c) jsonError('Cliente não encontrado', 404);
        jsonOk(['cliente' => $c]);

    case 'get_or_create_conversa':
        $cid = (int)($body['cliente_id'] ?? 0);
        if (!$cid) jsonError('Cliente inválido');
        $conv = DB::fetchOne('SELECT * FROM conversas WHERE cliente_id=? AND status="aberta" ORDER BY id DESC LIMIT 1', [$cid]);
        if (!$conv) {
            $proto = 'PRO' . date('ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $id = DB::insert('INSERT INTO conversas (cliente_id, operador_id, protocolo, status) VALUES (?,?,?,"aberta")',
                [$cid, $user['id'], $proto]);
            DB::insert('INSERT INTO protocolos (codigo, conversa_id, cliente_id, operador_id) VALUES (?,?,?,?)',
                [$proto, $id, $cid, $user['id']]);
            $conv = DB::fetchOne('SELECT * FROM conversas WHERE id=?', [$id]);
        }
        jsonOk($conv);

    case 'messages':
        $convId = (int)($_GET['conversa_id'] ?? 0);
        $after  = (int)($_GET['after'] ?? 0);
        if (!$convId) jsonError('Conversa inválida');
        $msgs = DB::fetchAll(
            'SELECT m.*, u.nome as operador_nome FROM mensagens m LEFT JOIN usuarios u ON u.id=m.enviado_por WHERE m.conversa_id=? AND m.id>? ORDER BY m.id ASC LIMIT 100',
            [$convId, $after]
        );
        jsonOk(array_map('fmtMsg', $msgs));

    case 'send':
        $convId  = (int)($body['conversa_id'] ?? 0);
        $cliId   = (int)($body['cliente_id'] ?? 0);
        $whats   = $body['whatsapp'] ?? '';
        $texto   = $body['conteudo'] ?? '';
        $tipo    = $body['tipo'] ?? 'texto';
        $arquivo = $body['arquivo_url'] ?? null;
        if (!$convId || !$cliId) jsonError('Dados inválidos');

        // Salvar no banco
        $mid = DB::insert(
            'INSERT INTO mensagens (conversa_id, cliente_id, direcao, tipo, conteudo, arquivo_url, lido, enviado_por) VALUES (?,?,?,?,?,?,1,?)',
            [$convId, $cliId, 'enviada', $tipo, $texto, $arquivo, $user['id']]
        );
        // Enviar via WAHA
        if ($whats) {
            try {
                $waha = new Waha();
                if ($tipo === 'audio' && $arquivo) $waha->sendAudio($whats, $arquivo);
                elseif (in_array($tipo, ['imagem','documento']) && $arquivo) $waha->sendFile($whats, $arquivo, $texto);
                else $waha->sendText($whats, $texto);
            } catch (Exception $e) {}
        }
        logAudit($user['id'], $user['login'], 'Mensagem', "Conv $convId → $whats");
        $msg = DB::fetchOne('SELECT * FROM mensagens WHERE id=?', [$mid]);
        jsonOk(fmtMsg($msg));

    case 'assumir':
        $cid = (int)($body['conversa_id'] ?? 0);
        DB::query('UPDATE conversas SET operador_id=?, status="aberta" WHERE id=?', [$user['id'], $cid]);
        logAudit($user['id'], $user['login'], 'Assumiu atendimento', "Conv $cid");
        jsonOk();

    case 'transfer':
        $cid    = (int)($body['conversa_id'] ?? 0);
        $destId = (int)($body['operador_destino_id'] ?? 0);
        $motivo = $body['motivo'] ?? '';
        $conv   = DB::fetchOne('SELECT * FROM conversas WHERE id=?', [$cid]);
        if (!$conv) jsonError('Conversa não encontrada');
        DB::query('UPDATE conversas SET operador_id=?, status="transferida" WHERE id=?', [$destId, $cid]);
        DB::insert('INSERT INTO transferencias (conversa_id, cliente_id, operador_origem_id, operador_destino_id, motivo) VALUES (?,?,?,?,?)',
            [$cid, $conv['cliente_id'], $user['id'], $destId, $motivo]);
        logAudit($user['id'], $user['login'], 'Transferência', "Conv $cid → Op $destId");
        jsonOk();

    case 'mark_seen':
        $whats = $body['whatsapp'] ?? '';
        $cliId = (int)($body['cliente_id'] ?? 0);
        if ($cliId) DB::query('UPDATE mensagens SET lido=1 WHERE cliente_id=? AND direcao="recebida"', [$cliId]);
        if ($whats) {
            try { (new Waha())->markSeen($whats); } catch (Exception $e) {}
        }
        jsonOk();

    default:
        jsonError('Ação inválida');
}
