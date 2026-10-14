<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirAdminApi();
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) responder(['ok' => false, 'error' => 'ID inválido.'], 400);
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT nome, endereco, total, status FROM historico_rescisoes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $registro = $stmt->fetch();
    if (!$registro) responder(['ok' => true, 'deleted' => 0]);

    $stmt = $pdo->prepare('DELETE FROM historico_rescisoes WHERE id = ?');
    $stmt->execute([$id]);
    $excluidos = $stmt->rowCount();
    if ($excluidos > 0) {
        registrarAuditoriaSistema(
            'rescisoes',
            'exclusao',
            (int)$id,
            'Rescisão excluída; locatário: ' . (string)$registro['nome']
                . '; endereço: ' . (string)$registro['endereco']
                . '; total: R$ ' . number_format((float)$registro['total'], 2, ',', '.')
                . '; status anterior: ' . ((string)$registro['status'] ?: 'Rascunho'),
            (int)$usuario['id']
        );
    }
    responder(['ok' => true, 'deleted' => $excluidos]);
} catch (Throwable $e) {
    error_log('Calculadora excluir.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível excluir o registro.'], 500);
}
