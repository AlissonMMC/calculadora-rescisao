const PROVIDERS = window.ORCAMENTOS_PROVIDERS;
const INITIAL_SETTINGS = window.ORCAMENTOS_SETTINGS;
const csrf = window.ORCAMENTOS_CSRF;
const form=document.getElementById('budgetForm'),file=document.getElementById('source_file'),sheet=document.getElementById('sheet_name'),sheetStatus=document.getElementById('sheetStatus'),validation=document.getElementById('validation'),sheetPreview=document.getElementById('sheetPreview'),previewItems=document.getElementById('previewItems'),errors=document.getElementById('errors'),generateBtn=document.getElementById('generateBtn');
const signaturePreviewGrid=document.getElementById('signaturePreviewGrid'),signaturePreviewHint=document.getElementById('signaturePreviewHint');
let sheetMeta=[];
let slots=[PROVIDERS[0]?.id||'',PROVIDERS[1]?.id||'',PROVIDERS[2]?.id||''];
function esc(s) {
    return String(s??'').replace(/[&<>'"]/g,m=>( {
        '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'
    }
    [m]));
}
function money(n) {
    return Number.isFinite(Number(n))?Number(n).toLocaleString('pt-BR', {
        style:'currency',currency:'BRL'
    }
    ):'—'
}
function showError(m) {
    errors.textContent=m;
    errors.classList.add('show')
}
function clearError() {
    errors.textContent='';
    errors.classList.remove('show')
}
function renderProviders() {
    const box=document.getElementById('providers');
    box.innerHTML='';
    for(let i=0;
    i<3;
    i++) {
        const selected=PROVIDERS.find(p=>String(p.id)===String(slots[i]))||PROVIDERS[i]|| {
        }
        const row=document.createElement('div');
        row.className='provider';
        row.innerHTML=`<div><div class="provider-name">Orçamento ${i+1}</div><div class="provider-meta" id="meta${i}">${esc(selected.nome||'Selecione um prestador')}</div></div><div class="provider-select"><select data-slot="${i}">${PROVIDERS.map(p=>`<option value="${esc(p.id)}" $ {
            String(p.id)===String(slots[i])?'selected':''
        }
        >$ {
            esc(p.nome)
        }
        </option>`).join('')}</select></div><div class="provider-image"><img class="preview" id="preview${i}" alt=""><span class="file-name" id="sigName${i}">${selected.assinatura?'Assinatura cadastrada':'Sem assinatura cadastrada'}</span><input type="file" id="imagem${i+1}" name="imagem${i+1}" accept="image/png,image/jpeg,image/jpg"><label class="btn secondary small" for="imagem${i+1}">Substituir</label></div><span class="hint">${esc(selected.cpf||'')}</span>`;
        box.appendChild(row);
        setStoredPreview(i,selected);
    }
    box.querySelectorAll('select').forEach(s=>s.addEventListener('change',()=> {
        slots[Number(s.dataset.slot)]=s.value;
        const p=PROVIDERS.find(x=>String(x.id)===String(s.value));
        document.getElementById('meta'+s.dataset.slot).textContent=p?.nome||'';
        setStoredPreview(Number(s.dataset.slot),p);
        updateSignaturePreview();
    }
    ));
    box.querySelectorAll('input[type=file]').forEach(inp=>inp.addEventListener('change',()=> {
        const i=Number(inp.id.replace('imagem',''))-1;
        const p=document.getElementById('preview'+i),n=document.getElementById('sigName'+i);
        if(inp.files[0]) {
            p.src=URL.createObjectURL(inp.files[0]);
            p.style.display='block';
            n.textContent='Substituição selecionada: '+inp.files[0].name;
        }
        else setStoredPreview(i,PROVIDERS.find(x=>String(x.id)===String(slots[i])));
        updateSignaturePreview();
    }
    ));
    updateSignaturePreview();
}
function setStoredPreview(i,p) {
    const img=document.getElementById('preview'+i);
    const name=document.getElementById('sigName'+i);
    if(p?.assinatura) {
        img.src='assinatura.php?id='+encodeURIComponent(p.id)+'&t='+Date.now();
        img.style.display='block';
        name.textContent='Assinatura cadastrada';
    }
    else {
        img.removeAttribute('src');
        img.style.display='none';
        name.textContent='Sem assinatura cadastrada';
    }
}
function signatureImageFor(i) {
    const inp=document.getElementById('imagem'+(i+1));
    if(inp?.files?.[0]) {
        return URL.createObjectURL(inp.files[0]);
    }
    const p=PROVIDERS.find(x=>String(x.id)===String(slots[i]));
    return p?.assinatura?('assinatura.php?id='+encodeURIComponent(p.id)+'&t='+Date.now()):'';
}
function updateSignaturePreview() {
    const height=Math.max(80,Math.min(320,Number(document.getElementById('signature_height').value)||150));
    const gap=Math.max(1,Math.min(6,Number(document.getElementById('signature_gap').value)||1));
    const align=document.getElementById('signature_align').value;
    const sigVisualHeight=Math.max(42,Math.min(112,Math.round(height*.43)));
    signaturePreviewHint.textContent=`${height}px · ${gap} linha(s) · ${align==='left'?'esquerda':align==='center'?'centro':'direita'}`;
    let html='';
    for(let i=0;
    i<3;
    i++) {
        const p=PROVIDERS.find(x=>String(x.id)===String(slots[i]))|| {
        }
        const name=esc(p.nome||'Prestador não selecionado');
        const src=signatureImageFor(i);
        html+=`<div class="signature-preview-card"><div class="sp-title">Orçamento ${i+1}</div><div class="sp-meta">${name}</div><div class="signature-paper"><div class="sp-paper-top"><span>IMOBILIARIA JAU - ORÇAMENTO</span><span>TOTAL</span></div><div class="sp-paper-total"><span>TOTAL</span></div><div class="signature-space" style="height:${gap*9}px"></div><div class="signature-slot ${align}">${src?`<img src="${esc(src)}" alt="Assinatura" style="height:${sigVisualHeight}px">`:'<span class="signature-placeholder">Sem assinatura cadastrada</span>'}</div><div class="sp-caption">Área reservada para assinatura</div></div></div>`;
    }
    signaturePreviewGrid.innerHTML=html||'<div class="signature-preview-empty">Selecione um prestador para visualizar a assinatura.</div>';
}
function renderValidation(m) {
    if(!m) {
        validation.innerHTML='';
        return;
    }
    const items=[['Arquivo','ok'],['TOTAL',m.total_row?'ok':'bad'],['PINTURA INTERNA',m.pintura_row?'ok':'bad'],['Materiais / Mão de obra',m.material_cols?'ok':'bad'],['Estrutura',m.header_row?'ok':'bad']];
    validation.innerHTML=items.map(([a,c])=>`<div class="check ${c}"><b>${c==='ok'?'✓':'!'} ${a}</b><span>${c==='ok'?'Validado':'Não encontrado'}</span></div>`).join('');
    generateBtn.disabled=!(m.total_row&&m.pintura_row&&m.material_cols&&sheet.value);
}
function updatePreview() {
    const m=sheetMeta.find(x=>x.name===sheet.value);
    if(!m) {
        sheetPreview.classList.remove('show');
        renderValidation(null);
        previewItems.innerHTML='<div class="preview-empty">Selecione uma aba para visualizar os itens.</div>';
        generateBtn.disabled=true;
        return;
    }
    document.getElementById('prevMat').textContent=money(m.materials);
    document.getElementById('prevMo').textContent=money(m.labor);
    document.getElementById('prevTotal').textContent=money(m.total);
    document.getElementById('previewHint').textContent=(m.material_header||m.labor_header)?`Cabeçalhos reconhecidos: ${m.material_header||'—'} / ${m.labor_header||'—'} · ${m.items||0} item(ns) detectado(s).`: `${m.items||0} item(ns) detectado(s).`;
    const rows=Array.isArray(m.items_preview)?m.items_preview:[];
    if(rows.length) {
        previewItems.innerHTML='<table><thead><tr><th>#</th><th>Serviço</th><th>Materiais</th><th>Mão de obra</th><th>Total</th></tr></thead><tbody>'+rows.map((it,idx)=>`<tr><td>${idx+1}</td><td class="desc">${esc(it.service||'')}</td><td class="money">${money(it.materials)}</td><td class="money">${money(it.labor)}</td><td class="money">${money(it.total)}</td></tr>`).join('')+'</tbody></table>';
    }
    else {
        previewItems.innerHTML='<div class="preview-empty">Nenhum item com valores foi encontrado nesta aba.</div>';
    }
    sheetPreview.classList.add('show');
    renderValidation(m)
}
async function jsonResponse(r) {
    const raw=await r.text();
    if(!raw.trim())throw new Error(`Servidor retornou HTTP ${r.status} sem conteúdo.`);
    let d;
    try {
        d=JSON.parse(raw)
    }
    catch(e) {
        throw new Error(`Servidor retornou HTTP ${r.status}, mas não enviou JSON.\n\n${raw.slice(0,3500)}`)
    }
    if(!r.ok||!d.ok) {
        let msg=d.error||d.message||`Falha no servidor (HTTP ${r.status}).`;
        if(d.saida_python)msg+=`\n\nDetalhe técnico:\n${d.saida_python}`;
        if(d.comando)msg+=`\n\nComando executado:\n${d.comando}`;
        throw new Error(msg)
    }
    return d
}
const filePickerName=document.getElementById('filePickerName'),filePickerHint=document.getElementById('filePickerHint');
function resetFilePicker() {
    filePickerName.textContent='Selecionar arquivo Excel';
    filePickerHint.textContent='Somente arquivos .xlsx';
}
function updateFilePicker() {
    const f=file.files[0];
    if(f) {
        filePickerName.textContent=f.name;
        filePickerHint.textContent='Arquivo selecionado · pronto para análise';
    }
    else resetFilePicker();
}
file.addEventListener('change',updateFilePicker);
file.addEventListener('change',async()=> {
    filePickerName.textContent=file.files[0]?.name||'Selecionar arquivo Excel';
    filePickerHint.textContent=file.files[0]?'Lendo arquivo e analisando abas...':'Somente arquivos .xlsx';
    clearError();
    sheet.disabled=true;
    generateBtn.disabled=true;
    sheetMeta=[];
    sheet.innerHTML='<option>Carregando e analisando...</option>';
    sheetStatus.textContent='Lendo e analisando as abas do Excel...';
    if(!file.files[0])return;
    const fd=new FormData();
    fd.append('source_file',file.files[0]);
    try {
        const d=await jsonResponse(await fetch('listar_planilhas.php', {
            method:'POST',body:fd
        }
        ));
        sheetMeta=(d.sheets||[]).map((x,i)=>( {
            ...x,index:Number.isInteger(x.index)?x.index:i,name:x.name
        }
        ));
        sheet.innerHTML='';
        sheetMeta.forEach((m,i)=> {
            const o=document.createElement('option');
            o.value=m.name;
            o.textContent=m.name;
            o.dataset.sheetIndex=String(m.index);
            if(i===0)o.selected=true;
            sheet.appendChild(o)
        }
        );
        sheet.disabled=false;
        sheetStatus.textContent=`${sheetMeta.length} aba(s) encontrada(s) e analisada(s).`;
        updatePreview();
    }
    catch(e) {
        sheet.innerHTML='<option>Erro ao ler/analisar arquivo</option>';
        sheetStatus.textContent='Não foi possível ler/analisar a planilha.';
        showError(e.message)
    }
}
);
sheet.addEventListener('change',()=> {
    clearError();
    updatePreview();
    if(sheet.value)sheetStatus.textContent=`Aba selecionada: ${sheet.value}.`;
}
);
document.getElementById('btnRefreshPreview').addEventListener('click',()=> {
    updatePreview();
    updateSignaturePreview();
}
);
['signature_height','signature_gap','signature_align'].forEach(id=>document.getElementById(id).addEventListener('input',updateSignaturePreview));
document.getElementById('clearFile').addEventListener('click',()=> {
    file.value='';
    resetFilePicker();
    sheet.disabled=true;
    sheet.innerHTML='<option>Selecione o arquivo primeiro</option>';
    sheetStatus.textContent='Aguardando arquivo.';
    sheetMeta=[];
    sheetPreview.classList.remove('show');
    validation.innerHTML='';
    previewItems.innerHTML='<div class="preview-empty">Selecione uma aba para visualizar os itens.</div>';
    generateBtn.disabled=true;
    clearError();
    updateSignaturePreview()
}
);
document.getElementById('resetBtn').addEventListener('click',()=> {
    form.reset();
    slots=[PROVIDERS[0]?.id||'',PROVIDERS[1]?.id||'',PROVIDERS[2]?.id||''];
    renderProviders();
    document.getElementById('signature_height').value=INITIAL_SETTINGS.signature_height;
    document.getElementById('signature_gap').value=INITIAL_SETTINGS.signature_gap;
    document.getElementById('signature_align').value=INITIAL_SETTINGS.signature_align;
    document.getElementById('clearFile').click();
    document.getElementById('result').classList.remove('show');
    document.getElementById('progress').classList.remove('show');
    clearError()
}
);
function progress(p,l) {
    document.getElementById('progress').classList.add('show');
    document.getElementById('progressBar').style.width=p+'%';
    document.getElementById('progressPct').textContent=p+'%';
    document.getElementById('progressLabel').textContent=l
}
renderProviders();
form.addEventListener('submit',async e=> {
    e.preventDefault();
    clearError();
    document.getElementById('result').classList.remove('show');
    generateBtn.disabled=true;
    generateBtn.textContent='Gerando...';
    progress(10,'Validando dados...');
    document.getElementById('providers_json').value=JSON.stringify(slots);
    document.getElementById('signature_settings').value=JSON.stringify( {
        height:Number(document.getElementById('signature_height').value),gap:Number(document.getElementById('signature_gap').value),align:document.getElementById('signature_align').value
    }
    );
    try {
        progress(25,'Enviando arquivo, prestadores e assinaturas...');
        const d=await jsonResponse(await fetch('processar.php', {
            method:'POST',body:new FormData(form),credentials:'same-origin'
        }
        ));
        progress(75,'Excel e PDFs gerados...');
        document.getElementById('downloadBtn').href=d.download;
        document.getElementById('pdf1').href=d.pdfs[0];
        document.getElementById('pdf2').href=d.pdfs[1];
        document.getElementById('pdf3').href=d.pdfs[2];
        document.getElementById('resultInfo').textContent=d.message||'Arquivos prontos para conferência.';
        progress(100,'Concluído');
        document.getElementById('result').classList.add('show');
    }
    catch(err) {
        showError(err.message);
        document.getElementById('progress').classList.remove('show')
    }
    finally {
        generateBtn.disabled=!(sheet.value&&sheetMeta.find(x=>x.name===sheet.value)?.total_row&&sheetMeta.find(x=>x.name===sheet.value)?.pintura_row&&sheetMeta.find(x=>x.name===sheet.value)?.material_cols);
        generateBtn.textContent='Gerar 3 orçamentos'
    }
}
);
