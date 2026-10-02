<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
exigirLoginApi();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
try {
    $stmt = db()->query('SELECT h.id, h.nome, h.endereco, h.total, h.total_adm, h.total_repasse, h.criado_em, h.atualizado_em, h.modo_nome, h.status, h.dados_json, u.nome AS usuario_nome, u.login AS usuario_login FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id = h.usuario_id ORDER BY h.id DESC');
    $itens = [];
    while ($row = $stmt->fetch()) $itens[] = normalizarItemBanco($row);
    responder(['ok' => true, 'data' => $itens]);
} catch (Throwable $e) {
    error_log('Calculadora listar.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível carregar o histórico.'], 500);
}
