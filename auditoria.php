<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';

$usuario = exigirAdminPagina();
$navPage = 'auditoria';
$navBase = '';

$q = trim((string)($_GET['q'] ?? ''));
$modulo = trim((string)($_GET['modulo'] ?? ''));
$pagina = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 30;
$itens = [];
$total = 0;
$erro = '';

try {
    $pdo = db();
    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = '(COALESCE(u.nome, "") LIKE ? OR COALESCE(u.login, "") LIKE ? OR a.acao LIKE ? OR a.detalhes LIKE ? OR a.modulo LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($modulo !== '') {
        $where[] = 'a.modulo = ?';
        $params[] = $modulo;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auditoria_sistema a LEFT JOIN usuarios u ON u.id=a.usuario_id $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare("SELECT a.*, u.nome AS usuario_nome, u.login AS usuario_login
                           FROM auditoria_sistema a
                           LEFT JOIN usuarios u ON u.id=a.usuario_id
                           $whereSql
                           ORDER BY a.id DESC
                           LIMIT $porPagina OFFSET $offset");
    $stmt->execute($params);
    $itens = $stmt->fetchAll();

    $modulos = $pdo->query("SELECT DISTINCT modulo FROM auditoria_sistema ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    error_log('Calculadora auditoria.php: ' . $e->getMessage());
    $erro = 'Não foi possível carregar a auditoria.';
    $totalPaginas = 1;
    $modulos = [];
}

$query = static function (array $extra = []) use ($q, $modulo): string {
    return http_build_query(array_filter(array_merge([
        'q' => $q,
        'modulo' => $modulo
    ], $extra), static fn($v) => $v !== '' && $v !== null));
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Auditoria — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/auditoria.css">
</head>
<body>
<?php require __DIR__ . '/includes/nav_global.php'; ?>
<main class="container audit-page">
    <section class="hero">
        <span class="eyebrow">Segurança e conformidade</span>
        <h1>Auditoria do sistema.</h1>
        <p>Registro central das ações realizadas pelos usuários, com data, responsável, módulo e origem.</p>
    </section>

    <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="panel audit-filter">
        <form method="get" class="audit-form">
            <div class="field">
                <label for="q">Pesquisar</label>
                <input id="q" name="q" type="search" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Usuário, ação ou detalhe...">
            </div>
            <div class="field">
                <label for="modulo">Módulo</label>
                <select id="modulo" name="modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $m): ?>
                        <option value="<?= htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') ?>" <?= $modulo === $m ? 'selected' : '' ?>><?= htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="audit-actions">
                <a class="btn" href="auditoria.php">Limpar</a>
                <button class="btn btn-primary" type="submit">Pesquisar</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2>Atividade registrada</h2>
                <p><?= number_format($total, 0, ',', '.') ?> evento(s) encontrado(s).</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="audit-table">
                <thead>
                    <tr><th>Data/hora</th><th>Usuário</th><th>Módulo</th><th>Ação</th><th>Registro</th><th>Detalhes</th><th>IP</th></tr>
                </thead>
                <tbody>
                <?php if (!$itens): ?>
                    <tr><td colspan="7"><div class="empty">Nenhum evento encontrado.</div></td></tr>
                <?php else: foreach ($itens as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($item['criado_em'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><strong><?= htmlspecialchars($item['usuario_nome'] ?: 'Sistema', ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($item['usuario_login'] ?: '', ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><span class="tag"><?= htmlspecialchars($item['modulo'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars($item['acao'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $item['registro_id'] ? '#' . (int)$item['registro_id'] : '—' ?></td>
                        <td class="details"><?= htmlspecialchars($item['detalhes'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($item['ip'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="pagination">
            <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <div>
                <?php if ($pagina > 1): ?><a class="btn" href="?<?= htmlspecialchars($query(['page' => $pagina - 1]), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a><?php endif; ?>
                <?php if ($pagina < $totalPaginas): ?><a class="btn btn-primary" href="?<?= htmlspecialchars($query(['page' => $pagina + 1]), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
