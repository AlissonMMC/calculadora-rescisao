/* Efeitos natalinos — música instrumental e animação decorativa. */
(() => {
  'use strict';
  if (window.__efeitosNatalinosInicializados) return;
  window.__efeitosNatalinosInicializados = true;

  const STORAGE_KEY = 'folhaNatalSomAtivo';
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || false;
  let audioContext = null;
  let masterGain = null;
  let somAtivo = true;
  let audioLiberado = false;
  let musicTimer = null;
  const MUSIC_POSITION_KEY = 'folhaNatalMusicPosition';
  const christmasMusic = new Audio('assets/audio/christmas-music.mp3');
  christmasMusic.preload = 'auto';
  christmasMusic.volume = 0.22;
  christmasMusic.loop = false;
  let musicPositionRestored = false;

  const saveMusicPosition = () => {
    if (!Number.isFinite(christmasMusic.currentTime)) return;
    try { localStorage.setItem(MUSIC_POSITION_KEY, String(christmasMusic.currentTime)); } catch (_) {}
  };

  christmasMusic.addEventListener('timeupdate', () => {
    if (christmasMusic.duration && christmasMusic.currentTime >= christmasMusic.duration - 0.18) {
      christmasMusic.currentTime = 0;
      if (somAtivo) christmasMusic.play().catch(() => {});
    }
    saveMusicPosition();
  });
  window.addEventListener('pagehide', saveMusicPosition);
  try { somAtivo = localStorage.getItem(STORAGE_KEY) !== 'false'; } catch (_) {}

  const getAudio = () => {
    if (!audioContext) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      audioContext = new AudioCtx();
      masterGain = audioContext.createGain();
      masterGain.gain.value = 0.22;
      masterGain.connect(audioContext.destination);
    }
    return audioContext;
  };

  const bell = (frequency, start, duration, volume = 0.22) => {
    const ctx = getAudio();
    if (!ctx || !masterGain) return;
    // Timbre de sininho: fundamental e dois harmônicos metálicos.
    [[1, 1], [2.76, .23], [5.4, .07]].forEach(([ratio, level]) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(frequency * ratio, start);
      gain.gain.setValueAtTime(0.0001, start);
      gain.gain.exponentialRampToValueAtTime(Math.max(.0002, volume * level), start + .012);
      gain.gain.exponentialRampToValueAtTime(.0001, start + duration * (ratio === 1 ? 1 : .72));
      osc.connect(gain);
      gain.connect(masterGain);
      osc.start(start);
      osc.stop(start + duration + .025);
    });
  };

  const songs = [
    {
      name: 'Jingle Bells',
      bpm: 0.29,
      notes: [
        [659,1],[659,1],[659,2],[659,1],[659,1],[659,2],
        [659,1],[784,1],[523,1.5],[587,0.5],[659,4],
        [698,1],[698,1],[698,1.5],[698,.5],[698,1],[659,1],[659,1],[659,1],
        [587,1],[587,1],[659,1],[587,2],[784,2]
      ]
    },
    {
      name: 'We Wish You a Merry Christmas',
      bpm: 0.31,
      notes: [
        [392,1],[523,1],[523,1],[587,1],[523,1],[494,1],[440,2],
        [440,1],[440,1],[587,1],[587,1],[659,1],[587,1],[523,2],
        [494,1],[494,1],[494,1],[659,1],[659,1],[698,1],[659,1],[587,2],
        [523,1],[392,1],[392,1],[440,1],[587,1],[494,1],[523,2]
      ]
    }
  ];

  const stopMusic = () => {
    saveMusicPosition();
    if (musicTimer) window.clearTimeout(musicTimer);
    musicTimer = null;
    christmasMusic.pause();
  };

  const startMusic = async () => {
    if (!somAtivo) return;
    try {
      if (christmasMusic.readyState < 1) {
        await new Promise((resolve, reject) => {
          const ready = () => { cleanup(); resolve(); };
          const failed = () => { cleanup(); reject(new Error('Não foi possível carregar a música.')); };
          const cleanup = () => {
            christmasMusic.removeEventListener('loadedmetadata', ready);
            christmasMusic.removeEventListener('error', failed);
          };
          christmasMusic.addEventListener('loadedmetadata', ready, { once: true });
          christmasMusic.addEventListener('error', failed, { once: true });
          christmasMusic.load();
        });
      }
      if (!musicPositionRestored) {
        let saved = 0;
        try { saved = Number(localStorage.getItem(MUSIC_POSITION_KEY) || 0); } catch (_) {}
        if (!Number.isFinite(saved) || saved < 0 || (christmasMusic.duration && saved >= christmasMusic.duration)) saved = 0;
        christmasMusic.currentTime = saved;
        musicPositionRestored = true;
      }
      await christmasMusic.play();
      audioLiberado = !christmasMusic.paused;
    } catch (_) {
      audioLiberado = false;
    }
  };

  const tone = (frequency, start, duration, volume, type = 'sine', endFrequency = frequency) => {
    const ctx = getAudio();
    if (!ctx || !somAtivo || !masterGain) return;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(frequency, start);
    if (endFrequency !== frequency) osc.frequency.exponentialRampToValueAtTime(Math.max(35, endFrequency), start + duration);
    gain.gain.setValueAtTime(.0001, start);
    gain.gain.exponentialRampToValueAtTime(Math.max(.0002, volume), start + Math.min(.025, duration / 4));
    gain.gain.exponentialRampToValueAtTime(.0001, start + duration);
    osc.connect(gain);
    gain.connect(masterGain);
    osc.start(start);
    osc.stop(start + duration + .03);
  };

  // Risada real do Papai Noel, armazenada localmente no projeto.
  const santaLaugh = new Audio('assets/audio/santa-laugh.mp3');
  santaLaugh.preload = 'auto';
  santaLaugh.volume = 0.9;
  const playSantaLaugh = () => {
    try {
      santaLaugh.pause();
      santaLaugh.currentTime = 0;
      const playback = santaLaugh.play();
      if (playback && typeof playback.catch === 'function') playback.catch(() => {});
    } catch (_) {}
  };

  const soundButton = document.createElement('button');
  soundButton.type = 'button';
  soundButton.className = 'christmas-sound-toggle';
  soundButton.setAttribute('aria-label', 'Ativar ou desativar música natalina');
  soundButton.innerHTML = '<span aria-hidden="true">♫</span><span class="christmas-sound-label"></span>';
  document.body.appendChild(soundButton);

  const refreshButton = () => {
    soundButton.querySelector('.christmas-sound-label').textContent = somAtivo ? 'Música ligada' : 'Música desligada';
    soundButton.setAttribute('aria-pressed', String(somAtivo));
    soundButton.classList.toggle('is-muted', !somAtivo);
  };
  refreshButton();

  soundButton.addEventListener('click', async () => {
    // Primeiro clique libera o áudio; só um clique posterior desliga a música.
    if (somAtivo && !audioLiberado) {
      await startMusic();
      refreshButton();
      return;
    }
    somAtivo = !somAtivo;
    try { localStorage.setItem(STORAGE_KEY, String(somAtivo)); } catch (_) {}
    refreshButton();
    if (somAtivo) await startMusic();
    else stopMusic();
  });

  const unlockAudio = async event => {
    if (event.target === soundButton || soundButton.contains(event.target)) return;
    if (somAtivo && !audioLiberado) await startMusic();
  };
  document.addEventListener('pointerdown', unlockAudio, { passive: true });
  document.addEventListener('keydown', unlockAudio);

  document.addEventListener('click', event => {
    const button = event.target.closest('button, input[type="submit"]');
    if (!button || button === soundButton || !somAtivo) return;
    const id = button.id || '';
    const label = (button.innerText || button.value || '').trim().toLocaleLowerCase('pt-BR');
    const isSave = id === 'btnSalvarHistorico' || /salvar no histórico|salvar rescisão/.test(label);
    const isBudget = id === 'generateBtn' || /gerar 3 orçamentos|gerar orçamento/.test(label);
    const isPhotos = id === 'generateButton' || /gerar documentos|gerar fotos/.test(label);
    if (isSave || isBudget || isPhotos) playSantaLaugh();
  }, true);


  // Decoração natalina: uma única peça por página, sem interferir nos controles.
  const addChristmasCardLights = () => {
    const path = window.location.pathname.toLowerCase();
    let target = null;

    if (/dashboard\.php$/.test(path)) {
      target = document.querySelector('.stats .stat-card') || document.querySelector('.workflow-grid .panel');
    } else if (/historico\.php$/.test(path) && !/historico_orcamentos/.test(path)) {
      target = document.querySelector('.filter-card') || document.querySelector('.history-card');
    } else if (/index\.php$/.test(path) && document.querySelector('#calculatorScreen')) {
      target = document.querySelector('#calculatorScreen .result-box') || document.querySelector('#modeScreen .mode-card');
    } else {
      const selectors = [
        'main .stat-card', 'main .filter-card', 'main .history-card',
        'main .panel', 'main .card', 'main section[class*="card"]',
        '.stat-card', '.filter-card', '.history-card', '.panel', '.card'
      ];
      for (const selector of selectors) {
        target = Array.from(document.querySelectorAll(selector)).find(element =>
          !element.closest('.global-sidebar, .global-topbar, .global-mobilebar') &&
          element.getClientRects().length > 0
        );
        if (target) break;
      }
    }

    if (!target || target.querySelector(':scope > .christmas-card-lights')) return;
    target.classList.add('christmas-decorated-card');
    const lights = document.createElement('div');
    lights.className = 'christmas-card-lights';
    lights.setAttribute('aria-hidden', 'true');
    const colors = ['#e34b4b', '#e6bd4b', '#4ca66a', '#4d8fd6', '#e34b4b', '#e6bd4b', '#4ca66a', '#4d8fd6', '#e34b4b'];
    colors.forEach((color, index) => {
      const bulb = document.createElement('span');
      bulb.style.setProperty('--bulb-color', color);
      bulb.style.setProperty('--bulb-delay', (index * 0.16) + 's');
      lights.appendChild(bulb);
    });
    target.appendChild(lights);
  };

  const addChristmasSheetTree = () => {
    const screen = document.querySelector('#calculatorScreen');
    const emailSection = document.querySelector('#emailCollapse');
    if (!screen || !emailSection || !/index\.php$/.test(window.location.pathname.toLowerCase())) return;

    const tree = document.createElement('div');
    tree.className = 'christmas-sheet-tree';
    tree.setAttribute('aria-hidden', 'true');
    tree.innerHTML = '<svg viewBox="0 0 180 270" xmlns="http://www.w3.org/2000/svg" role="presentation">' +
      '<path d="M90 7 79 29l-17 1 10 17-24 27 27-1-38 43 38-1-48 54h126l-48-54 38 1-38-43 27 1-24-27 10-17-17-1z" fill="#176b3d" stroke="#105330" stroke-width="3" stroke-linejoin="round"/>' +
      '<path d="M77 169h26v49H77z" rx="3" fill="#80502f"/>' +
      '<path d="M90 3 97 19l18 2-13 12 4 18-16-9-16 9 4-18-13-12 18-2z" fill="#e5bd55" stroke="#c7962d" stroke-width="2" stroke-linejoin="round"/>' +
      '<path d="M57 91q33 24 66 0M42 137q48 35 96 0M28 181q62 39 124 0" fill="none" stroke="#d7b45b" stroke-width="5" stroke-linecap="round"/>' +
      '<g stroke="#f7e7b0" stroke-width="1.5"><circle cx="63" cy="72" r="5" fill="#d94b4b"/><circle cx="111" cy="79" r="5" fill="#e8c34f"/><circle cx="78" cy="111" r="5" fill="#4f91d2"/><circle cx="126" cy="127" r="5" fill="#d94b4b"/><circle cx="54" cy="151" r="5" fill="#e8c34f"/><circle cx="101" cy="163" r="5" fill="#4f91d2"/><circle cx="75" cy="190" r="5" fill="#d94b4b"/><circle cx="119" cy="184" r="5" fill="#e8c34f"/></g>' +
      '<path d="M90 229v15" stroke="#80502f" stroke-width="5" stroke-linecap="round"/>' +
      '</svg>';

    emailSection.insertAdjacentElement('afterend', tree);
  };

  addChristmasCardLights();
  addChristmasSheetTree();

  const sleigh = document.createElement('div');
  sleigh.className = 'christmas-sleigh-pass';
  sleigh.setAttribute('aria-hidden', 'true');
  const scriptUrl = document.currentScript?.src || window.location.href;
  const sleighImageUrl = new URL('../../images/papai-noel-treno-renas.jpg', scriptUrl).href;
  sleigh.innerHTML = '<svg viewBox="0 0 360 118" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M28 64h120l-13-22h-29l-10-17h-31l12 17H57z"/><path d="M34 69c24 15 83 17 121 0l-8 12c-35 18-93 15-119-2z"/><path d="M45 84c34 17 88 17 120 0" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/><path d="M154 39l22 25-7 6-22-25z"/><path d="M183 30c12-12 26-9 34 1l-7 7c-7-6-13-6-20 1z"/><path d="M221 31c13-10 28-5 32 7l-9 5c-4-7-12-8-18-2z"/><path d="M265 40c12-9 25-3 27 9l-10 3c-2-7-8-9-14-4z"/><path d="M184 56l-7-13 10-5 8 13zM220 55l-4-14 11-3 5 14zM264 62l-1-13 11-1 2 13z"/><path d="M180 30l-9-12 15 3 5-10 4 15z"/><circle cx="205" cy="23" r="3"/><circle cx="243" cy="27" r="3"/><circle cx="282" cy="39" r="3"/></g><path d="M181 31q-7-17-19-12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
  document.body.appendChild(sleigh);

  const launchSleigh = () => {
    if (reducedMotion || document.hidden) return;
    sleigh.classList.remove('is-flying');
    void sleigh.offsetWidth;
    sleigh.classList.add('is-flying');
  };
  if (!reducedMotion) window.setInterval(launchSleigh, 15000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !reducedMotion) launchSleigh();
  });
  window.addEventListener('beforeunload', stopMusic);
})();
