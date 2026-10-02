const csrf = window.APP_CSRF;
document.querySelectorAll('.btn-excluir').forEach(btn=>btn.addEventListener('click',async()=> {
    if(!confirm(`Deseja excluir o cálculo de ${btn.dataset.nome}?`))return;
    try {
        const r=await fetch(`api/excluir.php?id=${encodeURIComponent(btn.dataset.id)}`, {
            method:'DELETE',headers: {
                'X-CSRF-Token':csrf
            }
        }
        ),j=await r.json();
        if(!r.ok||!j.ok)throw new Error(j.error||'Não foi possível excluir.');
        location.reload()
    }
    catch(e) {
        alert(e.message)
    }
}
));
