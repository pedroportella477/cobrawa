<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = auth();

// Logout
if (isset($_GET['logout'])) {
    DB::query('UPDATE usuarios SET status_operador="offline" WHERE id=?', [$user['id']]);
    logAudit($user['id'], $user['login'], 'Logout', 'Saída do sistema');
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$cfg = [
    'nome'    => getConfig('sistema_nome', 'CobraWA'),
    'tagline' => getConfig('sistema_tagline', 'Sistema de Cobrança WhatsApp'),
    'logo'    => getConfig('sistema_logo', ''),
    'tema'    => getConfig('sistema_tema', 'green'),
    'titulo'  => getConfig('sistema_titulo_browser', 'CobraWA'),
];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title id="doc-title"><?= htmlspecialchars($cfg['titulo']) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/emoji.css">
</head>
<body class="<?= $cfg['tema'] === 'red' ? 'theme-red' : '' ?>" id="app-body">

<canvas id="particles-canvas" style="position:fixed;inset:0;pointer-events:none;z-index:0;opacity:.4"></canvas>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo" id="sl-logo">
      <?php if ($cfg['logo']): ?>
        <img src="<?= htmlspecialchars($cfg['logo']) ?>" style="width:32px;height:32px;object-fit:contain;border-radius:8px" alt="Logo">
      <?php else: ?>📱<?php endif; ?>
    </div>
    <div>
      <div class="sidebar-brand" id="sl-nome"><?= htmlspecialchars($cfg['nome']) ?></div>
      <div class="sidebar-brand"><small id="sl-tag"><?= htmlspecialchars($cfg['tagline']) ?></small></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Principal</div>
    <div class="nav-item active" data-page="dashboard"><span class="ni">📊</span> Dashboard</div>
    <div class="nav-item" data-page="chat"><span class="ni">💬</span> Conversas <span class="nav-badge" id="nb-unread">0</span></div>

    <div class="nav-section-label" style="margin-top:8px">Gestão</div>
    <div class="nav-item" data-page="clientes"><span class="ni">👤</span> Clientes</div>
    <div class="nav-item" data-page="msgs-prontas"><span class="ni">📋</span> Msgs Prontas</div>
    <div class="nav-item" data-page="protocolos"><span class="ni">🔖</span> Protocolos</div>
    <div class="nav-item" data-page="alertas"><span class="ni">🔔</span> Alertas <span class="nav-badge danger" id="nb-alerts">0</span></div>

    <?php if (nivel($user, ['MASTER','ADMIN','SUPERVISOR'])): ?>
    <div class="nav-section-label" style="margin-top:8px">Administração</div>
    <?php if (nivel($user, ['MASTER','ADMIN'])): ?>
    <div class="nav-item" data-page="usuarios"><span class="ni">👥</span> Usuários</div>
    <?php endif; ?>
    <div class="nav-item" data-page="relatorios"><span class="ni">📈</span> Relatórios</div>
    <?php if (nivel($user, ['MASTER'])): ?>
    <div class="nav-item" data-page="waha"><span class="ni">⚡</span> Config. WAHA</div>
    <div class="nav-item" data-page="whitelabel"><span class="ni">🎨</span> Whitelabel</div>
    <?php endif; ?>
    <div class="nav-item" data-page="logs"><span class="ni">📄</span> Logs</div>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar" id="u-avatar"><?= strtoupper(substr($user['nome'],0,2)) ?></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user['nome']) ?></div>
      <div class="user-role" id="u-role-bar">● <?= $user['nivel'] ?></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;align-items:center">
      <span class="icon-btn" onclick="openModal('modal-perfil')" title="Meu Perfil">👤</span>
      <span class="icon-btn" onclick="location='?logout=1'" title="Sair">⎋</span>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main-content" id="main-content">
  <header class="top-header">
    <button class="header-btn" id="sidebar-toggle" onclick="toggleSidebar()">☰</button>
    <div>
      <div class="header-title" id="page-title">DASHBOARD</div>
      <div class="header-subtitle" id="page-subtitle">Visão geral do sistema</div>
    </div>
    <div style="flex:1"></div>
    <div class="header-actions">
      <div class="waha-status-bar" id="waha-bar">
        <div class="waha-dot" id="waha-dot"></div>
        <span id="waha-txt">Verificando WAHA...</span>
      </div>
      <!-- Status do operador -->
      <div class="op-status-wrap">
        <button class="header-btn op-btn" id="op-status-btn" onclick="toggleStatusMenu()">
          <span id="op-status-icon">🟢</span> <span id="op-status-txt">Online</span> ▾
        </button>
        <div class="op-status-menu" id="op-status-menu">
          <div onclick="setStatus('online')">🟢 Online</div>
          <div onclick="setStatus('ausente')">🟡 Ausente</div>
          <div onclick="setStatus('almoco')">🍽 Almoço</div>
          <div onclick="setStatus('cafe')">☕ Café</div>
          <div onclick="setStatus('offline')">🔴 Offline</div>
        </div>
      </div>
      <button class="header-btn" onclick="checkAlerts()" title="Alertas">🔔</button>
    </div>
  </header>

  <!-- ===== PÁGINAS ===== -->
  <div id="pages">

    <!-- DASHBOARD -->
    <div class="page active" id="page-dashboard">
      <div class="kpi-grid" id="kpi-grid">
        <div class="kpi-card"><div class="kpi-icon">👤</div><div class="kpi-val" id="kpi-clientes">—</div><div class="kpi-lbl">Clientes em Cobrança</div></div>
        <div class="kpi-card"><div class="kpi-icon">💬</div><div class="kpi-val" id="kpi-conversas">—</div><div class="kpi-lbl">Conversas Abertas</div></div>
        <div class="kpi-card"><div class="kpi-icon">🟢</div><div class="kpi-val" id="kpi-online">—</div><div class="kpi-lbl">Operadores Online</div></div>
        <div class="kpi-card"><div class="kpi-icon">📤</div><div class="kpi-val" id="kpi-enviadas">—</div><div class="kpi-lbl">Msg Enviadas Hoje</div></div>
        <div class="kpi-card"><div class="kpi-icon">📥</div><div class="kpi-val" id="kpi-recebidas">—</div><div class="kpi-lbl">Msg Recebidas Hoje</div></div>
        <div class="kpi-card"><div class="kpi-icon">🤝</div><div class="kpi-val" id="kpi-acordos">—</div><div class="kpi-lbl">Acordos Fechados</div></div>
      </div>
      <div class="charts-grid">
        <div class="card">
          <div class="card-header"><span>📊</span><span class="card-title">STATUS DE COBRANÇAS</span></div>
          <div class="card-body" id="chart-status"></div>
        </div>
        <div class="card">
          <div class="card-header"><span>👥</span><span class="card-title">OPERADORES ONLINE</span></div>
          <div class="card-body" id="chart-ops"></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span>⚡</span><span class="card-title">ATIVIDADE RECENTE</span></div>
        <table><thead><tr><th>Protocolo</th><th>Cliente</th><th>Operador</th><th>Ação</th><th>Data/Hora</th></tr></thead>
        <tbody id="tb-atividade"></tbody></table>
      </div>
    </div>

    <!-- CHAT -->
    <div class="page" id="page-chat">
      <div class="chat-layout">
        <div class="contact-list">
          <div class="contact-search">
            <input type="text" placeholder="🔍 Buscar cliente..." id="contact-search" oninput="filterContacts(this.value)">
          </div>
          <div class="contacts-scroll" id="contacts-scroll"></div>
        </div>
        <div class="chat-window" id="chat-window">
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--text3)">
            <div style="font-size:48px;margin-bottom:16px">💬</div>
            <div style="font-size:14px">Selecione um cliente para iniciar</div>
          </div>
        </div>
      </div>
    </div>

    <!-- CLIENTES -->
    <div class="page" id="page-clientes">
      <div class="filter-bar">
        <input type="text" placeholder="🔍 Buscar..." id="cl-search" onkeyup="loadClientes()" style="max-width:250px">
        <select id="cl-status" onchange="loadClientes()">
          <option value="">Todos os status</option>
          <option>Pendente</option><option>Em negociacao</option>
          <option>Pago</option><option>Judicial</option>
          <option>Equipamento retirado</option><option>Sem Contato</option>
        </select>
        <div style="margin-left:auto;display:flex;gap:8px">
          <button class="btn btn-secondary btn-sm" onclick="openModal('modal-import')">📥 Importar CSV</button>
          <button class="btn btn-primary btn-sm" onclick="openClientModal()">➕ Novo Cliente</button>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span>👤</span><span class="card-title">CLIENTES</span>
          <span style="margin-left:auto;font-size:11px;color:var(--text3)" id="cl-count"></span>
        </div>
        <div style="overflow-x:auto">
        <table><thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>WhatsApp</th><th>Produto</th><th>Dívida</th><th>Vencimento</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody id="tb-clientes"></tbody></table>
        </div>
      </div>
    </div>

    <!-- MSGS PRONTAS -->
    <div class="page" id="page-msgs-prontas">
      <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <button class="btn btn-primary" onclick="openMsgModal()">➕ Nova Mensagem</button>
      </div>
      <div id="msgs-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px"></div>
    </div>

    <!-- PROTOCOLOS -->
    <div class="page" id="page-protocolos">
      <div class="card">
        <div class="card-header"><span>🔖</span><span class="card-title">PROTOCOLOS DE ATENDIMENTO</span></div>
        <table><thead><tr><th>Protocolo</th><th>Cliente</th><th>Operador</th><th>Abertura</th><th>Status</th></tr></thead>
        <tbody id="tb-protocolos"></tbody></table>
      </div>
    </div>

    <!-- ALERTAS -->
    <div class="page" id="page-alertas">
      <?php if (nivel($user, ['MASTER','ADMIN','SUPERVISOR'])): ?>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header"><span>📢</span><span class="card-title">ENVIAR ALERTA</span></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Para</label>
              <select id="al-dest"><option value="">Todos os operadores</option></select></div>
            <div class="form-group"><label class="form-label">Prioridade</label>
              <select id="al-prior"><option>Normal</option><option>Urgente</option><option>Critico</option></select></div>
          </div>
          <div class="form-group"><label class="form-label">Mensagem</label>
            <textarea rows="3" id="al-msg" placeholder="Digite o alerta..."></textarea></div>
          <button class="btn btn-primary" onclick="sendAlert()">🔔 Enviar Alerta</button>
        </div>
      </div>
      <?php endif; ?>
      <div class="card">
        <div class="card-header"><span>📋</span><span class="card-title">HISTÓRICO DE ALERTAS</span></div>
        <table><thead><tr><th>Data/Hora</th><th>De</th><th>Para</th><th>Mensagem</th><th>Prioridade</th></tr></thead>
        <tbody id="tb-alertas"></tbody></table>
      </div>
    </div>

    <!-- USUÁRIOS -->
    <?php if (nivel($user, ['MASTER','ADMIN'])): ?>
    <div class="page" id="page-usuarios">
      <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
        <button class="btn btn-primary" onclick="openUserModal()">➕ Novo Usuário</button>
      </div>
      <div class="card">
        <div class="card-header"><span>👥</span><span class="card-title">USUÁRIOS DO SISTEMA</span></div>
        <table><thead><tr><th>Login</th><th>Nome</th><th>Nível</th><th>Setor</th><th>Status</th><th>Último Acesso</th><th>Ações</th></tr></thead>
        <tbody id="tb-usuarios"></tbody></table>
      </div>
    </div>
    <?php endif; ?>

    <!-- RELATÓRIOS -->
    <div class="page" id="page-relatorios">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:24px">
        <?php foreach (['operadores'=>['👤','Operadores'],'clientes'=>['📋','Clientes'],'cobrancas'=>['💰','Cobranças'],'conversas'=>['💬','Conversas']] as $k=>$v): ?>
        <div class="card" style="cursor:pointer" onclick="genReport('<?= $k ?>')">
          <div class="card-body" style="text-align:center;padding:28px">
            <div style="font-size:32px;margin-bottom:10px"><?= $v[0] ?></div>
            <div class="card-title"><?= strtoupper($v[1]) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <div class="card-header"><span>📊</span><span class="card-title">PRODUTIVIDADE</span></div>
        <table><thead><tr><th>Operador</th><th>Atendimentos</th><th>Msgs</th><th>Acordos</th><th>Valor Recuperado</th></tr></thead>
        <tbody id="tb-relatorio"></tbody></table>
      </div>
    </div>

    <!-- WAHA CONFIG -->
    <?php if (nivel($user, ['MASTER'])): ?>
    <div class="page" id="page-waha">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="card">
          <div class="card-header"><span>⚡</span><span class="card-title">CONFIGURAÇÃO WAHA</span></div>
          <div class="card-body">
            <div class="form-group"><label class="form-label">Servidor</label><input type="text" id="wc-server"></div>
            <div class="form-group"><label class="form-label">Sessão</label><input type="text" id="wc-session"></div>
            <div class="form-group"><label class="form-label">API Key</label><input type="text" id="wc-key"></div>
            <div class="form-group"><label class="form-label">Webhook URL</label><input type="text" id="wc-webhook" placeholder="https://seusite.com/api/webhook.php"></div>
            <div style="display:flex;gap:8px">
              <button class="btn btn-primary" onclick="testWaha()">🔌 Testar</button>
              <button class="btn btn-secondary" onclick="saveWaha()">💾 Salvar</button>
            </div>
            <div id="waha-result" style="margin-top:12px;font-size:12px"></div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span>📱</span><span class="card-title">STATUS DA SESSÃO</span></div>
          <div class="card-body" style="text-align:center">
            <div id="waha-session-info" style="margin-bottom:16px;font-size:13px;color:var(--text2)">Carregando...</div>
            <div style="display:flex;gap:8px;justify-content:center">
              <button class="btn btn-secondary btn-sm" onclick="loadWahaStatus()">🔄 Atualizar</button>
              <button class="btn btn-danger btn-sm" onclick="stopSession()">⏏ Desconectar</button>
            </div>
          </div>
        </div>
        <div class="card" style="grid-column:1/-1">
          <div class="card-header"><span>📡</span><span class="card-title">LOG WAHA</span></div>
          <div id="waha-log" style="font-family:var(--font-mono);font-size:11px;color:var(--text2);max-height:180px;overflow-y:auto;padding:12px 16px;background:var(--bg0);margin:12px;border-radius:8px"></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- WHITELABEL -->
    <?php if (nivel($user, ['MASTER'])): ?>
    <div class="page" id="page-whitelabel">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="card">
          <div class="card-header"><span>🎨</span><span class="card-title">IDENTIDADE VISUAL</span></div>
          <div class="card-body">
            <div class="form-group"><label class="form-label">Nome do Sistema</label><input type="text" id="wl-nome" value="<?= htmlspecialchars($cfg['nome']) ?>"></div>
            <div class="form-group"><label class="form-label">Tagline</label><input type="text" id="wl-tag" value="<?= htmlspecialchars($cfg['tagline']) ?>"></div>
            <div class="form-group"><label class="form-label">Título do Navegador</label><input type="text" id="wl-titulo" value="<?= htmlspecialchars($cfg['titulo']) ?>"></div>
            <div class="form-group"><label class="form-label">URL do Logotipo</label><input type="text" id="wl-logo" value="<?= htmlspecialchars($cfg['logo']) ?>" placeholder="https://..."></div>
            <div class="form-group">
              <label class="form-label">Tema</label>
              <div style="display:flex;gap:8px">
                <button class="btn <?= $cfg['tema'] === 'green' ? 'btn-primary' : 'btn-secondary' ?>" id="btn-green" onclick="setTema('green')">🟢 Verde Neon</button>
                <button class="btn <?= $cfg['tema'] === 'red' ? 'btn-primary' : 'btn-secondary' ?>" id="btn-red" onclick="setTema('red')">🔴 Vermelho Neon</button>
              </div>
            </div>
            <button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="saveWhitelabel()">💾 Aplicar em Todo o Sistema</button>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><span>👁</span><span class="card-title">PREVIEW</span></div>
          <div class="card-body" style="background:var(--bg0);border-radius:8px;padding:28px;text-align:center">
            <div style="width:52px;height:52px;background:var(--glass2);border:2px solid var(--neon);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:12px;box-shadow:var(--neon-glow)" id="wl-prev-icon">📱</div>
            <div style="font-family:var(--font-display);font-size:18px;color:var(--neon);letter-spacing:2px" id="wl-prev-nome"><?= htmlspecialchars($cfg['nome']) ?></div>
            <div style="font-size:10px;color:var(--text3);margin-top:4px" id="wl-prev-tag"><?= htmlspecialchars(strtoupper($cfg['tagline'])) ?></div>
          </div>
        </div>
        <div class="card" style="grid-column:1/-1">
          <div class="card-header"><span>ℹ</span><span class="card-title">RODAPÉ FIXO</span></div>
          <div class="card-body">
            <div class="info-box">Rodapé não editável conforme contrato de licença:<br>
            <strong>© <?= date('Y') ?> Desenvolvido por Pedro Portella</strong> —
            <a href="https://www.deltatelecomti.com.br" style="color:var(--neon)" target="_blank">deltatelecomti.com.br</a></div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- LOGS -->
    <div class="page" id="page-logs">
      <div class="card">
        <div class="card-header"><span>📄</span><span class="card-title">LOGS DE AUDITORIA</span>
          <select id="log-filter" onchange="loadLogs()" style="margin-left:auto;width:auto">
            <option value="">Todos</option><option>Login</option><option>Logout</option>
            <option>Mensagem</option><option>Transferência</option><option>Configuração</option>
          </select>
        </div>
        <table><thead><tr><th>Data/Hora</th><th>Usuário</th><th>IP</th><th>Ação</th><th>Detalhes</th></tr></thead>
        <tbody id="tb-logs"></tbody></table>
      </div>
    </div>

  </div><!-- /pages -->

  <footer>© <span id="fy"><?= date('Y') ?></span> Desenvolvido por <a href="https://www.deltatelecomti.com.br" target="_blank">Pedro Portella</a> — Delta Telecom TI</footer>
</div><!-- /main-content -->

<!-- ===== MODAIS ===== -->

<!-- Modal Perfil do Operador -->
<div class="modal-overlay" id="modal-perfil">
  <div class="modal">
    <h2>👤 MEU PERFIL</h2>
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
      <div class="user-avatar" style="width:56px;height:56px;font-size:20px" id="mp-avatar"><?= strtoupper(substr($user['nome'],0,2)) ?></div>
      <div>
        <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($user['nome']) ?></div>
        <div style="font-size:12px;color:var(--text3)"><?= $user['nivel'] ?> — <?= htmlspecialchars($user['setor'] ?? '') ?></div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Nome Completo</label><input type="text" id="mp-nome" value="<?= htmlspecialchars($user['nome']) ?>"></div>
    <div class="form-group"><label class="form-label">E-mail</label><input type="email" id="mp-email" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
    <div class="form-group"><label class="form-label">Status de Atendimento</label>
      <select id="mp-status">
        <option value="online" <?= $user['status_operador']==='online'?'selected':'' ?>>🟢 Online</option>
        <option value="ausente" <?= $user['status_operador']==='ausente'?'selected':'' ?>>🟡 Ausente</option>
        <option value="almoco" <?= $user['status_operador']==='almoco'?'selected':'' ?>>🍽 Em Almoço</option>
        <option value="cafe" <?= $user['status_operador']==='cafe'?'selected':'' ?>>☕ Em Café</option>
        <option value="offline" <?= $user['status_operador']==='offline'?'selected':'' ?>>🔴 Offline</option>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Nova Senha (deixe em branco para manter)</label><input type="password" id="mp-senha" placeholder="Nova senha..."></div>
    <div class="form-group"><label class="form-label">Confirmar Nova Senha</label><input type="password" id="mp-senha2" placeholder="Confirme..."></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-perfil')">Cancelar</button>
      <button class="btn btn-primary" onclick="savePerfil()">💾 Salvar Perfil</button>
    </div>
  </div>
</div>

<!-- Modal Cliente -->
<div class="modal-overlay" id="modal-cliente">
  <div class="modal" style="width:620px">
    <h2 id="modal-cliente-title">➕ NOVO CLIENTE</h2>
    <input type="hidden" id="c-id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Nome *</label><input type="text" id="c-nome"></div>
      <div class="form-group"><label class="form-label">CPF/CNPJ</label><input type="text" id="c-cpf"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Telefone</label><input type="text" id="c-tel"></div>
      <div class="form-group"><label class="form-label">WhatsApp *</label><input type="text" id="c-whats" placeholder="5511999990000"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">E-mail</label><input type="email" id="c-email"></div>
      <div class="form-group"><label class="form-label">Produto</label><input type="text" id="c-produto"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Valor da Dívida</label><input type="number" step="0.01" id="c-valor"></div>
      <div class="form-group"><label class="form-label">Vencimento</label><input type="date" id="c-venc"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Cidade</label><input type="text" id="c-cidade"></div>
      <div class="form-group"><label class="form-label">Estado</label>
        <select id="c-estado"><option>AC</option><option>AL</option><option>AM</option><option>AP</option><option>BA</option><option>CE</option><option>DF</option><option>ES</option><option>GO</option><option>MA</option><option>MG</option><option>MS</option><option>MT</option><option>PA</option><option>PB</option><option>PE</option><option>PI</option><option>PR</option><option>RJ</option><option selected>SP</option><option>RN</option><option>RO</option><option>RR</option><option>RS</option><option>SC</option><option>SE</option><option>TO</option></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">CEP</label><input type="text" id="c-cep"></div>
      <div class="form-group"><label class="form-label">Endereço</label><input type="text" id="c-end"></div>
    </div>
    <div class="form-group"><label class="form-label">Status de Cobrança</label>
      <select id="c-status">
        <option>Pendente</option><option>Em negociacao</option><option>Pago</option>
        <option>Judicial</option><option>Equipamento retirado</option><option>Sem Contato</option>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Observações</label><textarea rows="3" id="c-obs"></textarea></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-cliente')">Cancelar</button>
      <button class="btn btn-primary" onclick="saveCliente()">💾 Salvar</button>
    </div>
  </div>
</div>

<!-- Modal Importar CSV -->
<div class="modal-overlay" id="modal-import">
  <div class="modal">
    <h2>📥 IMPORTAR CSV</h2>
    <div class="info-box" style="margin-bottom:16px">Colunas esperadas: <strong>nome, whatsapp, cpf_cnpj, produto, valor_divida, data_vencimento</strong><br>Separador: vírgula | Encoding: UTF-8</div>
    <div class="form-group"><label class="form-label">Arquivo CSV</label><input type="file" id="csv-file" accept=".csv" onchange="previewCSV(this)"></div>
    <div id="csv-preview" style="display:none;margin-top:12px">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:8px">
        <div class="kpi-card"><div class="kpi-val" id="csv-total" style="font-size:22px">0</div><div class="kpi-lbl">Total</div></div>
        <div class="kpi-card"><div class="kpi-val" id="csv-ok" style="font-size:22px;color:var(--success)">0</div><div class="kpi-lbl">Válidos</div></div>
        <div class="kpi-card"><div class="kpi-val" id="csv-err" style="font-size:22px;color:var(--danger)">0</div><div class="kpi-lbl">Inválidos</div></div>
      </div>
      <div id="csv-errors" style="font-size:11px;color:var(--danger);max-height:80px;overflow-y:auto"></div>
    </div>
    <div id="csv-data" style="display:none"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-import')">Cancelar</button>
      <button class="btn btn-primary" onclick="doImport()">📥 Importar</button>
    </div>
  </div>
</div>

<!-- Modal Msg Pronta -->
<div class="modal-overlay" id="modal-msg">
  <div class="modal">
    <h2 id="modal-msg-title">📋 NOVA MENSAGEM PRONTA</h2>
    <input type="hidden" id="mp-id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Título</label><input type="text" id="mp-titulo"></div>
      <div class="form-group"><label class="form-label">Categoria</label>
        <select id="mp-cat">
          <option>Cobrança Amigável</option><option>Segunda Cobrança</option>
          <option>Cobrança Judicial</option><option>Acordo</option><option>Confirmação de Pagamento</option><option>Outro</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Corpo da Mensagem</label>
      <div style="font-size:11px;color:var(--text3);margin-bottom:6px">Variáveis: {nome} {valor} {produto} {vencimento} {protocolo}</div>
      <textarea rows="7" id="mp-corpo"></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-msg')">Cancelar</button>
      <button class="btn btn-primary" onclick="saveMsg()">💾 Salvar</button>
    </div>
  </div>
</div>

<!-- Modal Usuário -->
<?php if (nivel($user, ['MASTER','ADMIN'])): ?>
<div class="modal-overlay" id="modal-usuario">
  <div class="modal">
    <h2 id="modal-user-title">👤 NOVO USUÁRIO</h2>
    <input type="hidden" id="uu-id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Nome Completo</label><input type="text" id="uu-nome"></div>
      <div class="form-group"><label class="form-label">Login</label><input type="text" id="uu-login"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Senha</label><input type="password" id="uu-senha" placeholder="Deixe em branco para não alterar"></div>
      <div class="form-group"><label class="form-label">Confirmar</label><input type="password" id="uu-senha2"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Nível</label>
        <select id="uu-nivel"><option>OPERADOR</option><option>SUPERVISOR</option><option>ADMIN</option><?php if($user['nivel']==='MASTER'):?><option>MASTER</option><?php endif;?></select>
      </div>
      <div class="form-group"><label class="form-label">Setor</label><input type="text" id="uu-setor"></div>
    </div>
    <div class="form-group"><label class="form-label">E-mail</label><input type="email" id="uu-email"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-usuario')">Cancelar</button>
      <button class="btn btn-primary" onclick="saveUsuario()">💾 Salvar</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Transferência -->
<div class="modal-overlay" id="modal-transfer">
  <div class="modal" style="width:400px">
    <h2>↔ TRANSFERIR ATENDIMENTO</h2>
    <input type="hidden" id="tr-conv-id">
    <div class="form-group"><label class="form-label">Transferir para</label><select id="tr-dest"></select></div>
    <div class="form-group"><label class="form-label">Motivo</label><textarea rows="3" id="tr-motivo"></textarea></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('modal-transfer')">Cancelar</button>
      <button class="btn btn-primary" onclick="doTransfer()">↔ Transferir</button>
    </div>
  </div>
</div>

<!-- Popup de Alerta -->
<div id="alert-popup" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;width:360px;background:var(--bg2);border:1px solid var(--neon);border-radius:14px;padding:20px;box-shadow:var(--neon-glow-strong);animation:popup-slide .4s cubic-bezier(.34,1.56,.64,1)">
  <span style="position:absolute;top:12px;right:14px;cursor:pointer;color:var(--text3)" onclick="this.parentElement.style.display='none'">✕</span>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
    <span style="font-size:20px" id="ap-icon">🔔</span>
    <span style="font-family:var(--font-display);font-size:12px;color:var(--neon);letter-spacing:1px">ALERTA DO SUPERVISOR</span>
  </div>
  <div id="ap-msg" style="font-size:13px;color:var(--text2);margin-bottom:14px;line-height:1.5"></div>
  <button class="btn btn-primary btn-sm" onclick="document.getElementById('alert-popup').style.display='none'">✓ Entendido</button>
</div>

<!-- Toast container -->
<div id="toast-container" style="position:fixed;bottom:24px;right:24px;z-index:9998;display:flex;flex-direction:column-reverse;gap:8px"></div>

<script>
// Dados do usuário logado (PHP → JS)
const CUR_USER = {
  id: <?= $user['id'] ?>,
  login: "<?= addslashes($user['login']) ?>",
  nome: "<?= addslashes($user['nome']) ?>",
  nivel: "<?= $user['nivel'] ?>",
  status: "<?= $user['status_operador'] ?>"
};
const APP_URL = "<?= APP_URL ?>";
const CSRF    = "<?= csrfToken() ?>";
</script>
<script src="assets/js/particles.js"></script>
<script src="assets/js/emoji-picker.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/chat.js"></script>
</body>
</html>
