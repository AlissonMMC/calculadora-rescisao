<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario = exigirPermissaoPagina('dashboard.view');
$navPage = 'dashboard';
$navBase = "";
$csrf = csrfToken();

$periodo = (string)($_GET['periodo'] ?? '30d');
$statusFiltro = trim((string)($_GET['status'] ?? ''));
$dataInicial = trim((string)($_GET['de'] ?? ''));
$dataFinal = trim((string)($_GET['ate'] ?? ''));

$validDate = static function(string $v): ?string {
    if ($v === '') return null;
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
};

$dataInicial = $validDate($dataInicial) ?? '';
$dataFinal = $validDate($dataFinal) ?? '';
if ($statusFiltro !== '' && !statusValido($statusFiltro)) $statusFiltro = '';

$periodos = [
    'hoje' => 'Hoje',
    '7d' => 'Últimos 7 dias',
    '30d' => 'Últimos 30 dias',
    '90d' => 'Últimos 90 dias',
    'mes' => 'Este mês',
    'personalizado' => 'Período personalizado',
];

$where = [];
$params = [];

switch ($periodo) {
    case 'hoje':
        $where[] = 'h.criado_em >= CURDATE()';
        break;
    case '7d':
        $where[] = 'h.criado_em >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
        break;
    case '90d':
        $where[] = 'h.criado_em >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
        break;
    case 'mes':
        $where[] = 'h.criado_em >= DATE_FORMAT(CURDATE(), "%Y-%m-01")';
        break;
    case 'personalizado':
        if ($dataInicial !== '') {
            $where[] = 'h.criado_em >= ?';
            $params[] = $dataInicial . ' 00:00:00';
        }
        if ($dataFinal !== '') {
            $where[] = 'h.criado_em < ?';
            $params[] = (new DateTime($dataFinal))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        }
        break;
    default:
        $periodo = '30d';
        $where[] = 'h.criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        break;
}

if ($statusFiltro !== '') {
    $where[] = 'h.status = ?';
    $params[] = $statusFiltro;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stats = [
    'total_registros' => 0,
    'valor_total' => 0.0,
    'total_adm' => 0.0,
    'total_repasse' => 0.0,
    'hoje' => 0,
    'meus' => 0,
    'meus_adm' => 0.0,
    'meus_repasse' => 0.0,
    'em_aberto' => 0,
    'ticket_medio' => 0.0,
];

$statusCounts = array_fill_keys(STATUS_RESCISAO, 0);
$recentes = [];
$meses = [];
$erro = '';

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS qtd,
                COALESCE(SUM(h.total),0) AS total,
                COALESCE(SUM(h.total_adm),0) AS adm,
                COALESCE(SUM(h.total_repasse),0) AS repasse
         FROM historico_rescisoes h
         $whereSql"
    );
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];

    $stats['total_registros'] = (int)($row['qtd'] ?? 0);
    $stats['valor_total'] = (float)($row['total'] ?? 0);
    $stats['total_adm'] = (float)($row['adm'] ?? 0);
    $stats['total_repasse'] = (float)($row['repasse'] ?? 0);
    $stats['ticket_medio'] = $stats['total_registros'] > 0
        ? $stats['valor_total'] / $stats['total_registros']
        : 0.0;

    $whereAberto = $where;
    $paramsAberto = $params;
    $whereAberto[] = "h.status IN ('Rascunho','Em conferência','Pronto para cobrança')";
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM historico_rescisoes h WHERE " . implode(' AND ', $whereAberto)
    );
    $stmt->execute($paramsAberto);
    $stats['em_aberto'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT h.status, COUNT(*) qtd
         FROM historico_rescisoes h
         $whereSql
         GROUP BY h.status"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $rowStatus) {
        $st = $rowStatus['status'] ?: 'Rascunho';
        if (isset($statusCounts[$st])) $statusCounts[$st] = (int)$rowStatus['qtd'];
    }

    $stats['hoje'] = (int)$pdo->query(
        'SELECT COUNT(*) FROM historico_rescisoes WHERE criado_em >= CURDATE()'
    )->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) qtd, COALESCE(SUM(total_adm),0) adm, COALESCE(SUM(total_repasse),0) repasse
         FROM historico_rescisoes
         WHERE usuario_id = ?'
    );
    $stmt->execute([(int)$usuario['id']]);
    $meus = $stmt->fetch() ?: [];
    $stats['meus'] = (int)($meus['qtd'] ?? 0);
    $stats['meus_adm'] = (float)($meus['adm'] ?? 0);
    $stats['meus_repasse'] = (float)($meus['repasse'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT h.id, h.nome, h.endereco, h.total, h.criado_em, h.modo_nome, h.status,
                h.total_adm, h.total_repasse, u.nome AS usuario_nome
         FROM historico_rescisoes h
         LEFT JOIN usuarios u ON u.id = h.usuario_id
         $whereSql
         ORDER BY h.id DESC
         LIMIT 8"
    );
    $stmt->execute($params);
    $recentes = $stmt->fetchAll();

    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(criado_em, '%Y-%m') referencia,
                COUNT(*) qtd,
                COALESCE(SUM(total),0) total
         FROM historico_rescisoes
         WHERE criado_em >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
         GROUP BY DATE_FORMAT(criado_em, '%Y-%m')
         ORDER BY referencia ASC"
    );

    foreach ($stmt->fetchAll() as $rowMes) {
        $meses[(string)$rowMes['referencia']] = [
            'qtd' => (int)$rowMes['qtd'],
            'total' => (float)$rowMes['total'],
        ];
    }
} catch (Throwable $e) {
    error_log('Calculadora dashboard.php: ' . $e->getMessage());
    $erro = 'Não foi possível carregar os indicadores agora. Verifique a estrutura do banco.';
}

$inicioMes = new DateTimeImmutable('first day of this month');
$mesesGrafico = [];
$maxGrafico = 1.0;

for ($i = 5; $i >= 0; $i--) {
    $refMes = $inicioMes->modify("-{$i} month")->format('Y-m');
    $rotulo = $inicioMes->modify("-{$i} month")->format('M/y');

    $mesesGrafico[] = [
        'referencia' => $refMes,
        'rotulo' => ucfirst($rotulo),
        'qtd' => $meses[$refMes]['qtd'] ?? 0,
        'total' => $meses[$refMes]['total'] ?? 0.0,
    ];

    $maxGrafico = max($maxGrafico, (float)($meses[$refMes]['total'] ?? 0.0));
}

$iniciais = strtoupper(mb_substr(trim($usuario['nome']), 0, 1));
$perfilLabel = match (perfilNormalizado($usuario)) {
    'admin' => 'Administrador',
    'financeiro' => 'Financeiro',
    'consulta' => 'Somente consulta',
    default => 'Operacional',
};
$podeCriar = temPermissao($usuario, 'rescisao.create');
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
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Início — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/dashboard.css?v=20261005-dashboard">
</head>
<body>
<?php require __DIR__ . "/includes/nav_global.php"; ?>
<main class="container">
<?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <section class="hero"><div class="hero-content"><span class="eyebrow"><span class="eyebrow-dot"></span> Painel de gestão</span><h1>Olá, <?= htmlspecialchars(explode(' ', trim($usuario['nome']))[0] ?: 'usuário', ENT_QUOTES, 'UTF-8') ?>.<br><span>Veja o que precisa de atenção.</span></h1><p>Acompanhe o fluxo das rescisões, os valores registrados e os últimos lançamentos em um único painel.</p><div class="hero-actions"><?php if($podeCriar): ?><a class="btn btn-primary" href="index.php?nova=1">＋ Nova rescisão</a><?php endif; ?><a class="btn btn-secondary" href="historico.php">Abrir histórico completo →</a></div></div></section>

  <section class="dashboard-filters">
    <div class="dashboard-filters-head">
      <div>
        <span class="filter-kicker">Visão gerencial</span>
        <h2>Período e situação</h2>
        <p>Os indicadores abaixo respeitam os filtros selecionados.</p>
      </div>
      <span class="filter-context"><?= htmlspecialchars($periodos[$periodo] ?? 'Últimos 30 dias', ENT_QUOTES, 'UTF-8') ?><?= $statusFiltro ? ' · ' . htmlspecialchars($statusFiltro, ENT_QUOTES, 'UTF-8') : '' ?></span>
    </div>
    <form method="get" class="dashboard-filters-form">
      <div class="field"><label for="periodo">Período</label><select id="periodo" name="periodo"><?php foreach($periodos as $key=>$label): ?><option value="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>" <?= $periodo===$key?'selected':'' ?>><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="de">De</label><input id="de" name="de" type="date" value="<?= htmlspecialchars($dataInicial,ENT_QUOTES,'UTF-8') ?>"></div>
      <div class="field"><label for="ate">Até</label><input id="ate" name="ate" type="date" value="<?= htmlspecialchars($dataFinal,ENT_QUOTES,'UTF-8') ?>"></div>
      <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Todos os status</option><?php foreach(STATUS_RESCISAO as $st): ?><option value="<?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?>" <?= $statusFiltro===$st?'selected':'' ?>><?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
      <div class="filter-form-actions"><a class="btn" href="dashboard.php">Limpar</a><button class="btn btn-primary" type="submit">Aplicar filtros</button></div>
    </form>
  </section>

  <div class="section-label"><div><h2>Visão geral</h2><p>Indicadores consolidados do banco central.</p></div></div>
  <section class="stats">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Rescisões no período</span><span class="stat-icon">▦</span></div><strong><?= number_format($stats['total_registros'],0,',','.') ?></strong><div class="stat-foot">Registros filtrados</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total das cobranças</span><span class="stat-icon">R$</span></div><strong><?= htmlspecialchars($formatar($stats['valor_total']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot">Soma no período</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Em aberto</span><span class="stat-icon">!</span></div><strong><?= number_format($stats['em_aberto'],0,',','.') ?></strong><div class="stat-foot">Pendências operacionais</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Ticket médio</span><span class="stat-icon">↗</span></div><strong><?= htmlspecialchars($formatar($stats['ticket_medio']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot">Valor médio por rescisão</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total de ADM</span><span class="stat-icon">%</span></div><strong><?= htmlspecialchars($formatar($stats['total_adm']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot">Valores internos</div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total a repassar</span><span class="stat-icon">↗</span></div><strong><?= htmlspecialchars($formatar($stats['total_repasse']), ENT_QUOTES, 'UTF-8') ?></strong><div class="stat-foot"><?= $stats['hoje'] ?> criada(s) hoje</div></article>
  </section>

  <div class="section-label"><div><h2>Evolução</h2><p>Volume financeiro dos últimos 6 meses.</p></div></div>
  <section class="panel trend-panel">
    <div class="panel-head"><div><h3>Volume mensal</h3><p>Total das cobranças e quantidade de rescisões.</p></div></div>
    <div class="trend-chart">
      <?php foreach($mesesGrafico as $mes): $pct = $maxGrafico > 0 ? max(4, round(($mes['total'] / $maxGrafico) * 100)) : 4; ?>
      <div class="trend-col">
        <div class="trend-value"><?= htmlspecialchars($formatar((float)$mes['total']),ENT_QUOTES,'UTF-8') ?></div>
        <div class="trend-bar-track"><div class="trend-bar" style="height:<?= $pct ?>%"></div></div>
        <strong><?= (int)$mes['qtd'] ?></strong>
        <span><?= htmlspecialchars($mes['rotulo'],ENT_QUOTES,'UTF-8') ?></span>
      </div>
      <?php endforeach; ?>
    </div>
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
