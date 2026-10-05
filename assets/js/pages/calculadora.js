const campos = [
'dataInicial', 'dataFinal', 'dataInicioContrato', 'dataInicioAviso', 'dataFimAviso', 'aluguel', 'iptu', 'condominio', 'agua', 'luz', 'internet',
'mesesFaltantes', 'percentualProp', 'percentualAdm', 'valorAluguelInteiro', 'semAluguelInteiro',
'manutencao', 'chaveiro', 'seguroIncendio', 'seguroIncendioExtras',
'seguroFianca', 'seguroFiancaExtras',
'semAviso', 'semMulta', 'semEncargos', 'semManutencao', 'semChaveiro', 'semSeguros'
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
let historicoIdAtual = null;
let statusRescisaoAtual = 'Rascunho';
let timerRascunhoServidor = null;
let carregandoRascunhoServidor = false;
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
        check:'semSeguros', campos:['seguroIncendio','seguroIncendioExtras','seguroFianca','seguroFiancaExtras'], wrap:'optional-debt-field'
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
            if (box.checked) {
                if (CAMPOS_MONETARIOS.has(id)) definirValorMonetario(id, 0);
                else input.value = '0';
            }
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
// Campos monetários usam máscara de entrada: o usuário digita somente os
// algarismos e os dois últimos representam os centavos. Ex.: 150050 -> R$ 1.500,50.
const CAMPOS_MONETARIOS = new Set([
    'aluguel', 'iptu', 'condominio', 'agua', 'luz', 'internet',
    'valorAluguelInteiro', 'chaveiro', 'manutencao',
    'seguroIncendio', 'seguroFianca'
]);

function parseValorMonetario(valor) {
    if (valor === null || valor === undefined || valor === '') return 0;
    let texto = String(valor).trim();
    if (!texto) return 0;

    // Aceita tanto a máscara exibida (R$ 1.500,50) quanto valores antigos
    // salvos no formato decimal (1500.50).
    texto = texto.replace(/R\$/gi, '').replace(/\s/g, '');
    if (texto.includes(',')) {
        texto = texto.replace(/\./g, '').replace(',', '.');
    } else {
        texto = texto.replace(/[^0-9.-]/g, '');
    }
    const numero = Number.parseFloat(texto);
    return Number.isFinite(numero) ? Math.max(numero, 0) : 0;
}

function formatarCampoMonetario(valor) {
    return moeda.format(parseValorMonetario(valor));
}

function numero(id) {
    const el = document.getElementById(id);
    if (!el) return 0;
    const valor = CAMPOS_MONETARIOS.has(id) ? parseValorMonetario(el.value) : Number.parseFloat(el.value);
    return Number.isFinite(valor) ? Math.max(valor, 0) : 0;
}

function aplicarMascaraMonetaria(input) {
    if (!input || input.dataset.moneyReady === '1') return;
    input.dataset.moneyReady = '1';

    const atualizar = () => {
        const bruto = String(input.value ?? '');
        const digitos = bruto.replace(/\D/g, '');
        const centavos = digitos ? Number.parseInt(digitos, 10) : 0;
        input.value = moeda.format(centavos / 100);
    };

    // Normaliza valores antigos assim que a página é carregada.
    input.value = formatarCampoMonetario(input.value || 0);
    input.addEventListener('input', atualizar);
    input.addEventListener('focus', () => {
        // Mantém o campo pronto para digitação sem exigir que o usuário
        // posicione o cursor antes da máscara.
        requestAnimationFrame(() => {
            input.setSelectionRange(input.value.length, input.value.length);
        });
    });
    input.addEventListener('blur', () => {
        input.value = formatarCampoMonetario(input.value);
    });
}

function inicializarMascarasMonetarias() {
    document.querySelectorAll('input[data-money="true"]').forEach(aplicarMascaraMonetaria);
}

function definirValorMonetario(id, valor) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = moeda.format(parseValorMonetario(valor));
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
function definirStatusUI(status) {
    status = status || 'Rascunho';
    statusRescisaoAtual = status;
    const select = document.getElementById('statusRescisao');
    const label = document.getElementById('statusAtualLabel');
    const hint = document.getElementById('statusAtualHint');
    if (select) select.value = status;
    if (label) label.textContent = status;
    const dicas = {
        'Rascunho':'Cálculo em andamento.',
        'Em conferência':'Revise os tópicos antes da cobrança.',
        'Conferido':'Conferência final confirmada.',
        'Pronto para cobrança':'Conferido e liberado para o próximo passo.',
        'Cobrado':'Cobrança já registrada.',
        'Cancelado':'Registro cancelado.'
    }
    if (hint) hint.textContent = dicas[status] || 'Status atual da rescisão.';
}
async function atualizarStatusServidor(status) {
    if (!historicoIdAtual || carregandoRascunhoServidor) return;
    try {
        const r = await fetch('./api/status.php', {
            method:'POST', headers: {
                'Content-Type':'application/json','X-CSRF-Token':window.CSRF_TOKEN
            }
            , credentials:'same-origin',
            body:JSON.stringify( {
                id:historicoIdAtual,status
            }
            )
        }
        );
        if (r.status === 401) {
            window.location.href='login.php';
            return;
        }
        const j = await r.json();
        if (!r.ok || !j.ok) throw new Error(j.error || 'Não foi possível atualizar o status.');
        definirStatusUI(status);
    }
    catch (e) {
        alert(e.message);
        definirStatusUI(statusRescisaoAtual);
    }
}
function agendarSalvamentoRascunhoServidor() {
    if (restaurandoDados || carregandoRascunhoServidor) return;
    clearTimeout(timerRascunhoServidor);
    timerRascunhoServidor = setTimeout(async () => {
        try {
            const dados = obterDadosFormulario();
            const nome = document.getElementById('nomeInquilino')?.value.trim() || '';
            const camposRascunho = Object.entries(dados.campos || {
            }
            );
            const relevante = !!dados.modo || !!nome || camposRascunho.some(([id,v]) => id.startsWith('sem') ? Boolean(v) : ['0','',null,false].indexOf(v) === -1);
            if (!relevante) {
                await excluirRascunhoServidor();
                return;
            }
            const r = await fetch('./api/salvar_rascunho.php', {
                method:'POST', headers: {
                    'Content-Type':'application/json','X-CSRF-Token':window.CSRF_TOKEN
                }
                , credentials:'same-origin',
                body:JSON.stringify( {
                    nome,dados
                }
                )
            }
            );
            if (r.status === 401) {
                window.location.href='login.php';
                return;
            }
        }
        catch(e) {
        }
    }
    , 1200);
}
async function carregarRascunhoServidor() {
    try {
        carregandoRascunhoServidor = true;
        const r = await fetch('./api/carregar_rascunho.php?_='+Date.now(), {
            cache:'no-store',credentials:'same-origin'
        }
        );
        if (!r.ok) return false;
        const j = await r.json();
        if (!j.ok || !j.exists || !j.draft?.dados?.campos) return false;
        const nome = j.draft.nome || 'Rascunho';
        const usar = confirm(`Existe um rascunho salvo no servidor para ${nome}.\n\nDeseja continuar de onde parou?`);
        if (!usar) return false;
        const dados = j.draft.dados;
        Object.entries(dados.campos).forEach(([id,value])=> {
            const el=document.getElementById(id);
            if(!el) return;
            if(el.type==='checkbox') el.checked=Boolean(value);
            else if (CAMPOS_MONETARIOS.has(id)) definirValorMonetario(id, value ?? 0);
            else el.value=value??'';
        }
        );
        if (dados.campos.temAluguelInteiro !== undefined && dados.campos.semAluguelInteiro === undefined) {
            const el=document.getElementById('semAluguelInteiro');
            if(el) el.checked=!Boolean(dados.campos.temAluguelInteiro);
        }
        if (dados.modo === 'dias' || dados.modo === 'mes' || dados.modo === 'meses') modoSelecionado = dados.modo === 'dias' ? 'dias' : 'mes';
        atualizarModoUI();
        document.getElementById('modeScreen')?.classList.add('hidden');
        document.getElementById('calculatorScreen')?.classList.remove('hidden');
        return true;
    }
    catch(e) {
        return false;
    }
    finally {
        carregandoRascunhoServidor = false;
    }
}
async function excluirRascunhoServidor() {
    clearTimeout(timerRascunhoServidor);
    try {
        await fetch('./api/excluir_rascunho.php', {
            method:'DELETE',headers: {
                'X-CSRF-Token':window.CSRF_TOKEN
            }
            ,credentials:'same-origin'
        }
        );
    }
    catch(e) {
    }
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
    agendarSalvamentoRascunhoServidor();
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
            else if (CAMPOS_MONETARIOS.has(id)) definirValorMonetario(id, value ?? 0);
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
            // A abertura de uma nova rescisão não depende do histórico.
            // Não expulsamos o usuário da tela por uma falha isolada
            // da consulta em segundo plano.
            if (novaRescisaoInicial) {
                historicoOnline = false;
                historicoCarregando = false;
                renderizarHistorico();
                return false;
            }
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
 <span class="history-status-pill ${String(item.status||'Rascunho').toLowerCase().includes('cobrado') ? 'history-status-done' : (String(item.status||'').toLowerCase().includes('conferido') ? 'history-status-ok' : 'history-status-default')}">${escaparHtml(item.status || 'Rascunho')}</span>
 <div class="history-value-box"><span class="history-value-label">Total da rescisão</span><span class="history-value">${formatar(Number(item.total) || 0)}</span></div>
 <div class="history-actions">
 <button type="button" class="btn-history-open" data-history-index="${index}" title="Abrir cálculo">Abrir</button>
 <button type="button" class="btn-history-open" data-history-detail-index="${index}" title="Abrir relatório completo">Detalhes</button>
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
    const totalAdm = Number.parseFloat((document.getElementById('confTotalAdm')?.textContent || '').replace(/[^0-9,-]/g,'').replace(/\./g,'').replace(',','.')) || 0;
    const totalRepasse = Number.parseFloat((document.getElementById('confTotalRepasse')?.textContent || '').replace(/[^0-9,-]/g,'').replace(/\./g,'').replace(',','.')) || 0;
    const item = {
        id: historicoIdAtual || null, nome, endereco, total, totalAdm, totalRepasse, modoNome: nomeModo(), status: statusRescisaoAtual, dados: obterDadosFormulario()
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
        if (json.item) {
            historicoIdAtual = json.item.id || historicoIdAtual;
            definirStatusUI(json.item.status || statusRescisaoAtual);
            historicoCache = historicoCache.filter(reg => String(reg.id) !== String(json.item.id));
            historicoCache.unshift(json.item);
        }
        await excluirRascunhoServidor();
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
function decodificarDadosHistorico(item) {
    if (!item || typeof item !== 'object') return null;
    const candidatos = [];
    if (item.dados !== undefined) candidatos.push(item.dados);
    if (item.dados_json !== undefined) candidatos.push(item.dados_json);
    if (item.campos !== undefined) candidatos.push( {
        campos: item.campos, modo: item.modo, modoNome: item.modoNome
    }
    );
    for (let valor of candidatos) {
        for (let i = 0;
        i < 3;
        i++) {
            if (typeof valor === 'string') {
                const texto = valor.trim();
                if (!texto) break;
                try {
                    valor = JSON.parse(texto);
                    continue;
                }
                catch (_) {
                    break;
                }
            }
            break;
        }
        if (valor && typeof valor === 'object') {
            if (valor.campos && typeof valor.campos === 'object') return valor;
            const idsCampos = [...campos, 'nomeInquilino','cpfInquilino','enderecoImovel','assuntoEmail'];
            const possiveis = {
            }
            idsCampos.forEach(campoId => {
                if (Object.prototype.hasOwnProperty.call(valor, campoId)) possiveis[campoId] = valor[campoId];
            }
            );
            if (Object.keys(possiveis).length) {
                return {
                    campos: possiveis, modo: valor.modo ?? item.modo ?? item.modoNome ?? item.modo_nome ?? ''
                }
            }
        }
    }
    return null;
}
async function abrirHistoricoPorId(id, itemPreCarregado = null) {
    const numeroId = Number(id);
    if (!Number.isInteger(numeroId) || numeroId < 1) return;
    const calculatorScreen = document.getElementById('calculatorScreen');
    const modeScreen = document.getElementById('modeScreen');
    calculatorScreen?.classList.remove('history-direct-mode','hidden');
    modeScreen?.classList.add('hidden');
    // Evita que o cálculo inicial com valores padrão fique visível como se
    // fosse o registro carregado.
    restaurandoDados = true;
    clearTimeout(timerRascunhoServidor);
    timerRascunhoServidor = null;
    try {
        let item = null;
        let ultimoErro = null;
        // Fonte principal: endpoint dedicado que devolve o dados_json bruto.
        // Isso contorna qualquer transformação de listagem que possa perder
        // campos durante a abertura para edição.
        try {
            const resposta = await fetch(`carregar_edicao.php?id=${encodeURIComponent(numeroId)}&_=${Date.now()}`, {
                method:'GET', cache:'no-store', credentials:'same-origin'
            }
            );
            if (resposta.status === 401) {
                window.location.href='login.php';
                return;
            }
            const json = await resposta.json();
            if (!resposta.ok || !json.ok || !json.item) throw new Error(json.error || 'Não foi possível carregar o registro para edição.');
            item = json.item;
        }
        catch (e) {
            ultimoErro = e;
        }
        // Fallback para o preload e para a API antiga.
        if (!item && itemPreCarregado && Number(itemPreCarregado.id) === numeroId) item = itemPreCarregado;
        if (!item) {
            try {
                const resposta = await fetch(`./api/abrir.php?id=${encodeURIComponent(numeroId)}&_=${Date.now()}`, {
                    method:'GET', cache:'no-store', credentials:'same-origin'
                }
                );
                if (resposta.status === 401) {
                    window.location.href='login.php';
                    return;
                }
                const json = await resposta.json();
                if (!resposta.ok || !json.ok || !json.item) throw new Error(json.error || 'Não foi possível abrir o registro.');
                item = json.item;
            }
            catch (e) {
                ultimoErro = e;
            }
        }
        if (!item) throw (ultimoErro || new Error('Não foi possível carregar o registro para edição.'));
        const dados = decodificarDadosHistorico(item);
        const camposSalvos = dados?.campos && typeof dados.campos === 'object' ? dados.campos : null;
        if (!camposSalvos || !Object.keys(camposSalvos).length) {
            throw new Error('Este registro não contém os dados necessários para edição.');
        }
        historicoIdAtual = Number(item.id) || numeroId;
        const modoSalvo = String(dados.modo ?? item.modo ?? item.modoNome ?? item.modo_nome ?? '');
        modoSelecionado = modoSalvo.toLowerCase().includes('dias') ? 'dias' : (modoSalvo ? 'mes' : '');
        Object.entries(camposSalvos).forEach(([campoId, value]) => {
            const el = document.getElementById(campoId);
            if (!el) return;
            if (el.type === 'checkbox') {
                if (typeof value === 'boolean') el.checked = value;
                else el.checked = ['1','true','sim','yes','on'].includes(String(value).trim().toLowerCase());
            }
            else if (CAMPOS_MONETARIOS.has(campoId)) {
                definirValorMonetario(campoId, value ?? 0);
            } else {
                el.value = value ?? '';
            }
        }
        );
        if (camposSalvos.temAluguelInteiro !== undefined && camposSalvos.semAluguelInteiro === undefined) {
            const semAluguel = document.getElementById('semAluguelInteiro');
            if (semAluguel) semAluguel.checked = !Boolean(camposSalvos.temAluguelInteiro);
        }
        // Campos textuais são tratados explicitamente para garantir que não
        // dependam de uma estrutura antiga do JSON.
        ['nomeInquilino','cpfInquilino','enderecoImovel','assuntoEmail'].forEach(idCampo => {
            const el = document.getElementById(idCampo);
            if (el && Object.prototype.hasOwnProperty.call(camposSalvos,idCampo)) el.value = camposSalvos[idCampo] ?? '';
        }
        );
        restaurandoDados = false;
        definirBloqueio(false);
        definirStatusUI(item.status || 'Rascunho');
        topicosRevisados.diasFinais = false;
        topicosRevisados.multa = false;
        conferenciaConfirmada = false;
        if (modoSelecionado) {
            atualizarModoUI();
            document.getElementById('selectedMode')?.classList.remove('hidden');
            const modeText = document.getElementById('selectedModeText');
            if (modeText) modeText.textContent = nomeModo();
        }
        atualizarCamposSemDebito();
        atualizarTopicosSemDebito();
        calcular();
        clearTimeout(timerRascunhoServidor);
        timerRascunhoServidor = null;
        await excluirRascunhoServidor();
        try {
            localStorage.setItem(STORAGE_FORM, JSON.stringify(obterDadosFormulario()));
        }
        catch (_) {
        }
        history.replaceState(null, '', 'index.php');
        calculatorScreen?.scrollIntoView( {
            behavior:'smooth', block:'start'
        }
        );
    }
    catch (e) {
        restaurandoDados = false;
        alert(`Não foi possível abrir o registro do histórico.\n\n${e.message}`);
    }
}
function abrirHistorico(index) {
    const item = obterHistorico()[index];
    if (!item || !item.id) {
        alert('Este registro não contém dados suficientes para reabrir.');
        return;
    }
    if (!confirm('Abrir esta rescisão? Os dados atuais serão substituídos pelos dados salvos.')) return;
    abrirHistoricoPorId(item.id, item);
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
    if (historicoIdAtual && !['Cobrado','Cancelado'].includes(statusRescisaoAtual)) definirStatusUI('Em conferência');
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
        else if (CAMPOS_MONETARIOS.has(id)) definirValorMonetario(id, 0);
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
        else if (CAMPOS_MONETARIOS.has(id)) definirValorMonetario(id, value ?? 0);
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
    historicoIdAtual = null;
    definirStatusUI('Rascunho');
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
    // O aviso prévio considera SOMENTE o aluguel.
    // IPTU, condomínio, água, luz e internet não entram neste cálculo.
    const avisoValido = !document.getElementById('semAviso')?.checked && !avisoInvalido && dataInicioAviso && dataFimAviso;
    aluguelAviso = avisoValido ? (aluguel / 30) * diasAvisoCalculados : 0;
    totalAviso = aluguelAviso;
    const trAviso = document.createElement('tr');
    trAviso.innerHTML = `<td>Aluguel</td><td>${formatar(aluguelAviso)}</td>`;
    avisoBody.appendChild(trAviso);
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
    const semAluguelInteiro = document.getElementById('semAluguelInteiro')?.checked ?? true;
    const temAluguelInteiro = !semAluguelInteiro;
    const iptu = numero('iptu');
    const condominio = numero('condominio');
    const agua = numero('agua');
    const luz = numero('luz');
    const internet = numero('internet');
    const chaveiro = numero('chaveiro');
    // Seguros: o valor informado é o valor de UMA parcela.
    // Parcelas extras são somadas à primeira e cada destino pode ser ativado separadamente.
    const seguroIncendioParcela = numero('seguroIncendio');
    const seguroIncendioExtras = Math.floor(numero('seguroIncendioExtras'));
    const seguroIncendioTotal = seguroIncendioParcela * (1 + seguroIncendioExtras);
    const seguroFiancaParcela = numero('seguroFianca');
    const seguroFiancaExtras = Math.floor(numero('seguroFiancaExtras'));
    const seguroFiancaTotal = seguroFiancaParcela * (1 + seguroFiancaExtras);
    // Seguros sempre são cobrados nas duas frentes da rescisão: dias finais e aluguel inteiro.
    // O único controle do usuário é a quantidade de parcelas extras. O checkbox
    // “Não há seguros” continua sendo a exceção para desativar toda a cobrança.
    const seguroIncendioDias = !document.getElementById('semSeguros')?.checked;
    const seguroIncendioAluguel = !document.getElementById('semSeguros')?.checked;
    const seguroFiancaDias = !document.getElementById('semSeguros')?.checked;
    const seguroFiancaAluguel = !document.getElementById('semSeguros')?.checked;
    const seguroIncendioDiasValor = seguroIncendioDias ? seguroIncendioTotal : 0;
    const seguroFiancaDiasValor = seguroFiancaDias ? seguroFiancaTotal : 0;
    const seguroIncendioAluguelValor = seguroIncendioAluguel ? seguroIncendioTotal : 0;
    const seguroFiancaAluguelValor = seguroFiancaAluguel ? seguroFiancaTotal : 0;
    const segurosDiasFinais = seguroIncendioDiasValor + seguroFiancaDiasValor;
    const segurosAluguelInteiro = seguroIncendioAluguelValor + seguroFiancaAluguelValor;
    // No aluguel inteiro, entram o aluguel, os encargos e somente os seguros destinados a esta seção.
    // A ADM incide somente sobre o aluguel. Seguros não fazem parte do repasse ao proprietário.
    const encargosAluguelInteiro = iptu + condominio + agua + luz + internet + segurosAluguelInteiro;
    const aluguelInteiro = temAluguelInteiro
    ? aluguel + encargosAluguelInteiro
    : 0;
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
    // Seguros escolhidos para os dias finais entram integralmente como parcelas; não são rateados por 30.
    if (segurosDiasFinais > 0.005 && !invalido && dias > 0) {
        if (seguroIncendioDiasValor > 0.005) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>Seguro incêndio</td><td>${formatar(seguroIncendioDiasValor)}</td>`;
            corpo.appendChild(tr);
        }
        if (seguroFiancaDiasValor > 0.005) {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>Seguro fiança</td><td>${formatar(seguroFiancaDiasValor)}</td>`;
            corpo.appendChild(tr);
        }
        totalProporcionais += segurosDiasFinais;
    }
    document.getElementById('formulaDias').textContent =
    dias > 0 && !invalido
    ? `Valores mensais ÷ 30 × ${dias} ${dias === 1 ? 'dia' : 'dias'} + parcelas de seguro selecionadas`
    : 'Informe um período válido para visualizar o cálculo proporcional.';
    const admDiasFinais = aluguelDiasFinais * (percentualAdm / 100);
    // Seguros são cobrados do inquilino, mas não pertencem ao proprietário.
    // Portanto, embora entrem no total dos dias finais, ficam fora da base de repasse.
    const baseRepasseDiasFinais = Math.max(totalProporcionais - segurosDiasFinais, 0);
    const repasseDiasFinais = Math.max(baseRepasseDiasFinais - admDiasFinais, 0);
    document.getElementById('formulaAdmDiasFinais').textContent =
    `${formatar(aluguelDiasFinais)} × ${percentual(percentualAdm)}% = ${formatar(admDiasFinais)}`;
    document.getElementById('formulaRepasseDiasFinais').textContent =
    `${formatar(baseRepasseDiasFinais)} (sem seguros) − ${formatar(admDiasFinais)} = ${formatar(repasseDiasFinais)}`;
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
    // Os seguros são contabilizados apenas nos destinos selecionados:
    // dias finais e/ou aluguel inteiro. Não há soma automática adicional fora dessas seções.
    const total = totalProporcionais + totalAviso + multa + aluguelInteiro + manutencao + chaveiro;
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
    document.getElementById('resumoIncendio').textContent = formatar(seguroIncendioTotal);
    document.getElementById('resumoFianca').textContent = formatar(seguroFiancaTotal);
    document.getElementById('resumoParcelasIncendio').textContent = `${1 + seguroIncendioExtras} ${(1 + seguroIncendioExtras) === 1 ? 'parcela' : 'parcelas'}`;
    document.getElementById('resumoParcelasFianca').textContent = `${1 + seguroFiancaExtras} ${(1 + seguroFiancaExtras) === 1 ? 'parcela' : 'parcelas'}`;
    document.getElementById('resumoCobrancaIncendio').textContent = `Cobrança automática: dias finais + aluguel inteiro · ${formatar(seguroIncendioTotal)}`;
    document.getElementById('resumoCobrancaFianca').textContent = `Cobrança automática: dias finais + aluguel inteiro · ${formatar(seguroFiancaTotal)}`;
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
    if (!invalido && dias > 0) {
        if (seguroIncendioDiasValor > 0.005) detalhamentoFinais.push( {
            nome:'Seguro incêndio', valor:seguroIncendioDiasValor
        }
        );
        if (seguroFiancaDiasValor > 0.005) detalhamentoFinais.push( {
            nome:'Seguro fiança', valor:seguroFiancaDiasValor
        }
        );
    }
    // O detalhamento do aviso também deve conter somente o aluguel.
    const detalhamentoAviso = [ {
        nome: 'Aluguel',
        valor: aluguelAviso
    }
    ];
    const detalhamentoAluguelInteiro = [
    ['Aluguel', aluguel], ['IPTU', iptu], ['Condomínio', condominio],
    ['Água', agua], ['Luz', luz], ['Internet', internet],
    ['Seguro incêndio', seguroIncendioAluguelValor], ['Seguro fiança', seguroFiancaAluguelValor]
    ].map(([nome, valor]) => ( {
        nome, valor: temAluguelInteiro ? valor : 0
    }
    ));
    updateTopicSummaries();
    atualizarModeloEmail( {
        totalProporcionais, admDiasFinais, repasseDiasFinais, totalAviso, multa, aluguelInteiro, manutencao, chaveiro,
        seguroIncendio: seguroIncendioTotal, seguroFianca: seguroFiancaTotal, segurosDiasFinais, segurosAluguelInteiro,
        temAluguelInteiro, semAluguelInteiro, total,
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
    // Os seguros aparecem no e-mail conforme o destino selecionado: dias finais e/ou aluguel inteiro.
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
    const texto = `${assunto}\n\nOlá, tudo bem?\n\nEstamos enviando este e-mail para informar que o imóvel foi desocupado. As chaves do imóvel foram entregues na imobiliária no dia ${dataEntrega}, encerrando-se assim o contrato de locação.\n\nEstamos tentando contato com o Sr.(a)${identificacao}\n\nNeste e-mail estão as informações de melhorias e débitos em aberto do imóvel locado pelo mesmo,${enderecoTexto}\n\nABILIO AREIA SOLUÇÕES IMOBILIARIAS LTDA, pessoa jurídica inscrita no CNPJ nº 13.219.509/0001-71, com sede em R. Aristídes Lôbo Sobrinho, 63 - Chácara Braz Miraglia, Jaú - SP, 17207-300, vem através da presente DECLARAR os valores em aberto referente ao imóvel acima citado:\n\n${lista}\n\nTotalizando o valor de ${formatar(dados.total)}.${periodoAviso ? `\n${avisoTexto.trim()}` : ''}\n\nObs.: Os valores estão sujeitos a aprovação da seguradora. Caso não haja a cobertura total por parte da mesma, ficarão débitos pendentes a serem acertados na imobiliária.\n\nEm caso de dúvidas, por favor, contatar sua gestora de contrato.\n\nAtenciosamente,`;
    ultimoEmailAutomatico = texto;
    if (!emailEditando) document.getElementById('modeloEmail').value = texto;
}
// v29: modo compacto da calculadora
const STORAGE_TOPIC_STATE = 'calculadoraRescisaoTopicosAbertosV29';
const TOPIC_IDS = ['topicoDiasFinais','topicoAviso','topicoMulta','topicoAluguelInteiro','topicoManutencao','topicoChaveiro','topicoSeguros'];
function topicStateLoad() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_TOPIC_STATE) || '{}');
    }
    catch (_) {
        return {
        }
    }
}
function topicStateSave() {
    const state = {
    }
    TOPIC_IDS.forEach(id => {
        const el=document.getElementById(id);
        if (el) state[id]=el.classList.contains('is-open');
    }
    );
    try {
        localStorage.setItem(STORAGE_TOPIC_STATE, JSON.stringify(state));
    }
    catch (_) {
    }
}
function topicIsComplete(id) {
    const hasValue = field => {
        const el = document.getElementById(field);
        if (!el) return false;
        if (el.type === 'checkbox') return el.checked;
        if (CAMPOS_MONETARIOS.has(field)) return numero(field) > 0;
        return String(el.value || '').trim() !== '';
    };

    switch (id) {
        case 'topicoDiasFinais':
            return hasValue('dataInicial') && hasValue('dataFinal');
        case 'topicoAviso':
            return hasValue('semAviso') || (hasValue('dataInicioAviso') && hasValue('dataFimAviso'));
        case 'topicoMulta':
            if (hasValue('semMulta')) return true;
            return modoSelecionado === 'dias'
                ? hasValue('dataInicioContrato') && hasValue('dataFinal')
                : hasValue('mesesFaltantes');
        case 'topicoAluguelInteiro':
            return hasValue('semAluguelInteiro') || hasValue('valorAluguelInteiro');
        case 'topicoManutencao':
            return hasValue('semManutencao') || hasValue('manutencao');
        case 'topicoChaveiro':
            return hasValue('semChaveiro') || hasValue('chaveiro');
        case 'topicoSeguros':
            return hasValue('semSeguros') || hasValue('seguroIncendio') || hasValue('seguroFianca');
        default:
            return false;
    }
}

function refreshTopicStepButtons() {
    let concluidos = 0;

    document.querySelectorAll('.workflow-step[data-topic]').forEach(btn => {
        const id = btn.dataset.topic;
        const card = document.getElementById(id);
        const completo = topicIsComplete(id);

        btn.classList.toggle('active', !!card?.classList.contains('is-open'));
        btn.classList.toggle('complete', completo);
        btn.setAttribute(
            'aria-label',
            completo ? btn.textContent.trim() + ' — concluído' : btn.textContent.trim()
        );

        if (completo) concluidos++;
    });

    const total = document.querySelectorAll('.workflow-step[data-topic]').length;
    let progress = document.getElementById('workflowProgress');

    if (!progress) {
        const actions = document.querySelector('.calculator-toolbar-actions');
        if (actions) {
            progress = document.createElement('span');
            progress.id = 'workflowProgress';
            progress.className = 'workflow-progress';
            actions.prepend(progress);
        }
    }

    if (progress) {
        progress.textContent = total ? concluidos + '/' + total + ' concluídos' : '';
        progress.classList.toggle('complete', total > 0 && concluidos === total);
    }
}
function setTopicOpen(id, open, persist=true) {
    const card=document.getElementById(id);
    if (!card) return;
    card.classList.add('accordion-card');
    card.classList.toggle('is-open', open);
    card.classList.toggle('is-collapsed', !open);
    const toggle=card.querySelector('.topic-toggle');
    if (toggle) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.innerHTML = open ? 'Recolher <span class="toggle-icon">⌃</span>' : 'Abrir <span class="toggle-icon">⌄</span>';
    }
    if (persist) topicStateSave();
    refreshTopicStepButtons();
}
function initCompactTopics() {
    const saved=topicStateLoad();
    const OPTIONAL_STATIC = new Set(['topicoAluguelInteiro','topicoManutencao','topicoChaveiro','topicoSeguros']);
    TOPIC_IDS.forEach((id, index) => {
        const card=document.getElementById(id);
        if (!card) return;
        const heading=card.querySelector('.section-heading');
        if (!heading) return;
        // Os quatro tópicos complementares ficam sempre visíveis em 2x2.
        if (OPTIONAL_STATIC.has(id)) {
            card.classList.remove('accordion-card','is-collapsed');
            card.classList.add('is-open');
            const toggle=heading.querySelector('.topic-toggle');
            if (toggle) toggle.remove();
        }
        else {
            // Dias finais, aviso e multa continuam usando o acordeão compacto.
            card.classList.add('accordion-card');
            let toggle=heading.querySelector('.topic-toggle');
            if (!toggle) {
                toggle=document.createElement('button');
                toggle.type='button';
                toggle.className='topic-toggle';
                heading.appendChild(toggle);
            }
            toggle.addEventListener('click', ev => {
                ev.stopPropagation();
                setTopicOpen(id,!card.classList.contains('is-open'));
            }
            );
            heading.addEventListener('click', ev => {
                if (ev.target.closest('button,input,label,a')) return;
                setTopicOpen(id,!card.classList.contains('is-open'));
            }
            );
            const hasSaved=Object.prototype.hasOwnProperty.call(saved,id);
            const open=hasSaved ? !!saved[id] : (index < 3);
            setTopicOpen(id, open, false);
        }
        const inner=heading.children[1];
        if (inner && !inner.querySelector('.topic-summary')) {
            const sum=document.createElement('span');
            sum.className='topic-summary';
            sum.dataset.summaryFor=id;
            inner.appendChild(sum);
        }
    }
    );
    document.getElementById('btnAbrirTopicos')?.addEventListener('click',()=> {
        TOPIC_IDS.filter(id=>!OPTIONAL_STATIC.has(id)).forEach(id=>setTopicOpen(id,true,false));
        topicStateSave();
    }
    );
    document.getElementById('btnFecharTopicos')?.addEventListener('click',()=> {
        TOPIC_IDS.filter(id=>!OPTIONAL_STATIC.has(id)).forEach(id=>setTopicOpen(id,false,false));
        topicStateSave();
    }
    );
    function rolarParaTopico(id) {
        const alvo = document.getElementById(id);
        if (!alvo) return;
        const deslocamento = 24;
        const top = Math.max(0, alvo.getBoundingClientRect().top + window.scrollY - deslocamento);
        window.scrollTo( {
            top, behavior: 'smooth'
        }
        );
    }
    document.querySelectorAll('.workflow-step[data-topic]').forEach(btn=>btn.addEventListener('click',()=> {
        const id=btn.dataset.topic;
        if (!OPTIONAL_STATIC.has(id)) setTopicOpen(id,true);
        // O scroll ocorre no próximo frame para que o card termine de abrir
        // antes de calcular sua posição na página.
        requestAnimationFrame(()=>requestAnimationFrame(()=>rolarParaTopico(id)));
    }
    ));
    refreshTopicStepButtons();
}
function topicMoney(id) {
    return document.getElementById(id)?.textContent || 'R$ 0,00';
}
function updateTopicSummaries() {
    const values = {
        topicoDiasFinais: (() => {
            const d=document.getElementById('diasCalculados')?.textContent;
            const v=topicMoney('totalProporcionais');
            return d && d!=='0 dias' ? `${d} · ${v}` : 'Aguardando datas';
        }
        )(),
        topicoAviso: (() => {
            if(document.getElementById('semAviso')?.checked) return 'Não aplicável';
            const d=document.getElementById('diasAviso')?.textContent;
            const v=topicMoney('totalAviso');
            return d && d!=='0 dias' ? `${d} · ${v}` : 'Aguardando período';
        }
        )(),
        topicoMulta: (() => {
            if(document.getElementById('semMulta')?.checked) return 'Não aplicável';
            const v=topicMoney('resumoMulta');
            return modoSelecionado ? `${nomeModo()} · ${v}` : 'Selecione o critério';
        }
        )(),
        topicoAluguelInteiro: (() => {
            if(document.getElementById('semAluguelInteiro')?.checked) return 'Não aplicável';
            return topicMoney('resumoAluguelInteiro');
        }
        )(),
        topicoManutencao: (() => {
            if(document.getElementById('semManutencao')?.checked) return 'Não aplicável';
            return topicMoney('resumoManutencao');
        }
        )(),
        topicoChaveiro: (() => {
            if(document.getElementById('semChaveiro')?.checked) return 'Não aplicável';
            return topicMoney('resumoChaveiro');
        }
        )(),
        topicoSeguros: (() => {
            if(document.getElementById('semSeguros')?.checked) return 'Não aplicável';
            const a=Number((topicMoney('resumoIncendio')||'').replace(/[^0-9,-]/g,'').replace('.','').replace(',','.'))||0;
            const b=Number((topicMoney('resumoFianca')||'').replace(/[^0-9,-]/g,'').replace('.','').replace(',','.'))||0;
            return formatar(a+b);
        }
        )()
    }
    Object.entries(values).forEach(([id,text])=> {
        const el=document.querySelector(`[data-summary-for="${id}"]`);
        if(el)el.textContent=text;
    }
    );
    const inc=topicMoney('resumoIncendio'), fia=topicMoney('resumoFianca');
    const sc=document.getElementById('resumoSegurosCompacto');
    if(sc) {
        if(document.getElementById('semSeguros')?.checked) sc.textContent='R$ 0,00';
        else {
            const n1=Number((inc||'').replace(/[^0-9,-]/g,'').replace('.','').replace(',','.'))||0;
            const n2=Number((fia||'').replace(/[^0-9,-]/g,'').replace('.','').replace(',','.'))||0;
            sc.textContent=formatar(n1+n2);
        }
    }
}
function initFinanceDetails() {
    const btn=document.getElementById('btnFinanceDetails');
    const box=document.getElementById('financeDetails');
    if(!btn||!box)return;
    btn.addEventListener('click',()=> {
        const open=box.classList.toggle('is-collapsed');
        btn.setAttribute('aria-expanded',open?'false':'true');
        btn.querySelector('span:last-child').textContent=open?'＋':'−';
    }
    );
}
function initMiniCollapses() {
    // Esta inicialização pode ser chamada mais de uma vez quando a calculadora
    // restaura um cálculo ou troca o critério da multa. Evitamos registrar
    // dois listeners no mesmo botão, pois isso faria um clique abrir e fechar
    // o painel imediatamente.
    document.querySelectorAll('[data-collapse-target]').forEach(btn => {
        if (btn.dataset.miniCollapseBound === '1') return;
        btn.dataset.miniCollapseBound = '1';
        btn.addEventListener('click', () => {
            const box = document.getElementById(btn.dataset.collapseTarget);
            if (!box) return;
            const collapsed = box.classList.toggle('is-collapsed');
            btn.textContent = collapsed ? 'Mostrar' : 'Ocultar';
        });
    });
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
    initCompactTopics();
    initFinanceDetails();
    initMiniCollapses();
    updateTopicSummaries();
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
    historicoIdAtual = null;
    definirStatusUI('Rascunho');
    excluirRascunhoServidor();
    for (const id of campos) {
        const el = document.getElementById(id);
        if (!el) continue;
        if (el.type === 'date') el.value = '';
        else if (el.type === 'checkbox') el.checked = false;
        else if (CAMPOS_MONETARIOS.has(id)) definirValorMonetario(id, 0);
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
    if (window.PODE_CRIAR_RESCISAO === false) {
        alert('Seu perfil possui acesso somente para consulta e não pode iniciar uma nova rescisão.');
        return;
    }
    if (!confirm('Deseja iniciar uma nova rescisão? Os dados atuais serão apagados.')) return;
    historicoIdAtual = null;
    definirStatusUI('Rascunho');
    excluirRascunhoServidor();
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
document.getElementById('btnVerDetalhes')?.addEventListener('click', () => {
    if (!historicoIdAtual) {
        alert('Salve a rescisão no histórico antes de abrir o relatório completo.');
        return;
    }
    window.location.href = `detalhe.php?id=${encodeURIComponent(historicoIdAtual)}`;
}
);
document.getElementById('btnSalvarHistorico').addEventListener('click', salvarHistorico);
document.getElementById('statusRescisao')?.addEventListener('change', e => atualizarStatusServidor(e.target.value));
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
    const detail = event.target.closest('[data-history-detail-index]');
    if (detail) {
        const item = obterHistorico()[Number(detail.dataset.historyDetailIndex)];
        if (item?.id) window.location.href=`detalhe.php?id=${encodeURIComponent(item.id)}`;
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
    definirStatusUI('Conferido');
    if (historicoIdAtual) atualizarStatusServidor('Conferido');
    status.textContent='Conferência confirmada. O e-mail está liberado para cópia.';
    status.classList.add('ok');
    document.getElementById('btnCopiarEmail').disabled=false;
}
);
document.getElementById('btnDesbloquearConferencia').addEventListener('click', () => {
    conferenciaConfirmada=false;
    definirStatusUI('Em conferência');
    if (historicoIdAtual) atualizarStatusServidor('Em conferência');
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
const camposDiasFinais = new Set(['dataInicial','dataFinal','aluguel','iptu','condominio','agua','luz','internet','percentualAdm','semEncargos','seguroIncendio','seguroIncendioExtras','seguroFianca','seguroFiancaExtras']);
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
inicializarMascarasMonetarias();

const parametrosPagina = new URLSearchParams(window.location.search);
const historicoIdInicialPrevia = parametrosPagina.get('historico_id');
const novaRescisaoInicial = parametrosPagina.get('nova') === '1';
// Nova rescisão aberta pelo menu é sempre uma sessão limpa.
// Não restauramos localStorage nem rascunho do servidor, para que o usuário
// veja primeiro a escolha do critério da multa.
if (novaRescisaoInicial) {
    modoSelecionado = '';
    localStorage.removeItem(STORAGE_FORM);
    localStorage.removeItem(STORAGE_LOCK);
    historicoIdAtual = null;
    excluirRascunhoServidor();
}
// Quando a URL traz um histórico, o banco é a fonte de verdade.
const restaurouDadosLocais = (historicoIdInicialPrevia || novaRescisaoInicial)
? false
: restaurarDadosAutomaticamente();
if (!restaurouDadosLocais && !historicoIdInicialPrevia && !novaRescisaoInicial) carregarRascunhoServidor();
// Dados restaurados não significam conferência concluída.
// A multa (inclusive no critério por dias) só fica como conferida
// depois que o usuário revisar/alterar um campo do tópico nesta sessão.
topicosRevisados.diasFinais = false;
topicosRevisados.multa = false;
conferenciaConfirmada = false;
historicoIdAtual = null;
definirStatusUI('Rascunho');
historicoCache = [];
historicoOnline = false;
historicoCarregando = true;
renderizarHistorico();

// Em "Nova rescisão" a tela é independente do histórico.
// A consulta central será feita quando o usuário abrir/atualizar o histórico.
if (!novaRescisaoInicial) {
    carregarHistoricoServidor();
} else {
    historicoCarregando = false;
    renderizarHistorico();
}
revisaoBloqueada = localStorage.getItem(STORAGE_LOCK) === '1';
if (modoSelecionado && !novaRescisaoInicial) {
    atualizarModoUI();
    iniciarModo(modoSelecionado);
    document.getElementById('modeScreen').classList.add('hidden');
    document.getElementById('calculatorScreen').classList.remove('hidden');
}
else if (novaRescisaoInicial) {
    document.getElementById('modeScreen').classList.remove('hidden');
    document.getElementById('calculatorScreen').classList.add('hidden');
    document.querySelectorAll('.mode-option').forEach(btn => btn.classList.remove('selected'));
    document.getElementById('selectedMode')?.classList.add('hidden');
    window.scrollTo( {
        top: 0, behavior: 'instant'
    }
    );
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
const historicoIdInicial = historicoIdInicialPrevia;
if (historicoIdInicial) abrirHistoricoPorId(historicoIdInicial, window.HISTORICO_INICIAL || null);
document.addEventListener('keydown', event => {
    if (event.ctrlKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        salvarHistorico();
    }
    else if (event.ctrlKey && event.key.toLowerCase() === 'h') {
        event.preventDefault();
        window.location.href='historico.php';
    }
    else if (event.ctrlKey && event.key.toLowerCase() === 'n') {
        event.preventDefault();
        novaRescisao();
    }
    else if (event.key === 'Escape' && document.getElementById('calculatorScreen')?.classList.contains('history-direct-mode')) fecharHistoricoDireto();
}
);
