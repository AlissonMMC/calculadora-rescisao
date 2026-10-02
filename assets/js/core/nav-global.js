(function() {
    const root=document.documentElement,btn=document.getElementById('globalThemeToggle');
    const saved=localStorage.getItem('temaCalculadora')==='dark'?'dark':'light';
    root.setAttribute('data-theme',saved);
    if(!btn) return;
    const sync=()=> {
        const dark=root.getAttribute('data-theme')==='dark';
        btn.setAttribute('aria-label',dark?'Ativar modo claro':'Ativar modo escuro');
        btn.setAttribute('title',dark?'Ativar modo claro':'Ativar modo escuro');
        const moon=btn.querySelector('.moon'),sun=btn.querySelector('.sun');
        if(moon)moon.style.display=dark?'none':'block';
        if(sun)sun.style.display=dark?'block':'none';
    }
    sync();
    btn.addEventListener('click',()=> {
        const next=root.getAttribute('data-theme')==='dark'?'light':'dark';
        root.setAttribute('data-theme',next);
        localStorage.setItem('temaCalculadora',next);
        sync();
    }
    );
}
)();
