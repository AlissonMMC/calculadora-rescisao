<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirPermissaoApi('rescisao.edit');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
$entrada = entradaJson();
$id = (int)($entrada['id'] ?? 0);
$status = limparTexto($entrada['status'] ?? '', 30);
if ($id < 1 || !statusValido($status)) responder(['ok' => false, 'error' => 'Status inválido.'], 400);
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, usuario_id, status FROM historico_rescisoes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) responder(['ok' => false, 'error' => 'Rescisão não encontrada.'], 404);
    if ((int)$row['usuario_id'] !== (int)$usuario['id'] && perfilNormalizado($usuario) !== 'admin') responder(['ok' => false, 'error' => 'Você não pode alterar o status desta rescisão.'], 403);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE historico_rescisoes SET status = ?, atualizado_em = CURRENT_TIMESTAMP, ultimo_editor_id = ? WHERE id = ?');
    $stmt->execute([$status, (int)$usuario['id'], $id]);
    if ($status === 'Conferido') {
        $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ? WHERE id = ?')->execute([(int)$usuario['id'], $id]);
    }
    if ($status !== 'Conferido') {
        $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = NULL, conferido_por = NULL WHERE id = ?')->execute([$id]);
    }
    if ($status === 'Cobrado') {
        $pdo->prepare('UPDATE historico_rescisoes SET cobrado_em = CURRENT_TIMESTAMP, cobrado_por = ? WHERE id = ?')->execute([(int)$usuario['id'], $id]);
    }
    if ($status !== 'Cobrado') {
        $pdo->prepare('UPDATE historico_rescisoes SET cobrado_em = NULL, cobrado_por = NULL WHERE id = ?')->execute([$id]);
    }
    registrarAuditoria($id, (int)$usuario['id'], 'Alteração de status', 'Status: ' . ($row['status'] ?: 'Rascunho') . ' → ' . $status);
    $pdo->commit();
    responder(['ok' => true, 'status' => $status]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Calculadora status.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível alterar o status.'], 500);
}
