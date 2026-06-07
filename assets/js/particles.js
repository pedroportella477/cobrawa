// COBRAWA — Partículas de fundo animadas
(function () {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, particles = [];
  const COUNT = 40;

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }

  function neonColor() {
    // Detectar tema
    const red = document.body.classList.contains('theme-red');
    return red ? 'rgba(255,34,68,' : 'rgba(0,255,136,';
  }

  function Particle() {
    this.reset();
  }
  Particle.prototype.reset = function () {
    this.x  = Math.random() * W;
    this.y  = H + 10;
    this.vx = (Math.random() - .5) * .4;
    this.vy = -(Math.random() * 1.2 + .3);
    this.r  = Math.random() * 1.5 + .5;
    this.life = 0;
    this.maxLife = Math.random() * 200 + 100;
  };
  Particle.prototype.update = function () {
    this.x += this.vx;
    this.y += this.vy;
    this.life++;
    if (this.life > this.maxLife || this.y < -10) this.reset();
  };
  Particle.prototype.draw = function () {
    const alpha = Math.sin((this.life / this.maxLife) * Math.PI) * .7;
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.r, 0, Math.PI * 2);
    ctx.fillStyle = neonColor() + alpha + ')';
    ctx.fill();
  };

  resize();
  for (let i = 0; i < COUNT; i++) {
    const p = new Particle();
    p.y = Math.random() * H; // spread initial
    p.life = Math.floor(Math.random() * p.maxLife);
    particles.push(p);
  }

  function loop() {
    ctx.clearRect(0, 0, W, H);
    particles.forEach(p => { p.update(); p.draw(); });
    requestAnimationFrame(loop);
  }
  loop();
  window.addEventListener('resize', resize);
})();
