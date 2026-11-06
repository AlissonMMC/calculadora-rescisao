<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario = exigirAdminPagina();
$navPage = 'empresas';
$navBase = '';
$empresas = [];
$erro = '';
try {
    $empresas = db()->query('SELECT id, razao_social, cnpj, endereco, ativo FROM empresas ORDER BY ativo DESC, razao_social')->fetchAll();
} catch (Throwable $e) {
    $erro = 'Não foi possível carregar as empresas. Confirme se a migração 005 foi aplicada.';
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Empresas responsáveis — Folha de Cálculo</title>
<style>
body{background:#f5f6f8;color:#20242b;font-family:Arial,sans-serif}
.companies-wrap{max-width:1250px;margin:34px auto;padding:0 22px}
.companies-head{margin-bottom:22px}.companies-head h1{margin:0 0 8px;font-size:28px}.companies-head p{color:#69717d;margin:0}
.companies-grid{display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:18px}
.company-card{background:#fff;border:1px solid #e4e7ec;border-radius:14px;padding:22px;box-shadow:0 4px 18px #20242b08}
.company-card h2{font-size:18px;margin:0 0 8px}.company-card .sub{color:#77808c;font-size:13px;margin:0 0 18px}
.field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:6px;color:#344054}
.field input,.field textarea{width:100%;box-sizing:border-box;border:1px solid #d9dee6;border-radius:8px;padding:11px;font:inherit;background:#fff;color:#20242b}
.field input:focus,.field textarea:focus{outline:2px solid #d97706;outline-offset:1px}
.field textarea{min-height:85px;resize:vertical}.actions{display:flex;gap:8px;flex-wrap:wrap}
.btn{border:0;border-radius:8px;padding:10px 14px;background:#eceff3;color:#252a32;font-weight:700;cursor:pointer}
.btn-primary{background:#d97706;color:white}.btn-danger{background:#fff0ef;color:#b42318}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;font-size:13px}
th,td{text-align:left;padding:12px 10px;border-bottom:1px solid #edf0f3;vertical-align:top}
th{color:#68717e;font-size:11px;text-transform:uppercase}
.status{display:inline-block;border-radius:20px;padding:5px 9px;background:#e8f7ee;color:#18794e;font-weight:700}
.status.off{background:#f1f2f4;color:#737b86}
.notice{padding:12px;border-radius:8px;background:#fff4e5;color:#8a4b08;margin-bottom:16px}
.muted{color:#77808c;font-size:12px}
html[data-theme="dark"] body{background:#0d1117;color:#e7edf5}
html[data-theme="dark"] .companies-head h1,html[data-theme="dark"] .company-card h2{color:#f1f5f9}
html[data-theme="dark"] .companies-head p,html[data-theme="dark"] .company-card .sub,html[data-theme="dark"] .muted{color:#a6b1c2}
html[data-theme="dark"] .company-card{background:#151b23;border-color:#2b3542;box-shadow:0 4px 18px rgba(0,0,0,.18)}
html[data-theme="dark"] .field label{color:#d6deea}
html[data-theme="dark"] .field input,html[data-theme="dark"] .field textarea{background:#0f141b;color:#e7edf5;border-color:#354152}
html[data-theme="dark"] .field input::placeholder,html[data-theme="dark"] .field textarea::placeholder{color:#778598}
html[data-theme="dark"] th{color:#b8c4d4}
html[data-theme="dark"] td{color:#d5deea;border-bottom-color:#2b3542}
html[data-theme="dark"] .btn:not(.btn-primary):not(.btn-danger){background:#26313e;color:#e5edf7;border:1px solid #3a4656}
html[data-theme="dark"] .btn-danger{background:#3a2023;color:#ffb4b4;border:1px solid #603238}
html[data-theme="dark"] .status{background:#173b2b;color:#8ce0b2}
html[data-theme="dark"] .status.off{background:#303743;color:#c0c8d3}
html[data-theme="dark"] .notice{background:#3b2b17;color:#ffd59b;border:1px solid #6c4a1c}
@media(max-width:850px){.companies-grid{grid-template-columns:1fr}.companies-wrap{margin:20px auto;padding:0 14px}}
</style></head><body>
<?php require __DIR__ . '/includes/nav_global.php'; ?>
<main class="companies-wrap">
<header class="companies-head"><h1>Empresas responsáveis</h1><p>Cadastre as pessoas jurídicas que poderão ser selecionadas na folha de cálculo e no modelo de e-mail.</p></header>
<?php if ($erro): ?><div class="notice"><?= htmlspecialchars($erro,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<div id="notice" class="notice" hidden></div>
<div class="companies-grid">
<section class="company-card"><h2 id="formTitle">Cadastrar empresa</h2><p class="sub">Nenhuma empresa é pré-cadastrada. Inclua os dados manualmente.</p>
<form id="companyForm"><input type="hidden" id="companyId" value="">
<div class="field"><label for="razao">Razão social</label><input id="razao" maxlength="190" required></div>
<div class="field"><label for="cnpj">CNPJ</label><input id="cnpj" maxlength="18" placeholder="00.000.000/0000-00" required></div>
<div class="field"><label for="endereco">Endereço completo da sede</label><textarea id="endereco" maxlength="500" placeholder="Rua, número, complemento, bairro, cidade/UF e CEP" required></textarea></div>
<div class="actions"><button class="btn btn-primary" type="submit">Salvar empresa</button><button class="btn" type="button" id="cancelEdit" hidden>Cancelar edição</button></div></form></section>
<section class="company-card"><h2>Empresas cadastradas</h2><p class="sub">Empresas inativas permanecem no histórico, mas não aparecem para novas seleções.</p>
<div class="table-wrap"><table><thead><tr><th>Razão social</th><th>CNPJ</th><th>Endereço da sede</th><th>Status</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($empresas as $e): ?><tr>
<td><strong><?= htmlspecialchars($e['razao_social'],ENT_QUOTES,'UTF-8') ?></strong></td><td><?= htmlspecialchars($e['cnpj'],ENT_QUOTES,'UTF-8') ?></td>
<td><?= nl2br(htmlspecialchars($e['endereco'],ENT_QUOTES,'UTF-8')) ?></td>
<td><span class="status <?= (int)$e['ativo'] ? '' : 'off' ?>"><?= (int)$e['ativo'] ? 'Ativa' : 'Inativa' ?></span></td>
<td><div class="actions">
<button class="btn" type="button" data-edit='<?= htmlspecialchars(json_encode(['id'=>(int)$e['id'],'razao_social'=>$e['razao_social'],'cnpj'=>$e['cnpj'],'endereco'=>$e['endereco']],JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT),ENT_QUOTES,'UTF-8') ?>'>Editar</button>
<button class="btn <?= (int)$e['ativo'] ? 'btn-danger' : '' ?>" type="button" data-status="<?= (int)$e['id'] ?>"><?= (int)$e['ativo'] ? 'Desativar' : 'Ativar' ?></button>
</div></td></tr><?php endforeach; ?>
<?php if (!$empresas): ?><tr><td colspan="5" class="muted">Nenhuma empresa cadastrada.</td></tr><?php endif; ?>
</tbody></table></div></section></div></main>
<script>
const csrf = <?= json_encode($csrf) ?>, notice=document.getElementById('notice');
function avisar(msg,erro=false){notice.textContent=msg;notice.hidden=false;notice.style.background=erro?'#fff0ef':'#e8f7ee';notice.style.color=erro?'#b42318':'#18794e';}
function limpar(){document.getElementById('companyForm').reset();document.getElementById('companyId').value='';document.getElementById('formTitle').textContent='Cadastrar empresa';document.getElementById('cancelEdit').hidden=true;}
document.getElementById('cancelEdit').addEventListener('click',limpar);
document.getElementById('cnpj').addEventListener('input',e=>{let v=e.target.value.replace(/\D/g,'').slice(0,14);v=v.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d{1,2})$/,'$1-$2');e.target.value=v;});
async function post(payload){const r=await fetch('api/empresas.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},credentials:'same-origin',body:JSON.stringify(payload)});const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Falha na operação.');return j;}
document.getElementById('companyForm').addEventListener('submit',async e=>{e.preventDefault();try{await post({acao:'salvar',id:Number(document.getElementById('companyId').value)||0,razao_social:document.getElementById('razao').value,cnpj:document.getElementById('cnpj').value,endereco:document.getElementById('endereco').value});location.reload();}catch(err){avisar(err.message,true);}});
document.querySelectorAll('[data-edit]').forEach(btn=>btn.addEventListener('click',()=>{const d=JSON.parse(btn.dataset.edit);document.getElementById('companyId').value=d.id;document.getElementById('razao').value=d.razao_social;document.getElementById('cnpj').value=d.cnpj;document.getElementById('endereco').value=d.endereco;document.getElementById('formTitle').textContent='Editar empresa';document.getElementById('cancelEdit').hidden=false;window.scrollTo({top:0,behavior:'smooth'});}));
document.querySelectorAll('[data-status]').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm('Deseja alterar o status desta empresa?'))return;try{await post({acao:'status',id:Number(btn.dataset.status)});location.reload();}catch(err){avisar(err.message,true);}}));
</script></body></html>
