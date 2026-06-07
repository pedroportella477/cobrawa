<?php
/**
 * COBRAWA — Webhook para receber mensagens do WAHA
 * Configure esta URL no painel WAHA como webhook.
 * URL: https://seusite.com/api/webhook.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) { echo json_encode(['ok' => false]); exit; }

// Log bruto para debug
file_put_contents(__DIR__ . '/../assets/uploads/webhook_last.json', $raw);

$event   = $data['event']   ?? '';
$payload = $data['payload'] ?? [];

if ($event !== 'message' && $event !== 'message.any') {
    echo json_encode(['ok' => true, 'ignored' => true]); exit;
}

// Extrair dados
$from    = $payload['from']    ?? $payload['chatId'] ?? '';
$body_   = $payload['body']    ?? $payload['text']   ?? '';
$msgId   = $payload['id']      ?? '';
$type    = $payload['type']    ?? 'text';

// Remover sufixo @c.us
$whats = preg_replace('/@.+$/', '', $from);
$whats = preg_replace('/\D/', '', $whats);

if (!$whats || $whats === '55' || strlen($whats) < 10) {
    echo json_encode(['ok' => true, 'ignored' => 'invalid_number']); exit;
}

// Buscar cliente
$cliente = DB::fetchOne('SELECT * FROM clientes WHERE whatsapp=? OR whatsapp=? LIMIT 1', [$whats, '55'.$whats]);
if (!$cliente) {
    // Criar cliente automaticamente
    $cliId = DB::insert('INSERT INTO clientes (nome, whatsapp, status_cobranca) VALUES (?,?,?)',
        ['Desconhecido - ' . $whats, $whats, 'Sem Contato']);
    $cliente = DB::fetchOne('SELECT * FROM clientes WHERE id=?', [$cliId]);
}

// Obter ou criar conversa aberta
$conv = DB::fetchOne('SELECT * FROM conversas WHERE cliente_id=? AND status="aberta" ORDER BY id DESC LIMIT 1', [$cliente['id']]);
if (!$conv) {
    $proto = 'PRO' . date('ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $convId = DB::insert('INSERT INTO conversas (cliente_id, protocolo, status) VALUES (?,?,?)',
        [$cliente['id'], $proto, 'aberta']);
    $conv = DB::fetchOne('SELECT * FROM conversas WHERE id=?', [$convId]);
    DB::insert('INSERT INTO protocolos (codigo, conversa_id, cliente_id) VALUES (?,?,?)',
        [$proto, $convId, $cliente['id']]);
}

// Verificar duplicidade pelo waha_id
if ($msgId) {
    $dup = DB::fetchOne('SELECT id FROM mensagens WHERE waha_id=?', [$msgId]);
    if ($dup) { echo json_encode(['ok' => true, 'dup' => true]); exit; }
}

// Tipo de mensagem
$mType   = 'texto';
$arquivo = null;
if (in_array($type, ['image','video','audio','voice','ptt'])) {
    $mType   = in_array($type, ['audio','voice','ptt']) ? 'audio' : ($type === 'image' ? 'imagem' : 'documento');
    $arquivo = $payload['media']['url'] ?? $payload['fileUrl'] ?? null;
} elseif ($type === 'document') {
    $mType   = 'documento';
    $arquivo = $payload['media']['url'] ?? null;
}

$conteudo = $body_ ?: ($type !== 'text' ? "[$type]" : '');

// Salvar mensagem
DB::insert(
    'INSERT INTO mensagens (conversa_id, cliente_id, waha_id, direcao, tipo, conteudo, arquivo_url, lido) VALUES (?,?,?,?,?,?,?,0)',
    [$conv['id'], $cliente['id'], $msgId, 'recebida', $mType, $conteudo, $arquivo]
);

// Atualizar timestamp da conversa
DB::query('UPDATE conversas SET updated_at=NOW() WHERE id=?', [$conv['id']]);

echo json_encode(['ok' => true]);
