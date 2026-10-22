/* Fábio Lourenço — main.js
   Tudo dentro de uma função para não colidir com variáveis globais do browser
   (a antiga "const top" bloqueava o script inteiro, incluindo o formulário). */
(() => {
'use strict';
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const calmo = matchMedia('(prefers-reduced-motion: reduce)').matches;
const rato = matchMedia('(hover:hover) and (pointer:fine)').matches;

/* ---------- Cabeçalho, botão WhatsApp e menu ---------- */
const header = $('.top'), fab = $('.fab');
const onScroll = () => {
  header.classList.toggle('solid', scrollY > 40);
  fab.classList.toggle('show', scrollY > 700);
};
addEventListener('scroll', onScroll, { passive: true }); onScroll();

const menuBtn = $('.menu-btn'), nav = $('#menu');
menuBtn.addEventListener('click', () => {
  const aberto = nav.classList.toggle('open');
  menuBtn.setAttribute('aria-expanded', aberto);
});
$$('a', nav).forEach((a) => a.addEventListener('click', () => {
  nav.classList.remove('open'); menuBtn.setAttribute('aria-expanded', 'false');
}));

/* ---------- Gráfico: desenha as curvas quando entra no ecrã ---------- */
const chart = $('#chart');
if (chart && 'IntersectionObserver' in window && !calmo) {
  chart.classList.add('pre');
  new IntersectionObserver((e, o) => {
    if (e[0].isIntersecting) { chart.classList.remove('pre'); o.disconnect(); }
  }, { threshold: 0.5 }).observe(chart);
}

/* ---------- Formulário → WhatsApp ---------- */
const form = $('form[data-envio="whatsapp"]');
if (form) {
  const estado = $('#form-status'), botao = $('button[type=submit]', form);
  const textoInicial = estado.textContent;
  const tel = form.elements['Telefone'];

  // Telemóvel português: 9 dígitos a começar por 2 ou 9 (aceita +351 / 00351)
  const telValido = (v) => /^[29]\d{8}$/.test(v.replace(/[\s.\-()]/g, '').replace(/^(\+|00)?351/, ''));
  tel.addEventListener('input', () => tel.setCustomValidity(''));

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!telValido(tel.value)) {
      tel.setCustomValidity('Indique um número válido com 9 dígitos, por exemplo 912 345 678.');
      tel.reportValidity(); return;
    }
    const d = new FormData(form);
    const v = (k) => (d.get(k) || '').toString().trim();
    const zona = [v('Freguesia'), v('Concelho')].filter(Boolean).join(', ');

    const linhas = [
      `Olá Fábio! Gostaria de pedir uma *avaliação gratuita* do meu imóvel.`,
      '',
      `*Nome:* ${v('Nome')}`,
      `*Telemóvel:* ${v('Telefone')}`,
      v('Email') ? `*E-mail:* ${v('Email')}` : null,
      `*Tipo de imóvel:* ${v('Tipo de imóvel')}`,
      `*Localização:* ${zona}`,
      v('Área aproximada') ? `*Área aproximada:* ${v('Área aproximada')} m²` : null,
      `*Objetivo:* ${v('Objetivo')}`,
      `*Prazo:* ${v('Prazo')}`,
      '',
      'Enviado através do site.'
    ].filter((l) => l !== null);

    const url = `https://wa.me/${form.dataset.numero}?text=${encodeURIComponent(linhas.join('\n'))}`;

    // Guarda o pedido na base de dados (api/pedido.php). Corre em segundo plano e nunca bloqueia o WhatsApp.
    try {
      fetch('api/pedido.php', {
        method: 'POST', keepalive: true,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nome: v('Nome'), telefone: v('Telefone'), email: v('Email'), tipo: v('Tipo de imóvel'),
          concelho: v('Concelho'), freguesia: v('Freguesia'), area: v('Área aproximada'),
          objetivo: v('Objetivo'), prazo: v('Prazo'), website: v('website'), consentimento: !!d.get('consent')
        })
      }).catch(() => {});
    } catch (err) { /* sem base de dados disponível: o WhatsApp continua a funcionar */ }

    botao.classList.add('loading'); botao.disabled = true;
    estado.textContent = 'A abrir o WhatsApp… só tem de carregar em enviar.';
    estado.classList.add('ok');

    // Em telemóvel abre a própria app; se o browser bloquear o novo separador, segue na mesma janela.
    const janela = open(url, '_blank', 'noopener');
    if (!janela) location.href = url;

    setTimeout(() => {
      botao.classList.remove('loading'); botao.disabled = false;
      estado.classList.remove('ok'); estado.textContent = textoInicial;
    }, 4000);
  });

  // Zonas clicáveis: pré-selecionam o concelho no formulário
  $$('[data-zona]').forEach((a) => a.addEventListener('click', () => {
    form.elements['Concelho'].value = a.dataset.zona;
    setTimeout(() => form.elements['Nome'].focus({ preventScroll: true }), 700);
  }));
}

/* ---------- Retrato 3D: um único sistema, suave, com o rato ---------- */
const portrait = $('.portrait'), stage = $('.stage');
if (portrait && stage && rato && !calmo) {
  let alvoX = 2, alvoY = -9, x = 2, y = -9, ativo = false;
  const anima = () => {
    x += (alvoX - x) * .08; y += (alvoY - y) * .08;
    stage.style.setProperty('--rx', x.toFixed(2) + 'deg');
    stage.style.setProperty('--ry', y.toFixed(2) + 'deg');
    if (ativo || Math.abs(alvoX - x) > .02 || Math.abs(alvoY - y) > .02) requestAnimationFrame(anima);
  };
  portrait.addEventListener('pointerenter', () => { ativo = true; requestAnimationFrame(anima); });
  portrait.addEventListener('pointermove', (e) => {
    const r = portrait.getBoundingClientRect();
    const px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
    alvoY = px * 18 - 4; alvoX = -py * 12 + 2;
  });
  portrait.addEventListener('pointerleave', () => { ativo = false; alvoX = 2; alvoY = -9; requestAnimationFrame(anima); });
}

/* ---------- Brilho que segue o rato nos cartões ---------- */
if (rato) {
  $$('.cmp,.prop-card,.card-form,.c-panel').forEach((el) => {
    el.addEventListener('pointermove', (e) => {
      const b = el.getBoundingClientRect();
      el.style.setProperty('--mx', (e.clientX - b.left) + 'px');
      el.style.setProperty('--my', (e.clientY - b.top) + 'px');
    });
  });
}

const G = window.gsap, ST = window.ScrollTrigger;
if (G && ST && !calmo) G.registerPlugin(ST);
let scroll = 0; // progresso do scroll no hero (0 a 1), usado pela cena 3D

/* ---------- Cena 3D (Three.js): cidade em blocos, com "o seu imóvel" a vermelho ---------- */
(function cena() {
  const cv = $('#scene'), hero = $('.hero');
  if (!window.THREE || calmo || !cv) return;
  let r; try { r = new THREE.WebGLRenderer({ canvas: cv, antialias: true, alpha: true, powerPreference: 'high-performance' }); } catch (e) { return; }
  // Até 2.5x: nítido em ecrãs retina e 4K sem pesar demasiado
  r.setPixelRatio(Math.min(devicePixelRatio || 1, 2.5));
  const s = new THREE.Scene(); s.fog = new THREE.Fog(0x060A13, 16, 36);
  const cam = new THREE.PerspectiveCamera(38, 1, .1, 100);
  s.add(new THREE.AmbientLight(0x6f95ff, .7));
  const sol = new THREE.DirectionalLight(0xffffff, 1.2); sol.position.set(6, 10, 7); s.add(sol);
  const g = new THREE.Group(); s.add(g);
  const mat = new THREE.MeshStandardMaterial({ color: 0x102252, roughness: .45, metalness: .3 });
  const hot = new THREE.MeshStandardMaterial({ color: 0xd7222b, emissive: 0x7a0c14, roughness: .3 });
  const edge = new THREE.LineBasicMaterial({ color: 0x3a6bff, transparent: true, opacity: .55 });
  let seed = 11; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
  const ALVO = { x: 2, z: -1 };
  for (let x = -7; x <= 7; x++) for (let z = -7; z <= 7; z++) {
    const alvo = x === ALVO.x && z === ALVO.z;
    if (!alvo && rnd() < .28) continue;
    const h = alvo ? 5.4 : .4 + Math.pow(rnd(), 2) * 4.2;
    const geo = new THREE.BoxGeometry(.85, h, .85); geo.translate(0, h / 2, 0);
    const m = new THREE.Mesh(geo, alvo ? hot : mat);
    m.position.set(x * 1.3, 0, z * 1.3); m.scale.y = .001;
    m.add(new THREE.LineSegments(new THREE.EdgesGeometry(geo), edge)); g.add(m);
    G ? G.to(m.scale, { y: 1, duration: 1.6, delay: .2 + Math.hypot(x, z) * .07, ease: 'power3.out' }) : (m.scale.y = 1);
  }
  const chao = new THREE.GridHelper(22, 22, 0x3a6bff, 0x14306e); chao.material.transparent = true; chao.material.opacity = .35; g.add(chao);

  // Anel a pulsar à volta do imóvel
  const anel = new THREE.Mesh(new THREE.RingGeometry(.75, .82, 64), new THREE.MeshBasicMaterial({ color: 0xd7222b, transparent: true, opacity: .8, side: THREE.DoubleSide }));
  anel.rotation.x = -Math.PI / 2; anel.position.set(ALVO.x * 1.3, .02, ALVO.z * 1.3); g.add(anel);
  const luz = new THREE.PointLight(0xd7222b, 1.4, 7); luz.position.set(ALVO.x * 1.3, 6.2, ALVO.z * 1.3); g.add(luz);

  // Partículas de luz a subir
  const N = 140, pos = new Float32Array(N * 3), vel = new Float32Array(N);
  for (let i = 0; i < N; i++) { pos[i * 3] = (rnd() - .5) * 20; pos[i * 3 + 1] = rnd() * 9; pos[i * 3 + 2] = (rnd() - .5) * 20; vel[i] = .004 + rnd() * .008; }
  const pg = new THREE.BufferGeometry(); pg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  const pts = new THREE.Points(pg, new THREE.PointsMaterial({ color: 0x8fb0ff, size: .06, transparent: true, opacity: .7, depthWrite: false })); g.add(pts);

  let mx = 0, my = 0, vis = true, t = 0;
  addEventListener('pointermove', (e) => { mx = e.clientX / innerWidth - .5; my = e.clientY / innerHeight - .5; }, { passive: true });
  new IntersectionObserver((e) => (vis = e[0].isIntersecting)).observe(hero);
  const fit = () => { const w = hero.clientWidth, h = hero.clientHeight; r.setSize(w, h, false); cam.aspect = w / h; cam.updateProjectionMatrix(); };
  new ResizeObserver(fit).observe(hero); fit();
  r.setAnimationLoop(() => {
    if (!vis || document.hidden) return;
    t += .004;
    g.rotation.y += ((t * .6 + mx * .5 + scroll * 1.2) - g.rotation.y) * .06;
    g.position.y += ((-scroll * 3) - g.position.y) * .06;
    cam.position.set(9 + mx * 2, 8.5 - my * 2, 13);
    cam.lookAt(2.5, 1.2, 0);
    hot.emissiveIntensity = .8 + Math.sin(t * 25) * .35;
    const k = (t * 14) % 1; anel.scale.setScalar(1 + k * 2.2); anel.material.opacity = .8 * (1 - k);
    const a = pg.attributes.position;
    for (let i = 0; i < N; i++) { let yy = a.getY(i) + vel[i]; if (yy > 9) yy = 0; a.setY(i, yy); }
    a.needsUpdate = true;
    r.render(s, cam);
  });
})();

/* ---------- Animações de scroll (GSAP) ---------- */
if (G && ST && !calmo) {
  const h1 = $('h1');
  h1.innerHTML = h1.textContent.trim().split(/\s+/).map((w) => `<span class="w"><span>${w}</span></span>`).join(' ');
  G.from('h1 .w>span', { yPercent: 115, rotateX: -80, transformOrigin: '0 100%', duration: 1.2, stagger: .08, ease: 'power4.out', delay: .1 });

  ST.create({ trigger: '.hero', start: 'top top', end: 'bottom top', onUpdate: (self) => (scroll = self.progress) });
  G.to('.portrait', { yPercent: -10, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  G.to('.progress', { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: .3 } });

  const em = (el, vars, trig = el) => G.from(el, { duration: 1, ease: 'power3.out', scrollTrigger: { trigger: trig, start: 'top 85%' }, ...vars });
  G.utils.toArray('section:not(.hero) h2').forEach((h) => em(h, { y: 60, opacity: 0, rotateX: -25, transformPerspective: 800, transformOrigin: '0 100%' }));
  G.utils.toArray('.cmp,.card-form,.prop-card,.c-panel').forEach((el, i) => em(el, { y: 90, rotateX: 14, opacity: 0, transformPerspective: 900, delay: (i % 2) * .12 }));
  G.utils.toArray('.steps li').forEach((li) => em(li, { x: 70, opacity: 0 }));
  G.utils.toArray('.gallery img').forEach((img, i) => G.from(img, { clipPath: 'inset(0 0 100% 0)', scale: 1.15, duration: 1.3, delay: i * .1, ease: 'power3.inOut', scrollTrigger: { trigger: '.gallery', start: 'top 80%' } }));
  G.from('.a-photo', { rotateY: -25, x: -80, opacity: 0, transformPerspective: 1000, duration: 1.2, ease: 'power3.out', scrollTrigger: { trigger: '.a-photo', start: 'top 80%' } });
  G.from('.areas li', { y: 80, rotateX: -60, opacity: 0, transformPerspective: 700, stagger: .1, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: '.areas ul', start: 'top 85%' } });
  G.from('.faq details', { y: 30, opacity: 0, stagger: .07, duration: .7, scrollTrigger: { trigger: '.f-list', start: 'top 85%' } });

  if (rato) {
    $$('.cmp,.prop-card,.card-form').forEach((el) => {
      el.addEventListener('pointermove', (e) => {
        const b = el.getBoundingClientRect(), px = (e.clientX - b.left) / b.width - .5, py = (e.clientY - b.top) / b.height - .5;
        G.to(el, { rotateY: px * 6, rotateX: -py * 6, transformPerspective: 900, duration: .5, overwrite: 'auto' });
      });
      el.addEventListener('pointerleave', () => G.to(el, { rotateX: 0, rotateY: 0, duration: .8, ease: 'elastic.out(1,.6)' }));
    });
    $$('.btn.red').forEach((b) => {
      b.addEventListener('pointermove', (e) => { const r = b.getBoundingClientRect(); G.to(b, { x: (e.clientX - r.left - r.width / 2) * .22, y: (e.clientY - r.top - r.height / 2) * .3, duration: .3 }); });
      b.addEventListener('pointerleave', () => G.to(b, { x: 0, y: 0, duration: .8, ease: 'elastic.out(1,.5)' }));
    });
  }
}
})();
