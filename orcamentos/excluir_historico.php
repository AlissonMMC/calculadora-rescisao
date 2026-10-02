<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../api/config.php';
require __DIR__ . '/includes/storage.php';

function resp(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ehAdmin(array $u): bool {
    $p = strtolower((string)($u['perfil'] ?? $u['role'] ?? $u['nivel'] ?? ''));
    return !empty($u['is_admin']) || !empty($u['admin']) || $p === 'admin' || strtolower((string)($u['login'] ?? '')) === 'admin';
}

try {
    $u = usuarioAtual();
    if (!$u) resp(['ok'=>false,'error'=>'Sessão expirada.'], 401);
    if (!ehAdmin($u)) resp(['ok'=>false,'error'=>'Apenas administradores podem excluir orçamentos do histórico.'], 403);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(['ok'=>false,'error'=>'Método não permitido.'], 405);

    $csrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
        resp(['ok'=>false,'error'=>'Token de segurança inválido.'], 419);
    }

    $job = trim((string)($_POST['job'] ?? ''));
    if (!preg_match('/^[a-f0-9]{24}$/i', $job)) {
        resp(['ok'=>false,'error'=>'Identificador do orçamento inválido.'], 400);
    }

    $storage = orcamentosStorage();
    $historyFile = $storage . '/historico_orcamentos.json';
    $data = is_file($historyFile) ? json_decode((string)file_get_contents($historyFile), true) : [];
    if (!is_array($data)) $data = [];

    $before = count($data);
    $removed = null;
    $kept = [];
    foreach ($data as $row) {
        if (!is_array($row) || (string)($row['job'] ?? '') !== $job) {
            $kept[] = $row;
        } else {
            $removed = $row;
        }
    }
    if ($removed === null || count($kept) === $before) {
        resp(['ok'=>false,'error'=>'Orçamento não encontrado no histórico.'], 404);
    }

    if (file_put_contents($historyFile, json_encode(array_values($kept), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        resp(['ok'=>false,'error'=>'Não foi possível atualizar o histórico.'], 500);
    }

    // Excluir também os arquivos gerados daquele processamento para evitar lixo no servidor.
    $jobDir = $storage . DIRECTORY_SEPARATOR . $job;
    $removedFiles = false;
    if (is_dir($jobDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($jobDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) @rmdir($path); else @unlink($path);
        }
        @rmdir($jobDir);
        $removedFiles = true;
    }

    $logFile = $storage . '/logs/processamentos.jsonl';
    if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0775, true);
    @file_put_contents($logFile, json_encode([
        'created_at'=>date('d/m/Y H:i:s'),
        'usuario'=>$u['nome'] ?? $u['login'] ?? '',
        'locatario'=>$removed['locatario'] ?? '',
        'status'=>'EXCLUIDO',
        'job'=>$job,
        'message'=>'Orçamento removido do histórico pelo administrador.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

    resp(['ok'=>true,'message'=>'Orçamento excluído do histórico.', 'arquivos_excluidos'=>$removedFiles]);
} catch (Throwable $e) {
    resp(['ok'=>false,'error'=>'Erro interno ao excluir o orçamento.','details'=>$e->getMessage()], 500);
}
