<?php
/**
 * COBRAWA — Instalador Automático
 * Acesse: http://seusite.com/install/
 * APAGUE ou PROTEJA esta pasta após instalar!
 */
session_start();
$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

function installBaseUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    $url = preg_replace('#/install/?$#i', '', $url);
    return rtrim($url, '/');
}

function phpConfigValue($value): string {
    return var_export($value, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Testar conexão
        $host = $_POST['db_host'];
        $port = $_POST['db_port'] ?: 3306;
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        $name = $_POST['db_name'];
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            // Criar banco se não existir
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
            // Executar schema
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $q) {
                if ($q) $pdo->exec($q);
            }
            // Salvar config
            $_SESSION['install'] = compact('host','port','user','pass','name');
            header('Location: ?step=2');
            exit;
        } catch (Exception $e) {
            $error = 'Erro de conexão: ' . $e->getMessage();
        }
    } elseif ($step == 2) {
        if (empty($_SESSION['install'])) {
            $error = 'Sessão da instalação expirada. Refazer o passo 1.';
            $step = 1;
        } else {
        $d = $_SESSION['install'];
        try {
            $pdo = new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // Atualizar senha master
            $senha = password_hash($_POST['master_pass'], PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE usuarios SET senha=?, nome=?, email=? WHERE login='master'")->execute([$senha, $_POST['master_nome'], $_POST['master_email']]);
            // Salvar config.php
            $appUrl = installBaseUrl($_POST['app_url'] ?? '');
            if ($appUrl === '') { throw new Exception('Informe a URL base do sistema.'); }
            $conf = "<?php\n";
            $conf .= "/**\n * COBRAWA — Configuração gerada pelo instalador.\n */\n";
            $conf .= "define('DB_HOST', " . phpConfigValue($d['host']) . ");\n";
            $conf .= "define('DB_PORT', " . (int)$d['port'] . ");\n";
            $conf .= "define('DB_USER', " . phpConfigValue($d['user']) . ");\n";
            $conf .= "define('DB_PASS', " . phpConfigValue($d['pass']) . ");\n";
            $conf .= "define('DB_NAME', " . phpConfigValue($d['name']) . ");\n";
            $conf .= "define('APP_URL', " . phpConfigValue($appUrl) . ");\n";
            $conf .= "define('SECRET_KEY', " . phpConfigValue(bin2hex(random_bytes(32))) . ");\n";
            $conf .= "define('SESSION_TIMEOUT', 480);\n";
            $conf .= "define('MAX_LOGIN_ATTEMPTS', 5);\n";
            $conf .= "define('APP_DEBUG', false);\n";
            file_put_contents(dirname(__DIR__) . '/config/config.php', $conf);
            header('Location: ?step=3');
            exit;
        } catch (Exception $e) {
            $error = 'Erro: ' . $e->getMessage();
        }
        }
    }
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CobraWA — Instalação</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#050508;color:#e2f0e8;font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center}
.box{background:#0d1117;border:1px solid rgba(0,255,136,0.3);border-radius:16px;padding:40px;width:520px;max-width:95vw;box-shadow:0 0 30px rgba(0,255,136,0.1)}
h1{font-size:22px;color:#00ff88;margin-bottom:4px;letter-spacing:2px}
p.sub{color:#4a6858;font-size:12px;margin-bottom:28px}
.step-bar{display:flex;gap:8px;margin-bottom:28px}
.step{flex:1;height:4px;border-radius:2px;background:#162030}
.step.done{background:#00ff88}
label{display:block;font-size:11px;color:#8ba898;letter-spacing:1px;text-transform:uppercase;margin-bottom:5px;margin-top:14px}
input{width:100%;background:rgba(0,0,0,0.4);border:1px solid rgba(0,255,136,0.2);border-radius:8px;padding:11px 14px;color:#e2f0e8;font-size:13px;outline:none}
input:focus{border-color:#00ff88}
.row{display:grid;grid-template-columns:3fr 1fr;gap:10px}
button{margin-top:24px;width:100%;background:#00ff88;color:#000;border:none;border-radius:8px;padding:13px;font-size:14px;font-weight:700;cursor:pointer;letter-spacing:1px}
.err{background:rgba(255,68,100,0.1);border:1px solid rgba(255,68,100,0.3);border-radius:8px;padding:10px 14px;color:#ff4466;font-size:12px;margin-top:12px}
.ok{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.2);border-radius:8px;padding:16px;color:#00ff88;font-size:14px;text-align:center}
.ok a{color:#fff;font-weight:700}
</style>
</head>
<body>
<div class="box">
  <h1>📱 COBRAWA</h1>
  <p class="sub">INSTALADOR AUTOMÁTICO DO SISTEMA</p>
  <div class="step-bar">
    <div class="step <?= $step >= 1 ? 'done' : '' ?>"></div>
    <div class="step <?= $step >= 2 ? 'done' : '' ?>"></div>
    <div class="step <?= $step >= 3 ? 'done' : '' ?>"></div>
  </div>

  <?php if ($step == 1): ?>
  <form method="POST">
    <h2 style="font-size:15px;margin-bottom:4px">Passo 1 — Banco de Dados</h2>
    <p style="font-size:12px;color:#4a6858;margin-bottom:16px">Configure a conexão com o MySQL/MariaDB</p>
    <div class="row">
      <div><label>Host do Banco</label><input name="db_host" value="localhost" required></div>
      <div><label>Porta</label><input name="db_port" value="3306" required></div>
    </div>
    <label>Usuário do MySQL</label><input name="db_user" required placeholder="root">
    <label>Senha do MySQL</label><input type="password" name="db_pass" placeholder="(em branco se não tiver)">
    <label>Nome do Banco de Dados</label><input name="db_name" value="cobrawa" required>
    <?php if ($error): ?><div class="err">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <button>TESTAR E INSTALAR BANCO →</button>
  </form>

  <?php elseif ($step == 2): ?>
  <form method="POST">
    <h2 style="font-size:15px;margin-bottom:4px">Passo 2 — Configuração Inicial</h2>
    <p style="font-size:12px;color:#4a6858;margin-bottom:16px">Defina o usuário master e URL do sistema</p>
    <label>Nome do Administrador Master</label><input name="master_nome" value="Master Admin" required>
    <label>E-mail do Master</label><input type="email" name="master_email" placeholder="admin@empresa.com" required>
    <label>Senha do Master (mínimo 8 caracteres)</label><input type="password" name="master_pass" required minlength="8">
    <label>URL Base do Sistema</label><input name="app_url" placeholder="https://seusite.com.br/cobrawa" required>
    <?php if ($error): ?><div class="err">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <button>FINALIZAR INSTALAÇÃO →</button>
  </form>

  <?php elseif ($step == 3): ?>
  <div class="ok">
    <div style="font-size:32px;margin-bottom:12px">✅</div>
    <div style="font-size:16px;font-weight:700;margin-bottom:8px">Instalação Concluída!</div>
    <div style="font-size:12px;color:#8ba898;margin-bottom:20px">O sistema CobraWA foi instalado com sucesso.<br><strong style="color:#ff4466">⚠ IMPORTANTE: Apague ou proteja a pasta /install/ agora!</strong></div>
    <a href="../">→ Acessar o Sistema</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
