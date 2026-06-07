<?php
/**
 * COBRAWA — Bootstrap: carrega config, DB e sessão
 */

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    $installUrl = '/install/';
    if (php_sapi_name() !== 'cli') {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!str_starts_with($uri, '/install')) {
            header('Location: ' . $installUrl);
            exit;
        }
    }
}
if (file_exists($configFile)) {
    require_once $configFile;
}
require_once __DIR__ . '/../config/db.php';


// Valores padrão para evitar erro fatal caso config.php antigo/incompleto tenha sido gerado.
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 480);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);
if (!defined('APP_URL')) define('APP_URL', '');

function appUrl(): string {
    $configured = trim((string)APP_URL);
    if ($configured !== '') return rtrim($configured, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

// Iniciar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT * 60,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Regenerar ID de sessão a cada 30 min
if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

/**
 * Retorna o usuário logado ou redireciona para login
 */
function auth(): array {
    if (empty($_SESSION['usuario_id'])) {
        if (isAjax()) { jsonError('Não autenticado', 401); }
        header('Location: ' . appUrl() . '/login.php');
        exit;
    }
    $u = DB::fetchOne('SELECT * FROM usuarios WHERE id=? AND ativo=1', [$_SESSION['usuario_id']]);
    if (!$u) {
        session_destroy();
        header('Location: ' . appUrl() . '/login.php');
        exit;
    }
    return $u;
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
}

function jsonOk($data = null, string $msg = 'OK'): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'msg' => $msg, 'data' => $data]);
    exit;
}

function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}

function getConfig(string $chave, string $default = ''): string {
    $r = DB::fetchOne('SELECT valor FROM configuracoes WHERE chave=?', [$chave]);
    return $r['valor'] ?? $default;
}

function setConfig(string $chave, string $valor): void {
    DB::query('INSERT INTO configuracoes (chave,valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=?', [$chave, $valor, $valor]);
}

function getWahaConfig(): array {
    return DB::fetchOne('SELECT * FROM waha_config ORDER BY id DESC LIMIT 1') ?? [
        'servidor' => 'http://127.0.0.1:3000',
        'sessao'   => 'default',
        'api_key'  => '',
    ];
}

function logAudit(int $uid, string $login, string $acao, string $det = ''): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    DB::query('INSERT INTO logs (usuario_id,usuario_login,ip,acao,detalhes) VALUES (?,?,?,?,?)',
        [$uid, $login, $ip, $acao, $det]);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrfCheck(): void {
    $tok = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $tok)) jsonError('Token CSRF inválido', 403);
}

function ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function nivel(array $u, array $levels): bool {
    return in_array($u['nivel'], $levels);
}
