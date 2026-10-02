<?php
require __DIR__ . '/api/config.php';
$usuario = exigirLoginPagina();
header('Content-Type: application/json; charset=utf-8');

function respostaJson(array $dados, int $status = 200): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$id = max(0, (int)($_GET['id'] ?? 0));
if ($id < 1) respostaJson(['ok' => false, 'error' => 'ID de histórico inválido.'], 400);

try {
    $stmt = db()->prepare(
        'SELECT h.id, h.usuario_id, h.nome, h.endereco, h.total, h.total_adm, h.total_repasse,
                h.criado_em, h.atualizado_em, h.modo_nome, h.status, h.dados_json,
                u.nome AS usuario_nome, u.login AS usuario_login
         FROM historico_rescisoes h
         LEFT JOIN usuarios u ON u.id = h.usuario_id
         WHERE h.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) respostaJson(['ok' => false, 'error' => 'Rescisão não encontrada.'], 404);

    $dados = $row['dados_json'] ?? '';
    // Compatibilidade com versões que acabaram gravando JSON dentro de uma string JSON.
    for ($i = 0; $i < 3 && is_string($dados); $i++) {
        $texto = trim($dados);
        if ($texto === '') break;
        $decodificado = json_decode($texto, true);
        if (json_last_error() !== JSON_ERROR_NONE) break;
        $dados = $decodificado;
    }

    if (!is_array($dados)) $dados = [];

    respostaJson([
        'ok' => true,
        'item' => [
            'id' => (int)$row['id'],
            'usuario_id' => (int)$row['usuario_id'],
            'nome' => (string)($row['nome'] ?? ''),
            'endereco' => (string)($row['endereco'] ?? ''),
            'total' => (float)($row['total'] ?? 0),
            'totalAdm' => (float)($row['total_adm'] ?? 0),
            'totalRepasse' => (float)($row['total_repasse'] ?? 0),
            'criado_em' => $row['criado_em'],
            'atualizado_em' => $row['atualizado_em'],
            'modoNome' => (string)($row['modo_nome'] ?? ''),
            'modo_nome' => (string)($row['modo_nome'] ?? ''),
            'status' => (string)($row['status'] ?? 'Rascunho'),
            'dados' => $dados,
            'usuarioNome' => (string)($row['usuario_nome'] ?? ''),
            'usuarioLogin' => (string)($row['usuario_login'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    error_log('carregar_edicao.php: ' . $e->getMessage());
    respostaJson(['ok' => false, 'error' => 'Não foi possível carregar a rescisão para edição.'], 500);
}
