/* Efeitos natalinos opcionais — preserva a identidade visual e não interfere nos fluxos do sistema. */
(() => {
  'use strict';

  if (window.__efeitosNatalinosInicializados) return;
  window.__efeitosNatalinosInicializados = true;

  const STORAGE_KEY = 'folhaNatalSomAtivo';
  const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let audioContext = null;
  let somAtivo = localStorage.getItem(STORAGE_KEY) !== 'false';
  let audioLiberado = false;
  let bellsTimer = null;

  const ensureAudio = () => {
    if (!audioContext) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      audioContext = new AudioCtx();
    }
    if (audioContext.state === 'suspended') audioContext.resume().catch(() => {});
    audioLiberado = true;
    return audioContext;
  };

  const tone = (frequency, start, duration, volume, type = 'sine', endFrequency = frequency) => {
    const ctx = ensureAudio();
    if (!ctx || !somAtivo) return;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(frequency, start);
    if (endFrequency !== frequency) osc.frequency.exponentialRampToValueAtTime(Math.max(35, endFrequency), start + duration);
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(Math.max(0.0002, volume), start + Math.min(0.025, duration / 4));
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + duration + 0.03);
  };

  const playBells = () => {
    const ctx = ensureAudio();
    if (!ctx || !somAtivo) return;
    const now = ctx.currentTime;
    // Pequena sequência de sinos, macia e em volume baixo.
    [[1318.5, 0, .9], [1568, .22, .85], [1975.5, .48, 1.05], [1568, 1.05, .8]].forEach(([hz, offset, duration]) => {
      tone(hz, now + offset, duration, 0.018, 'sine');
      tone(hz * 2.01, now + offset, duration * .72, 0.005, 'sine');
    });
  };

  const playSantaLaugh = () => {
    const ctx = ensureAudio();
    if (!ctx || !somAtivo) return;
    const now = ctx.currentTime + .03;
    // Síntese vocal estilizada: três "ho" graves com queda de tom e harmônicos.
    [[112, .0, .34], [105, .43, .38], [94, .91, .58]].forEach(([base, offset, duration], index) => {
      const start = now + offset;
      tone(base * 1.0, start, duration, .075, 'triangle', base * .72);
      tone(base * 2.02, start + .015, duration * .82, .025, 'sawtooth', base * 1.42);
      tone(base * 3.01, start + .02, duration * .68, .009, 'sine', base * 2.1);
      // Um pequeno sopro dá corpo ao "ho" sem usar arquivo de voz.
      tone(48 + index * 3, start, duration * .75, .012, 'triangle', 38);
    });
  };

  const stopBells = () => {
    if (bellsTimer) window.clearInterval(bellsTimer);
    bellsTimer = null;
  };

  const startBells = () => {
    stopBells();
    if (!somAtivo || !audioLiberado) return;
    playBells();
    bellsTimer = window.setInterval(playBells, 5200);
  };

  const soundButton = document.createElement('button');
  soundButton.type = 'button';
  soundButton.className = 'christmas-sound-toggle';
  soundButton.setAttribute('aria-label', 'Ativar ou desativar sons natalinos');
  soundButton.setAttribute('aria-pressed', String(somAtivo));
  soundButton.innerHTML = '<span aria-hidden="true">♫</span><span class="christmas-sound-label"></span>';
  document.body.appendChild(soundButton);

  const refreshButton = () => {
    soundButton.querySelector('.christmas-sound-label').textContent = somAtivo ? 'Sons ligados' : 'Sons desligados';
    soundButton.setAttribute('aria-pressed', String(somAtivo));
    soundButton.classList.toggle('is-muted', !somAtivo);
  };
  refreshButton();

  soundButton.addEventListener('click', () => {
    somAtivo = !somAtivo;
    localStorage.setItem(STORAGE_KEY, String(somAtivo));
    refreshButton();
    if (somAtivo) {
      ensureAudio();
      startBells();
    } else {
      stopBells();
    }
  });

  // O navegador só libera áudio depois de uma interação explícita.
  const unlockAudio = () => {
    if (!audioLiberado) {
      ensureAudio();
      startBells();
    }
  };
  document.addEventListener('pointerdown', unlockAudio, { once: true, passive: true });
  document.addEventListener('keydown', unlockAudio, { once: true });

  // Efeitos sonoros apenas nos comandos de ação solicitados.
  document.addEventListener('click', event => {
    const button = event.target.closest('button, input[type="submit"]');
    if (!button || button === soundButton || !somAtivo) return;
    const id = button.id || '';
    const label = (button.innerText || button.value || '').trim().toLocaleLowerCase('pt-BR');
    const isSave = id === 'btnSalvarHistorico' || /salvar no histórico|salvar rescisão/.test(label);
    const isBudget = id === 'generateBtn' || /gerar 3 orçamentos|gerar orçamento/.test(label);
    const isPhotos = id === 'generateButton' || /gerar documentos|gerar fotos/.test(label);
    if (isSave || isBudget || isPhotos) {
      unlockAudio();
      playSantaLaugh();
    }
  }, true);

  // Silhueta do trenó passa discretamente ao fundo a cada 15 segundos.
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

  window.addEventListener('beforeunload', stopBells);
})();
