<?php
declare(strict_types=1);

require __DIR__ . '/../api/config.php';
require __DIR__ . '/includes/storage.php';

$u = usuarioAtual();
if (!$u) {
    header('Location: ../login.php');
    exit;
}

$perfil = strtolower((string)($u['perfil'] ?? $u['role'] ?? $u['nivel'] ?? ''));
$admin = !empty($u['is_admin']) || !empty($u['admin']) || $perfil === 'admin' || strtolower((string)($u['login'] ?? '')) === 'admin';
if (!$admin) {
    http_response_code(403);
    exit('Acesso restrito ao administrador.');
}

function monitoramentoHtml(mixed $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function monitoramentoDataHora(string $valor): ?DateTimeImmutable {
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    foreach (['!d/m/Y H:i:s', '!d/m/Y H:i', '!Y-m-d H:i:s', '!Y-m-d\TH:i:s'] as $formato) {
        $data = DateTimeImmutable::createFromFormat($formato, $valor);
        $errosData = DateTimeImmutable::getLastErrors();

        if (
            $data !== false &&
            ($errosData === false || ($errosData['warning_count'] === 0 && $errosData['error_count'] === 0))
        ) {
            return $data;
        }
    }

    try {
        return new DateTimeImmutable($valor);
    } catch (Throwable) {
        return null;
    }
}

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

$q = trim((string)($_GET['q'] ?? ''));
$statusFiltro = strtoupper(trim((string)($_GET['status'] ?? '')));
$dataInicioInformada = trim((string)($_GET['data_inicio'] ?? ''));
$dataFimInformada = trim((string)($_GET['data_fim'] ?? ''));
$pagina = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 30;

$dataInicioNormalizada = $normalizarDataFiltro($dataInicioInformada);
$dataFimNormalizada = $normalizarDataFiltro($dataFimInformada);
$erroFiltro = '';

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

$arquivoLog = orcamentosStorage() . '/logs/processamentos.jsonl';
$rows = [];
$statusDisponiveis = ['OK' => 'Sucesso', 'ERRO' => 'Erro'];
$erro = '';

if (is_file($arquivoLog) && is_readable($arquivoLog)) {
    $handle = @fopen($arquivoLog, 'rb');

    if ($handle !== false) {
        while (($linha = fgets($handle)) !== false) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }

            $registro = json_decode($linha, true);
            if (!is_array($registro)) {
                continue;
            }

            $statusRegistro = strtoupper(trim((string)($registro['status'] ?? '')));
            if ($statusRegistro !== '' && !isset($statusDisponiveis[$statusRegistro])) {
                $statusDisponiveis[$statusRegistro] = ucfirst(strtolower($statusRegistro));
            }

            if (!$intervaloValido) {
                continue;
            }

            if ($statusFiltro !== '' && $statusRegistro !== $statusFiltro) {
                continue;
            }

            $dataRegistro = monitoramentoDataHora((string)($registro['created_at'] ?? ''));
            if ($dataInicio !== '' && (!$dataRegistro || $dataRegistro->format('Y-m-d') < $dataInicio)) {
                continue;
            }
            if ($dataFim !== '' && (!$dataRegistro || $dataRegistro->format('Y-m-d') > $dataFim)) {
                continue;
            }

            if ($q !== '') {
                $camposPesquisa = [
                    $registro['locatario'] ?? '',
                    $registro['usuario'] ?? '',
                    $registro['message'] ?? '',
                    $registro['error'] ?? '',
                    $registro['status'] ?? '',
                ];
                $textoPesquisa = implode(' ', array_map(static fn($valor): string => (string)$valor, $camposPesquisa));
                $encontrado = function_exists('mb_stripos')
                    ? mb_stripos($textoPesquisa, $q, 0, 'UTF-8') !== false
                    : stripos($textoPesquisa, $q) !== false;

                if (!$encontrado) {
                    continue;
                }
            }

            $rows[] = $registro;
        }

        fclose($handle);
    } else {
        $erro = 'Não foi possível abrir o arquivo de registros de processamento.';
    }
} elseif (is_file($arquivoLog) && !is_readable($arquivoLog)) {
    $erro = 'O arquivo de registros existe, mas não pode ser lido pelo servidor.';
}

$rows = array_reverse($rows);
$total = count($rows);
$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;
$itens = array_slice($rows, $offset, $porPagina);

$filtrosAtivos = (int)($q !== '') + (int)($statusFiltro !== '') + (int)($dataInicio !== '') + (int)($dataFim !== '');

$query = static function (array $extra = []) use ($q, $statusFiltro, $dataInicio, $dataFim): string {
    return http_build_query(array_filter(array_merge([
        'q' => $q,
        'status' => $statusFiltro,
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
    ], $extra), static fn($valor) => $valor !== '' && $valor !== null));
};

$menuPage = 'monitoramento';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Monitoramento — Folha de Cálculo</title>
    <link rel="stylesheet" href="../assets/css/orcamentos/monitoramento.css">
</head>
<body>
<?php require __DIR__ . '/includes/nav.php'; ?>

<main class="wrap monitor-page">
    <section class="monitor-hero">
        <div>
            <span class="monitor-eyebrow">Operação e diagnóstico</span>
            <h1>Monitoramento</h1>
            <p>Consulte o histórico de execução do gerador de orçamentos, identifique falhas e confira o tempo de processamento.</p>
        </div>
        <div class="monitor-summary" aria-label="Resumo dos resultados">
            <span class="monitor-summary-label">Registros encontrados</span>
            <strong><?= number_format($total, 0, ',', '.') ?></strong>
            <span class="monitor-summary-caption"><?= $filtrosAtivos > 0 ? $filtrosAtivos . ' filtro(s) ativo(s)' : 'Sem filtros ativos' ?></span>
        </div>
    </section>

    <?php if ($erro !== ''): ?><div class="monitor-notice monitor-notice-error" role="alert"><?= monitoramentoHtml($erro) ?></div><?php endif; ?>
    <?php if ($erroFiltro !== ''): ?><div class="monitor-notice monitor-notice-error" role="alert"><?= monitoramentoHtml($erroFiltro) ?></div><?php endif; ?>

    <section class="monitor-card monitor-filter-card">
        <div class="monitor-section-heading">
            <div>
                <h2>Filtros de pesquisa</h2>
                <p>Pesquise por locatário, usuário ou mensagem e refine por status e período.</p>
            </div>
            <span class="monitor-filter-note">Aplicação manual</span>
        </div>

        <form method="get" class="monitor-filters">
            <div class="monitor-field monitor-search-field">
                <label for="q">Pesquisa livre</label>
                <input id="q" name="q" type="search" value="<?= monitoramentoHtml($q) ?>" placeholder="Locatário, usuário ou detalhe do processamento">
            </div>

            <div class="monitor-field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Todos os status</option>
                    <?php foreach ($statusDisponiveis as $valorStatus => $rotuloStatus): ?>
                        <option value="<?= monitoramentoHtml($valorStatus) ?>" <?= $statusFiltro === $valorStatus ? 'selected' : '' ?>>
                            <?= monitoramentoHtml($rotuloStatus) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="monitor-field">
                <label for="data_inicio">Data inicial</label>
                <input id="data_inicio" name="data_inicio" type="date" value="<?= monitoramentoHtml($dataInicio) ?>" max="<?= monitoramentoHtml($dataFim) ?>">
            </div>

            <div class="monitor-field">
                <label for="data_fim">Data final</label>
                <input id="data_fim" name="data_fim" type="date" value="<?= monitoramentoHtml($dataFim) ?>" min="<?= monitoramentoHtml($dataInicio) ?>">
            </div>

            <div class="monitor-filter-actions">
                <a class="monitor-btn monitor-btn-secondary" href="monitoramento.php">Limpar filtros</a>
                <button class="monitor-btn monitor-btn-primary" type="submit">Aplicar filtros</button>
            </div>
        </form>
    </section>

    <section class="monitor-card monitor-results-card">
        <div class="monitor-section-heading">
            <div>
                <h2>Histórico de processamentos</h2>
                <p><?= number_format($total, 0, ',', '.') ?> evento(s) encontrado(s)<?= $total > 0 ? ' · página ' . $pagina . ' de ' . $totalPaginas : '' ?>.</p>
            </div>
        </div>

        <div class="monitor-table-wrap">
            <table class="monitor-table">
                <thead>
                    <tr>
                        <th scope="col">Data e hora</th>
                        <th scope="col">Locatário / usuário</th>
                        <th scope="col">Status</th>
                        <th scope="col">Duração</th>
                        <th scope="col">Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$itens): ?>
                    <tr><td colspan="5"><div class="monitor-empty">Nenhum processamento corresponde aos filtros informados.</div></td></tr>
                <?php else: foreach ($itens as $item): ?>
                    <?php
                    $dataHora = monitoramentoDataHora((string)($item['created_at'] ?? ''));
                    $statusItem = strtoupper(trim((string)($item['status'] ?? '')));
                    $classeStatus = $statusItem === 'OK' ? 'monitor-status-success' : ($statusItem === 'ERRO' ? 'monitor-status-error' : 'monitor-status-neutral');
                    $rotuloStatus = $statusDisponiveis[$statusItem] ?? ($statusItem !== '' ? ucfirst(strtolower($statusItem)) : 'Não informado');
                    $duracao = isset($item['duration_ms']) && is_numeric($item['duration_ms'])
                        ? number_format(((float)$item['duration_ms']) / 1000, 2, ',', '.') . ' s'
                        : '—';
                    $detalhes = (string)($item['message'] ?? $item['error'] ?? '');
                    ?>
                    <tr>
                        <td class="monitor-date"><?= monitoramentoHtml($dataHora ? $dataHora->format('d/m/Y H:i:s') : ($item['created_at'] ?? '—')) ?></td>
                        <td>
                            <strong class="monitor-tenant"><?= monitoramentoHtml($item['locatario'] ?? 'Não informado') ?></strong>
                            <small class="monitor-user">Usuário: <?= monitoramentoHtml($item['usuario'] ?? '—') ?></small>
                        </td>
                        <td><span class="monitor-status <?= monitoramentoHtml($classeStatus) ?>"><?= monitoramentoHtml($rotuloStatus) ?></span></td>
                        <td class="monitor-duration"><?= monitoramentoHtml($duracao) ?></td>
                        <td class="monitor-details"><?= monitoramentoHtml($detalhes !== '' ? $detalhes : '—') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <nav class="monitor-pagination" aria-label="Paginação do monitoramento">
            <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <div>
                <?php if ($pagina > 1): ?><a class="monitor-btn monitor-btn-secondary" href="?<?= monitoramentoHtml($query(['page' => $pagina - 1])) ?>">← Anterior</a><?php endif; ?>
                <?php if ($pagina < $totalPaginas): ?><a class="monitor-btn monitor-btn-primary" href="?<?= monitoramentoHtml($query(['page' => $pagina + 1])) ?>">Próxima →</a><?php endif; ?>
            </div>
        </nav>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
