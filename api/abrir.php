<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirPermissaoApi('detalhe.view');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) responder(['ok' => false, 'error' => 'ID inválido.'], 400);
try {
    $stmt = db()->prepare('SELECT h.id, h.usuario_id, h.nome, h.endereco, h.total, h.total_adm, h.total_repasse, h.criado_em, h.atualizado_em, h.modo_nome, h.status, h.dados_json, u.nome AS usuario_nome, u.login AS usuario_login FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) responder(['ok' => false, 'error' => 'Registro não encontrado.'], 404);
    // Leitura liberada para usuários autenticados; alteração é protegida por propriedade/perfil nas APIs.
    responder(['ok' => true, 'item' => normalizarItemBanco($row)]);
} catch (Throwable $e) {
    error_log('Calculadora abrir.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível abrir o registro.'], 500);
}
