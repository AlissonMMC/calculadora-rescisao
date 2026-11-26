<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario=exigirLoginPagina();
$navPage='contestacoes';
$navBase='';
$csrf=csrfToken();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contestação de vistoria — Folha de Cálculo</title><link rel="stylesheet" href="assets/css/pages/contestacoes.css"></head><body>
<?php require __DIR__ . '/includes/nav_global.php'; ?>
<main class="contest-wrap">
<header class="contest-hero"><div><span class="eyebrow">Módulo de vistorias</span><h1>Contestação de vistoria</h1><p>Compare o estado do imóvel na entrada e na saída, registre as justificativas e gere um documento organizado.</p></div><button class="cv-btn cv-primary" id="newContestacao">＋ Nova contestação</button></header>
<section class="cv-card cv-list-card" id="listSection"><div class="cv-section-head"><div><h2>Contestações registradas</h2><p>Consulte, edite ou gere novamente o documento.</p></div><span class="cv-count" id="listCount">Carregando...</span></div><div class="cv-table-wrap"><table class="cv-table"><thead><tr><th>Inquilino / imóvel</th><th>Data</th><th>Contrato</th><th>Fotos</th><th>Ações</th></tr></thead><tbody id="contestacoesList"><tr><td colspan="5" class="cv-empty">Carregando registros...</td></tr></tbody></table></div></section>
<section class="cv-card cv-form-card" id="formSection" hidden>
<div class="cv-section-head"><div><span class="cv-kicker" id="formKicker">NOVO REGISTRO</span><h2 id="formTitle">Dados da contestação</h2><p>Preencha os dados da locação e adicione os cômodos que precisam ser contestados.</p></div><button class="cv-btn" type="button" id="closeForm">Voltar à lista</button></div>
<form id="contestacaoForm">
<input type="hidden" id="contestacaoId" value="">
<div class="cv-fields">
<div class="cv-field"><label for="nome_inquilino">Nome do inquilino *</label><input id="nome_inquilino" maxlength="180" required></div>
<div class="cv-field"><label for="cpf_inquilino">CPF do inquilino</label><input id="cpf_inquilino" maxlength="14" placeholder="000.000.000-00"></div>
<div class="cv-field cv-wide"><label for="endereco_locacao">Endereço da locação *</label><input id="endereco_locacao" maxlength="255" required></div>
<div class="cv-field"><label for="data_contestacao">Data da contestação *</label><input id="data_contestacao" type="date" required></div>
<div class="cv-field"><label for="numero_contrato">Número do contrato</label><input id="numero_contrato" maxlength="80"></div>
<div class="cv-field"><label for="fianca">Fiança</label><input id="fianca" maxlength="180" placeholder="Ex.: fiador, seguro-fiança, caução..."></div>
</div>
<div class="cv-rooms-head"><div><h3>Comparação por cômodo</h3><p>Adicione fotos de entrada e saída e descreva as diferenças observadas.</p></div><button class="cv-btn cv-primary" type="button" id="addRoom">＋ Adicionar cômodo</button></div>
<div id="roomsContainer"></div>
<div class="cv-form-footer"><span id="saveFeedback" role="status"></span><button class="cv-btn" type="button" id="cancelForm">Cancelar</button><button class="cv-btn cv-primary" type="submit" id="saveContestacao">Salvar contestação</button></div>
</form></section>
</main>
<script>window.APP_CSRF=<?= json_encode($csrf) ?>;</script><script src="assets/js/pages/contestacoes.js"></script></body></html>