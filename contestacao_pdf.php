<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';

$usuario = exigirLoginPagina();
$id = max(0, (int)($_GET['id'] ?? 0));
if ($id < 1) { http_response_code(400); exit('Identificador inválido.'); }

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM contestacoes_vistoria WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item || ((int)$item['usuario_id'] !== (int)$usuario['id'] && ($usuario['perfil'] ?? '') !== 'admin')) {
    http_response_code(404);
    exit('Contestação não encontrada.');
}

function removerTemporariosContestacao(string $path): void {
    if (!is_dir($path) || is_link($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $file = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_file($file) || is_link($file)) @unlink($file);
        elseif (is_dir($file)) removerTemporariosContestacao($file);
    }
    @rmdir($path);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'contestacao_' . bin2hex(random_bytes(12));
$photosDir = $root . DIRECTORY_SEPARATOR . 'fotos';
$output = $root . DIRECTORY_SEPARATOR . 'contestacao.pdf';
$input = $root . DIRECTORY_SEPARATOR . 'dados.json';

try {
    if (!mkdir($photosDir, 0775, true) && !is_dir($photosDir)) {
        throw new RuntimeException('Não foi possível preparar os arquivos temporários.');
    }

    $comodos = json_decode((string)$item['comodos_json'], true);
    if (!is_array($comodos)) $comodos = [];
    $data = [
        'id' => $id,
        'nome_inquilino' => (string)$item['nome_inquilino'],
        'cpf_inquilino' => (string)$item['cpf_inquilino'],
        'endereco_locacao' => (string)$item['endereco_locacao'],
        'data_contestacao' => (string)$item['data_contestacao'],
        'data_contestacao_br' => date('d/m/Y', strtotime((string)$item['data_contestacao'])),
        'numero_contrato' => (string)$item['numero_contrato'],
        'fianca' => (string)$item['fianca'],
        'comodos' => $comodos,
        'photos' => [],
    ];

    $stmtFotos = $pdo->prepare('SELECT id, comodo_indice, lado, nome_arquivo, mime_type, conteudo FROM contestacoes_vistoria_fotos WHERE contestacao_id = ? ORDER BY comodo_indice, lado, id');
    $stmtFotos->execute([$id]);
    $photoIndex = 0;
    foreach ($stmtFotos->fetchAll() as $foto) {
        $ext = $foto['mime_type'] === 'image/png' ? 'png' : 'jpg';
        $filename = 'foto_' . $photoIndex . '.' . $ext;
        $written = file_put_contents($photosDir . DIRECTORY_SEPARATOR . $filename, $foto['conteudo']);
        if ($written === false || $written < 1) throw new RuntimeException('Não foi possível preparar uma das fotografias.');
        $data['photos'][] = [
            'room_index' => (int)$foto['comodo_indice'],
            'side' => (string)$foto['lado'],
            'file' => $filename,
        ];
        $photoIndex++;
    }

    file_put_contents($input, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    $pythonScript = __DIR__ . '/tools/gerar_contestacao_pdf.py';
    if (!is_file($pythonScript)) throw new RuntimeException('O gerador de PDF não foi encontrado.');

    $python = trim((string)envValue('PYTHON_LAUNCHER', 'py'));
    if ($python === '') $python = 'py';
    $command = escapeshellarg($python) . ' -3 '
        . escapeshellarg($pythonScript)
        . ' --input ' . escapeshellarg($input)
        . ' --photos-dir ' . escapeshellarg($photosDir)
        . ' --output ' . escapeshellarg($output);

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($command, $descriptors, $pipes, __DIR__);
    if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o gerador Python.');

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || !is_file($output) || filesize($output) < 1) {
        error_log('Contestação PDF #' . $id . ': ' . trim((string)$stderr . ' ' . (string)$stdout));
        throw new RuntimeException('Não foi possível gerar o PDF. Confira se Python, Pillow e ReportLab estão instalados.');
    }

    registrarAuditoriaSistema('contestacoes_vistoria', 'geracao_pdf', $id, 'PDF da contestação gerado.', (int)$usuario['id']);
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="contestacao_vistoria_' . $id . '.pdf"');
    header('Content-Length: ' . (string)filesize($output));
    readfile($output);
} catch (Throwable $e) {
    error_log('Contestação PDF #' . $id . ': ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} finally {
    removerTemporariosContestacao($root);
}
