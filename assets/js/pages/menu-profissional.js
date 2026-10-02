const campos = [
'dataInicial', 'dataFinal', 'dataInicioContrato', 'dataInicioAviso', 'dataFimAviso', 'aluguel', 'iptu', 'condominio', 'agua', 'luz', 'internet',
'mesesFaltantes', 'percentualProp', 'percentualAdm', 'valorAluguelInteiro', 'semAluguelInteiro',
'manutencao', 'chaveiro', 'seguroIncendio', 'seguroFianca', 'semAviso', 'semMulta', 'semEncargos', 'semManutencao', 'semChaveiro', 'semSeguros'
];
const itens = [
['Aluguel', 'aluguel'],
['IPTU', 'iptu'],
['Condomínio', 'condominio'],
['Água', 'agua'],
['Luz', 'luz'],
['Internet', 'internet']
];
const moeda = new Intl.NumberFormat('pt-BR', {
    style: 'currency', currency: 'BRL'
}
);
let modoSelecionado = '';
const STORAGE_FORM = 'calculadoraRescisaoDados';
const STORAGE_HISTORY = 'calculadoraRescisaoHistorico';
let restaurandoDados = false;
let revisaoBloqueada = false;
let conferenciaConfirmada = false;
let emailEditando = false;
let filtroHistorico = '';
let historicoCache = [];
let historicoOnline = false;
let historicoCarregando = false;
let ultimoEmailAutomatico = '';
// Controle da conferência final: estes tópicos só podem aparecer como conferidos
// depois que o usuário efetivamente os revisar/informar nesta sessão.
const topicosRevisados = {
    diasFinais: false, multa: false
}
const STORAGE_LOCK = 'calculadoraRescisaoBloqueada';
const opcionais = [
['encargos', 'semEncargos', 'Encargos'],
['manutencao', 'semManutencao', 'Manutenção/melhorias'],
['chaveiro', 'semChaveiro', 'Chaveiro'],
['aluguelInteiro', 'semAluguelInteiro', 'Aluguel inteiro'],
['seguros', 'semSeguros', 'Seguros']
];
function atualizarTopicosSemDebito() {
    const semAviso = document.getElementById('semAviso');
    const semMulta = document.getElementById('semMulta');
    const aviso = document.getElementById('topicoAviso');
    const multa = document.getElementById('topicoMulta');
    if (aviso) {
        aviso.classList.toggle('topic-na', !!semAviso?.checked);
        aviso.querySelectorAll('input:not(#semAviso), select, textarea, button').forEach(el => {
            if (el.id !== 'btnAlterarModo') el.disabled = !!semAviso?.checked;
        }
        );
    }
    if (multa) {
        multa.classList.toggle('topic-na', !!semMulta?.checked);
        multa.querySelectorAll('input:not(#semMulta), select, textarea, button').forEach(el => {
            if (el.id !== 'btnAlterarModo') el.disabled = !!semMulta?.checked;
        }
        );
    }
}
function atualizarCamposSemDebito() {
    const grupos = [
    {
        check:'semEncargos', campos:['iptu','condominio','agua','luz','internet'], wrap:'optional-debt-field'
    }
    ,
    {
        check:'semManutencao', campos:['manutencao'], wrap:'optional-debt-field'
    }
    ,
    {
        check:'semChaveiro', campos:['chaveiro'], wrap:'optional-debt-field'
    }
    ,
    {
        check:'semAluguelInteiro', campos:['valorAluguelInteiro'], wrap:'optional-debt-field'
    }
    ,
    {
        check:'semSeguros', campos:['seguroIncendio','seguroFianca'], wrap:'optional-debt-field'
    }
    ];
    grupos.forEach(( {
        check, campos
    }
    ) => {
        const box = document.getElementById(check);
        if (!box) return;
        campos.forEach(id => {
            const input = document.getElementById(id);
            if (!input) return;
            input.disabled = box.checked;
            if (box.checked) input.value = '0';
        }
        );
        campos.forEach(id => {
            const input = document.getElementById(id);
            const wrap = input?.closest('.optional-debt-field');
            if (wrap) wrap.classList.toggle('is-na', box.checked);
        }
        );
    }
    );
}
['semAviso','semMulta'].forEach(id => {
    const box = document.getElementById(id);
    if (box) box.addEventListener('change', () => {
        atualizarTopicosSemDebito();
        calcular();
    }
    );
}
);
function numero(id) {
    const el = document.getElementById(id);
    if (!el) return 0;
    const valor = Number.parseFloat(el.value);
    return Number.isFinite(valor) ? Math.max(valor, 0) : 0;
}
function formatar(valor) {
    return moeda.format(valor || 0);
}
function percentual(valor) {
    return valor.toLocaleString('pt-BR', {
        maximumFractionDigits: 2
    }
    );
}
function dataUtc(valor) {
    if (!valor) return null;
    const [ano, mes, dia] = valor.split('-').map(Number);
    return new Date(Date.UTC(ano, mes - 1, dia));
}
function calcularDias() {
    const inicio = dataUtc(document.getElementById('dataInicial').value);
    const fim = dataUtc(document.getElementById('dataFinal').value);
    if (!inicio || !fim) return {
        dias: 0, invalido: false
    }
    const diferenca = Math.round((fim - inicio) / 86400000);
    if (diferenca < 0) return {
        dias: 0, invalido: true
    }
    return {
        dias: diferenca + 1, invalido: false
    }
}
function calcularDiasContrato() {
    const inicioValor = document.getElementById('dataInicioContrato').value;
    const fimValor = document.getElementById('dataFinal').value;
    const inicio = dataUtc(inicioValor);
    const fim = dataUtc(fimValor);
    if (!inicio || !fim) return {
        dias: 0, invalido: false, igual: false
    }
    const diferenca = Math.round((fim - inicio) / 86400000);
    // Para a multa por dias, a contagem é intencionalmente um dia a menos.
    // Ex.: 01/01 até 10/01 = 9 dias de permanência para esta regra.
    if (diferenca <= 0) return {
        dias: 0, invalido: true, igual: diferenca === 0
    }
    return {
        dias: diferenca, invalido: false, igual: false
    }
}
function calcularDiasAviso() {
    const inicio = dataUtc(document.getElementById('dataInicioAviso').value);
    const fim = dataUtc(document.getElementById('dataFimAviso').value);
    if (!inicio || !fim) return {
        dias: 0, invalido: false
    }
    const diferenca = Math.round((fim - inicio) / 86400000);
    if (diferenca < 0) return {
        dias: 0, invalido: true
    }
    return {
        dias: diferenca + 1, invalido: false
    }
}
function formatarData(valor) {
    if (!valor) return '';
    const [ano, mes, dia] = valor.split('-');
    return `${dia}/${mes}/${ano}`;
}
function nomeModo() {
    return modoSelecionado === 'dias' ? 'Multa por dias' : 'Multa por mês';
}
function atualizarModoUI() {
    const isDias = modoSelecionado === 'dias';
    document.getElementById('multaMesFields').classList.toggle('hidden', isDias);
    document.getElementById('multaDiasFields').classList.toggle('hidden', !isDias);
    document.getElementById('multaSubtitle').textContent = isDias
    ? 'A multa será calculada pelos dias faltantes para completar 1095 dias.'
    : 'Informe os meses faltantes para completar 36 meses de contrato.';
    document.getElementById('currentModeText').textContent = nomeModo();
    document.getElementById('resumoModo').textContent = nomeModo();
}
function obterDadosFormulario() {
    const dados = {
        modo: modoSelecionado, campos: {
        }
    }
    for (const id of campos) {
        const el = document.getElementById(id);
        if (!el) continue;
        dados.campos[id] = el.type === 'checkbox' ? el.checked : el.value;
    }
    ['nomeInquilino','cpfInquilino','enderecoImovel','assuntoEmail'].forEach(id => {
        const el = document.getElementById(id);
        if (el) dados.campos[id] = el.value;
    }
    );
    return dados;
}
function salvarDadosAutomaticamente() {
    if (restaurandoDados) return;
    try {
        localStorage.setItem(STORAGE_FORM, JSON.stringify(obterDadosFormulario()));
        const texto = document.getElementById('saveStatusText');
        const status = document.getElementById('saveStatus');
        if (texto && status) {
            texto.textContent = `Salvo automaticamente às ${new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'})}`;
            status.classList.add('saved');
        }
    }
    catch (e) {
    }
}
function restaurarDadosAutomaticamente() {
    try {
        const salvo = JSON.parse(localStorage.getItem(STORAGE_FORM) || 'null');
        if (!salvo || !salvo.campos) return false;
        restaurandoDados = true;
        Object.entries(salvo.campos).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'checkbox') el.checked = Boolean(value);
            else el.value = value ?? '';
        }
        );
        // Compatibilidade com versões anteriores: antes o campo era "temAluguelInteiro".
        if (salvo.campos.temAluguelInteiro !== undefined && salvo.campos.semAluguelInteiro === undefined) {
            const semAluguel = document.getElementById('semAluguelInteiro');
            if (semAluguel) semAluguel.checked = !Boolean(salvo.campos.temAluguelInteiro);
        }
        else if (salvo.campos.semAluguelInteiro === undefined) {
            const semAluguel = document.getElementById('semAluguelInteiro');
            if (semAluguel) semAluguel.checked = false;
        }
        // Compatibilidade com versões anteriores: aproveita o primeiro percentual de ADM salvo.
        if (!salvo.campos.percentualAdm) {
            const antigo = salvo.campos.percentualAdmDiasFinais ?? salvo.campos.percentualAdmAviso ?? salvo.campos.percentualAdmAluguel;
            if (antigo !== undefined) {
                const elAdm = document.getElementById('percentualAdm');
                if (elAdm) elAdm.value = antigo;
            }
        }
        if (salvo.modo === 'dias' || salvo.modo === 'mes' || salvo.modo === 'meses') modoSelecionado = salvo.modo === 'meses' ? 'mes' : salvo.modo;
        restaurandoDados = false;
        return true;
    }
    catch (e) {
        restaurandoDados = false;
        return false;
    }
}
function atualizarConferenciaInterna(admDiasFinais, admAviso, admMulta, admAluguel, repasseDiasFinais, repasseAviso, repasseMulta, repasseAluguel) {
    const totalAdm = admDiasFinais + admAviso + admMulta + admAluguel;
    const totalRepasse = repasseDiasFinais + repasseAviso + repasseMulta + repasseAluguel;
    document.getElementById('confAdmDiasFinais').textContent = formatar(admDiasFinais);
    document.getElementById('confAdmAviso').textContent = formatar(admAviso);
    document.getElementById('confAdmMulta').textContent = formatar(admMulta);
    document.getElementById('confAdmAluguel').textContent = formatar(admAluguel);
    document.getElementById('confTotalAdm').textContent = formatar(totalAdm);
    document.getElementById('confTotalRepasse').textContent = formatar(totalRepasse);
}
function atualizarChecklist( {
    invalido, contratoInvalido, avisoInvalido, dataInicial, dataFinal, dataInicioAviso, dataFimAviso, contratoValido
}
) {
    // A conferência visual foi centralizada no painel "Conferência final antes da cobrança".
    // Mantemos a função para preservar as chamadas existentes do sistema.
    return;
}
function obterHistorico() {
    return Array.isArray(historicoCache) ? historicoCache : [];
}
function historicoLocalFallback() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_HISTORY) || '[]');
    }
    catch (e) {
        return [];
    }
}
async function carregarHistoricoServidor(opcoes = {
}
) {
    const {
        mostrarErro = true, permitirMigracao = true
    }
    = opcoes;
    historicoCarregando = true;
    renderizarHistorico();
    try {
        const resposta = await fetch(`./api/listar.php?_=${Date.now()}`, {
            method: 'GET', cache: 'no-store', credentials: 'same-origin'
        }
        );
        const json = await resposta.json();
        if (resposta.status === 401) {
            window.location.href = 'login.php';
            return false;
        }
        if (!resposta.ok || !json.ok) throw new Error(json.error || 'Falha ao carregar histórico.');
        historicoCache = Array.isArray(json.data) ? json.data : [];
        historicoOnline = true;
        historicoCarregando = false;
        renderizarHistorico();
        // Migração opcional dos registros antigos que ainda estejam apenas neste navegador.
        const legado = historicoLocalFallback();
        const chaveMigracao = 'calculadoraRescisaoMigracaoDB_v2';
        if (permitirMigracao && legado.length && !localStorage.getItem(chaveMigracao)) {
            const importar = confirm(`Foram encontrados ${legado.length} registro(s) antigos salvos neste navegador.\n\nDeseja importar esses registros para o histórico central da imobiliária?`);
            localStorage.setItem(chaveMigracao, '1');
            if (importar) {
                let importados = 0;
                for (const item of legado) {
                    try {
                        const r = await fetch(`./api/salvar.php?_=${Date.now()}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN
                            }
                            ,
                            credentials: 'same-origin',
                            body: JSON.stringify(item)
                        }
                        );
                        const j = await r.json();
                        if (r.ok && j.ok) importados++;
                    }
                    catch (e) {
                    }
                }
                await carregarHistoricoServidor( {
                    mostrarErro: false, permitirMigracao: false
                }
                );
                localStorage.removeItem(STORAGE_HISTORY);
                if (importados > 0) alert(`${importados} registro(s) antigo(s) foram importado(s) para o histórico central.`);
            }
        }
        return true;
    }
    catch (e) {
        historicoOnline = false;
        historicoCarregando = false;
        historicoCache = [];
        renderizarHistorico();
        if (mostrarErro) alert(`Não foi possível conectar ao histórico central.\n\n${e.message}`);
        return false;
    }
}
function escaparHtml(valor) {
    return String(valor ?? '').replace(/[&<>'"]/g, c => ( {
        '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'
    }
    [c]));
}
function renderizarHistorico() {
    const lista = obterHistorico();
    const el = document.getElementById('historyList');
    const termo = filtroHistorico.trim().toLowerCase();
    const filtrada = termo ? lista.filter(item => `${item.nome || ''} ${item.endereco || ''} ${item.usuarioNome || ''}`.toLowerCase().includes(termo)) : lista;
    const qtdEl = document.getElementById('historyStatQtd');
    const totalEl = document.getElementById('historyStatTotal');
    const ultimoEl = document.getElementById('historyStatUltimo');
    const noteEl = document.getElementById('historyResultsNote');
    if (qtdEl) qtdEl.textContent = historicoCarregando ? '…' : String(lista.length);
    if (totalEl) totalEl.textContent = historicoCarregando ? 'Carregando…' : formatar(lista.reduce((s, item) => s + (Number(item.total) || 0), 0));
    if (ultimoEl) {
        if (lista.length && lista[0].data) {
            const partes = String(lista[0].data).split(',');
            ultimoEl.textContent = partes[0] || 'Hoje';
            ultimoEl.title = lista[0].data;
        }
        else ultimoEl.textContent = '—';
    }
    if (noteEl) {
        if (historicoCarregando) noteEl.textContent = 'Carregando histórico central…';
        else if (!historicoOnline) noteEl.textContent = 'Histórico central indisponível';
        else if (!lista.length) noteEl.textContent = 'Nenhum registro salvo ainda';
        else if (termo) noteEl.textContent = `${filtrada.length} registro(s) encontrado(s) para “${termo}”`;
        else noteEl.textContent = `Mostrando ${lista.length} registro(s) • histórico completo`;
    }
    el.className = 'history-list-modern';
    if (historicoCarregando) {
        el.innerHTML = '<div class="history-empty"><div class="history-empty-icon">↺</div><strong>Carregando histórico</strong><br>Consultando o banco central.</div>';
        return;
    }
    if (!lista.length) {
        el.innerHTML = '<div class="history-empty"><div class="history-empty-icon">▣</div><strong>Nenhuma rescisão salva</strong><br>Os cálculos salvos aparecerão aqui.</div>';
        return;
    }
    if (!filtrada.length) {
        el.innerHTML = '<div class="history-empty"><div class="history-empty-icon">⌕</div><strong>Nenhum registro encontrado</strong><br>Tente outro nome, endereço ou responsável.</div>';
        return;
    }
    el.innerHTML = filtrada.map(item => {
        const index = lista.indexOf(item);
        const modo = item.modoNome || 'Critério não informado';
        const responsavel = item.usuarioNome || 'Responsável não informado';
        return `<div class="history-item">
 <div class="history-main">
 <strong title="${escaparHtml(item.nome || 'Inquilino não informado')}">${escaparHtml(item.nome || 'Inquilino não informado')}</strong>
 <span class="history-property" title="${escaparHtml(item.endereco || 'Imóvel não informado')}">${escaparHtml(item.endereco || 'Imóvel não informado')}</span>
 <div class="history-meta">
 <span class="history-date">Salvo em ${escaparHtml(item.data || 'data não informada')}</span>
 <span class="history-date">Responsável: ${escaparHtml(responsavel)}</span>
 </div>
 </div>
 <div class="history-side">
 ${index === 0 ? '<span class="history-recent">Mais recente</span>' : ''}
 <span class="history-mode ${modo.toLowerCase().includes('dias') ? 'mode-dias' : 'mode-mes'}">
 <span class="history-mode-icon">${modo.toLowerCase().includes('dias') ? 'D' : 'M'}</span>
 <span>${modo.toLowerCase().includes('dias') ? 'Multa por dias' : 'Multa por mês'}</span>
 </span>
 <div class="history-value-box"><span class="history-value-label">Total da rescisão</span><span class="history-value">${formatar(Number(item.total) || 0)}</span></div>
 <div class="history-actions">
 <button type="button" class="btn-history-open" data-history-index="${index}" title="Abrir cálculo">Abrir</button>
 <button type="button" class="btn-history-open" data-history-duplicate-index="${index}" title="Criar uma cópia deste cálculo">Duplicar</button>
 ${window.USUARIO_LOGADO.perfil === 'admin' ? `<button type="button" class="btn-history-delete" data-history-delete-index="${index}" title="Excluir somente este registro">Excluir</button>` : ''}
 </div>
 </div>
 </div>`;
    }
    ).join('');
}
async function salvarHistorico() {
    calcular();
    const nome = document.getElementById('nomeInquilino').value.trim();
    const endereco = document.getElementById('enderecoImovel').value.trim();
    const totalTexto = document.getElementById('totalGeral').textContent || '';
    const total = Number.parseFloat(totalTexto.replace(/[^0-9,-]/g,'').replace(/\./g,'').replace(',','.')) || 0;
    const item = {
        nome, endereco, total, modoNome: nomeModo(), dados: obterDadosFormulario()
    }
    const btn = document.getElementById('btnSalvarHistorico');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Salvando…';
    }
    try {
        const resposta = await fetch('./api/salvar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN
            }
            ,
            body: JSON.stringify(item)
        }
        );
        if (resposta.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        const json = await resposta.json();
        if (!resposta.ok || !json.ok) throw new Error(json.error || 'Não foi possível salvar no banco.');
        historicoOnline = true;
        if (json.item) historicoCache.unshift(json.item);
        renderizarHistorico();
        // Reconsulta o banco para manter a lista local exatamente igual à lista central.
        await carregarHistoricoServidor( {
            mostrarErro: false, permitirMigracao: false
        }
        );
        if (btn) {
            btn.textContent = 'Salvo ✓';
            setTimeout(() => btn.textContent = 'Salvar no histórico', 1400);
        }
    }
    catch (e) {
        alert(`Não foi possível salvar no histórico central.\n\n${e.message}`);
        if (btn) btn.textContent = 'Salvar no histórico';
    }
    finally {
        if (btn) btn.disabled = false;
    }
}
async function excluirHistorico(index) {
    const lista = obterHistorico();
    const item = lista[index];
    if (!item || !item.id) return;
    const nome = item.nome || 'este cálculo';
    if (!confirm(`Deseja excluir o cálculo de ${nome}?\n\nEssa ação não apaga os outros registros.`)) return;
    try {
        const resposta = await fetch(`./api/excluir.php?id=${encodeURIComponent(item.id)}`, {
            method: 'DELETE', headers: {
                'X-CSRF-Token': window.CSRF_TOKEN
            }
        }
        );
        if (resposta.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        const json = await resposta.json();
        if (!resposta.ok || !json.ok) throw new Error(json.error || 'Não foi possível excluir.');
        historicoCache = lista.filter(reg => String(reg.id) !== String(item.id));
        renderizarHistorico();
    }
    catch (e) {
        alert(`Não foi possível excluir do banco central.\n\n${e.message}`);
    }
}
async function abrirHistoricoDireto() {
    const modeScreen = document.getElementById('modeScreen');
    const calculatorScreen = document.getElementById('calculatorScreen');
    const historyCard = document.getElementById('historyCard');
    if (!calculatorScreen || !historyCard) return;
    calculatorScreen.classList.add('history-direct-mode');
    calculatorScreen.classList.remove('hidden');
    if (modeScreen) modeScreen.classList.add('hidden');
    // Ao abrir pelo menu, sempre consulta novamente o banco central.
    // Isso evita mostrar uma lista antiga/vazia que ficou em memória durante a inicialização.
    await carregarHistoricoServidor( {
        mostrarErro: true, permitirMigracao: false
    }
    );
    requestAnimationFrame(() => {
        historyCard.scrollIntoView( {
            behavior: 'smooth', block: 'start'
        }
        );
    }
    );
}
async function atualizarHistoricoManual() {
    await carregarHistoricoServidor( {
        mostrarErro: true, permitirMigracao: false
    }
    );
}
function fecharHistoricoDireto() {
    const calculatorScreen = document.getElementById('calculatorScreen');
    const modeScreen = document.getElementById('modeScreen');
    if (!calculatorScreen) return;
    calculatorScreen.classList.remove('history-direct-mode');
    if (!modoSelecionado) {
        calculatorScreen.classList.add('hidden');
        if (modeScreen) modeScreen.classList.remove('hidden');
    }
    else {
        atualizarModoUI();
        calculatorScreen.classList.remove('hidden');
        window.scrollTo( {
            top: 0, behavior: 'smooth'
        }
        );
    }
}
async function abrirHistoricoPorId(id) {
    const numeroId = Number(id);
    if (!Number.isInteger(numeroId) || numeroId < 1) return;
    try {
        const resposta = await fetch(`./api/abrir.php?id=${encodeURIComponent(numeroId)}&_=${Date.now()}`, {
            method: 'GET', cache: 'no-store', credentials: 'same-origin'
        }
        );
        if (resposta.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        const json = await resposta.json();
        if (!resposta.ok || !json.ok || !json.item) throw new Error(json.error || 'Não foi possível abrir o registro.');
        const item = json.item;
        if (!item.dados || !item.dados.campos) {
            alert('Este registro não contém os dados completos para reabrir.');
            return;
        }
        restaurandoDados = true;
        Object.entries(item.dados.campos).forEach(([campoId, value]) => {
            const el = document.getElementById(campoId);
            if (!el) return;
            if (el.type === 'checkbox') el.checked = Boolean(value);
            else el.value = value ?? '';
        }
        );
        if (item.dados.campos.temAluguelInteiro !== undefined && item.dados.campos.semAluguelInteiro === undefined) {
            const semAluguel = document.getElementById('semAluguelInteiro');
            if (semAluguel) semAluguel.checked = !Boolean(item.dados.campos.temAluguelInteiro);
        }
        modoSelecionado = (item.dados.modo === 'dias') ? 'dias' : ((item.dados.modo === 'mes' || item.dados.modo === 'meses') ? 'mes' : '');
        restaurandoDados = false;
        topicosRevisados.diasFinais = false;
        topicosRevisados.multa = false;
        document.getElementById('calculatorScreen')?.classList.remove('history-direct-mode');
        if (modoSelecionado) {
            atualizarModoUI();
            document.getElementById('modeScreen')?.classList.add('hidden');
            document.getElementById('selectedMode')?.classList.remove('hidden');
            document.getElementById('calculatorScreen')?.classList.remove('hidden');
        }
        calcular();
        salvarDadosAutomaticamente();
        history.replaceState(null, '', 'index.php');
        document.getElementById('calculatorScreen')?.scrollIntoView( {
            behavior: 'smooth', block: 'start'
        }
        );
    }
    catch (e) {
        alert(`Não foi possível abrir o registro do histórico.\n\n${e.message}`);
    }
}
function abrirHistorico(index) {
    const item = obterHistorico()[index];
    if (!item || !item.dados || !item.dados.campos) {
        alert('Este registro não contém os dados completos para reabrir.');
        return;
    }
    if (!confirm('Abrir esta rescisão? Os dados atuais serão substituídos pelos dados salvos.')) return;
    document.getElementById('calculatorScreen')?.classList.remove('history-direct-mode');
    restaurandoDados = true;
    Object.entries(item.dados.campos).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'checkbox') el.checked = Boolean(value);
        else el.value = value ?? '';
    }
    );
    if (item.dados.campos.temAluguelInteiro !== undefined && item.dados.campos.semAluguelInteiro === undefined) {
        const semAluguel = document.getElementById('semAluguelInteiro');
        if (semAluguel) semAluguel.checked = !Boolean(item.dados.campos.temAluguelInteiro);
    }
    modoSelecionado = (item.dados.modo === 'dias') ? 'dias' : ((item.dados.modo === 'mes' || item.dados.modo === 'meses') ? 'mes' : '');
    restaurandoDados = false;
    topicosRevisados.diasFinais = false;
    topicosRevisados.multa = false;
    if (modoSelecionado) {
        atualizarModoUI();
        document.getElementById('modeScreen').classList.add('hidden');
        document.getElementById('selectedMode').classList.remove('hidden');
        document.getElementById('calculatorScreen').classList.remove('hidden');
    }
    calcular();
    salvarDadosAutomaticamente();
    document.getElementById('calculatorScreen').scrollIntoView( {
        behavior: 'smooth', block: 'start'
    }
    );
}
async function limparHistoricoServidor() {
    if (window.USUARIO_LOGADO.perfil !== 'admin') {
        alert('Somente administradores podem limpar o histórico central.');
        return;
    }
    if (!confirm('Deseja apagar todo o histórico central da imobiliária?\n\nEsta ação não pode ser desfeita.')) return;
    try {
        const resposta = await fetch('./api/limpar.php', {
            method: 'DELETE', headers: {
                'X-CSRF-Token': window.CSRF_TOKEN
            }
        }
        );
        if (resposta.status === 401) {
            window.location.href = 'login.php';
            return;
        }
        const json = await resposta.json();
        if (!resposta.ok || !json.ok) throw new Error(json.error || 'Não foi possível limpar o histórico.');
        historicoCache = [];
        renderizarHistorico();
    }
    catch (e) {
        alert(`Não foi possível limpar o histórico central.\n\n${e.message}`);
    }
}
function atualizarAlertaImportante( {
    invalido, contratoInvalido, avisoInvalido, dataInicial, dataFinal, dataInicioAviso, dataFimAviso, contratoValido
}
) {
    const alertas = [];
    if (invalido) alertas.push('Corrija as datas dos dias finais: a entrega das chaves não pode ser anterior à data inicial.');
    if (modoSelecionado === 'dias' && dataInicioAviso && dataFimAviso && contratoInvalido) alertas.push('Corrija o início do contrato: ele deve ser anterior à entrega das chaves e não pode ser igual à data inicial dos dias finais.');
    if (!document.getElementById('semAviso')?.checked && avisoInvalido) alertas.push('Corrija as datas do aviso prévio.');
    const box = document.getElementById('alertBox');
    if (!box) return;
    box.innerHTML = alertas.map(a => `• ${a}`).join('<br>');
    box.classList.toggle('show', alertas.length > 0);
}
function atualizarConferenciaFinal( {
    invalido, contratoInvalido, avisoInvalido, contratoValido, dataInicial, dataFinal, dataInicioAviso, dataFimAviso, total
}
) {
    const semAviso = document.getElementById('semAviso')?.checked;
    const semMulta = document.getElementById('semMulta')?.checked;
    const semEncargos = document.getElementById('semEncargos')?.checked;
    const semManutencao = document.getElementById('semManutencao')?.checked;
    const semSeguros = document.getElementById('semSeguros')?.checked;
    const encargos = ['iptu','condominio','agua','luz','internet'].some(id => numero(id) > 0);
    const seguros = ['seguroIncendio','seguroFianca'].some(id => numero(id) > 0);
    const manutencao = numero('manutencao') > 0;
    const nome = document.getElementById('nomeInquilino').value.trim();
    const endereco = document.getElementById('enderecoImovel').value.trim();
    const diasFinaisConferidos = topicosRevisados.diasFinais
    && !!(dataInicial && dataFinal && !invalido);
    const mesesFaltantesEl = document.getElementById('mesesFaltantes');
    const mesesFaltantesPreenchidos = mesesFaltantesEl && String(mesesFaltantesEl.value).trim() !== '' && Number(mesesFaltantesEl.value) > 0;
    // O menu usa data-mode="mes"; manter este valor consistente em toda a lógica.
    const multaDadosValidos = semMulta
    ? true
    : (modoSelecionado === 'dias'
    ? !!(dataInicioContratoValue() && contratoValido && !contratoInvalido)
    : (modoSelecionado === 'mes' && mesesFaltantesPreenchidos));
    // No critério por meses, o próprio preenchimento de meses faltantes representa
    // a definição da multa. A ADM é apenas um rateio da multa e não deve impedir
    // que o tópico seja marcado como conferido.
    const multaConferida = semMulta
    ? true
    : (modoSelecionado === 'mes'
    ? (topicosRevisados.multa || mesesFaltantesPreenchidos) && multaDadosValidos
    : topicosRevisados.multa && multaDadosValidos);
    const checks = [
    [diasFinaisConferidos, 'Dias finais'],
    [modoSelecionado !== 'dias' || !!(dataInicioContratoValue() && contratoValido && !contratoInvalido), 'Início do contrato / entrega das chaves'],
    [!!nome, 'Nome do inquilino'],
    [!!endereco, 'Endereço do imóvel'],
    [semAviso || !!(dataInicioAviso && dataFimAviso && !avisoInvalido), 'Aviso prévio'],
    [multaConferida, 'Multa rescisória'],
    [semEncargos || encargos, 'Encargos'],
    [semManutencao || manutencao, 'Manutenção / melhorias'],
    [document.getElementById('semChaveiro')?.checked || numero('chaveiro') > 0, 'Chaveiro'],
    [document.getElementById('semAluguelInteiro')?.checked || numero('valorAluguelInteiro') > 0, 'Aluguel inteiro'],
    [semSeguros || seguros, 'Seguros'],
    [numero('aluguel') > 0, 'Valor do aluguel'],
    [Number.isFinite(total) && total >= 0, 'Total calculado']
    ];
    const tbody = document.getElementById('conferenceTableBody');
    tbody.innerHTML = checks.map(([ok, label]) => `<tr><td>${label}</td><td class="${ok ? 'status-ok' : 'status-warn'}">${ok ? '✓ Conferido' : '⚠ Revisar'}</td></tr>`).join('');
    const tudoOk = checks.every(x => x[0]);
    const status = document.getElementById('conferenceFinalStatus');
    status.textContent = tudoOk ? 'Todos os pontos obrigatórios estão conferidos.' : 'Há itens que precisam ser revisados antes da cobrança.';
    status.classList.toggle('ok', tudoOk);
    const btn = document.getElementById('btnConfirmarConferencia');
    btn.disabled = !tudoOk;
    if (!tudoOk) conferenciaConfirmada = false;
    const copy = document.getElementById('btnCopiarEmail');
    if (copy) copy.disabled = !conferenciaConfirmada;
}
function dataInicioContratoValue() {
    return document.getElementById('dataInicioContrato')?.value || '';
}
function marcarConferenciaComoPendente() {
    conferenciaConfirmada = false;
    const copy = document.getElementById('btnCopiarEmail');
    if (copy) copy.disabled = true;
    const status = document.getElementById('conferenceFinalStatus');
    if (status && !status.classList.contains('ok')) status.textContent = 'Há itens que precisam ser revisados.';
}
function definirBloqueio(bloquear) {
    revisaoBloqueada = !!bloquear;
    localStorage.setItem(STORAGE_LOCK, revisaoBloqueada ? '1' : '0');
    document.body.classList.toggle('locked', revisaoBloqueada);
    const btn = document.getElementById('btnBloquear');
    if (btn) btn.textContent = revisaoBloqueada ? '🔓 Desbloquear revisão' : '🔒 Bloquear revisão';
    document.querySelectorAll('#calculatorScreen input, #calculatorScreen textarea, #calculatorScreen select').forEach(el => {
        if (el.id === 'modeloEmail') el.disabled = false;
        else el.disabled = revisaoBloqueada;
    }
    );
    if (!revisaoBloqueada) {
        atualizarCamposSemDebito();
        atualizarTopicosSemDebito();
        document.getElementById('valorAluguelInteiro').disabled = document.getElementById('semAluguelInteiro')?.checked ?? true;
    }
}
function limparTopico(tipo) {
    const mapa = {
        dias:['dataInicial','dataFinal','aluguel','iptu','condominio','agua','luz','internet','percentualAdm'],
        aviso:['dataInicioAviso','dataFimAviso','semAviso'],
        multa:['mesesFaltantes','dataInicioContrato','semMulta','percentualProp'],
        aluguel:['semAluguelInteiro','valorAluguelInteiro'],
        manutencao:['manutencao','semManutencao'],
        chaveiro:['chaveiro','semChaveiro'],
        seguros:['seguroIncendio','seguroFianca','semSeguros']
    }
    const ids = mapa[tipo] || [];
    if (!ids.length) return;
    if (!confirm('Limpar somente este tópico? Os demais dados serão mantidos.')) return;
    restaurandoDados = true;
    ids.forEach(id => {
        const el=document.getElementById(id);
        if(!el) return;
        if(el.type==='checkbox') el.checked=false;
        else el.value = el.type==='date' ? '' : '0';
    }
    );
    restaurandoDados = false;
    calcular();
    salvarDadosAutomaticamente();
}
function duplicarHistorico(index) {
    const lista = obterHistorico();
    const item = lista[index];
    if (!item?.dados?.campos) return;
    document.getElementById('calculatorScreen')?.classList.remove('history-direct-mode');
    restaurandoDados = true;
    Object.entries(item.dados.campos).forEach(([id,value]) => {
        const el=document.getElementById(id);
        if(!el) return;
        if(el.type==='checkbox') el.checked=Boolean(value);
        else el.value=value??'';
    }
    );
    if (item.dados.campos.temAluguelInteiro !== undefined && item.dados.campos.semAluguelInteiro === undefined) {
        const semAluguel=document.getElementById('semAluguelInteiro');
        if(semAluguel) semAluguel.checked=!Boolean(item.dados.campos.temAluguelInteiro);
    }
    modoSelecionado = (item.dados.modo === 'dias') ? 'dias' : ((item.dados.modo === 'mes' || item.dados.modo === 'meses') ? 'mes' : '');
    restaurandoDados = false;
    conferenciaConfirmada = false;
    topicosRevisados.diasFinais = false;
    topicosRevisados.multa = false;
    if (modoSelecionado) {
        atualizarModoUI();
        document.getElementById('modeScreen').classList.add('hidden');
        document.getElementById('calculatorScreen').classList.remove('hidden');
    }
    calcular();
    salvarDadosAutomaticamente();
    document.getElementById('calculatorScreen').scrollIntoView( {
        behavior:'smooth',block:'start'
    }
    );
}
function duplicarUltimo() {
    const lista=obterHistorico();
    if(lista.length) duplicarHistorico(0);
}
function calcular() {
    const aluguel = numero('aluguel');
    const percentualProp = Math.min(numero('percentualProp'), 100);
    const percentualAdm = Math.min(numero('percentualAdm'), 100);
    atualizarCamposSemDebito();
    atualizarTopicosSemDebito();
    const {
        dias, invalido
    }
    = calcularDias();
    const contratoCalc = calcularDiasContrato();
    const {
        dias: diasContrato, invalido: contratoInvalido
    }
    = contratoCalc;
    const {
        dias: diasAvisoCalculados, invalido: avisoInvalido
    }
    = calcularDiasAviso();
    const dataInicioContratoValor = document.getElementById('dataInicioContrato').value;
    const dataInicioAvisoValor = document.getElementById('dataInicioAviso').value;
    const dataFimAvisoValor = document.getElementById('dataFimAviso').value;
    const dataFinalValor = document.getElementById('dataFinal').value;
    const dataInicialValor = document.getElementById('dataInicial').value;
    // Não alteramos min/max dinamicamente enquanto o usuário preenche as datas.
    // Alguns navegadores podem limpar o valor de um input type=date quando
    // uma restrição min/max passa a invalidá-lo durante a digitação.
    // A validação das relações entre as datas é feita abaixo, sem apagar o valor informado.
    document.getElementById('dataInicioContrato').closest('.field').classList.toggle('invalid', modoSelecionado === 'dias' && contratoCalc.invalido);
    document.getElementById('dataFinal').closest('.field').classList.toggle('invalid', invalido);
    document.getElementById('dataFimAviso').closest('.field').classList.toggle('invalid', avisoInvalido);
    document.getElementById('erroDataFinal').textContent = invalido
    ? 'A entrega das chaves deve ser igual ou posterior à data inicial.'
    : 'A entrega deve ser igual ou posterior à data inicial.';
    document.getElementById('erroFimAviso').textContent = avisoInvalido
    ? 'O fim do aviso deve ser igual ou posterior ao início.'
    : 'O fim deve ser igual ou posterior ao início.';
    document.getElementById('erroInicioContrato').textContent = contratoCalc.igual
    ? 'O início do contrato não pode ser igual à data inicial dos dias finais.'
    : 'O início do contrato deve ser anterior à entrega das chaves.';
    const contratoValido = modoSelecionado === 'dias' && dataInicioContratoValor && dataFinalValor && !contratoInvalido;
    const diasDoInquilino = contratoValido ? diasContrato : 0;
    const diasFaltantes = modoSelecionado === 'dias'
    ? (contratoValido ? Math.max(0, 1095 - diasDoInquilino) : 0)
    : 0;
    let multa = 0;
    if (document.getElementById('semMulta')?.checked) {
        multa = 0;
        document.getElementById('formulaMulta').textContent = 'Multa rescisória marcada como não aplicável neste contrato.';
    }
    else if (modoSelecionado === 'dias') {
        multa = contratoValido
        ? (aluguel * 3 / 1095) * diasFaltantes
        : 0;
        document.getElementById('formulaMulta').textContent =
        contratoValido
        ? `${formatar(aluguel)} × 3 ÷ 1095 × ${diasFaltantes} dias faltantes = ${formatar(multa)}`
        : 'Informe o início do contrato e a entrega das chaves para calcular a multa por dias.';
    }
    else {
        const mesesFaltantes = Math.min(numero('mesesFaltantes'), 36);
        multa = (aluguel * 3 / 36) * mesesFaltantes;
        document.getElementById('formulaMulta').textContent =
        `${formatar(aluguel)} × 3 ÷ 36 × ${mesesFaltantes} meses faltantes = ${formatar(multa)}`;
    }
    const admMulta = multa * (percentualProp / 100);
    const repasseMulta = Math.max(multa - admMulta, 0);
    document.getElementById('formulaAdmMulta').textContent =
    `${formatar(multa)} × ${percentual(percentualProp)}% = ${formatar(admMulta)}`;
    document.getElementById('formulaRepasseMulta').textContent =
    `${formatar(multa)} − ${formatar(admMulta)} = ${formatar(repasseMulta)}`;
    const dataInicial = document.getElementById('dataInicial').value;
    const dataFinal = document.getElementById('dataFinal').value;
    const diasCalculados = document.getElementById('diasCalculados');
    const periodoTexto = document.getElementById('periodoTexto');
    if (invalido) {
        diasCalculados.textContent = 'Data inválida';
        diasCalculados.className = 'negative';
        periodoTexto.textContent = 'A entrega das chaves deve ser igual ou posterior à data inicial.';
    }
    else if (dataInicial && dataFinal) {
        diasCalculados.textContent = `${dias} ${dias === 1 ? 'dia' : 'dias'}`;
        diasCalculados.className = dias > 0 ? 'positive' : '';
        periodoTexto.textContent = `${formatarData(dataInicial)} até ${formatarData(dataFinal)}`;
    }
    else {
        diasCalculados.textContent = '0 dias';
        diasCalculados.className = '';
        periodoTexto.textContent = 'Informe as duas datas';
    }
    const elDiasInquilino = document.getElementById('diasDoInquilino');
    const elDiasFaltantes = document.getElementById('diasFaltantes');
    if (modoSelecionado === 'dias') {
        elDiasInquilino.textContent = contratoInvalido
        ? 'Data inválida'
        : (document.getElementById('dataInicioContrato').value && document.getElementById('dataFinal').value
        ? `${diasDoInquilino} ${diasDoInquilino === 1 ? 'dia' : 'dias'}`
        : 'Informe as datas');
        elDiasFaltantes.textContent = contratoInvalido
        ? 'Data inválida'
        : (document.getElementById('dataInicioContrato').value && document.getElementById('dataFinal').value
        ? `${diasFaltantes} ${diasFaltantes === 1 ? 'dia' : 'dias'}`
        : 'Informe as datas');
    }
    else {
        elDiasInquilino.textContent = '0 dias';
        elDiasFaltantes.textContent = '0 dias';
    }
    const dataInicioAviso = document.getElementById('dataInicioAviso').value;
    const dataFimAviso = document.getElementById('dataFimAviso').value;
    let totalAviso = 0;
    let aluguelAviso = 0;
    const avisoBody = document.getElementById('avisoBody');
    avisoBody.innerHTML = '';
    for (const [nome, id] of itens) {
        const valorMensal = numero(id);
        const proporcional = (document.getElementById('semAviso')?.checked || avisoInvalido || !dataInicioAviso || !dataFimAviso) ? 0 : (valorMensal / 30) * diasAvisoCalculados;
        totalAviso += proporcional;
        if (id === 'aluguel') aluguelAviso = proporcional;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${nome}</td><td>${formatar(proporcional)}</td>`;
        avisoBody.appendChild(tr);
    }
    const admAviso = aluguelAviso * (percentualAdm / 100);
    const repasseAviso = Math.max(totalAviso - admAviso, 0);
    document.getElementById('diasAviso').textContent = avisoInvalido
    ? 'Data inválida'
    : (dataInicioAviso && dataFimAviso ? `${diasAvisoCalculados} ${diasAvisoCalculados === 1 ? 'dia' : 'dias'}` : '0 dias');
    document.getElementById('periodoAvisoTexto').textContent = avisoInvalido
    ? 'O fim do aviso deve ser igual ou posterior ao início.'
    : (dataInicioAviso && dataFimAviso ? `${formatarData(dataInicioAviso)} até ${formatarData(dataFimAviso)}` : 'Informe as duas datas');
    document.getElementById('formulaAviso').textContent = document.getElementById('semAviso')?.checked
    ? 'Aviso prévio marcado como não aplicável neste contrato.'
    : ((!avisoInvalido && dataInicioAviso && dataFimAviso)
    ? `Valor mensal ÷ 30 × ${diasAvisoCalculados} ${diasAvisoCalculados === 1 ? 'dia' : 'dias'}`
    : 'Informe um período válido para visualizar o cálculo proporcional.');
    document.getElementById('formulaAdmAviso').textContent = `${formatar(aluguelAviso)} × ${percentual(percentualAdm)}% = ${formatar(admAviso)}`;
    document.getElementById('formulaRepasseAviso').textContent = `${formatar(totalAviso)} − ${formatar(admAviso)} = ${formatar(repasseAviso)}`;
    document.getElementById('totalAviso').textContent = formatar(totalAviso);
    let totalProporcionais = 0;
    let aluguelDiasFinais = 0;
    const corpo = document.getElementById('proporcionaisBody');
    corpo.innerHTML = '';
    for (const [nome, id] of itens) {
        const valorMensal = numero(id);
        const proporcional = invalido ? 0 : (valorMensal / 30) * dias;
        totalProporcionais += proporcional;
        if (id === 'aluguel') aluguelDiasFinais = proporcional;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${nome}</td><td>${formatar(proporcional)}</td>`;
        corpo.appendChild(tr);
    }
    document.getElementById('formulaDias').textContent =
    dias > 0 && !invalido
    ? `Valor mensal ÷ 30 × ${dias} ${dias === 1 ? 'dia' : 'dias'}`
    : 'Informe um período válido para visualizar o cálculo proporcional.';
    const admDiasFinais = aluguelDiasFinais * (percentualAdm / 100);
    const repasseDiasFinais = Math.max(totalProporcionais - admDiasFinais, 0);
    document.getElementById('formulaAdmDiasFinais').textContent =
    `${formatar(aluguelDiasFinais)} × ${percentual(percentualAdm)}% = ${formatar(admDiasFinais)}`;
    document.getElementById('formulaRepasseDiasFinais').textContent =
    `${formatar(totalProporcionais)} − ${formatar(admDiasFinais)} = ${formatar(repasseDiasFinais)}`;
    const semAluguelInteiro = document.getElementById('semAluguelInteiro')?.checked ?? true;
    const temAluguelInteiro = !semAluguelInteiro;
    const iptu = numero('iptu');
    const condominio = numero('condominio');
    const agua = numero('agua');
    const luz = numero('luz');
    const internet = numero('internet');
    const chaveiro = numero('chaveiro');
    const seguroIncendio = numero('seguroIncendio');
    const seguroFianca = numero('seguroFianca');
    // No aluguel inteiro, entram o aluguel, os encargos e os seguros para a cobrança do inquilino.
    // A ADM incide somente sobre o aluguel. Seguros não fazem parte do repasse ao proprietário.
    const encargosAluguelInteiro = iptu + condominio + agua + luz + internet + seguroIncendio + seguroFianca;
    const aluguelInteiro = temAluguelInteiro
    ? aluguel + encargosAluguelInteiro
    : 0;
    const admAluguel = temAluguelInteiro
    ? aluguel * (percentualAdm / 100)
    : 0;
    const baseRepasseAluguel = aluguel + iptu + condominio + agua + luz + internet;
    const repasseAluguel = temAluguelInteiro
    ? Math.max(baseRepasseAluguel - admAluguel, 0)
    : 0;
    document.getElementById('valorAluguelInteiro').disabled = !temAluguelInteiro;
    document.getElementById('valorAluguelInteiro').value = temAluguelInteiro
    ? aluguelInteiro.toFixed(2)
    : '0';
    document.getElementById('formulaAdmAluguel').textContent =
    `${formatar(aluguel)} × ${percentual(percentualAdm)}% = ${formatar(admAluguel)}`;
    document.getElementById('formulaRepasseAluguel').textContent =
    `${formatar(aluguelInteiro)} − ${formatar(admAluguel)} = ${formatar(repasseAluguel)}`;
    const manutencao = numero('manutencao');
    // Chaveiro é cobrado integralmente do inquilino e não participa de ADM/repasse.
    // Quando o aluguel inteiro está marcado, os seguros já estão dentro dele
    // e não podem ser somados novamente no total geral.
    const total = totalProporcionais + totalAviso + multa + aluguelInteiro + manutencao + chaveiro
    + (temAluguelInteiro ? 0 : seguroIncendio + seguroFianca);
    document.getElementById('totalProporcionais').textContent = formatar(totalProporcionais);
    document.getElementById('resumoProporcionais').textContent = formatar(totalProporcionais);
    document.getElementById('resumoAdmDiasFinais').textContent = formatar(admDiasFinais);
    document.getElementById('resumoRepasseDiasFinais').textContent = formatar(repasseDiasFinais);
    document.getElementById('resumoAviso').textContent = formatar(totalAviso);
    document.getElementById('resumoMulta').textContent = formatar(multa);
    document.getElementById('resumoAdmMulta').textContent = formatar(admMulta);
    document.getElementById('resumoRepasseMulta').textContent = formatar(repasseMulta);
    document.getElementById('resumoAluguelInteiro').textContent = formatar(aluguelInteiro);
    document.getElementById('resumoAdmAluguel').textContent = formatar(admAluguel);
    document.getElementById('resumoRepasseAluguel').textContent = formatar(repasseAluguel);
    document.getElementById('resumoManutencao').textContent = formatar(manutencao);
    document.getElementById('resumoChaveiro').textContent = formatar(chaveiro);
    document.getElementById('resumoIncendio').textContent = formatar(seguroIncendio);
    document.getElementById('resumoFianca').textContent = formatar(seguroFianca);
    document.getElementById('totalGeral').textContent = formatar(total);
    atualizarConferenciaInterna(admDiasFinais, admAviso, admMulta, admAluguel, repasseDiasFinais, repasseAviso, repasseMulta, repasseAluguel);
    atualizarChecklist( {
        invalido, contratoInvalido, avisoInvalido, dataInicial, dataFinal, dataInicioAviso, dataFimAviso, contratoValido
    }
    );
    atualizarAlertaImportante( {
        invalido, contratoInvalido, avisoInvalido, dataInicial, dataFinal, dataInicioAviso, dataFimAviso, contratoValido
    }
    );
    atualizarConferenciaFinal( {
        invalido, contratoInvalido, avisoInvalido, contratoValido, dataInicial, dataFinal, dataInicioAviso, dataFimAviso, total
    }
    );
    marcarConferenciaComoPendente();
    salvarDadosAutomaticamente();
    document.getElementById('resumoPeriodo').textContent = invalido
    ? 'intervalo inválido'
    : (dataInicial && dataFinal ? `${formatarData(dataInicial)} até ${formatarData(dataFinal)}` : 'não informado');
    document.getElementById('resumoDias').textContent = invalido ? '—' : String(dias);
    document.getElementById('resumoDiasFaltantes').textContent = modoSelecionado === 'dias'
    ? (!contratoValido ? '—' : String(diasFaltantes))
    : 'não se aplica';
    const detalhamentoFinais = itens.map(([nome, id]) => ( {
        nome,
        valor: invalido ? 0 : (numero(id) / 30) * dias
    }
    ));
    const detalhamentoAviso = itens.map(([nome, id]) => ( {
        nome,
        valor: (avisoInvalido || !dataInicioAviso || !dataFimAviso) ? 0 : (numero(id) / 30) * diasAvisoCalculados
    }
    ));
    const detalhamentoAluguelInteiro = [
    ['Aluguel', aluguel], ['IPTU', iptu], ['Condomínio', condominio],
    ['Água', agua], ['Luz', luz], ['Internet', internet],
    ['Seguro incêndio', seguroIncendio], ['Seguro fiança', seguroFianca]
    ].map(([nome, valor]) => ( {
        nome, valor: temAluguelInteiro ? valor : 0
    }
    ));
    atualizarModeloEmail( {
        totalProporcionais, admDiasFinais, repasseDiasFinais, totalAviso, multa, aluguelInteiro, manutencao, chaveiro,
        seguroIncendio, seguroFianca, temAluguelInteiro, semAluguelInteiro, total,
        dataInicial, dataFinal, dataInicioAviso, dataFimAviso,
        detalhamentoFinais, detalhamentoAviso, detalhamentoAluguelInteiro
    }
    );
}
function atualizarModeloEmail(dados) {
    const nome = document.getElementById('nomeInquilino').value.trim() || 'Sr.(a)';
    const cpf = document.getElementById('cpfInquilino').value.trim();
    const endereco = document.getElementById('enderecoImovel').value.trim();
    const assunto = document.getElementById('assuntoEmail').value.trim() || 'Declaração de valores em aberto - encerramento da locação';
    const addLinha = (lista, descricao, valor) => {
        if (valor > 0.005) lista.push(`- ${descricao}: ${formatar(valor)}`);
    }
    const linhasDebitos = [];
    dados.detalhamentoFinais.forEach(item => addLinha(linhasDebitos, `${item.nome} - período final`, item.valor));
    dados.detalhamentoAviso.forEach(item => addLinha(linhasDebitos, `${item.nome} - aviso prévio`, item.valor));
    if (dados.multa > 0.005) addLinha(linhasDebitos, 'Multa rescisória', dados.multa);
    if (dados.aluguelInteiro > 0.005) addLinha(linhasDebitos, 'Aluguel inteiro + encargos', dados.aluguelInteiro);
    if (dados.manutencao > 0.005) addLinha(linhasDebitos, 'Melhorias / manutenção', dados.manutencao);
    if (dados.chaveiro > 0.005) addLinha(linhasDebitos, 'Chaveiro', dados.chaveiro);
    if (!dados.temAluguelInteiro) {
        addLinha(linhasDebitos, 'Seguro incêndio', dados.seguroIncendio);
        addLinha(linhasDebitos, 'Seguro fiança', dados.seguroFianca);
    }
    const dataEntrega = dados.dataFinal ? formatarData(dados.dataFinal) : 'não informada';
    const periodo = dados.dataInicial && dados.dataFinal
    ? `${formatarData(dados.dataInicial)} até ${formatarData(dados.dataFinal)}`
    : 'não informado';
    const periodoAviso = dados.dataInicioAviso && dados.dataFimAviso
    ? `${formatarData(dados.dataInicioAviso)} até ${formatarData(dados.dataFimAviso)}`
    : '';
    const lista = linhasDebitos.length ? linhasDebitos.join('\n') : '- Não há débitos calculados no momento.';
    const identificacao = cpf ? ` ${nome} CPF: ${cpf}.` : ` ${nome}.`;
    const enderecoTexto = endereco ? ` localizado em ${endereco}.` : '.';
    const avisoTexto = periodoAviso ? `\nPeríodo considerado para aviso prévio: ${periodoAviso}.` : '';
    const texto = `${assunto}\n\nOlá, tudo bem?\n\nEstamos enviando este e-mail para informar que o imóvel foi desocupado. As chaves do imóvel foram entregues na imobiliária no dia ${dataEntrega}, encerrando-se assim o contrato de locação.\n\nEstamos tentando contato com o Sr.(a)${identificacao}\n\nNeste e-mail estão as informações de melhorias e débitos em aberto do imóvel locado pelo mesmo,${enderecoTexto}\n\nABILIO AREIA SOLUÇÕES IMOBILIARIAS LTDA, pessoa jurídica inscrita no CNPJ nº 13.219.509/0001-71, com sede em R. Aristídes Lôbo Sobrinho, 63 - Chácara Braz Miraglia, Jaú - SP, 17207-300, vem através da presente DECLARAR os valores em aberto referente ao imóvel acima citado:\n\n${lista}\n\nTotalizando o valor de ${formatar(dados.total)}.${periodoAviso ? `\n$ {
        avisoTexto.trim()
    }
    ` : ''}\n\nObs.: Os valores estão sujeitos a aprovação da seguradora. Caso não haja a cobertura total por parte da mesma, ficarão débitos pendentes a serem acertados na imobiliária.\n\nEm caso de dúvidas, por favor, contatar sua gestora de contrato.\n\nAtenciosamente,`;
    ultimoEmailAutomatico = texto;
    if (!emailEditando) document.getElementById('modeloEmail').value = texto;
}
function iniciarModo(modo) {
    const mudouModo = modoSelecionado !== modo;
    modoSelecionado = modo;
    // Ao trocar o critério, a conferência da multa precisa ser refeita.
    // Nunca reaproveitamos uma conferência feita para o outro tipo de multa.
    if (mudouModo) {
        topicosRevisados.multa = false;
        conferenciaConfirmada = false;
    }
    document.querySelectorAll('.mode-option').forEach(btn => {
        btn.classList.toggle('selected', btn.dataset.mode === modo);
    }
    );
    const texto = nomeModo();
    document.getElementById('selectedMode').classList.remove('hidden');
    document.getElementById('selectedModeText').textContent = texto;
}
function continuar() {
    if (!modoSelecionado) return;
    // Ao entrar no cálculo pelo menu, a multa sempre começa pendente de revisão.
    topicosRevisados.multa = false;
    conferenciaConfirmada = false;
    document.getElementById('modeScreen').classList.add('hidden');
    document.getElementById('calculatorScreen').classList.remove('hidden');
    atualizarModoUI();
    calcular();
    window.scrollTo( {
        top: 0, behavior: 'smooth'
    }
    );
    document.getElementById('dataInicial').focus();
}
function voltarModo() {
    document.getElementById('calculatorScreen').classList.add('hidden');
    document.getElementById('modeScreen').classList.remove('hidden');
    document.querySelectorAll('.mode-option').forEach(btn => btn.classList.toggle('selected', btn.dataset.mode === modoSelecionado));
    window.scrollTo( {
        top: 0, behavior: 'smooth'
    }
    );
}
function limpar() {
    for (const id of campos) {
        const el = document.getElementById(id);
        if (!el) continue;
        if (el.type === 'date') el.value = '';
        else if (el.type === 'checkbox') el.checked = false;
        else el.value = '0';
    }
    ['nomeInquilino','cpfInquilino','enderecoImovel'].forEach(id => {
        const el=document.getElementById(id);
        if(el) el.value='';
    }
    );
    calcular();
    salvarDadosAutomaticamente();
    document.getElementById('dataInicial').focus();
}
function novaRescisao() {
    if (!confirm('Deseja iniciar uma nova rescisão? Os dados atuais serão apagados.')) return;
    localStorage.removeItem(STORAGE_FORM);
    topicosRevisados.diasFinais = false;
    topicosRevisados.multa = false;
    limpar();
    modoSelecionado = '';
    document.getElementById('selectedMode').classList.add('hidden');
    voltarModo();
}
document.querySelectorAll('.mode-option').forEach(btn => {
    btn.addEventListener('click', () => {
        iniciarModo(btn.dataset.mode);
        salvarDadosAutomaticamente();
    }
    );
}
);
document.getElementById('btnContinuar').addEventListener('click', continuar);
document.getElementById('btnAlterarModo').addEventListener('click', voltarModo);
document.getElementById('btnMenuHistorico')?.addEventListener('click', () => {
    window.location.href = 'historico.php';
}
);
document.getElementById('btnVoltarCalculadoraHistorico')?.addEventListener('click', fecharHistoricoDireto);
document.getElementById('btnAtualizarHistorico')?.addEventListener('click', atualizarHistoricoManual);
function imprimirCalculo() {
    calcular();
    const agora = new Date();
    document.getElementById('printDate').textContent = agora.toLocaleString('pt-BR');
    const nomePrint = document.getElementById('nomeInquilino').value.trim() || 'Inquilino não informado';
    const enderecoPrint = document.getElementById('enderecoImovel').value.trim() || 'Imóvel não informado';
    document.getElementById('printOnlyHeader').textContent = `RESCISÃO DE LOCAÇÃO — ${nomePrint} — ${enderecoPrint}`;
    document.body.classList.add('modo-impressao');
    // O diálogo nativo de impressão precisa ser chamado diretamente pelo clique.
    window.print();
    setTimeout(() => document.body.classList.remove('modo-impressao'), 500);
}
document.getElementById('btnImprimir').addEventListener('click', imprimirCalculo);
document.getElementById('btnSalvarHistorico').addEventListener('click', salvarHistorico);
document.getElementById('btnNovaRescisao').addEventListener('click', novaRescisao);
document.getElementById('btnBloquear').addEventListener('click', () => definirBloqueio(!revisaoBloqueada));
document.querySelectorAll('[data-help]').forEach(btn => btn.addEventListener('click', () => {
    const box = document.getElementById(btn.dataset.help);
    if (box) box.classList.toggle('show');
}
));
document.querySelectorAll('[data-clear-topic]').forEach(btn => btn.addEventListener('click', () => limparTopico(btn.dataset.clearTopic)));
document.getElementById('historyList').addEventListener('click', (event) => {
    const excluir = event.target.closest('[data-history-delete-index]');
    if (excluir) {
        excluirHistorico(Number(excluir.dataset.historyDeleteIndex));
        return;
    }
    const dup = event.target.closest('[data-history-duplicate-index]');
    if (dup) {
        duplicarHistorico(Number(dup.dataset.historyDuplicateIndex));
        return;
    }
    const btn = event.target.closest('[data-history-index]');
    if (btn) abrirHistorico(Number(btn.dataset.historyIndex));
}
);
document.getElementById('btnLimparHistorico').addEventListener('click', limparHistoricoServidor);
document.getElementById('buscaHistorico').addEventListener('input', e => {
    filtroHistorico=e.target.value;
    renderizarHistorico();
}
);
document.getElementById('btnDuplicarUltimo').addEventListener('click', duplicarUltimo);
document.getElementById('btnLimpar').addEventListener('click', limpar);
document.getElementById('btnAtualizarEmail').addEventListener('click', () => {
    emailEditando = false;
    const area=document.getElementById('modeloEmail');
    area.readOnly=true;
    area.value=ultimoEmailAutomatico;
    document.getElementById('btnEditarEmail').textContent='Editar texto';
}
);
document.getElementById('btnEditarEmail').addEventListener('click', () => {
    emailEditando = !emailEditando;
    const area=document.getElementById('modeloEmail');
    area.readOnly=!emailEditando;
    document.getElementById('btnEditarEmail').textContent=emailEditando?'Bloquear edição':'Editar texto';
    if (emailEditando) area.focus();
}
);
['nomeInquilino','cpfInquilino','enderecoImovel','assuntoEmail'].forEach(id => document.getElementById(id).addEventListener('input', () => {
    calcular();
    marcarConferenciaComoPendente();
}
));
document.getElementById('btnConfirmarConferencia').addEventListener('click', () => {
    const status=document.getElementById('conferenceFinalStatus');
    if (document.getElementById('btnConfirmarConferencia').disabled) return;
    conferenciaConfirmada=true;
    status.textContent='Conferência confirmada. O e-mail está liberado para cópia.';
    status.classList.add('ok');
    document.getElementById('btnCopiarEmail').disabled=false;
}
);
document.getElementById('btnDesbloquearConferencia').addEventListener('click', () => {
    conferenciaConfirmada=false;
    document.getElementById('btnCopiarEmail').disabled=true;
    document.getElementById('conferenceFinalStatus').textContent='Revisão reaberta. Confira os dados novamente.';
}
);
document.getElementById('btnCopiarEmail').addEventListener('click', async () => {
    if (!conferenciaConfirmada) return;
    const area=document.getElementById('modeloEmail');
    try {
        await navigator.clipboard.writeText(area.value);
    }
    catch(e) {
        area.focus();
        area.select();
        document.execCommand('copy');
    }
    const botao=document.getElementById('btnCopiarEmail');
    botao.textContent='Copiado ✓';
    setTimeout(()=>botao.textContent='Copiar e-mail',1400);
}
);
const camposDiasFinais = new Set(['dataInicial','dataFinal','aluguel','iptu','condominio','agua','luz','internet','percentualAdm','semEncargos']);
// A multa pode depender de campos que também pertencem aos dias finais.
// Qualquer alteração em um desses campos conta como revisão da multa,
// desde que, ao final, todos os dados obrigatórios da multa estejam válidos.
const camposMulta = new Set([
'mesesFaltantes',
'dataInicioContrato',
'dataFinal',
'aluguel',
'percentualProp',
'semMulta'
]);
function marcarTopicoRevisadoPorCampo(id) {
    if (camposDiasFinais.has(id)) topicosRevisados.diasFinais = true;
    if (camposMulta.has(id)) topicosRevisados.multa = true;
}
campos.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    const marcarTopicoRevisado = () => marcarTopicoRevisadoPorCampo(id);
    el.addEventListener('input', () => {
        if (revisaoBloqueada) return;
        marcarTopicoRevisado();
        marcarConferenciaComoPendente();
        calcular();
    }
    );
    el.addEventListener('change', () => {
        if (revisaoBloqueada) return;
        marcarTopicoRevisado();
        marcarConferenciaComoPendente();
        calcular();
    }
    );
}
);
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('wheel', event => event.preventDefault(), {
        passive: false
    }
    );
    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') event.preventDefault();
    }
    );
}
);
const btnTema = document.getElementById('btnTema');
function aplicarTema(tema) {
    const escuro = tema === 'dark';
    document.documentElement.setAttribute('data-theme', escuro ? 'dark' : 'light');
    btnTema.innerHTML = escuro ? '☀️ <span>Modo claro</span>' : '🌙 <span>Modo escuro</span>';
    btnTema.setAttribute('aria-label', escuro ? 'Ativar modo claro' : 'Ativar modo escuro');
    btnTema.setAttribute('title', escuro ? 'Ativar modo claro' : 'Ativar modo escuro');
    localStorage.setItem('temaCalculadora', escuro ? 'dark' : 'light');
}
btnTema.addEventListener('click', () => {
    const atual = document.documentElement.getAttribute('data-theme') || 'light';
    aplicarTema(atual === 'dark' ? 'light' : 'dark');
}
);
const btnLimparHistorico = document.getElementById('btnLimparHistorico');
if (btnLimparHistorico && window.USUARIO_LOGADO.perfil !== 'admin') btnLimparHistorico.style.display = 'none';
const temaSalvo = localStorage.getItem('temaCalculadora');
aplicarTema(temaSalvo === 'dark' ? 'dark' : 'light');
restaurarDadosAutomaticamente();
// Dados restaurados não significam conferência concluída.
// A multa (inclusive no critério por dias) só fica como conferida
// depois que o usuário revisar/alterar um campo do tópico nesta sessão.
topicosRevisados.diasFinais = false;
topicosRevisados.multa = false;
conferenciaConfirmada = false;
historicoCache = [];
historicoOnline = false;
historicoCarregando = true;
renderizarHistorico();
carregarHistoricoServidor();
revisaoBloqueada = localStorage.getItem(STORAGE_LOCK) === '1';
if (modoSelecionado) {
    atualizarModoUI();
    iniciarModo(modoSelecionado);
    document.getElementById('modeScreen').classList.add('hidden');
    document.getElementById('calculatorScreen').classList.remove('hidden');
}
opcionais.forEach(([campo, check]) => {
    const box = document.getElementById(check);
    if (box) box.addEventListener('change', () => {
        atualizarCamposSemDebito();
        calcular();
        salvarDadosAutomaticamente();
    }
    );
}
);
atualizarCamposSemDebito();
atualizarTopicosSemDebito();
calcular();
definirBloqueio(revisaoBloqueada);
const historicoIdInicial = new URLSearchParams(window.location.search).get('historico_id');
if (historicoIdInicial) abrirHistoricoPorId(historicoIdInicial);
