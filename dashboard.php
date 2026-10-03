<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario = exigirPermissaoPagina('dashboard.view');
$navPage = 'dashboard';
$navBase = "";
$csrf = csrfToken();

$stats = [
    'total_registros' => 0,
    'valor_total' => 0.0,
    'total_adm' => 0.0,
    'total_repasse' => 0.0,
    'hoje' => 0,
    'meus' => 0,
    'meus_adm' => 0.0,
    'meus_repasse' => 0.0,
];
$statusCounts = array_fill_keys(STATUS_RESCISAO, 0);
$recentes = [];
$erro = '';

try {
    $pdo = db();
    $row = $pdo->query('SELECT COUNT(*) AS qtd, COALESCE(SUM(total),0) AS total, COALESCE(SUM(total_adm),0) AS adm, COALESCE(SUM(total_repasse),0) AS repasse FROM historico_rescisoes')->fetch() ?: [];
    $stats['total_registros'] = (int)($row['qtd'] ?? 0);
    $stats['valor_total'] = (float)($row['total'] ?? 0);
    $stats['total_adm'] = (float)($row['adm'] ?? 0);
    $stats['total_repasse'] = (float)($row['repasse'] ?? 0);

    $stats['hoje'] = (int)$pdo->query('SELECT COUNT(*) FROM historico_rescisoes WHERE criado_em >= CURDATE()')->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) qtd, COALESCE(SUM(total_adm),0) adm, COALESCE(SUM(total_repasse),0) repasse FROM historico_rescisoes WHERE usuario_id = ?');
    $stmt->execute([(int)$usuario['id']]);
    $meus = $stmt->fetch() ?: [];
    $stats['meus'] = (int)($meus['qtd'] ?? 0);
    $stats['meus_adm'] = (float)($meus['adm'] ?? 0);
    $stats['meus_repasse'] = (float)($meus['repasse'] ?? 0);

    $statusRows = $pdo->query('SELECT status, COUNT(*) qtd FROM historico_rescisoes GROUP BY status')->fetchAll();
    foreach ($statusRows as $row) {
        $st = $row['status'] ?: 'Rascunho';
        if (isset($statusCounts[$st])) $statusCounts[$st] = (int)$row['qtd'];
    }

    $stmt = $pdo->query('SELECT h.id, h.nome, h.endereco, h.total, h.criado_em, h.modo_nome, h.status, h.total_adm, h.total_repasse, u.nome AS usuario_nome
                         FROM historico_rescisoes h
                         LEFT JOIN usuarios u ON u.id = h.usuario_id
                         ORDER BY h.id DESC LIMIT 8');
    $recentes = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Calculadora dashboard.php: ' . $e->getMessage());
    $erro = 'Não foi possível carregar os indicadores agora. Verifique se a migração de gestão foi executada.';
}

$iniciais = strtoupper(mb_substr(trim($usuario['nome']), 0, 1));
$perfilLabel = $usuario['perfil'] === 'admin' ? 'Administrador' : 'Usuário';
$formatar = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$statusClass = static function(string $status): string {
    return match ($status) {
        'Conferido' => 'ok',
        'Pronto para cobrança' => 'ready',
        'Cobrado' => 'done',
        'Cancelado' => 'cancelled',
        'Em conferência' => 'review',
        default => 'draft',
    };
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Início — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/dashboard.css">
</head>
<body>
<?php require __DIR__ . "/includes/nav_global.php"; ?>
<main class="container">
<?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <section class="hero"><div class="hero-content"><span class="eyebrow"><span class="eyebrow-dot"></span> Painel de gestão</span><h1>Olá, <?= htmlspecialchars(explode(' ', trim($usuario['nome']))[0] ?: 'usuário', ENT_QUOTES, 'UTF-8') ?>.<br><span>Veja o que precisa de atenção.</span></h1><p>Acompanhe o fluxo das rescisões, os valores registrados e os últimos lançamentos em um único painel.</p><div class="hero-actions"><a class="btn btn-primary" href="index.php?nova=1">＋ Nova rescisão</a><a class="btn btn-secondary" href="historico.php">Abrir histórico completo →</a></div></div></section>

  <div class="section-label"><div><h2>Visão geral</h2><p>Indicadores consolidados do banco central.</p></div></div>
  <section class="stats">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Rescisões registradas</span><span class="stat-icon">▦</span></div><strong><?= number_format($stats['total_registros'],0,',','.') ?></strong><div class="stat-foot">Todos os usuários</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total das cobranças</span><span class="stat-icon">R$</span></div><strong><?= htmlspecialchars($formatar($stats['valor_total']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot">Soma dos registros</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total de ADM</span><span class="stat-icon">%</span></div><strong><?= htmlspecialchars($formatar($stats['total_adm']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot">Valores internos calculados</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total a repassar</span><span class="stat-icon">↗</span></div><strong><?= htmlspecialchars($formatar($stats['total_repasse']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot"><?= $stats['hoje'] ?> registro(s) criado(s) hoje</div></article>
  </section>

  <div class="section-label"><div><h2>Fluxo operacional</h2><p>Veja rapidamente onde estão as rescisões.</p></div></div>
  <section class="workflow-grid">
    <article class="panel"><div class="panel-head"><div><h3>Status das rescisões</h3><p>Distribuição atual por etapa.</p></div><a class="btn" href="historico.php">Gerenciar</a></div><div class="workflow-list">
      <?php foreach (STATUS_RESCISAO as $st): ?><div class="workflow-row"><div class="workflow-label"><span class="workflow-dot <?= $statusClass($st) ?>"></span><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></div><strong class="workflow-count"><?= number_format($statusCounts[$st] ?? 0,0,',','.') ?></strong></div><?php endforeach; ?>
    </div></article>
    <article class="panel"><div class="panel-head"><div><h3>Seu desempenho</h3><p>Atividade associada à sua conta.</p></div></div><div class="financial-box"><div class="financial-item"><span>Minhas rescisões</span><strong><?= number_format($stats['meus'],0,',','.') ?></strong></div><div class="financial-item"><span>Registradas hoje</span><strong><?= number_format($stats['hoje'],0,',','.') ?></strong></div><div class="financial-item"><span>Minha ADM</span><strong><?= htmlspecialchars($formatar($stats['meus_adm']), ENT_QUOTES, 'UTF-8') ?></strong></div><div class="financial-item"><span>Meu repasse</span><strong><?= htmlspecialchars($formatar($stats['meus_repasse']), ENT_QUOTES, 'UTF-8') ?></strong></div></div></article>
  </section>

  <div class="section-label"><div><h2>Pesquisar rapidamente</h2><p>Abra o histórico já com o termo preenchido.</p></div></div>
  <section class="panel"><div class="search-box"><input id="buscaRapida" type="search" placeholder="Inquilino, imóvel ou responsável..."><a id="btnBuscaRapida" class="btn btn-primary" href="historico.php">Pesquisar</a></div></section>

  <div class="section-label"><div><h2>Últimas rescisões</h2><p>Os registros mais recentes do banco central.</p></div><a href="historico.php">Abrir histórico completo →</a></div>
  <section class="panel"><div class="panel-head"><div><h3>Atividade recente</h3><p>Mostrando os 8 últimos lançamentos.</p></div><a class="btn" href="historico.php">Ver todos</a></div><div class="table-wrap" style="overflow:auto"><table class="recent-table"><thead><tr><th>Inquilino / imóvel</th><th>Data</th><th>Critério</th><th>Status</th><th>Responsável</th><th>Total</th><th></th></tr></thead><tbody>
    <?php if (!$recentes): ?><tr><td colspan="7"><div class="empty">Nenhuma rescisão foi registrada ainda.</div></td></tr><?php else: foreach ($recentes as $item): ?><tr><td><span class="record-name"><?= htmlspecialchars($item['nome'] ?: 'Inquilino não informado', ENT_QUOTES, 'UTF-8') ?></span><span class="record-sub"><?= htmlspecialchars($item['endereco'] ?: 'Imóvel não informado', ENT_QUOTES, 'UTF-8') ?></span></td><td class="record-meta"><?= htmlspecialchars(date('d/m/Y H:i',strtotime($item['criado_em'])), ENT_QUOTES, 'UTF-8') ?></td><td><span class="mode"><?= htmlspecialchars($item['modo_nome'] ?: 'Critério não informado', ENT_QUOTES, 'UTF-8') ?></span></td><td><span class="status <?= $statusClass($item['status'] ?? 'Rascunho') ?>"><?= htmlspecialchars($item['status'] ?? 'Rascunho', ENT_QUOTES, 'UTF-8') ?></span></td><td class="record-meta"><?= htmlspecialchars($item['usuario_nome'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td><td class="record-total"><?= htmlspecialchars($formatar((float)$item['total']), ENT_QUOTES, 'UTF-8') ?></td><td><a class="btn" href="detalhe.php?id=<?= (int)$item['id'] ?>">Detalhes</a></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
</main>
</body></html>
