<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
exigirLoginApi();
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
try {
    $stmt = db()->prepare('DELETE FROM rascunhos_rescisoes WHERE usuario_id = ?');
    $stmt->execute([(int)usuarioAtual()['id']]);
    responder(['ok' => true]);
} catch (Throwable $e) {
    error_log('Calculadora excluir_rascunho.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível excluir o rascunho.'], 500);
}
