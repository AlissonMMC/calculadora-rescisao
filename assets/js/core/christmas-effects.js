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
    if (!somAtivo) return;
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

  const sleigh = document.createElement('div');
  sleigh.className = 'christmas-sleigh-pass';
  sleigh.setAttribute('aria-hidden', 'true');
  const scriptUrl = document.currentScript?.src || window.location.href;
  const sleighImageUrl = new URL('../../assets/images/papai-noel-treno-renas.jpg', scriptUrl).href;
  sleigh.innerHTML = `<img src="${sleighImageUrl}" alt="Papai Noel em seu trenó com as renas">`;
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
