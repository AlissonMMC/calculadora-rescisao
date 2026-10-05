<?php
declare(strict_types=1);
require __DIR__ . '/../api/config.php';
require __DIR__ . '/includes/storage.php';

$usuario = usuarioAtual();
if (!$usuario) { header('Location: ../login.php'); exit; }
$csrf = csrfToken();

function usuarioEhAdmin(array $u): bool {
    $perfil = strtolower((string)($u['perfil'] ?? $u['role'] ?? $u['nivel'] ?? ''));
    return !empty($u['is_admin']) || !empty($u['admin']) || $perfil === 'admin' || strtolower((string)($u['login'] ?? '')) === 'admin';
}

$orcStorage = orcamentosStorage();
$prestadoresFile = $orcStorage . '/prestadores.json';
$defaults = [
    ['id'=>1,'nome'=>'Rogério Vieira de Brito','cpf'=>'323.634.328-19','telefone'=>'','endereco'=>'','assinatura'=>''],
    ['id'=>2,'nome'=>'Marlon Macedo Ledis','cpf'=>'272.390.238-26','telefone'=>'','endereco'=>'','assinatura'=>''],
    ['id'=>3,'nome'=>'Waldemar de Agostini Junior','cpf'=>'343.689.258-07','telefone'=>'','endereco'=>'','assinatura'=>''],
];
if (is_file($prestadoresFile)) {
    $loaded = json_decode((string)file_get_contents($prestadoresFile), true);
    if (is_array($loaded) && $loaded) $defaults = $loaded;
}
$settingsFile = $orcStorage . '/config_gerador.json';
$settings = ['signature_height'=>150,'signature_gap'=>1,'signature_align'=>'left'];
if (is_file($settingsFile)) {
    $loaded = json_decode((string)file_get_contents($settingsFile), true);
    if (is_array($loaded)) $settings = array_merge($settings, $loaded);
}
$menuPage='gerador';
?>
<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gerador de Orçamentos — Folha de Cálculo</title>
<link rel="stylesheet" href="../assets/css/orcamentos/index.css"></head>
<body>
<?php require __DIR__ . '/includes/nav.php'; ?>
<main class="container">
<div class="hero"><div><span class="eyebrow">Processamento web · Opção B</span><h1>Gerador de Orçamentos</h1><p>Leia uma planilha, valide sua estrutura, escolha os prestadores, gere as três versões, incorpore as assinaturas e exporte Excel + PDF sem alterar o arquivo original.</p></div><div class="userbox"><small>Usuário conectado</small><strong><?=htmlspecialchars((string)($usuario['nome'] ?? $usuario['login'] ?? 'Usuário'),ENT_QUOTES,'UTF-8')?></strong><div class="server"><span class="dot"></span>Servidor pronto</div></div></div>
<form id="budgetForm" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="providers_json" id="providers_json"><input type="hidden" name="signature_settings" id="signature_settings">
<div class="grid">
<section class="card"><h2 class="section-title"><span class="num">1</span>Arquivo do orçamento</h2><div class="field"><label for="source_file">Arquivo Excel</label><div class="file-row"><label class="file-picker" for="source_file"><input id="source_file" name="source_file" type="file" accept=".xlsx" required><span class="file-picker-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg></span><span class="file-picker-copy"><strong id="filePickerName">Selecionar arquivo Excel</strong><small id="filePickerHint">Somente arquivos .xlsx</small></span><span class="file-picker-arrow">›</span></label><button class="btn secondary" id="clearFile" type="button">Limpar</button></div><span class="hint">O original fica intacto. O servidor trabalha em uma cópia temporária.</span></div><div class="field"><label for="sheet_name">Planilha a copiar</label><select id="sheet_name" name="sheet_name" required disabled><option value="">Selecione o arquivo primeiro</option></select></div><div class="field"><span class="hint" id="sheetStatus">Aguardando arquivo.</span></div><div class="validation" id="validation"></div><div class="preview-card" id="sheetPreview"><div class="preview-head"><strong style="font-size:.75rem">Pré-visualização da planilha</strong><span class="hint" id="previewHint"></span></div><div class="preview-grid"><div class="preview-value"><small>MATERIAIS</small><strong id="prevMat">—</strong></div><div class="preview-value"><small>MÃO DE OBRA</small><strong id="prevMo">—</strong></div><div class="preview-value"><small>TOTAL</small><strong id="prevTotal">—</strong></div></div><div class="preview-items" id="previewItems"><div class="preview-empty">Selecione uma aba para visualizar os itens.</div></div></div></section>
<section class="card"><h2 class="section-title"><span class="num">2</span>Dados da locação</h2><div class="field"><label for="locatario">Locatário</label><input id="locatario" name="locatario" required></div><div class="field"><label for="endereco">Endereço</label><input id="endereco" name="endereco" required></div><div class="field"><label for="contato">Contato</label><input id="contato" name="contato" required></div><div class="field"><label for="data_locacao">Data</label><input id="data_locacao" name="data_locacao" type="date" required></div><div class="field"><label for="metragem">Metragem (m²)</label><input id="metragem" name="metragem" type="number" min="0" step="0.01" required></div></section>
<section class="card full"><h2 class="section-title"><span class="num">3</span>Prestadores e assinaturas</h2><a href="prestadores.php" class="btn secondary small" style="float:right;margin-top:-42px;text-decoration:none">Gerenciar prestadores e assinaturas</a><p class="hint" style="margin-top:-7px;margin-bottom:12px">Escolha quem ficará em cada orçamento. A assinatura cadastrada para cada prestador fica salva no servidor e é carregada automaticamente nos próximos orçamentos. O arquivo abaixo serve apenas para substituir a assinatura nesta geração.</p><div class="providers" id="providers"></div></section>
<section class="card full"><h2 class="section-title"><span class="num">4</span>Ajustes de assinatura e conferência</h2><div class="settings-grid"><div class="setting"><label for="signature_height">Altura da assinatura (px)</label><input id="signature_height" type="number" min="80" max="320" step="10" value="<?=htmlspecialchars((string)$settings['signature_height'],ENT_QUOTES,'UTF-8')?>"></div><div class="setting"><label for="signature_gap">Espaço após o TOTAL (linhas)</label><input id="signature_gap" type="number" min="1" max="6" step="1" value="<?=htmlspecialchars((string)$settings['signature_gap'],ENT_QUOTES,'UTF-8')?>"></div><div class="setting"><label for="signature_align">Alinhamento da assinatura</label><select id="signature_align"><option value="left">Esquerda</option><option value="center" <?=($settings['signature_align']??'')==='center'?'selected':''?>>Centro</option><option value="right" <?=($settings['signature_align']??'')==='right'?'selected':''?>>Direita</option></select></div></div><div class="signature-preview-wrap"><div class="signature-preview-head"><div><strong>Prévia para ajuste da assinatura</strong><div class="signature-scale-note">A prévia mostra a região abaixo do TOTAL. Altura, espaço e alinhamento são atualizados na hora.</div></div><span class="hint" id="signaturePreviewHint"></span></div><div class="signature-preview-grid" id="signaturePreviewGrid"><div class="signature-preview-empty">Selecione um prestador para visualizar a assinatura.</div></div></div><div class="footer-tools"><span class="admin-note">A validação verifica TOTAL e as colunas de valores. A coluna MÃO DE OBRA foi ampliada para evitar cortes.</span><button type="button" class="btn secondary small" id="btnRefreshPreview">Atualizar prévia</button></div></section>
</div>
<div class="actions"><button class="btn secondary" type="button" id="resetBtn">Limpar formulário</button><button class="btn" type="submit" id="generateBtn" disabled>Gerar 3 orçamentos</button></div>
<div class="progress" id="progress"><div class="progress-head"><span id="progressLabel">Processando...</span><span id="progressPct">0%</span></div><div class="bar"><i id="progressBar"></i></div></div>
<div class="errors" id="errors"></div>
<div class="result" id="result"><div class="result-top"><div><strong>Orçamentos gerados com sucesso.</strong><span class="hint" id="resultInfo"></span></div><a class="btn" id="downloadBtn" href="#">Baixar ZIP</a></div><div class="pdf-grid"><a class="btn secondary small" id="pdf1" target="_blank">Abrir Orçamento</a><a class="btn secondary small" id="pdf2" target="_blank">Abrir Orçamento 2</a><a class="btn secondary small" id="pdf3" target="_blank">Abrir Orçamento 3</a></div></div>
</form></main>
<script>
window.ORCAMENTOS_PROVIDERS=<?=json_encode($defaults,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
window.ORCAMENTOS_SETTINGS=<?=json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
window.ORCAMENTOS_CSRF='<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>';
</script>
<script src="../assets/js/orcamentos/index.js?v=20261003"></script></body></html>
