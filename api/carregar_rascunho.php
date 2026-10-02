<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
exigirLoginApi();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
try {
    $stmt = db()->prepare('SELECT id, nome, dados_json, atualizado_em FROM rascunhos_rescisoes WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([(int)usuarioAtual()['id']]);
    $row = $stmt->fetch();
    if (!$row) responder(['ok' => true, 'exists' => false]);
    $dados = json_decode($row['dados_json'] ?? '', true);
    if (!is_array($dados)) $dados = ['modo' => '', 'campos' => []];
    responder(['ok' => true, 'exists' => true, 'draft' => [
        'id' => (int)$row['id'],
        'nome' => $row['nome'] ?? '',
        'atualizadoEm' => !empty($row['atualizado_em']) ? date('d/m/Y H:i', strtotime($row['atualizado_em'])) : '',
        'dados' => $dados
    ]]);
} catch (Throwable $e) {
    error_log('Calculadora carregar_rascunho.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível consultar o rascunho.'], 500);
}
