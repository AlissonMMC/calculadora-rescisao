(function(){
    const root=document.documentElement,body=document.body;
    const themeButton=document.getElementById('globalThemeToggle');
    const desktopToggle=document.getElementById('globalSidebarToggle');
    const mobileToggle=document.getElementById('globalSidebarToggleMobile');
    const backdrop=document.getElementById('globalSidebarBackdrop');

    root.setAttribute('data-theme',localStorage.getItem('temaCalculadora')==='dark'?'dark':'light');
    if(localStorage.getItem('folhaSidebarCollapsed')==='1' && window.matchMedia('(min-width: 821px)').matches){
        body.classList.add('global-sidebar-collapsed');
    }

    function syncTheme(){
        if(!themeButton)return;
        const dark=root.getAttribute('data-theme')==='dark';
        themeButton.setAttribute('aria-label',dark?'Ativar modo claro':'Ativar modo escuro');
        themeButton.setAttribute('title',dark?'Ativar modo claro':'Ativar modo escuro');
        const moon=themeButton.querySelector('.moon'),sun=themeButton.querySelector('.sun');
        if(moon)moon.style.display=dark?'none':'block';
        if(sun)sun.style.display=dark?'block':'none';
    }
    function mobileMenu(open){
        body.classList.toggle('global-sidebar-open',open);
        if(mobileToggle)mobileToggle.setAttribute('aria-expanded',open?'true':'false');
    }
    desktopToggle?.addEventListener('click',()=>{
        const collapsed=!body.classList.contains('global-sidebar-collapsed');
        body.classList.toggle('global-sidebar-collapsed',collapsed);
        localStorage.setItem('folhaSidebarCollapsed',collapsed?'1':'0');
    });
    mobileToggle?.addEventListener('click',()=>mobileMenu(!body.classList.contains('global-sidebar-open')));
    backdrop?.addEventListener('click',()=>mobileMenu(false));
    document.querySelectorAll('.global-nav-link').forEach(link=>link.addEventListener('click',()=>mobileMenu(false)));
    themeButton?.addEventListener('click',()=>{
        const next=root.getAttribute('data-theme')==='dark'?'light':'dark';
        root.setAttribute('data-theme',next);
        localStorage.setItem('temaCalculadora',next);
        syncTheme();
    });
    syncTheme();
})();