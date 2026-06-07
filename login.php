<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Já logado → dashboard
if (!empty($_SESSION['usuario_id'])) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$login || !$senha) {
        $erro = 'Preencha todos os campos.';
    } else {
        $u = DB::fetchOne('SELECT * FROM usuarios WHERE login=? AND ativo=1', [$login]);
        if (!$u) {
            $erro = 'Usuário não encontrado.';
        } elseif ($u['bloqueado']) {
            $erro = 'Conta bloqueada. Contate o administrador.';
        } elseif (!password_verify($senha, $u['senha'])) {
            $tentativas = $u['tentativas_login'] + 1;
            $bloqueado  = $tentativas >= MAX_LOGIN_ATTEMPTS ? 1 : 0;
            DB::query('UPDATE usuarios SET tentativas_login=?, bloqueado=? WHERE id=?',
                [$tentativas, $bloqueado, $u['id']]);
            $restantes = MAX_LOGIN_ATTEMPTS - $tentativas;
            $erro = $bloqueado
                ? 'Conta bloqueada após muitas tentativas.'
                : "Senha incorreta. Tentativas restantes: $restantes";
        } else {
            // Login OK
            DB::query('UPDATE usuarios SET tentativas_login=0, bloqueado=0, ultimo_acesso=NOW(), ip_ultimo_acesso=?, status_operador="online" WHERE id=?',
                [ip(), $u['id']]);
            $_SESSION['usuario_id']    = $u['id'];
            $_SESSION['usuario_login'] = $u['login'];
            $_SESSION['usuario_nivel'] = $u['nivel'];
            logAudit($u['id'], $u['login'], 'Login', 'Acesso ao sistema via ' . ip());
            header('Location: ' . APP_URL . '/');
            exit;
        }
    }
    }
}

$nome_sistema = getConfig('sistema_nome', 'CobraWA');
$tagline      = getConfig('sistema_tagline', 'Sistema de Cobrança WhatsApp');
$logo         = getConfig('sistema_logo', '');
$tema         = getConfig('sistema_tema', 'green');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars(getConfig('sistema_titulo_browser','CobraWA')) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<style>
body{background:var(--bg0);display:flex;align-items:center;justify-content:center;min-height:100vh;overflow:hidden}
.login-wrap{position:relative;z-index:10;width:420px;max-width:95vw}
.login-card{background:var(--glass);border:1px solid var(--border2);border-radius:20px;padding:44px 48px;backdrop-filter:blur(20px);box-shadow:var(--neon-glow)}
.login-logo{text-align:center;margin-bottom:32px}
.login-icon{width:64px;height:64px;background:var(--glass2);border:2px solid var(--neon);border-radius:18px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:14px;box-shadow:var(--neon-glow-strong);animation:pulse-logo 3s ease-in-out infinite}
@keyframes pulse-logo{0%,100%{box-shadow:var(--neon-glow)}50%{box-shadow:var(--neon-glow-strong)}}
.sys-name{font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--neon);letter-spacing:2px}
.sys-tag{font-size:11px;color:var(--text3);margin-top:3px;letter-spacing:1px}
.fl{margin-bottom:16px}
.fl label{display:block;font-size:11px;font-weight:500;color:var(--text2);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:7px}
.fl input{width:100%;background:rgba(0,0,0,0.4);border:1px solid var(--border2);border-radius:8px;padding:12px 16px;color:var(--text);font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s}
.fl input:focus{border-color:var(--neon);box-shadow:0 0 0 3px rgba(0,255,136,.1)}
.opts{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;font-size:12px}
.chk{display:flex;align-items:center;gap:7px;cursor:pointer;color:var(--text2)}
.chk input{accent-color:var(--neon)}
.forgot{color:var(--neon);text-decoration:none}
.forgot:hover{text-decoration:underline}
.btn-in{width:100%;background:var(--neon);color:#000;border:none;border-radius:8px;padding:14px;font-family:var(--font-display);font-size:13px;font-weight:700;letter-spacing:2px;cursor:pointer;transition:all .2s;box-shadow:var(--neon-glow)}
.btn-in:hover{box-shadow:var(--neon-glow-strong);transform:translateY(-1px)}
.err{background:rgba(255,68,100,.1);border:1px solid rgba(255,68,100,.3);border-radius:8px;padding:10px 14px;color:var(--danger);font-size:12px;margin-bottom:16px}
.note{text-align:center;font-size:11px;color:var(--text3);margin-top:18px}
</style>
</head>
<body class="<?= $tema === 'red' ? 'theme-red' : '' ?>">
<canvas id="particles-canvas" style="position:fixed;inset:0;pointer-events:none;z-index:0"></canvas>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <?php if ($logo): ?>
        <img src="<?= htmlspecialchars($logo) ?>" style="height:56px;margin-bottom:12px;border-radius:12px" alt="Logo">
      <?php else: ?>
        <div class="login-icon">📱</div>
      <?php endif; ?>
      <div class="sys-name"><?= htmlspecialchars($nome_sistema) ?></div>
      <div class="sys-tag"><?= htmlspecialchars(strtoupper($tagline)) ?></div>
    </div>

    <?php if ($erro): ?><div class="err">⚠ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">
      <div class="fl">
        <label>Usuário</label>
        <input type="text" name="login" placeholder="Digite seu usuário" autocomplete="username" required>
      </div>
      <div class="fl">
        <label>Senha</label>
        <input type="password" name="senha" placeholder="••••••••" autocomplete="current-password" required>
      </div>
      <div class="opts">
        <label class="chk"><input type="checkbox" name="lembrar"> Lembrar login</label>
        <a href="#" class="forgot" onclick="alert('Contate o administrador para redefinir sua senha.')">Esqueci a senha</a>
      </div>
      <button type="submit" class="btn-in">▶ ACESSAR SISTEMA</button>
    </form>
    <div class="note">Acesso registrado com IP e timestamp para auditoria.</div>
  </div>
</div>
<script src="assets/js/particles.js"></script>
</body>
</html>
