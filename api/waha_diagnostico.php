<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/waha.php';
$user = auth();
if (!nivel($user, ['MASTER'])) jsonError('Sem permissão', 403);

$cfg = getWahaConfig();
$waha = new Waha($cfg);
$test = $waha->test();

jsonOk([
    'php_curl' => extension_loaded('curl') ? 'OK' : 'ERRO: php-curl não instalado',
    'config' => [
        'servidor' => $cfg['servidor'] ?? null,
        'sessao' => $cfg['sessao'] ?? null,
        'api_key_inicio' => isset($cfg['api_key']) ? substr($cfg['api_key'], 0, 6) . '...' : null,
    ],
    'teste_waha' => $test,
]);
