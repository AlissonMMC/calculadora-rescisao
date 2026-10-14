<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirPermissaoApi('rescisao.create');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
$entrada = entradaJson();
$dados = is_array($entrada['dados'] ?? null) ? $entrada['dados'] : [];
$nome = limparTexto($entrada['nome'] ?? '', 150);
try {
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO rascunhos_rescisoes (usuario_id, nome, dados_json, atualizado_em) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE nome = VALUES(nome), dados_json = VALUES(dados_json), atualizado_em = CURRENT_TIMESTAMP');
    $stmt->execute([(int)$usuario['id'], $nome, $json]);
    responder(['ok' => true]);
} catch (JsonException $e) {
    responder(['ok' => false, 'error' => 'Não foi possível preparar o rascunho.'], 400);
} catch (Throwable $e) {
    error_log('Calculadora salvar_rascunho.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível salvar o rascunho no servidor.'], 500);
}
