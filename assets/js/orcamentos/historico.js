const CSRF = window.ORCAMENTOS_CSRF;
const toast = document.getElementById('toast');
document.getElementById('toast');
function showToast(msg,err=false) {
    toast.textContent=msg;
    toast.className='toast show'+(err?' error':'');
    clearTimeout(window.__toastTimer);
    window.__toastTimer=setTimeout(()=>toast.className='toast',3200)
}
document.querySelectorAll('[data-job]').forEach(btn=>btn.addEventListener('click',async()=> {
    const job=btn.dataset.job;
    if(!confirm('Excluir este orçamento do histórico? Os arquivos gerados também serão removidos do servidor.'))return;
    btn.disabled=true;
    btn.textContent='Excluindo...';
    try {
        const fd=new FormData();
        fd.append('csrf_token',CSRF);
        fd.append('job',job);
        const r=await fetch('excluir_historico.php', {
            method:'POST',body:fd,credentials:'same-origin'
        }
        );
        const j=await r.json();
        if(!r.ok||!j.ok)throw new Error(j.error||'Não foi possível excluir.');
        btn.closest('.row')?.remove();
        showToast(j.message||'Orçamento excluído.');
    }
    catch(e) {
        btn.disabled=false;
        btn.textContent='Excluir';
        showToast(e.message,true)
    }
}
));
