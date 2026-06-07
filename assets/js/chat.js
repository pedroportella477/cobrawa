// ============================================================
// COBRAWA — Módulo de Chat
// ============================================================

'use strict';

let activeClient    = null;
let activeConversaId = null;
let msgPolling      = null;
let mediaRecorder   = null;
let recChunks       = [];
let recTimer        = null;
let recSec          = 0;
let micPermission   = null; // null = não perguntado, true/false = resultado

// ─── CONTATOS ──────────────────────────────────────────────

async function loadContacts(search = '') {
  const r = await api(`chat.php?action=contacts&search=${encodeURIComponent(search)}`);
  if (!r.ok) return;
  const scroll = document.getElementById('contacts-scroll');
  if (!scroll) return;
  scroll.innerHTML = r.data.map(c => {
    const initials = c.nome.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    const unread   = parseInt(c.nao_lidas) || 0;
    return `
      <div class="contact-item ${activeClient?.id == c.id ? 'active' : ''}"
           onclick="openChat(${c.id})" id="ci-${c.id}">
        <div class="c-avatar">
          ${initials}
          ${c.status_operador === 'online' ? '<div class="online-dot"></div>' : ''}
        </div>
        <div class="c-info">
          <div class="c-name">${esc(c.nome)}</div>
          <div class="c-preview">${esc(c.ultima_msg || 'Sem mensagens')}</div>
        </div>
        <div class="c-meta">
          <div class="c-time">${c.ultima_hora || ''}</div>
          ${unread ? `<div class="c-unread">${unread}</div>` : ''}
        </div>
      </div>
    `;
  }).join('') || '<div style="color:var(--text3);text-align:center;padding:30px">Nenhum contato</div>';
}

function filterContacts(q) {
  loadContacts(q);
}

async function openChatWithClient(clientId) {
  showPage('chat');
  await loadContacts();
  setTimeout(() => openChat(clientId), 300);
}

async function openChat(clientId) {
  // Marcar ativo
  document.querySelectorAll('.contact-item').forEach(el => el.classList.remove('active'));
  document.getElementById('ci-' + clientId)?.classList.add('active');

  // Buscar dados do cliente
  const r = await api(`chat.php?action=get_client&id=${clientId}`);
  if (!r.ok) { toast(r.msg || 'Erro ao abrir chat', 'danger'); return; }
  activeClient = r.data.cliente;

  // Criar ou obter conversa
  const rc = await api('chat.php', { action: 'get_or_create_conversa', cliente_id: clientId });
  if (!rc.ok) { toast('Erro ao iniciar conversa', 'danger'); return; }
  activeConversaId = rc.data.id;
  const protocolo  = rc.data.protocolo;

  // Renderizar janela de chat
  renderChatWindow(activeClient, protocolo, rc.data);

  // Carregar mensagens
  await loadMessages();

  // Iniciar polling
  if (msgPolling) clearInterval(msgPolling);
  msgPolling = setInterval(pollMessages, 5000);

  // Marcar como lidas na WAHA
  if (activeClient.whatsapp) {
    api('chat.php', { action: 'mark_seen', whatsapp: activeClient.whatsapp }).catch(() => {});
  }

  // Se havia msg pronta selecionada
  if (window._selectedMsg) {
    applyMsgPronta(window._selectedMsg);
    window._selectedMsg = null;
  }
}

function renderChatWindow(client, protocolo, conversa) {
  const w = document.getElementById('chat-window');
  const initials = client.nome.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
  const valor = parseFloat(client.valor_divida || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });

  w.innerHTML = `
    <div class="chat-header">
      <div class="c-avatar" style="width:38px;height:38px;font-size:14px">${initials}</div>
      <div class="chat-header-info">
        <div class="chat-header-name">${esc(client.nome)}</div>
        <div class="chat-header-sub">📱 ${esc(client.whatsapp)} · Dívida: R$ ${valor}</div>
      </div>
      <div class="chat-header-actions">
        <select class="status-select" onchange="updateClienteStatus(${client.id}, this.value)" title="Status de cobrança">
          ${['Pendente','Em negociacao','Pago','Judicial','Equipamento retirado','Sem Contato']
            .map(s => `<option value="${s}" ${client.status_cobranca===s?'selected':''}>${s==='Em negociacao'?'Em negociação':s}</option>`).join('')}
        </select>
        <button class="btn btn-secondary btn-sm" onclick="openTransferModal()">↔ Transferir</button>
        <button class="btn btn-primary btn-sm" onclick="assumirAtendimento()">✓ Assumir</button>
        <button class="btn btn-secondary btn-sm" onclick="showPage('clientes');editCliente(${client.id})">👤 Perfil</button>
      </div>
    </div>
    <div class="protocol-bar">
      🔖 Protocolo: <span class="protocol-code">${protocolo}</span>
      · Status: <span id="conv-status" style="color:var(--${conversa.status==='aberta'?'success':'text3'})">${conversa.status}</span>
    </div>
    <div class="chat-messages" id="chat-messages"></div>
    <div class="chat-input-area">
      <div class="chat-tools">
        <div class="tool-btn" title="Emoji" data-emoji-trigger onclick="EmojiPicker.toggle(document.getElementById('chat-msg-input'), this)">😊</div>
        <div class="tool-btn" title="Anexar arquivo" onclick="attachFile()">📎</div>
        <div class="tool-btn" title="Gravar áudio" id="audio-btn" onclick="handleAudio()">🎤</div>
        <div class="tool-btn" title="Mensagens prontas" onclick="showMsgProntaMenu()">📋</div>
      </div>
      <div class="chat-input-wrap" style="position:relative">
        <div class="recording-bar" id="recording-bar">
          <div class="rec-dot"></div>
          <span class="rec-time" id="rec-time">00:00</span>
          <span style="color:var(--text3);font-size:12px">Gravando...</span>
          <button class="btn btn-danger btn-sm" style="margin-left:auto" onclick="cancelAudio()">✕</button>
          <button class="btn btn-primary btn-sm" onclick="stopAudio()">⏹ Enviar</button>
        </div>
        <textarea class="chat-input" id="chat-msg-input" rows="1"
          placeholder="Digite uma mensagem..."
          onkeydown="handleMsgKey(event)"
          oninput="autoResizeChat(this)"></textarea>
        <!-- Msg pronta picker -->
        <div id="msg-pronta-menu" style="display:none;position:absolute;bottom:calc(100% + 8px);left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:var(--radius-lg);padding:8px;z-index:300;max-height:220px;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.6)">
          <div style="font-size:11px;color:var(--text3);padding:4px 8px;margin-bottom:4px">MENSAGENS PRONTAS</div>
          <div id="msg-pronta-list"></div>
        </div>
      </div>
      <button class="chat-send" onclick="sendChatMessage()">➤</button>
    </div>
  `;
  // Fechar menu ao clicar fora
  document.addEventListener('click', e => {
    if (!e.target.closest('#msg-pronta-menu') && !e.target.closest('.tool-btn[onclick*="showMsgPronta"]')) {
      document.getElementById('msg-pronta-menu')?.style && (document.getElementById('msg-pronta-menu').style.display = 'none');
    }
  });
}

// ─── MENSAGENS ─────────────────────────────────────────────

async function loadMessages() {
  if (!activeConversaId) return;
  const r = await api(`chat.php?action=messages&conversa_id=${activeConversaId}`);
  if (!r.ok) return;
  renderMessages(r.data);
}

async function pollMessages() {
  if (!activeConversaId || currentPage !== 'chat') return;
  const r = await api(`chat.php?action=messages&conversa_id=${activeConversaId}&after=${window._lastMsgId || 0}`);
  if (!r.ok || !r.data.length) return;
  appendMessages(r.data);
}

function renderMessages(msgs) {
  const cont = document.getElementById('chat-messages');
  if (!cont) return;
  if (!msgs.length) {
    cont.innerHTML = '<div style="text-align:center;color:var(--text3);padding:40px">Sem mensagens. Inicie a conversa!</div>';
    return;
  }
  cont.innerHTML = `<div class="msg-date-sep">${new Date().toLocaleDateString('pt-BR', { weekday:'long', day:'numeric', month:'long' })}</div>`;
  msgs.forEach(m => appendMsg(cont, m, false));
  window._lastMsgId = msgs[msgs.length - 1]?.id || 0;
  cont.scrollTop = cont.scrollHeight;
}

function appendMessages(msgs) {
  const cont = document.getElementById('chat-messages');
  if (!cont) return;
  msgs.forEach(m => appendMsg(cont, m, true));
  window._lastMsgId = msgs[msgs.length - 1]?.id || 0;
  cont.scrollTop = cont.scrollHeight;
}

function appendMsg(cont, m, scroll = true) {
  const sent = m.direcao === 'enviada';
  const div  = document.createElement('div');
  div.className = 'msg-row ' + (sent ? 'sent' : 'received');
  let content = '';
  if (m.tipo === 'audio') {
    content = `<audio controls src="${esc(m.arquivo_url || '')}" style="max-width:240px"></audio>`;
  } else if (m.tipo === 'imagem') {
    content = `<img src="${esc(m.arquivo_url || '')}" style="max-width:220px;border-radius:8px;display:block;margin-bottom:4px"><span>${esc(m.conteudo || '')}</span>`;
  } else if (m.tipo === 'documento') {
    content = `<a href="${esc(m.arquivo_url || '')}" target="_blank" style="color:var(--neon)">📎 ${esc(m.conteudo || 'Documento')}</a>`;
  } else {
    content = esc(m.conteudo || '').replace(/\n/g, '<br>');
  }
  div.innerHTML = `
    <div class="msg-bubble">
      ${!sent ? `<div class="msg-sender">${esc(activeClient?.nome || 'Cliente')}</div>` : ''}
      ${content}
      <div class="msg-time">${m.hora || ''} ${sent ? '<span style="color:var(--neon)">✓✓</span>' : ''}</div>
    </div>
  `;
  cont.appendChild(div);
  if (scroll) cont.scrollTop = cont.scrollHeight;
}

async function sendChatMessage(text = null, tipo = 'texto', arquivo_url = null) {
  const input = document.getElementById('chat-msg-input');
  const msg   = text !== null ? text : (input?.value?.trim() || '');
  if (!msg && tipo === 'texto') return;
  if (!activeClient || !activeConversaId) { toast('Selecione um contato!', 'danger'); return; }

  const r = await api('chat.php', {
    action:       'send',
    conversa_id:  activeConversaId,
    cliente_id:   activeClient.id,
    whatsapp:     activeClient.whatsapp,
    conteudo:     msg,
    tipo,
    arquivo_url,
  });

  if (r.ok) {
    if (input && tipo === 'texto') { input.value = ''; input.style.height = 'auto'; }
    appendMessages([r.data]);
  } else toast(r.msg || 'Erro ao enviar', 'danger');
}

function handleMsgKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
}

function autoResizeChat(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ─── ASSUMIR ATENDIMENTO ───────────────────────────────────

async function assumirAtendimento() {
  if (!activeConversaId) return;
  const r = await api('chat.php', { action: 'assumir', conversa_id: activeConversaId });
  if (r.ok) {
    toast('Atendimento assumido!', 'success');
    document.getElementById('conv-status').textContent = 'aberta';
    document.getElementById('conv-status').style.color = 'var(--success)';
  } else toast(r.msg || 'Erro', 'danger');
}

// ─── TRANSFERÊNCIA ─────────────────────────────────────────

async function openTransferModal() {
  if (!activeConversaId) return;
  sv('tr-conv-id', activeConversaId);
  // Carregar operadores
  const r = await api('usuarios.php?action=operadores');
  const sel = document.getElementById('tr-dest');
  if (r.ok && sel) {
    sel.innerHTML = r.data
      .filter(u => u.id != CUR_USER.id)
      .map(u => `<option value="${u.id}">${esc(u.nome)} (${u.nivel})</option>`).join('');
  }
  openModal('modal-transfer');
}

async function doTransfer() {
  const motivo = fv('tr-motivo');
  if (!motivo) { toast('Informe o motivo!', 'danger'); return; }
  const r = await api('chat.php', {
    action:         'transfer',
    conversa_id:    fv('tr-conv-id'),
    operador_destino_id: fv('tr-dest'),
    motivo,
  });
  if (r.ok) { toast('Atendimento transferido!', 'success'); closeModal('modal-transfer'); loadContacts(); }
  else toast(r.msg || 'Erro', 'danger');
}

// ─── ÁUDIO ─────────────────────────────────────────────────

function handleAudio() {
  if (mediaRecorder && mediaRecorder.state === 'recording') {
    stopAudio();
    return;
  }
  startAudio();
}

async function startAudio() {
  // Pedir permissão apenas uma vez
  if (micPermission === null) {
    try {
      await navigator.mediaDevices.getUserMedia({ audio: true }).then(s => { s.getTracks().forEach(t => t.stop()); });
      micPermission = true;
    } catch (e) {
      micPermission = false;
      toast('Permissão de microfone negada. Acesse as configurações do navegador.', 'danger');
      return;
    }
  }
  if (!micPermission) { toast('Microfone sem permissão.', 'danger'); return; }

  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    recChunks = [];
    mediaRecorder = new MediaRecorder(stream);
    mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recChunks.push(e.data); };
    mediaRecorder.onstop = handleAudioStop;
    mediaRecorder.start();

    // UI
    document.getElementById('recording-bar').classList.add('show');
    document.getElementById('chat-msg-input').style.display = 'none';
    document.getElementById('audio-btn').style.background = 'rgba(255,68,100,.2)';
    recSec = 0;
    recTimer = setInterval(() => {
      recSec++;
      const m = String(Math.floor(recSec / 60)).padStart(2, '0');
      const s = String(recSec % 60).padStart(2, '0');
      document.getElementById('rec-time').textContent = `${m}:${s}`;
      if (recSec >= 300) stopAudio(); // max 5 min
    }, 1000);
  } catch (e) {
    toast('Erro ao acessar microfone: ' + e.message, 'danger');
  }
}

function stopAudio() {
  if (!mediaRecorder || mediaRecorder.state !== 'recording') return;
  mediaRecorder.stop();
  mediaRecorder.stream?.getTracks().forEach(t => t.stop());
  clearInterval(recTimer);
  document.getElementById('recording-bar').classList.remove('show');
  document.getElementById('chat-msg-input').style.display = '';
  document.getElementById('audio-btn').style.background = '';
}

function cancelAudio() {
  recChunks = [];
  stopAudio();
  toast('Gravação cancelada', 'warning');
}

async function handleAudioStop() {
  if (!recChunks.length) return;
  const blob = new Blob(recChunks, { type: 'audio/webm' });
  if (blob.size < 1000) { toast('Áudio muito curto', 'warning'); return; }

  // Upload do áudio
  const fd = new FormData();
  fd.append('audio', blob, 'audio.webm');
  fd.append('_csrf', CSRF);
  try {
    const r = await fetch(APP_URL + '/api/upload.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    });
    const data = await r.json();
    if (data.ok) {
      await sendChatMessage('🎤 Áudio', 'audio', data.url);
      toast('Áudio enviado!', 'success');
    } else toast(data.msg || 'Erro no upload', 'danger');
  } catch (e) {
    toast('Erro ao enviar áudio', 'danger');
  }
}

// ─── ARQUIVO ───────────────────────────────────────────────

function attachFile() {
  const inp = document.createElement('input');
  inp.type = 'file';
  inp.accept = 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt';
  inp.onchange = async e => {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 16 * 1024 * 1024) { toast('Arquivo muito grande (máx 16MB)', 'danger'); return; }
    const fd = new FormData();
    fd.append('file', file);
    fd.append('_csrf', CSRF);
    toast('Enviando arquivo...', 'info');
    try {
      const r   = await fetch(APP_URL + '/api/upload.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      const data = await r.json();
      if (data.ok) {
        const tipo = file.type.startsWith('image/') ? 'imagem' : 'documento';
        await sendChatMessage(file.name, tipo, data.url);
        toast('Arquivo enviado!', 'success');
      } else toast(data.msg || 'Erro', 'danger');
    } catch (e) {
      toast('Erro ao enviar arquivo', 'danger');
    }
  };
  inp.click();
}

// ─── MENSAGENS PRONTAS NO CHAT ─────────────────────────────

async function showMsgProntaMenu() {
  const menu = document.getElementById('msg-pronta-menu');
  if (!menu) return;
  if (menu.style.display !== 'none') { menu.style.display = 'none'; return; }

  const r = await api('msgs.php?action=list');
  const list = document.getElementById('msg-pronta-list');
  if (r.ok && list) {
    list.innerHTML = r.data.map(m => `
      <div onclick="applyMsgPronta(${JSON.stringify(m).replace(/"/g,'&quot;')})"
           style="padding:8px 10px;cursor:pointer;border-radius:6px;font-size:12px;color:var(--text2);border-bottom:1px solid var(--border);transition:background .12s"
           onmouseover="this.style.background='var(--glass2)'" onmouseout="this.style.background=''">
        <div style="font-weight:500;color:var(--text)">${esc(m.titulo)}</div>
        <div style="font-size:11px;margin-top:1px">${esc(m.corpo.substring(0,60))}...</div>
      </div>
    `).join('') || '<div style="color:var(--text3);padding:8px 10px">Nenhuma mensagem</div>';
  }
  menu.style.display = 'block';
}

function applyMsgPronta(m) {
  if (typeof m === 'string') m = JSON.parse(m);
  let texto = m.corpo;
  if (activeClient) {
    const valor = parseFloat(activeClient.valor_divida || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    const prot  = document.querySelector('.protocol-code')?.textContent || '';
    texto = texto
      .replace(/{nome}/g,        activeClient.nome || '')
      .replace(/{valor}/g,       'R$ ' + valor)
      .replace(/{produto}/g,     activeClient.produto || '')
      .replace(/{vencimento}/g,  activeClient.data_vencimento || '')
      .replace(/{protocolo}/g,   prot);
  }
  const inp = document.getElementById('chat-msg-input');
  if (inp) { inp.value = texto; autoResizeChat(inp); inp.focus(); }
  const menu = document.getElementById('msg-pronta-menu');
  if (menu) menu.style.display = 'none';
}
