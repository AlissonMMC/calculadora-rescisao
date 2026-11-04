<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario = exigirAdminPagina();
$navPage = 'empresas';
$navBase = '';
$csrf = csrfToken();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validarCsrf();
        $acao = (string)($_POST['acao'] ?? '');
        $pdo = db();

        if ($acao === 'salvar') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $razaoSocial = limparTexto($_POST['razao_social'] ?? '', 180);
            $cnpj = preg_replace('/\D+/', '', (string)($_POST['cnpj'] ?? '')) ?? '';
            $endereco = limparTexto($_POST['endereco'] ?? '', 500);
            if ($razaoSocial === '' || strlen($cnpj) !== 14 || $endereco === '') {
                throw new RuntimeException('Informe razão social, CNPJ com 14 dígitos e endereço completo da sede.');
            }
            $stmt = $pdo->prepare('SELECT id FROM empresas WHERE cnpj = ? AND id <> ? LIMIT 1');
            $stmt->execute([$cnpj, $id]);
            if ($stmt->fetch()) throw new RuntimeException('Já existe uma empresa cadastrada com este CNPJ.');

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE empresas SET razao_social = ?, cnpj = ?, endereco = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$razaoSocial, $cnpj, $endereco, $id]);
                registrarAuditoriaSistema('empresas', 'edicao', $id, 'Cadastro empresarial atualizado: ' . $razaoSocial, (int)$usuario['id']);
                $mensagem = 'Empresa atualizada com sucesso.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO empresas (razao_social, cnpj, endereco, ativo) VALUES (?, ?, ?, 1)');
                $stmt->execute([$razaoSocial, $cnpj, $endereco]);
                $id = (int)$pdo->lastInsertId();
                registrarAuditoriaSistema('empresas', 'criacao', $id, 'Empresa cadastrada: ' . $razaoSocial, (int)$usuario['id']);
                $mensagem = 'Empresa cadastrada com sucesso.';
            }
        } elseif ($acao === 'alternar') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $stmt = $pdo->prepare('SELECT id, razao_social, ativo FROM empresas WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $empresa = $stmt->fetch();
            if (!$empresa) throw new RuntimeException('Empresa não encontrada.');
            $novoStatus = (int)$empresa['ativo'] ? 0 : 1;
            $pdo->prepare('UPDATE empresas SET ativo = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?')->execute([$novoStatus, $id]);
            registrarAuditoriaSistema('empresas', $novoStatus ? 'ativacao' : 'desativacao', $id, 'Empresa ' . $empresa['razao_social'] . ($novoStatus ? ' ativada.' : ' desativada.'), (int)$usuario['id']);
            $mensagem = $novoStatus ? 'Empresa ativada.' : 'Empresa desativada.';
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$empresas = [];
try {
    $empresas = db()->query('SELECT id, razao_social, cnpj, endereco, ativo, criado_em, atualizado_em FROM empresas ORDER BY ativo DESC, razao_social ASC')->fetchAll();
} catch (Throwable $e) {
    $erro = 'A tabela de empresas ainda não existe. Importe o arquivo database/migrations/20261104_create_empresas.sql pelo phpMyAdmin e atualize esta página.';
}
$fmtCnpj = static function(string $cnpj): string {
    $n = preg_replace('/\D+/', '', $cnpj) ?? '';
    return strlen($n) === 14 ? substr($n,0,2).'.'.substr($n,2,3).'.'.substr($n,5,3).'/'.substr($n,8,4).'-'.substr($n,12,2) : $cnpj;
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Empresas — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/core/nav-global.css">
<link rel="stylesheet" href="assets/css/core/layout-standard.css">
<link rel="stylesheet" href="assets/css/core/components-standard.css">
<style>
.empresas-wrap{max-width:1180px;margin:32px auto;padding:0 22px 48px}
.empresas-hero,.empresa-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:24px;margin-bottom:20px;box-shadow:0 10px 28px rgba(17,24,39,.05)}
.empresas-hero .eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;color:#c2410c;text-transform:uppercase}
.empresas-hero h1{margin:8px 0;font-size:30px;color:#171717}
.empresas-hero p,.empresa-muted{color:#737373}
.empresa-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.empresa-field{display:flex;flex-direction:column;gap:6px}
.empresa-field.full{grid-column:1/-1}
.empresa-field label{font-size:13px;font-weight:700;color:#374151}
.empresa-field input,.empresa-field textarea{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:11px 12px;font:inherit}
.empresa-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-top:14px}
.empresa-btn{border:0;border-radius:9px;padding:10px 15px;background:#171717;color:white;font-weight:700;cursor:pointer}
.empresa-btn.secondary{background:#f3f4f6;color:#222}
.empresa-btn.warning{background:#fff7ed;color:#9a3412}
.empresa-table{width:100%;border-collapse:collapse}
.empresa-table th,.empresa-table td{padding:13px 10px;text-align:left;border-bottom:1px solid #eee;vertical-align:top}
.empresa-table th{font-size:11px;text-transform:uppercase;color:#737373;letter-spacing:.06em}
.empresa-status{font-size:12px;font-weight:700}.empresa-status.off{color:#b91c1c}.empresa-status.on{color:#15803d}
.empresa-edit{margin-top:8px}.empresa-edit summary{cursor:pointer;color:#9a3412;font-weight:700}
.empresa-message{padding:12px 14px;border-radius:10px;margin-bottom:16px;background:#ecfdf5;color:#166534}
.empresa-error{padding:12px 14px;border-radius:10px;margin-bottom:16px;background:#fef2f2;color:#991b1b}
@media(max-width:700px){.empresa-form{grid-template-columns:1fr}.empresa-field.full{grid-column:auto}.empresa-table{min-width:720px}.empresa-table-wrap{overflow:auto}}
</style>
</head>
<body>
<?php require __DIR__ . '/includes/nav_global.php'; ?>
<main class="empresas-wrap">
<section class="empresas-hero"><span class="eyebrow">Administração</span><h1>Cadastro de empresas</h1><p>Gerencie as empresas que podem ser selecionadas nas rescisões. Os dados utilizados em cada rescisão ficam salvos como uma cópia histórica.</p></section>
<?php if ($mensagem): ?><div class="empresa-message"><?= htmlspecialchars($mensagem,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if ($erro): ?><div class="empresa-error"><?= htmlspecialchars($erro,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<section class="empresa-card">
<h2>Cadastrar nova empresa</h2>
<form method="post" class="empresa-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="acao" value="salvar">
<div class="empresa-field"><label for="razao_social">Razão social</label><input id="razao_social" name="razao_social" maxlength="180" required></div>
<div class="empresa-field"><label for="cnpj">CNPJ</label><input id="cnpj" name="cnpj" maxlength="18" placeholder="00.000.000/0000-00" required></div>
<div class="empresa-field full"><label for="endereco">Endereço completo da sede</label><textarea id="endereco" name="endereco" rows="2" maxlength="500" placeholder="Rua, número, bairro, cidade/UF e CEP" required></textarea></div>
<div class="empresa-field full"><div class="empresa-actions"><button class="empresa-btn" type="submit">Cadastrar empresa</button><span class="empresa-muted">O CNPJ será armazenado somente com números.</span></div></div>
</form>
</section>
<section class="empresa-card">
<h2>Empresas cadastradas</h2>
<?php if (!$empresas): ?><p class="empresa-muted">Nenhuma empresa cadastrada.</p><?php else: ?>
<div class="empresa-table-wrap"><table class="empresa-table"><thead><tr><th>Razão social / CNPJ</th><th>Endereço da sede</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($empresas as $empresa): ?>
<tr>
<td><strong><?= htmlspecialchars($empresa['razao_social'],ENT_QUOTES,'UTF-8') ?></strong><br><span class="empresa-muted"><?= htmlspecialchars($fmtCnpj((string)$empresa['cnpj']),ENT_QUOTES,'UTF-8') ?></span></td>
<td><?= htmlspecialchars($empresa['endereco'],ENT_QUOTES,'UTF-8') ?></td>
<td><span class="empresa-status <?= (int)$empresa['ativo']?'on':'off' ?>"><?= (int)$empresa['ativo']?'Ativa':'Inativa' ?></span></td>
<td>
<details class="empresa-edit"><summary>Editar cadastro</summary>
<form method="post" class="empresa-form" style="min-width:320px;margin-top:12px">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?= (int)$empresa['id'] ?>">
<div class="empresa-field"><label>Razão social</label><input name="razao_social" maxlength="180" value="<?= htmlspecialchars($empresa['razao_social'],ENT_QUOTES,'UTF-8') ?>" required></div>
<div class="empresa-field"><label>CNPJ</label><input name="cnpj" maxlength="18" value="<?= htmlspecialchars($fmtCnpj((string)$empresa['cnpj']),ENT_QUOTES,'UTF-8') ?>" required></div>
<div class="empresa-field full"><label>Endereço completo da sede</label><textarea name="endereco" rows="2" maxlength="500" required><?= htmlspecialchars($empresa['endereco'],ENT_QUOTES,'UTF-8') ?></textarea></div>
<div class="empresa-field full"><div class="empresa-actions"><button class="empresa-btn" type="submit">Salvar alterações</button></div></div>
</form>
</details>
<form method="post" style="margin-top:8px" onsubmit="return confirm('<?= (int)$empresa['ativo']?'Desativar':'Ativar' ?> esta empresa?')">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?= (int)$empresa['id'] ?>">
<button class="empresa-btn <?= (int)$empresa['ativo']?'warning':'secondary' ?>" type="submit"><?= (int)$empresa['ativo']?'Desativar':'Ativar' ?></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
</main>
</body></html>