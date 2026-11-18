/* Efeitos natalinos — melodias sintetizadas e animação decorativa. */
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
  let melodyIndex = 0;
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

  const playSong = () => {
    const ctx = getAudio();
    if (!ctx || !somAtivo || !audioLiberado || ctx.state !== 'running') return;
    const song = songs[melodyIndex % songs.length];
    melodyIndex++;
    let cursor = ctx.currentTime + .12;
    song.notes.forEach(([hz, beats]) => {
      const duration = song.bpm * beats;
      if (hz > 0) bell(hz, cursor, Math.min(duration * .92, 1.15), .16);
      cursor += duration;
    });
    const delay = Math.max(9000, (cursor - ctx.currentTime) * 1000 + 1800);
    musicTimer = window.setTimeout(playSong, delay);
  };

  const stopMusic = () => {
    if (musicTimer) window.clearTimeout(musicTimer);
    musicTimer = null;
  };

  const startMusic = async () => {
    const ctx = getAudio();
    if (!ctx || !somAtivo) return;
    try {
      if (ctx.state !== 'running') await ctx.resume();
      audioLiberado = ctx.state === 'running';
      stopMusic();
      if (audioLiberado) playSong();
    } catch (_) { audioLiberado = false; }
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
