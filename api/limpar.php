<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirAdminApi();
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
try {
    db()->beginTransaction();
    $pdo = db();
    $stmt = $pdo->query('SELECT id FROM historico_rescisoes');
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) registrarAuditoria((int)$id, (int)$usuario['id'], 'Exclusão em massa', 'Histórico limpo pelo administrador');
    $pdo->exec('DELETE FROM historico_rescisoes');
    $pdo->commit();
    responder(['ok' => true]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Calculadora limpar.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível limpar o histórico.'], 500);
}
