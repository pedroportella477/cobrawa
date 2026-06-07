// COBRAWA — Emoji Picker leve (sem dependências)
const EmojiPicker = (function () {
  const CATS = {
    '😊': ['😊','😀','😁','😂','🤣','😃','😄','😅','😆','😉','😋','😎','😍','🥰','😘','😗','😙','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽','🤖'],
    '👍': ['👍','👎','👊','✊','🤛','🤜','🤞','✌️','🤟','🤘','👌','🤌','🤏','👈','👉','👆','👇','☝️','👋','🤚','🖐️','✋','🖖','💪','🦵','🦶','👂','🦻','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️','👅','👄','🫦'],
    '❤️': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💝','💘','💟','☮️','✝️','☪️','🕉️','☸️','✡️','🔯','🕎','☯️','☦️','🛐','⛎','♈','♉','♊','♋','♌','♍','♎','♏','♐','♑','♒','♓'],
    '🎉': ['🎉','🎊','🎈','🎁','🎀','🎗️','🎟️','🎫','🏆','🥇','🥈','🥉','🏅','🎖️','🎪','🤹','🎭','🎨','🎬','🎤','🎧','🎼','🎵','🎶','🎹','🥁','🪘','🎷','🎺','🎸','🎻','🪕','🎮','🕹️','🎲','♟️','🎯','🎳','🎰','🎱'],
    '🌍': ['🌍','🌎','🌏','🌐','🗺️','🧭','🌋','⛰️','🏔️','🗻','🏕️','🏖️','🏜️','🏝️','🏞️','🏟️','🏛️','🏗️','🧱','🪨','🪵','🛖','🏠','🏡','🏢','🏣','🏤','🏥','🏦','🏨','🏩','🏪','🏫','🏬','🏭','🏯','🏰','💒','🗼','🗽'],
    '🐶': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐔','🐧','🐦','🐤','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🪱','🐛','🦋','🐌','🐞','🐜','🪲','🦗','🕷️'],
    '🍎': ['🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑','🫒','🥦','🥬','🥒','🌶️','🫑','🧄','🧅','🥔','🍠','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳','🧈','🥞','🧇'],
    '🚗': ['🚗','🚕','🚙','🚌','🚎','🏎️','🚓','🚑','🚒','🚐','🛻','🚚','🚛','🚜','🏍️','🛵','🛺','🚲','🛴','🛹','🛼','🚏','🛣️','🛤️','⛽','🚨','🚥','🚦','🛑','🚧','⚓','🪝','⛵','🚤','🛥️','🛳️','⛴️','🚢','✈️','🛩️'],
  };

  let currentCat = '😊';
  let targetInput = null;
  let container = null;

  function build() {
    if (document.getElementById('cobrawa-emoji-picker')) return;
    const el = document.createElement('div');
    el.className = 'emoji-picker';
    el.id = 'cobrawa-emoji-picker';
    el.innerHTML = `
      <div class="emoji-tabs" id="ep-tabs"></div>
      <div class="emoji-search"><input type="text" placeholder="Buscar emoji..." id="ep-search" oninput="EmojiPicker.search(this.value)"></div>
      <div class="emoji-grid" id="ep-grid"></div>
    `;
    document.body.appendChild(el);
    container = el;

    const tabs = document.getElementById('ep-tabs');
    Object.keys(CATS).forEach((k, i) => {
      const t = document.createElement('span');
      t.className = 'emoji-tab' + (i === 0 ? ' active' : '');
      t.textContent = k;
      t.title = k;
      t.onclick = () => { EmojiPicker.setCategory(k); tabs.querySelectorAll('.emoji-tab').forEach(x => x.classList.remove('active')); t.classList.add('active'); };
      tabs.appendChild(t);
    });
    renderGrid(CATS[currentCat]);

    document.addEventListener('click', (e) => {
      if (!container.contains(e.target) && !e.target.closest('[data-emoji-trigger]')) {
        EmojiPicker.hide();
      }
    });
  }

  function renderGrid(emojis) {
    const grid = document.getElementById('ep-grid');
    if (!grid) return;
    grid.innerHTML = '';
    emojis.forEach(e => {
      const btn = document.createElement('span');
      btn.className = 'emoji-btn';
      btn.textContent = e;
      btn.onclick = () => EmojiPicker.insert(e);
      grid.appendChild(btn);
    });
  }

  return {
    init() { build(); },
    toggle(inputEl, triggerEl) {
      if (!container) build();
      targetInput = inputEl;
      if (container.classList.contains('show')) {
        this.hide();
        return;
      }
      // Position near trigger
      const rect = triggerEl.getBoundingClientRect();
      container.style.position = 'fixed';
      container.style.bottom = (window.innerHeight - rect.top + 8) + 'px';
      container.style.left = rect.left + 'px';
      container.style.zIndex = 9999;
      container.classList.add('show');
    },
    hide() {
      if (container) container.classList.remove('show');
    },
    setCategory(cat) {
      currentCat = cat;
      renderGrid(CATS[cat]);
    },
    search(q) {
      if (!q) { renderGrid(CATS[currentCat]); return; }
      const all = Object.values(CATS).flat();
      // Basic: just show all emojis filtered (emoji doesn't have text search easily, show all)
      renderGrid(all.slice(0, 64));
    },
    insert(emoji) {
      if (!targetInput) return;
      const start = targetInput.selectionStart;
      const end   = targetInput.selectionEnd;
      const val   = targetInput.value;
      targetInput.value = val.slice(0, start) + emoji + val.slice(end);
      targetInput.selectionStart = targetInput.selectionEnd = start + emoji.length;
      targetInput.focus();
      // Trigger resize if textarea
      const ev = new Event('input', { bubbles: true });
      targetInput.dispatchEvent(ev);
    }
  };
})();

EmojiPicker.init();
