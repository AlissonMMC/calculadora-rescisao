<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
exigirPermissaoApi('historico.delete');
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) responder(['ok' => false, 'error' => 'ID inválido.'], 400);
try {
    $stmt = db()->prepare('DELETE FROM historico_rescisoes WHERE id = ?');
    $stmt->execute([$id]);
    responder(['ok' => true, 'deleted' => $stmt->rowCount()]);
} catch (Throwable $e) {
    error_log('Calculadora excluir.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível excluir o registro.'], 500);
}
