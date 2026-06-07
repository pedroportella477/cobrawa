// ============================================================
// COBRAWA — JavaScript Principal
// Desenvolvido por Pedro Portella | deltatelecomti.com.br
// ============================================================

'use strict';

// ─── UTILITÁRIOS ───────────────────────────────────────────

async function api(endpoint, data = null, method = null) {
  const opts = {
    method: method || (data ? 'POST' : 'GET'),
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': CSRF,
    },
  };
  if (data) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(data);
  }
  try {
    const r = await fetch(APP_URL + '/api/' + endpoint, opts);
    return await r.json();
  } catch (e) {
    console.error('API error:', endpoint, e);
    return { ok: false, msg: 'Erro de conexão' };
  }
}

function toast(msg, type = 'success', dur = 3500) {
  const colors = { success: 'var(--success)', danger: 'var(--danger)', warning: 'var(--warning)', info: 'var(--info)' };
  const icons  = { success: '✓', danger: '✕', warning: '⚠', info: 'ℹ' };
  const el = document.createElement('div');
  el.style.cssText = `
    background:var(--bg2);border:1px solid ${colors[type]};border-radius:var(--radius);
    padding:11px 15px;font-size:13px;color:var(--text);
    display:flex;align-items:center;gap:8px;max-width:340px;
    box-shadow:0 0 12px ${colors[type]}30;
    animation:popup-slide .3s ease;
  `;
  el.innerHTML = `<span style="color:${colors[type]}">${icons[type]}</span> ${msg}`;
  const c = document.getElementById('toast-container');
  c.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }, dur);
}

function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function fv(id) { return document.getElementById(id)?.value?.trim() || ''; }
function sv(id, v) { const el = document.getElementById(id); if (el) el.value = v ?? ''; }

function badge(status) {
  const map = {
    'Pendente':              'badge-warning',
    'Em negociacao':         'badge-info',
    'Pago':                  'badge-success',
    'Judicial':              'badge-danger',
    'Equipamento retirado':  'badge-gray',
    'Sem Contato':           'badge-danger',
    'aberta':                'badge-info',
    'encerrada':             'badge-gray',
    'transferida':           'badge-warning',
    'MASTER':                'badge-success',
    'ADMIN':                 'badge-info',
    'SUPERVISOR':            'badge-warning',
    'OPERADOR':              'badge-gray',
  };
  return `<span class="badge ${map[status] || 'badge-gray'}">${status}</span>`;
}

function statusLabel(s) {
  const map = { online:'🟢 Online', ausente:'🟡 Ausente', almoco:'🍽 Almoço', cafe:'☕ Café', offline:'🔴 Offline' };
  return map[s] || s;
}

// Click fora fecha menus
document.addEventListener('click', e => {
  if (!e.target.closest('.op-status-wrap')) {
    document.getElementById('op-status-menu')?.classList.remove('show');
  }
  if (!e.target.closest('.modal-overlay')) return;
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
  }
});

// ─── NAVEGAÇÃO ─────────────────────────────────────────────

const PAGE_TITLES = {
  dashboard:    ['DASHBOARD', 'Visão geral do sistema'],
  chat:         ['CONVERSAS', 'Chat WhatsApp integrado'],
  clientes:     ['CLIENTES', 'Gerenciamento de carteira'],
  'msgs-prontas': ['MENSAGENS PRONTAS', 'Templates de cobrança'],
  protocolos:   ['PROTOCOLOS', 'Atendimentos registrados'],
  alertas:      ['ALERTAS', 'Notificações e alertas'],
  usuarios:     ['USUÁRIOS', 'Gestão de usuários'],
  relatorios:   ['RELATÓRIOS', 'Análise de dados'],
  waha:         ['CONFIG WAHA', 'Integração WhatsApp'],
  whitelabel:   ['WHITELABEL', 'Personalização do sistema'],
  logs:         ['LOGS', 'Auditoria e rastreamento'],
};

let currentPage = 'dashboard';

document.querySelectorAll('.nav-item[data-page]').forEach(item => {
  item.addEventListener('click', () => showPage(item.dataset.page));
});

function showPage(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

  const el = document.getElementById('page-' + page);
  if (!el) return;
  el.classList.add('active');

  document.querySelectorAll(`.nav-item[data-page="${page}"]`).forEach(n => n.classList.add('active'));

  const t = PAGE_TITLES[page] || [page.toUpperCase(), ''];
  document.getElementById('page-title').textContent = t[0];
  document.getElementById('page-subtitle').textContent = t[1];
  currentPage = page;

  // Carregar dados da página
  const loaders = {
    dashboard:      loadDashboard,
    chat:           loadContacts,
    clientes:       loadClientes,
    'msgs-prontas': loadMsgsProntas,
    protocolos:     loadProtocolos,
    alertas:        loadAlertas,
    usuarios:       loadUsuarios,
    relatorios:     loadRelatorios,
    waha:           loadWahaPage,
    logs:           loadLogs,
  };
  if (loaders[page]) loaders[page]();
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ─── STATUS DO OPERADOR ────────────────────────────────────

function toggleStatusMenu() {
  document.getElementById('op-status-menu').classList.toggle('show');
}

const STATUS_ICONS = { online:'🟢', ausente:'🟡', almoco:'🍽', cafe:'☕', offline:'🔴' };

async function setStatus(s) {
  document.getElementById('op-status-menu').classList.remove('show');
  const r = await api('usuario.php', { action: 'set_status', status: s });
  if (r.ok) {
    document.getElementById('op-status-btn').innerHTML =
      `${STATUS_ICONS[s]} <span id="op-status-txt">${statusLabel(s)}</span> ▾`;
    sv('mp-status', s);
    toast('Status alterado: ' + statusLabel(s), 'success');
  }
}

// ─── DASHBOARD ─────────────────────────────────────────────

async function loadDashboard() {
  const r = await api('dashboard.php');
  if (!r.ok) return;
  const d = r.data;
  sv2('kpi-clientes', d.total_clientes);
  sv2('kpi-conversas', d.conversas_abertas);
  sv2('kpi-online', d.operadores_online);
  sv2('kpi-enviadas', d.msgs_enviadas);
  sv2('kpi-recebidas', d.msgs_recebidas);
  sv2('kpi-acordos', d.acordos);

  // Gráfico status
  const statusEl = document.getElementById('chart-status');
  if (statusEl && d.status_counts) {
    const total = d.status_counts.reduce((s, x) => s + parseInt(x.total), 0) || 1;
    statusEl.innerHTML = d.status_counts.map(s => `
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
          <span style="color:var(--text2)">${s.status_cobranca}</span>
          <span>${s.total}</span>
        </div>
        <div style="height:5px;background:var(--bg4);border-radius:3px;overflow:hidden">
          <div style="width:${Math.round(s.total/total*100)}%;height:100%;background:var(--neon);border-radius:3px;transition:width .6s"></div>
        </div>
      </div>
    `).join('');
  }

  // Gráfico operadores
  const opsEl = document.getElementById('chart-ops');
  if (opsEl && d.ops_status) {
    opsEl.innerHTML = d.ops_status.map(o => `
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <span>${STATUS_ICONS[o.status_operador] || '⚫'}</span>
        <span style="color:var(--text2);font-size:12px;flex:1">${statusLabel(o.status_operador)}</span>
        <span style="font-family:var(--font-mono);font-size:12px;color:var(--neon)">${o.qtd}</span>
      </div>
    `).join('');
  }

  // Atividade recente
  const tb = document.getElementById('tb-atividade');
  if (tb && d.atividade) {
    tb.innerHTML = d.atividade.map(a => `
      <tr>
        <td class="font-mono" style="font-size:11px;color:var(--neon)">${a.protocolo || '—'}</td>
        <td>${a.cliente}</td>
        <td style="color:var(--text2)">${a.operador || '—'}</td>
        <td>${a.acao}</td>
        <td style="color:var(--text3);font-size:12px">${a.created_at}</td>
      </tr>
    `).join('') || '<tr><td colspan="5" style="color:var(--text3);text-align:center;padding:20px">Nenhuma atividade</td></tr>';
  }
}

function sv2(id, v) {
  const el = document.getElementById(id);
  if (!el) return;
  // Animate count up
  const target = parseInt(v) || 0;
  let cur = 0;
  const step = Math.ceil(target / 30);
  const timer = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = cur.toLocaleString('pt-BR');
    if (cur >= target) clearInterval(timer);
  }, 25);
}

// ─── CLIENTES ──────────────────────────────────────────────

let clientesData = [];

async function loadClientes() {
  const search = fv('cl-search');
  const status = fv('cl-status');
  const r = await api(`clientes.php?action=list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`);
  if (!r.ok) return;
  clientesData = r.data.clientes;
  document.getElementById('cl-count').textContent = r.data.total + ' registros';
  const tb = document.getElementById('tb-clientes');
  tb.innerHTML = clientesData.map(c => `
    <tr>
      <td><strong>${esc(c.nome)}</strong></td>
      <td class="font-mono" style="font-size:11px">${esc(c.cpf_cnpj || '—')}</td>
      <td style="color:var(--neon);white-space:nowrap">${esc(c.whatsapp)}</td>
      <td style="color:var(--text2)">${esc(c.produto || '—')}</td>
      <td style="color:var(--warning);font-weight:600">R$ ${parseFloat(c.valor_divida||0).toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
      <td style="color:var(--text3)">${c.data_vencimento || '—'}</td>
      <td>
        <select class="status-select" onchange="updateClienteStatus(${c.id}, this.value)">
          ${['Pendente','Em negociacao','Pago','Judicial','Equipamento retirado','Sem Contato']
            .map(s => `<option value="${s}" ${c.status_cobranca===s?'selected':''}>${s==='Em negociacao'?'Em negociação':s}</option>`).join('')}
        </select>
      </td>
      <td>
        <div style="display:flex;gap:3px">
          <button class="btn btn-secondary btn-sm" onclick="openChatWithClient(${c.id})" title="Abrir chat">💬</button>
          <button class="btn btn-secondary btn-sm" onclick="editCliente(${c.id})" title="Editar">✏</button>
          <button class="btn btn-danger btn-sm" onclick="deleteCliente(${c.id})" title="Excluir">🗑</button>
        </div>
      </td>
    </tr>
  `).join('') || '<tr><td colspan="8" style="text-align:center;color:var(--text3);padding:20px">Nenhum cliente encontrado</td></tr>';
}

async function updateClienteStatus(id, status) {
  const r = await api('clientes.php', { action: 'update_status', id, status });
  if (r.ok) toast('Status atualizado!', 'success');
  else toast(r.msg || 'Erro ao atualizar', 'danger');
}

function openClientModal(id = null) {
  document.getElementById('modal-cliente-title').textContent = id ? '✏ EDITAR CLIENTE' : '➕ NOVO CLIENTE';
  sv('c-id', id || '');
  if (!id) {
    ['c-nome','c-cpf','c-tel','c-whats','c-email','c-produto','c-valor','c-cidade','c-cep','c-end','c-obs'].forEach(f => sv(f, ''));
    sv('c-status', 'Pendente');
  }
  openModal('modal-cliente');
}

async function editCliente(id) {
  const c = clientesData.find(x => x.id == id);
  if (!c) return;
  openClientModal(id);
  sv('c-nome', c.nome); sv('c-cpf', c.cpf_cnpj); sv('c-tel', c.telefone);
  sv('c-whats', c.whatsapp); sv('c-email', c.email); sv('c-produto', c.produto);
  sv('c-valor', c.valor_divida); sv('c-venc', c.data_vencimento);
  sv('c-cidade', c.cidade); sv('c-estado', c.estado);
  sv('c-cep', c.cep); sv('c-end', c.endereco); sv('c-obs', c.observacoes);
  sv('c-status', c.status_cobranca);
}

async function saveCliente() {
  const nome  = fv('c-nome');
  const whats = fv('c-whats');
  if (!nome)  { toast('Informe o nome!', 'danger'); return; }
  if (!whats) { toast('Informe o WhatsApp!', 'danger'); return; }
  const data = {
    action:          fv('c-id') ? 'update' : 'create',
    id:              fv('c-id') || undefined,
    nome, cpf_cnpj:  fv('c-cpf'), telefone: fv('c-tel'),
    whatsapp: whats, email: fv('c-email'), produto: fv('c-produto'),
    valor_divida:    fv('c-valor'), data_vencimento: fv('c-venc'),
    cidade: fv('c-cidade'), estado: fv('c-estado'),
    cep: fv('c-cep'), endereco: fv('c-end'),
    status_cobranca: fv('c-status'), observacoes: fv('c-obs'),
  };
  const r = await api('clientes.php', data);
  if (r.ok) { toast('Cliente salvo!', 'success'); closeModal('modal-cliente'); loadClientes(); }
  else toast(r.msg || 'Erro ao salvar', 'danger');
}

async function deleteCliente(id) {
  if (!confirm('Excluir este cliente? Esta ação não pode ser desfeita.')) return;
  const r = await api('clientes.php', { action: 'delete', id });
  if (r.ok) { toast('Cliente excluído!', 'warning'); loadClientes(); }
  else toast(r.msg || 'Erro', 'danger');
}

// ─── IMPORTAR CSV ──────────────────────────────────────────

let csvRows = [];

function previewCSV(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const lines = e.target.result.split('\n').filter(l => l.trim());
    const header = lines[0].split(',').map(h => h.trim().toLowerCase().replace(/"/g,''));
    csvRows = [];
    const errors = [];
    lines.slice(1).forEach((line, i) => {
      const vals = line.split(',').map(v => v.trim().replace(/^"|"$/g,''));
      const row = {};
      header.forEach((h, j) => row[h] = vals[j] || '');
      if (!row.nome) { errors.push(`Linha ${i+2}: nome obrigatório`); return; }
      if (!row.whatsapp && !row.telefone) { errors.push(`Linha ${i+2}: whatsapp obrigatório`); return; }
      row.whatsapp = row.whatsapp || row.telefone;
      csvRows.push(row);
    });
    document.getElementById('csv-preview').style.display = 'block';
    sv('csv-total', lines.length - 1);
    sv('csv-ok', csvRows.length);
    sv('csv-err', errors.length);
    document.getElementById('csv-errors').innerHTML = errors.map(e => `• ${e}`).join('<br>');
  };
  reader.readAsText(file, 'UTF-8');
}

async function doImport() {
  if (!csvRows.length) { toast('Nenhum dado válido para importar', 'danger'); return; }
  const r = await api('clientes.php', { action: 'import', rows: csvRows });
  if (r.ok) {
    toast(`Importados: ${r.data.imported} clientes!`, 'success');
    closeModal('modal-import');
    loadClientes();
    csvRows = [];
  } else toast(r.msg || 'Erro na importação', 'danger');
}

// ─── MSGS PRONTAS ──────────────────────────────────────────

async function loadMsgsProntas() {
  const r = await api('msgs.php?action=list');
  if (!r.ok) return;
  const grid = document.getElementById('msgs-grid');
  grid.innerHTML = r.data.map(m => `
    <div class="card">
      <div class="card-header">
        <span>📋</span>
        <span class="card-title">${esc(m.titulo)}</span>
        <span class="badge badge-info" style="margin-left:auto;font-size:10px">${esc(m.categoria)}</span>
      </div>
      <div class="card-body">
        <div style="font-size:12px;color:var(--text2);line-height:1.6;margin-bottom:12px;white-space:pre-line;max-height:80px;overflow:hidden">
          ${esc(m.corpo).substring(0,140)}...
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <button class="btn btn-primary btn-sm" onclick="useMsg(${m.id})">📤 Usar no Chat</button>
          <button class="btn btn-secondary btn-sm" onclick="editMsg(${m.id})">✏ Editar</button>
          <button class="btn btn-danger btn-sm" onclick="deleteMsg(${m.id})">🗑</button>
        </div>
      </div>
    </div>
  `).join('') || '<div style="color:var(--text3);text-align:center;padding:40px">Nenhuma mensagem cadastrada</div>';

  // Cache para usar no chat
  window._msgs = r.data;
}

function openMsgModal(id = null) {
  document.getElementById('modal-msg-title').textContent = id ? '✏ EDITAR MENSAGEM' : '📋 NOVA MENSAGEM';
  sv('mp-id', id || '');
  if (!id) { sv('mp-titulo',''); sv('mp-corpo',''); }
  openModal('modal-msg');
}

async function editMsg(id) {
  const msgs = window._msgs || [];
  const m = msgs.find(x => x.id == id);
  if (!m) return;
  openMsgModal(id);
  sv('mp-titulo', m.titulo);
  sv('mp-cat', m.categoria);
  sv('mp-corpo', m.corpo);
}

async function saveMsg() {
  const titulo = fv('mp-titulo');
  const corpo  = fv('mp-corpo');
  if (!titulo || !corpo) { toast('Preencha título e corpo!', 'danger'); return; }
  const r = await api('msgs.php', {
    action: fv('mp-id') ? 'update' : 'create',
    id: fv('mp-id') || undefined,
    titulo, categoria: fv('mp-cat'), corpo,
  });
  if (r.ok) { toast('Mensagem salva!', 'success'); closeModal('modal-msg'); loadMsgsProntas(); }
  else toast(r.msg || 'Erro', 'danger');
}

async function deleteMsg(id) {
  if (!confirm('Excluir esta mensagem?')) return;
  const r = await api('msgs.php', { action: 'delete', id });
  if (r.ok) { toast('Mensagem excluída', 'warning'); loadMsgsProntas(); }
  else toast(r.msg || 'Erro', 'danger');
}

function useMsg(id) {
  const msgs = window._msgs || [];
  const m = msgs.find(x => x.id == id);
  if (!m) return;
  window._selectedMsg = m;
  showPage('chat');
  toast('Mensagem pronta selecionada. Abra um contato para usar.', 'info');
}

// ─── PROTOCOLOS ────────────────────────────────────────────

async function loadProtocolos() {
  const r = await api('protocolos.php?action=list');
  if (!r.ok) return;
  const tb = document.getElementById('tb-protocolos');
  tb.innerHTML = r.data.map(p => `
    <tr>
      <td class="font-mono" style="color:var(--neon);font-size:11px">${p.codigo}</td>
      <td>${esc(p.cliente || '—')}</td>
      <td style="color:var(--text2)">${esc(p.operador || '—')}</td>
      <td style="color:var(--text3);font-size:12px">${p.created_at}</td>
      <td>${badge(p.status)}</td>
    </tr>
  `).join('') || '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:20px">Nenhum protocolo</td></tr>';
}

// ─── ALERTAS ───────────────────────────────────────────────

async function loadAlertas() {
  const r = await api('alertas.php?action=list');
  if (!r.ok) return;
  const tb = document.getElementById('tb-alertas');
  tb.innerHTML = r.data.map(a => `
    <tr>
      <td style="font-size:12px;color:var(--text3)">${a.created_at}</td>
      <td><strong>${esc(a.remetente || '—')}</strong></td>
      <td style="color:var(--text2)">${a.destinatario || 'Todos'}</td>
      <td>${esc(a.mensagem).substring(0,60)}...</td>
      <td>${badge(a.prioridade)}</td>
    </tr>
  `).join('');
  // Popular select de destinatários
  const sel = document.getElementById('al-dest');
  if (sel && r.operadores) {
    sel.innerHTML = '<option value="">Todos os operadores</option>' +
      r.operadores.map(o => `<option value="${o.id}">${esc(o.nome)}</option>`).join('');
  }
}

async function sendAlert() {
  const msg = fv('al-msg');
  if (!msg) { toast('Digite uma mensagem!', 'danger'); return; }
  const r = await api('alertas.php', {
    action:      'send',
    mensagem:    msg,
    destinatario_id: fv('al-dest') || null,
    prioridade:  fv('al-prior'),
  });
  if (r.ok) {
    toast('Alerta enviado!', 'success');
    sv('al-msg', '');
    loadAlertas();
    // Mostrar popup local
    showAlertPopup('🔔', msg);
  } else toast(r.msg || 'Erro', 'danger');
}

function showAlertPopup(icon, msg) {
  const p = document.getElementById('alert-popup');
  document.getElementById('ap-icon').textContent = icon || '🔔';
  document.getElementById('ap-msg').textContent = msg;
  p.style.display = 'block';
  p.style.animation = 'popup-slide .4s cubic-bezier(.34,1.56,.64,1)';
  setTimeout(() => { if (p.style.display !== 'none') p.style.display = 'none'; }, 8000);
}

async function checkAlerts() {
  const r = await api('alertas.php?action=unread');
  if (r.ok && r.data && r.data.length) {
    const a = r.data[0];
    const icons = { Normal:'🔔', Urgente:'⚠️', Critico:'🚨' };
    showAlertPopup(icons[a.prioridade] || '🔔', a.mensagem);
    await api('alertas.php', { action: 'mark_read', id: a.id });
  }
}

// ─── USUÁRIOS ──────────────────────────────────────────────

let usuariosData = [];

async function loadUsuarios() {
  const r = await api('usuarios.php?action=list');
  if (!r.ok) return;
  usuariosData = r.data;
  const tb = document.getElementById('tb-usuarios');
  if (!tb) return;
  tb.innerHTML = r.data.map(u => `
    <tr>
      <td class="font-mono">${esc(u.login)}</td>
      <td><strong>${esc(u.nome)}</strong></td>
      <td>${badge(u.nivel)}</td>
      <td style="color:var(--text2)">${esc(u.setor || '—')}</td>
      <td><span style="font-size:12px">${statusLabel(u.status_operador)}</span></td>
      <td style="color:var(--text3);font-size:12px">${u.ultimo_acesso || 'Nunca'}</td>
      <td>
        <div style="display:flex;gap:4px">
          <button class="btn btn-secondary btn-sm" onclick="editUsuario(${u.id})">✏</button>
          ${u.nivel !== 'MASTER' ? `<button class="btn btn-danger btn-sm" onclick="deleteUsuario(${u.id})">🗑</button>` : ''}
        </div>
      </td>
    </tr>
  `).join('');
}

function openUserModal() {
  sv('uu-id',''); sv('uu-nome',''); sv('uu-login',''); sv('uu-senha','');
  sv('uu-senha2',''); sv('uu-setor',''); sv('uu-email','');
  document.getElementById('modal-user-title').textContent = '👤 NOVO USUÁRIO';
  openModal('modal-usuario');
}

function editUsuario(id) {
  const u = usuariosData.find(x => x.id == id);
  if (!u) return;
  document.getElementById('modal-user-title').textContent = '✏ EDITAR USUÁRIO';
  sv('uu-id', u.id); sv('uu-nome', u.nome); sv('uu-login', u.login);
  sv('uu-nivel', u.nivel); sv('uu-setor', u.setor); sv('uu-email', u.email);
  sv('uu-senha',''); sv('uu-senha2','');
  openModal('modal-usuario');
}

async function saveUsuario() {
  const nome  = fv('uu-nome');
  const login = fv('uu-login');
  const senha = fv('uu-senha');
  const id    = fv('uu-id');
  if (!nome || !login) { toast('Preencha nome e login!', 'danger'); return; }
  if (!id && !senha)   { toast('Informe a senha!', 'danger'); return; }
  if (senha && senha !== fv('uu-senha2')) { toast('Senhas não conferem!', 'danger'); return; }
  const r = await api('usuarios.php', {
    action: id ? 'update' : 'create',
    id: id || undefined,
    nome, login, senha: senha || undefined,
    nivel: fv('uu-nivel'), setor: fv('uu-setor'), email: fv('uu-email'),
  });
  if (r.ok) { toast('Usuário salvo!', 'success'); closeModal('modal-usuario'); loadUsuarios(); }
  else toast(r.msg || 'Erro', 'danger');
}

async function deleteUsuario(id) {
  if (!confirm('Excluir este usuário?')) return;
  const r = await api('usuarios.php', { action: 'delete', id });
  if (r.ok) { toast('Usuário excluído!', 'warning'); loadUsuarios(); }
  else toast(r.msg || 'Erro', 'danger');
}

// ─── PERFIL ────────────────────────────────────────────────

async function savePerfil() {
  const nome  = fv('mp-nome');
  const email = fv('mp-email');
  const senha = fv('mp-senha');
  const status = fv('mp-status');
  if (senha && senha !== fv('mp-senha2')) { toast('Senhas não conferem!', 'danger'); return; }
  const r = await api('usuario.php', {
    action: 'update_profile', nome, email,
    senha: senha || undefined, status_operador: status,
  });
  if (r.ok) {
    toast('Perfil atualizado!', 'success');
    setStatus(status);
    closeModal('modal-perfil');
  } else toast(r.msg || 'Erro', 'danger');
}

// ─── RELATÓRIOS ────────────────────────────────────────────

async function loadRelatorios() {
  const r = await api('relatorios.php?action=produtividade');
  if (!r.ok) return;
  const tb = document.getElementById('tb-relatorio');
  if (!tb) return;
  tb.innerHTML = r.data.map(o => `
    <tr>
      <td><strong>${esc(o.operador)}</strong></td>
      <td>${o.atendimentos}</td>
      <td>${o.mensagens}</td>
      <td style="color:var(--success);font-weight:600">${o.acordos}</td>
      <td style="color:var(--warning);font-weight:600">R$ ${parseFloat(o.valor||0).toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
    </tr>
  `).join('') || '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:20px">Sem dados</td></tr>';
}

async function genReport(tipo) {
  toast('Gerando relatório... Em produção: exporta via API', 'info');
  const r = await api(`relatorios.php?action=${tipo}`);
  if (r.ok) toast(`Relatório de ${tipo} gerado!`, 'success');
}

// ─── LOGS ──────────────────────────────────────────────────

async function loadLogs() {
  const filter = document.getElementById('log-filter')?.value || '';
  const r = await api(`logs.php?action=list&filter=${encodeURIComponent(filter)}`);
  if (!r.ok) return;
  const tb = document.getElementById('tb-logs');
  tb.innerHTML = r.data.map(l => `
    <tr>
      <td style="font-family:var(--font-mono);font-size:11px;color:var(--text3)">${l.created_at}</td>
      <td><strong>${esc(l.usuario_login || '—')}</strong></td>
      <td class="font-mono" style="font-size:11px;color:var(--text3)">${l.ip}</td>
      <td>${badge(l.acao)}</td>
      <td style="color:var(--text2);font-size:12px">${esc(l.detalhes || '')}</td>
    </tr>
  `).join('') || '<tr><td colspan="5" style="text-align:center;color:var(--text3);padding:20px">Nenhum log</td></tr>';
}

// ─── WAHA CONFIG ───────────────────────────────────────────

async function loadWahaPage() {
  const r = await api('waha_config.php?action=get');
  if (!r.ok) return;
  sv('wc-server', r.data.servidor || 'http://127.0.0.1:3000');
  sv('wc-session', r.data.sessao || 'default');
  sv('wc-key', r.data.api_key || '');
  sv('wc-webhook', r.data.webhook_url || (APP_URL + '/api/webhook.php'));
  loadWahaStatus();
}

async function loadWahaStatus() {
  const el = document.getElementById('waha-session-info');
  if (el) el.textContent = 'Verificando...';
  const r = await api('waha_config.php?action=status');
  if (el) {
    if (r.ok) {
      const s = r.data;
      el.innerHTML = `<span class="badge badge-${s.status==='WORKING'?'success':'danger'}">● ${s.status}</span><br>
        <small style="color:var(--text3)">${s.name || 'Sessão: ' + (s.session||'—')}</small>`;
    } else {
      el.innerHTML = '<span class="badge badge-danger">● DESCONECTADO</span>';
    }
  }
  // Atualizar barra de status
  updateWahaBar(r.ok && r.data?.status === 'WORKING');
  addWahaLog(r.ok ? `INFO — Sessão ${r.data?.status || 'verificada'}` : 'ERROR — Falha ao verificar sessão');
}

async function testWaha() {
  const el = document.getElementById('waha-result');
  el.textContent = '⏳ Testando...';
  const r = await api('waha_config.php?action=test', {
    servidor: fv('wc-server'), sessao: fv('wc-session'), api_key: fv('wc-key'), webhook_url: fv('wc-webhook'),
  });
  if (r.ok) {
    el.innerHTML = `<span style="color:var(--success)">✓ Conexão estabelecida! Sessão: ${r.data?.name || 'default'} — Status: ${r.data?.status || 'OK'}</span>`;
    updateWahaBar(true);
    addWahaLog('SUCCESS — Teste de conexão OK');
  } else {
    el.innerHTML = `<span style="color:var(--danger)">✕ Erro: ${r.msg}</span>`;
    addWahaLog('ERROR — Falha no teste: ' + r.msg);
  }
}

async function saveWaha() {
  const r = await api('waha_config.php', {
    action: 'save',
    servidor: fv('wc-server'), sessao: fv('wc-session'),
    api_key: fv('wc-key'), webhook_url: fv('wc-webhook'),
  });
  if (r.ok) { toast('Configuração WAHA salva!', 'success'); addWahaLog('INFO — Config salva'); }
  else toast(r.msg || 'Erro', 'danger');
}

async function stopSession() {
  if (!confirm('Desconectar a sessão WhatsApp?')) return;
  const r = await api('waha_config.php', { action: 'stop' });
  if (r.ok) { toast('Sessão desconectada', 'warning'); loadWahaStatus(); }
}

function addWahaLog(msg) {
  const el = document.getElementById('waha-log');
  if (!el) return;
  const ts = new Date().toLocaleString('pt-BR');
  el.innerHTML += `[${ts}] ${msg}<br>`;
  el.scrollTop = el.scrollHeight;
}

function updateWahaBar(online) {
  const dot = document.getElementById('waha-dot');
  const txt = document.getElementById('waha-txt');
  if (!dot || !txt) return;
  dot.classList.toggle('off', !online);
  txt.textContent = online ? 'WAHA Conectado' : 'WAHA Offline';
}

// ─── WHITELABEL ────────────────────────────────────────────

let _tema = document.body.classList.contains('theme-red') ? 'red' : 'green';

function setTema(t) {
  _tema = t;
  document.body.classList.toggle('theme-red', t === 'red');
  document.getElementById('btn-green').className = 'btn ' + (t==='green' ? 'btn-primary' : 'btn-secondary');
  document.getElementById('btn-red').className   = 'btn ' + (t==='red'   ? 'btn-primary' : 'btn-secondary');
}

document.getElementById('wl-nome')?.addEventListener('input', e => {
  document.getElementById('wl-prev-nome').textContent = e.target.value;
});
document.getElementById('wl-tag')?.addEventListener('input', e => {
  document.getElementById('wl-prev-tag').textContent = e.target.value.toUpperCase();
});

async function saveWhitelabel() {
  const nome   = fv('wl-nome');
  const tagline = fv('wl-tag');
  const titulo  = fv('wl-titulo');
  const logo    = fv('wl-logo');
  if (!nome) { toast('Informe o nome do sistema!', 'danger'); return; }
  const r = await api('whitelabel.php', {
    action: 'save', nome, tagline, titulo, logo, tema: _tema,
  });
  if (r.ok) {
    toast('Configurações aplicadas em todo o sistema!', 'success');
    // Atualizar em tempo real TODAS as ocorrências do nome
    applyWhitelabelLive(nome, tagline, titulo, logo, _tema);
  } else toast(r.msg || 'Erro', 'danger');
}

function applyWhitelabelLive(nome, tagline, titulo, logo, tema) {
  // Atualiza TODAS as instâncias do nome no DOM
  document.getElementById('sl-nome').textContent = nome;
  document.getElementById('sl-tag').textContent  = tagline;
  document.title = titulo || nome;
  document.getElementById('doc-title')?.setAttribute('content', titulo || nome);

  // Logo na sidebar
  const logoEl = document.getElementById('sl-logo');
  if (logo) {
    logoEl.innerHTML = `<img src="${esc(logo)}" style="width:32px;height:32px;object-fit:contain;border-radius:8px" alt="Logo">`;
  } else {
    logoEl.textContent = '📱';
  }

  // Preview
  document.getElementById('wl-prev-nome').textContent = nome;
  document.getElementById('wl-prev-tag').textContent  = tagline.toUpperCase();
  if (logo) document.getElementById('wl-prev-icon').innerHTML = `<img src="${esc(logo)}" style="width:44px;height:44px;object-fit:contain;border-radius:10px">`;

  // Tema
  document.body.classList.toggle('theme-red', tema === 'red');
}

// ─── UTILITÁRIO DOM ────────────────────────────────────────

function esc(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── INIT ──────────────────────────────────────────────────

(function init() {
  // Inicializar status do operador na barra
  const si = STATUS_ICONS[CUR_USER.status] || '🟢';
  const st = statusLabel(CUR_USER.status);
  const btn = document.getElementById('op-status-btn');
  if (btn) btn.innerHTML = `${si} <span>${st}</span> ▾`;

  // Carregar dashboard inicial
  loadDashboard();

  // Polling de alertas a cada 30s
  setInterval(checkAlerts, 30000);

  // Polling de status WAHA a cada 60s
  setInterval(() => api('waha_config.php?action=status').then(r => updateWahaBar(r.ok && r.data?.status==='WORKING')), 60000);
  // Verificar WAHA logo no início
  api('waha_config.php?action=status').then(r => updateWahaBar(r.ok && r.data?.status==='WORKING'));

  // Badge de não lidas
  setInterval(updateBadges, 15000);
  updateBadges();
})();

async function updateBadges() {
  const r = await api('dashboard.php?action=badges');
  if (!r.ok) return;
  const nb = document.getElementById('nb-unread');
  const na = document.getElementById('nb-alerts');
  if (nb) { nb.textContent = r.data.unread || 0; nb.style.display = r.data.unread ? '' : 'none'; }
  if (na) { na.textContent = r.data.alerts || 0; na.style.display = r.data.alerts ? '' : 'none'; }
}
