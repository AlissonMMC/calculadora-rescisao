<?php
declare(strict_types=1);

require __DIR__ . '/api/config.php';

$usuario = exigirAdminPagina();
$navPage = 'auditoria';
$navBase = '';

$q = trim((string)($_GET['q'] ?? ''));
$modulo = trim((string)($_GET['modulo'] ?? ''));
$acaoFiltro = trim((string)($_GET['acao'] ?? ''));
$usuarioFiltro = trim((string)($_GET['usuario'] ?? ''));
$dataInicioInformada = trim((string)($_GET['data_inicio'] ?? ''));
$dataFimInformada = trim((string)($_GET['data_fim'] ?? ''));
$pagina = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 30;

$itens = [];
$total = 0;
$totalPaginas = 1;
$modulos = [];
$acoes = [];
$usuariosFiltro = [];
$erro = '';
$erroFiltro = '';

$normalizarDataFiltro = static function (string $valor): ?string {
    if ($valor === '') {
        return '';
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    $errosData = DateTimeImmutable::getLastErrors();

    if (
        $data === false ||
        ($errosData !== false && ($errosData['warning_count'] > 0 || $errosData['error_count'] > 0)) ||
        $data->format('Y-m-d') !== $valor
    ) {
        return null;
    }

    return $valor;
};

$dataInicioNormalizada = $normalizarDataFiltro($dataInicioInformada);
$dataFimNormalizada = $normalizarDataFiltro($dataFimInformada);

if ($dataInicioNormalizada === null || $dataFimNormalizada === null) {
    $erroFiltro = 'Informe um intervalo de datas válido.';
}
$dataInicio = is_string($dataInicioNormalizada) ? $dataInicioNormalizada : '';
$dataFim = is_string($dataFimNormalizada) ? $dataFimNormalizada : '';

$intervaloValido = true;
if ($dataInicio !== '' && $dataFim !== '' && $dataInicio > $dataFim) {
    $erroFiltro = 'A data inicial não pode ser posterior à data final.';
    $intervaloValido = false;
}

try {
    $pdo = db();
    $where = [];
    $params = [];

    if (!$intervaloValido) {
        $where[] = '1 = 0';
    }

    if ($q !== '') {
        $where[] = '(COALESCE(u.nome, "") LIKE ? OR COALESCE(u.login, "") LIKE ? OR a.acao LIKE ? OR COALESCE(a.detalhes, "") LIKE ? OR a.modulo LIKE ? OR COALESCE(a.ip, "") LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    if ($modulo !== '') {
        $where[] = 'a.modulo = ?';
        $params[] = $modulo;
    }

    if ($acaoFiltro !== '') {
        $where[] = 'a.acao = ?';
        $params[] = $acaoFiltro;
    }

    if ($usuarioFiltro === 'sistema') {
        $where[] = '(a.usuario_id IS NULL OR a.usuario_id = 0)';
    } elseif ($usuarioFiltro !== '' && ctype_digit($usuarioFiltro)) {
        $where[] = 'a.usuario_id = ?';
        $params[] = (int)$usuarioFiltro;
    }

    if ($dataInicio !== '') {
        $where[] = 'a.criado_em >= ?';
        $params[] = $dataInicio . ' 00:00:00';
    }

    if ($dataFim !== '') {
        $where[] = 'a.criado_em <= ?';
        $params[] = $dataFim . ' 23:59:59';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auditoria_sistema a LEFT JOIN usuarios u ON u.id = a.usuario_id $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;

    $stmt = $pdo->prepare(
        "SELECT a.*, u.nome AS usuario_nome, u.login AS usuario_login
         FROM auditoria_sistema a
         LEFT JOIN usuarios u ON u.id = a.usuario_id
         $whereSql
         ORDER BY a.criado_em DESC, a.id DESC
         LIMIT $porPagina OFFSET $offset"
    );
    $stmt->execute($params);
    $itens = $stmt->fetchAll();

    $modulos = $pdo->query("SELECT DISTINCT modulo FROM auditoria_sistema WHERE modulo <> '' ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN);
    $acoes = $pdo->query("SELECT DISTINCT acao FROM auditoria_sistema WHERE acao <> '' ORDER BY acao")->fetchAll(PDO::FETCH_COLUMN);
    $usuariosFiltro = $pdo->query("SELECT id, nome, login FROM usuarios ORDER BY nome, login")->fetchAll();
} catch (Throwable $e) {
    error_log('Calculadora auditoria.php: ' . $e->getMessage());
    $erro = 'Não foi possível carregar os registros de auditoria.';
    $totalPaginas = 1;
}

$query = static function (array $extra = []) use ($q, $modulo, $acaoFiltro, $usuarioFiltro, $dataInicio, $dataFim): string {
    return http_build_query(array_filter(array_merge([
        'q' => $q,
        'modulo' => $modulo,
        'acao' => $acaoFiltro,
        'usuario' => $usuarioFiltro,
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
    ], $extra), static fn($valor) => $valor !== '' && $valor !== null));
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
    <section class="hero audit-hero">
        <span class="eyebrow">Segurança e conformidade</span>
        <h1>Auditoria do sistema</h1>
        <p>Consulte ações por responsável, módulo, tipo de evento e período, com registro de data, origem e detalhes.</p>
    </section>

    <?php if ($erro !== ''): ?><div class="error audit-notice"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($erroFiltro !== ''): ?><div class="error audit-notice" role="alert"><?= htmlspecialchars($erroFiltro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="panel audit-filter">
        <div class="filter-heading">
            <div>
                <h2>Filtros de pesquisa</h2>
                <p>Combine os campos para localizar eventos específicos.</p>
            </div>
            <span class="filter-hint">Os filtros são aplicados ao clicar no botão.</span>
        </div>

        <form method="get" class="audit-form">
            <div class="field audit-search-field">
                <label for="q">Pesquisa livre</label>
                <input id="q" name="q" type="search" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Usuário, ação, detalhe ou IP">
            </div>

            <div class="field">
                <label for="usuario">Responsável</label>
                <select id="usuario" name="usuario">
                    <option value="">Todos os usuários</option>
                    <option value="sistema" <?= $usuarioFiltro === 'sistema' ? 'selected' : '' ?>>Sistema / sem usuário</option>
                    <?php foreach ($usuariosFiltro as $itemUsuario): ?>
                        <option value="<?= (int)$itemUsuario['id'] ?>" <?= $usuarioFiltro === (string)$itemUsuario['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$itemUsuario['nome'], ENT_QUOTES, 'UTF-8') ?><?= !empty($itemUsuario['login']) ? ' · ' . htmlspecialchars((string)$itemUsuario['login'], ENT_QUOTES, 'UTF-8') : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="modulo">Módulo</label>
                <select id="modulo" name="modulo">
                    <option value="">Todos os módulos</option>
                    <?php foreach ($modulos as $m): ?>
                        <option value="<?= htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') ?>" <?= $modulo === (string)$m ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="acao">Tipo de ação</label>
                <select id="acao" name="acao">
                    <option value="">Todas as ações</option>
                    <?php foreach ($acoes as $acao): ?>
                        <option value="<?= htmlspecialchars((string)$acao, ENT_QUOTES, 'UTF-8') ?>" <?= $acaoFiltro === (string)$acao ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$acao, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="data_inicio">Data inicial</label>
                <input id="data_inicio" name="data_inicio" type="date" value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="field">
                <label for="data_fim">Data final</label>
                <input id="data_fim" name="data_fim" type="date" value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>" min="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="audit-actions">
                <a class="btn audit-btn-secondary" href="auditoria.php">Limpar filtros</a>
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            </div>
        </form>
    </section>

    <section class="panel audit-results">
        <div class="panel-head">
            <div>
                <h2>Eventos registrados</h2>
                <p><?= number_format($total, 0, ',', '.') ?> registro(s) encontrado(s)<?= $total > 0 ? ' · página ' . $pagina . ' de ' . $totalPaginas : '' ?>.</p>
            </div>
        </div>

        <div class="table-wrap audit-table-wrap">
            <table class="audit-table">
                <thead>
                    <tr><th>Data/hora</th><th>Responsável</th><th>Módulo</th><th>Ação</th><th>Registro</th><th>Detalhes</th><th>IP</th></tr>
                </thead>
                <tbody>
                <?php if (!$itens): ?>
                    <tr><td colspan="7"><div class="empty">Nenhum evento corresponde aos filtros informados.</div></td></tr>
                <?php else: foreach ($itens as $item): ?>
                    <tr>
                        <td class="audit-date"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime((string)$item['criado_em'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><strong><?= htmlspecialchars((string)($item['usuario_nome'] ?: 'Sistema'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)($item['usuario_login'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><span class="tag"><?= htmlspecialchars((string)$item['modulo'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars((string)$item['acao'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= !empty($item['registro_id']) ? '#' . (int)$item['registro_id'] : '—' ?></td>
                        <td class="details"><?= htmlspecialchars((string)($item['detalhes'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)($item['ip'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="pagination">
            <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <div>
                <?php if ($pagina > 1): ?><a class="btn audit-btn-secondary" href="?<?= htmlspecialchars($query(['page' => $pagina - 1]), ENT_QUOTES, 'UTF-8') ?>">← Anterior</a><?php endif; ?>
                <?php if ($pagina < $totalPaginas): ?><a class="btn btn-primary" href="?<?= htmlspecialchars($query(['page' => $pagina + 1]), ENT_QUOTES, 'UTF-8') ?>">Próxima →</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
