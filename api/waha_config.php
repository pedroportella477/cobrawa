<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/waha.php';
$user   = auth();
if (!nivel($user, ['MASTER'])) jsonError('Sem permissão', 403);
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

function normalize_waha_server(string $server): string {
    $server = trim($server);
    if ($server !== '' && !preg_match('~^https?://~i', $server)) $server = 'http://' . $server;
    return rtrim($server, '/');
}

switch ($action) {
    case 'get':
        $cfg = DB::fetchOne('SELECT * FROM waha_config ORDER BY id DESC LIMIT 1');
        jsonOk($cfg ?: [
            'servidor'=>'http://179.125.50.250:3000',
            'sessao'=>'default',
            'api_key'=>'d9e3b58c458249d88fe98454a27ee7f4',
            'webhook_url'=>'https://lebarone.deltatelecomti.com.br/api/webhook.php'
        ]);

    case 'save':
        $servidor = normalize_waha_server($body['servidor'] ?? '');
        $sessao = trim($body['sessao'] ?? 'default') ?: 'default';
        $apiKey = trim($body['api_key'] ?? '');
        $webhook = trim($body['webhook_url'] ?? '');
        if (!$servidor) jsonError('Informe o servidor WAHA com http://IP:3000');
        if (!$apiKey) jsonError('Informe a API Key do WAHA');

        $cfg = DB::fetchOne('SELECT id FROM waha_config LIMIT 1');
        if ($cfg) {
            DB::query('UPDATE waha_config SET servidor=?, sessao=?, api_key=?, webhook_url=? WHERE id=?',
                [$servidor, $sessao, $apiKey, $webhook ?: null, $cfg['id']]);
        } else {
            DB::insert('INSERT INTO waha_config (servidor, sessao, api_key, webhook_url) VALUES (?,?,?,?)',
                [$servidor, $sessao, $apiKey, $webhook ?: null]);
        }
        logAudit($user['id'], $user['login'], 'Configuração', 'Atualizou config WAHA');
        jsonOk(['servidor'=>$servidor, 'sessao'=>$sessao]);

    case 'test':
        $cfg = [
            'servidor' => normalize_waha_server($body['servidor'] ?? ''),
            'sessao' => trim($body['sessao'] ?? 'default') ?: 'default',
            'api_key' => trim($body['api_key'] ?? ''),
        ];
        if (!$cfg['servidor']) jsonError('Servidor vazio. Use http://179.125.50.250:3000');
        if (!$cfg['api_key']) jsonError('API Key vazia. Cole a WAHA_API_KEY dos logs do WAHA.');
        $waha = new Waha($cfg);
        $r = $waha->test();
        if (empty($r['ok'])) {
            $det = $r['error'] ?? 'Falha no teste WAHA';
            if (!empty($r['_url'])) $det .= ' | URL: ' . $r['_url'];
            if (isset($r['_http_code'])) $det .= ' | HTTP: ' . $r['_http_code'];
            jsonError($det);
        }
        jsonOk($r);

    case 'status':
        try {
            $waha = new Waha();
            $r = $waha->sessionStatus();
            if (!empty($r['error'])) jsonError($r['error']);
            jsonOk($r);
        } catch (Exception $e) {
            jsonError($e->getMessage());
        }

    case 'start':
        $waha = new Waha();
        $r = $waha->startSession();
        logAudit($user['id'], $user['login'], 'Configuração', 'Iniciou sessão WAHA');
        jsonOk($r);

    case 'stop':
        $waha = new Waha();
        $r = $waha->stopSession();
        logAudit($user['id'], $user['login'], 'Configuração', 'Desconectou sessão WAHA');
        jsonOk($r);

    default:
        jsonError('Ação inválida');
}
