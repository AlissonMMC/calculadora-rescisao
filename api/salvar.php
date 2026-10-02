<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirLoginApi();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
$entrada = entradaJson();
$id = (int)($entrada['id'] ?? 0);
$nome = limparTexto($entrada['nome'] ?? '', 150);
$endereco = limparTexto($entrada['endereco'] ?? '', 255);
$total = is_numeric($entrada['total'] ?? null) ? max(0, (float)$entrada['total']) : 0;
$totalAdm = is_numeric($entrada['totalAdm'] ?? null) ? max(0, (float)$entrada['totalAdm']) : 0;
$totalRepasse = is_numeric($entrada['totalRepasse'] ?? null) ? max(0, (float)$entrada['totalRepasse']) : 0;
$modoNome = limparTexto($entrada['modoNome'] ?? '', 50);
$status = limparTexto($entrada['status'] ?? 'Rascunho', 30);
if (!statusValido($status)) $status = 'Rascunho';
$dados = is_array($entrada['dados'] ?? null) ? $entrada['dados'] : [];
try {
    $jsonDados = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo = db();
    $pdo->beginTransaction();

    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT id, usuario_id, status, nome FROM historico_rescisoes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existente = $stmt->fetch();
        if (!$existente) { $pdo->rollBack(); responder(['ok' => false, 'error' => 'Rescisão não encontrada.'], 404); }
        $permitido = ((int)$existente['usuario_id'] === (int)$usuario['id']) || $usuario['perfil'] === 'admin';
        if (!$permitido) { $pdo->rollBack(); responder(['ok' => false, 'error' => 'Você não pode alterar esta rescisão.'], 403); }

        $stmt = $pdo->prepare('UPDATE historico_rescisoes SET nome = ?, endereco = ?, total = ?, total_adm = ?, total_repasse = ?, modo_nome = ?, status = ?, atualizado_em = CURRENT_TIMESTAMP, ultimo_editor_id = ? WHERE id = ?');
        $stmt->execute([$nome, $endereco, $total, $totalAdm, $totalRepasse, $modoNome, $status, (int)$usuario['id'], $id]);
        $acao = 'Edição';
        $detalhes = 'Registro atualizado';
        if ((string)$existente['status'] !== $status) {
            $acao = 'Alteração de status';
            $detalhes = 'Status: ' . ($existente['status'] ?: 'Rascunho') . ' → ' . $status;
        }
        if ($status === 'Conferido') {
            $stmt = $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ?, cobrado_em = NULL, cobrado_por = NULL WHERE id = ?');
            $stmt->execute([(int)$usuario['id'], $id]);
        } elseif ($status === 'Cobrado') {
            $stmt = $pdo->prepare('UPDATE historico_rescisoes SET cobrado_em = CURRENT_TIMESTAMP, cobrado_por = ?, conferido_em = COALESCE(conferido_em, CURRENT_TIMESTAMP), conferido_por = COALESCE(conferido_por, ?) WHERE id = ?');
            $stmt->execute([(int)$usuario['id'], (int)$usuario['id'], $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = NULL, conferido_por = NULL, cobrado_em = NULL, cobrado_por = NULL WHERE id = ?');
            $stmt->execute([$id]);
        }
        registrarAuditoria($id, (int)$usuario['id'], $acao, $detalhes);
    } else {
        $stmt = $pdo->prepare('INSERT INTO historico_rescisoes (usuario_id, ultimo_editor_id, nome, endereco, total, total_adm, total_repasse, modo_nome, status, dados_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int)$usuario['id'], (int)$usuario['id'], $nome, $endereco, $total, $totalAdm, $totalRepasse, $modoNome, $status, $jsonDados]);
        $id = (int)$pdo->lastInsertId();
        if ($status === 'Conferido') {
            $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ? WHERE id = ?')->execute([(int)$usuario['id'], $id]);
        } elseif ($status === 'Cobrado') {
            $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ?, cobrado_em = CURRENT_TIMESTAMP, cobrado_por = ? WHERE id = ?')->execute([(int)$usuario['id'], (int)$usuario['id'], $id]);
        }
        registrarAuditoria($id, (int)$usuario['id'], 'Criação', 'Rescisão criada com status: ' . $status);
    }

    // Sempre grava a versão mais recente do JSON após a decisão de criar/editar.
    $stmt = $pdo->prepare('UPDATE historico_rescisoes SET dados_json = ? WHERE id = ?');
    $stmt->execute([$jsonDados, $id]);

    $stmt = $pdo->prepare('SELECT h.id, h.nome, h.endereco, h.total, h.total_adm, h.total_repasse, h.criado_em, h.atualizado_em, h.modo_nome, h.status, h.dados_json, u.nome AS usuario_nome, u.login AS usuario_login FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    $pdo->commit();
    responder(['ok' => true, 'item' => normalizarItemBanco($item)]);
} catch (JsonException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    responder(['ok' => false, 'error' => 'Os dados da rescisão não puderam ser serializados.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Calculadora salvar.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível salvar no banco.'], 500);
}
