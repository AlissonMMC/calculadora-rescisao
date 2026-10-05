const csrf = window.APP_CSRF;
const id = window.APP_ID;
const select=document.getElementById('statusSelect');
select?.addEventListener('change',async()=> {
    try {
        const r=await fetch('api/status.php', {
            method:'POST',headers: {
                'Content-Type':'application/json','X-CSRF-Token':csrf
            }
            ,credentials:'same-origin',body:JSON.stringify( {
                id,status:select.value
            }
            )
        }
        );
        const j=await r.json();
        if(!r.ok||!j.ok)throw new Error(j.error||'Não foi possível atualizar o status.');
        location.reload()
    }
    catch(e) {
        alert(e.message)
    }
}
);
document.getElementById('btnPrint')?.addEventListener('click',()=>window.print());

document.getElementById('btnExcluirDetalhe')?.addEventListener('click', async () => {
    const id = Number(window.APP_ID || 0);
    if (!id || !window.APP_IS_ADMIN) return;
    if (!confirm('Deseja excluir esta rescisão? Esta ação não pode ser desfeita.')) return;

    try {
        const r = await fetch(`api/excluir.php?id=${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.APP_CSRF
            },
            credentials: 'same-origin'
        });
        const j = await r.json();
        if (!r.ok || !j.ok) {
            throw new Error(j.error || 'Não foi possível excluir a rescisão.');
        }
        window.location.href = 'historico.php';
    } catch (e) {
        alert(e.message);
    }
});
