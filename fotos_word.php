<?php
declare(strict_types=1);

require __DIR__ . '/api/config.php';

$usuario = exigirLoginPagina();
$navPage = 'fotos_word';
$navBase = '';
$csrf = csrfToken();

$storageRoot = __DIR__ . '/storage/fotos_word';
$pythonScript = __DIR__ . '/tools/gerar_fotos_word.py';
$mensagemErro = '';
$resultado = null;

function fotosWordRemoverDiretorio(string $diretorio): void {
    if (!is_dir($diretorio) || is_link($diretorio)) {
        return;
    }

    $itens = scandir($diretorio);
    if ($itens === false) {
        return;
    }

    foreach ($itens as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $caminho = $diretorio . DIRECTORY_SEPARATOR . $item;
        if (is_link($caminho) || is_file($caminho)) {
            @unlink($caminho);
        } elseif (is_dir($caminho)) {
            fotosWordRemoverDiretorio($caminho);
        }
    }

    @rmdir($diretorio);
}

function fotosWordBytesIni(string $valor): int {
    $valor = trim($valor);
    if ($valor === '') {
        return 0;
    }

    $unidade = strtolower(substr($valor, -1));
    $numero = (float)$valor;

    if ($unidade === 'g') {
        $numero *= 1024;
        $unidade = 'm';
    }
    if ($unidade === 'm') {
        $numero *= 1024;
        $unidade = 'k';
    }
    if ($unidade === 'k') {
        $numero *= 1024;
    }

    return (int)$numero;
}

function fotosWordLimparAntigos(string $raiz): void {
    if (!is_dir($raiz)) {
        return;
    }

    foreach (glob($raiz . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $pasta) {
        $id = basename($pasta);
        if (preg_match('/^[a-f0-9]{32}$/', $id) && filemtime($pasta) < time() - 172800) {
            fotosWordRemoverDiretorio($pasta);
        }
    }
}

if (!is_dir($storageRoot)) {
    @mkdir($storageRoot, 0775, true);
}
fotosWordLimparAntigos($storageRoot);

// Entrega os arquivos por uma rota autenticada; o armazenamento não é público.
if (isset($_GET['baixar'])) {
    $tipo = (string)$_GET['baixar'];
    $idSolicitado = (string)($_GET['id'] ?? '');
    $sessaoArquivo = $_SESSION['fotos_word_output'] ?? null;

    if (
        !in_array($tipo, ['docx', 'pdf'], true) ||
        !preg_match('/^[a-f0-9]{32}$/', $idSolicitado) ||
        !is_array($sessaoArquivo) ||
        !isset($sessaoArquivo['id']) ||
        !hash_equals((string)$sessaoArquivo['id'], $idSolicitado)
    ) {
        http_response_code(404);
        exit('Arquivo não encontrado ou sessão expirada. Gere os documentos novamente.');
    }

    $extensao = $tipo === 'docx' ? 'docx' : 'pdf';
    $caminho = $storageRoot . DIRECTORY_SEPARATOR . $idSolicitado . DIRECTORY_SEPARATOR . 'fotos_redimensionadas.' . $extensao;
    if (!is_file($caminho) || filesize($caminho) < 1) {
        http_response_code(404);
        exit('O arquivo não está mais disponível. Gere os documentos novamente.');
    }

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . ($tipo === 'docx'
        ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        : 'application/pdf'));
    header('Content-Disposition: attachment; filename="fotos_redimensionadas.' . $extensao . '"');
    header('Content-Length: ' . (string)filesize($caminho));
    readfile($caminho);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diretorioTemporario = '';
    $diretorioResultado = '';
    $geracaoConcluida = false;

    try {
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limitePost = fotosWordBytesIni((string)ini_get('post_max_size'));
        if ($contentLength > 0 && $limitePost > 0 && $contentLength > $limitePost) {
            throw new RuntimeException(
                'O envio ultrapassou o limite de ' . ini_get('post_max_size') . ' configurado no PHP. Para contratos grandes, configure post_max_size=1100M, upload_max_filesize=1050M e max_file_uploads=850 no php.ini, salve e reinicie o Apache.'
            );
        }

        validarCsrf();

        $larguraTexto = str_replace(',', '.', trim((string)($_POST['largura_cm'] ?? '12')));
        if (!is_numeric($larguraTexto)) {
            throw new RuntimeException('Informe uma largura numérica para as fotos.');
        }
        $largura = (float)$larguraTexto;
        if ($largura < 5 || $largura > 17.4) {
            throw new RuntimeException('A largura deve ficar entre 5 cm e 17,4 cm para caber na página A4.');
        }

        $arquivosZip = $_FILES['arquivo_zip'] ?? null;
        $zipEnviado = is_array($arquivosZip) && (int)($arquivosZip['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $arquivosFotos = $_FILES['fotos'] ?? null;
        $fotosEnviadas = false;

        if (is_array($arquivosFotos) && isset($arquivosFotos['error']) && is_array($arquivosFotos['error'])) {
            foreach ($arquivosFotos['error'] as $erroUpload) {
                if ((int)$erroUpload !== UPLOAD_ERR_NO_FILE) {
                    $fotosEnviadas = true;
                    break;
                }
            }
        }

        if ($zipEnviado && $fotosEnviadas) {
            throw new RuntimeException('Escolha uma pasta de fotos ou um arquivo ZIP, não os dois ao mesmo tempo.');
        }
        if (!$zipEnviado && !$fotosEnviadas) {
            throw new RuntimeException('Selecione uma pasta com fotos ou envie um arquivo ZIP.');
        }

        $idSaida = bin2hex(random_bytes(16));
        $diretorioTemporario = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fotos-word-' . bin2hex(random_bytes(12));
        if (!mkdir($diretorioTemporario, 0700, true) && !is_dir($diretorioTemporario)) {
            throw new RuntimeException('Não foi possível preparar os arquivos temporários.');
        }

        $argumentoEntrada = '';
        $quantidadeRecebida = 0;

        if ($zipEnviado) {
            $erroZip = (int)($arquivosZip['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($erroZip !== UPLOAD_ERR_OK) {
                $mensagemUpload = [
                    UPLOAD_ERR_INI_SIZE => 'O ZIP excedeu upload_max_filesize do PHP.',
                    UPLOAD_ERR_FORM_SIZE => 'O ZIP excedeu o limite permitido pelo formulário.',
                    UPLOAD_ERR_PARTIAL => 'O ZIP foi enviado parcialmente. Tente novamente.',
                    UPLOAD_ERR_NO_TMP_DIR => 'A pasta temporária de uploads do PHP não está disponível.',
                    UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o ZIP recebido.',
                    UPLOAD_ERR_EXTENSION => 'O PHP interrompeu o envio do ZIP.',
                ];
                throw new RuntimeException($mensagemUpload[$erroZip] ?? 'Falha ao receber o arquivo ZIP.');
            }

            $nomeZip = (string)($arquivosZip['name'] ?? '');
            if (strtolower(pathinfo($nomeZip, PATHINFO_EXTENSION)) !== 'zip') {
                throw new RuntimeException('O arquivo enviado precisa estar no formato .zip.');
            }
            if ((int)($arquivosZip['size'] ?? 0) > 1024 * 1024 * 1024) {
                throw new RuntimeException('O ZIP pode ter no máximo 1 GB.');
            }

            $caminhoZip = $diretorioTemporario . DIRECTORY_SEPARATOR . 'fotos.zip';
            if (!is_uploaded_file((string)($arquivosZip['tmp_name'] ?? '')) ||
                !move_uploaded_file((string)$arquivosZip['tmp_name'], $caminhoZip)) {
                throw new RuntimeException('Não foi possível receber o arquivo ZIP.');
            }
            $argumentoEntrada = ' --zip-file ' . escapeshellarg($caminhoZip);
        } else {
            $lista = [];
            $nomes = $arquivosFotos['name'] ?? [];
            $temporarios = $arquivosFotos['tmp_name'] ?? [];
            $tamanhos = $arquivosFotos['size'] ?? [];
            $erros = $arquivosFotos['error'] ?? [];

            foreach ($erros as $indice => $erroFoto) {
                $erroFoto = (int)$erroFoto;
                if ($erroFoto === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($erroFoto !== UPLOAD_ERR_OK) {
                    $mensagemUpload = [
                        UPLOAD_ERR_INI_SIZE => 'Uma das fotos excedeu upload_max_filesize do PHP. Tente enviar um ZIP menor.',
                        UPLOAD_ERR_FORM_SIZE => 'Uma das fotos excedeu o limite de envio.',
                        UPLOAD_ERR_PARTIAL => 'Uma das fotos foi enviada parcialmente. Tente novamente.',
                        UPLOAD_ERR_NO_TMP_DIR => 'A pasta temporária de uploads do PHP não está disponível.',
                        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar uma foto recebida.',
                        UPLOAD_ERR_EXTENSION => 'O PHP interrompeu o envio de uma foto.',
                    ];
                    throw new RuntimeException($mensagemUpload[$erroFoto] ?? 'Falha ao receber uma das fotos.');
                }

                $nome = basename((string)($nomes[$indice] ?? 'foto'));
                $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
                $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tif', 'tiff'];
                if (!in_array($extensao, $permitidas, true)) {
                    continue;
                }

                $tamanho = (int)($tamanhos[$indice] ?? 0);
                if ($tamanho > 40 * 1024 * 1024) {
                    throw new RuntimeException('A foto ' . $nome . ' excede 40 MB.');
                }

                $lista[] = [
                    'nome' => $nome,
                    'tmp' => (string)($temporarios[$indice] ?? ''),
                    'tamanho' => $tamanho,
                ];
            }

            usort($lista, static function (array $a, array $b): int {
                return strnatcasecmp($a['nome'], $b['nome']);
            });

            if (count($lista) < 1) {
                throw new RuntimeException('Nenhuma imagem compatível foi encontrada na pasta selecionada.');
            }
            if (count($lista) > 800) {
                throw new RuntimeException('Selecione no máximo 800 fotos por vez.');
            }

            $totalBytes = 0;
            $pastaFotos = $diretorioTemporario . DIRECTORY_SEPARATOR . 'fotos';
            if (!mkdir($pastaFotos, 0700, true) && !is_dir($pastaFotos)) {
                throw new RuntimeException('Não foi possível preparar as fotos recebidas.');
            }

            foreach ($lista as $foto) {
                $totalBytes += $foto['tamanho'];
                if ($totalBytes > 700 * 1024 * 1024) {
                    throw new RuntimeException('O total das fotos excede 1 GB. Divida as fotos em dois envios.');
                }

                $temporario = $foto['tmp'];
                $infoImagem = is_file($temporario) ? @getimagesize($temporario) : false;
                $mime = is_array($infoImagem) ? (string)($infoImagem['mime'] ?? '') : '';
                $extensoesMime = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/bmp' => 'bmp',
                    'image/x-ms-bmp' => 'bmp',
                    'image/webp' => 'webp',
                    'image/tiff' => 'tif',
                ];
                if (!isset($extensoesMime[$mime])) {
                    continue;
                }

                $quantidadeRecebida++;
                $destino = $pastaFotos . DIRECTORY_SEPARATOR . sprintf('%04d.%s', $quantidadeRecebida, $extensoesMime[$mime]);
                if (!is_uploaded_file($temporario) || !move_uploaded_file($temporario, $destino)) {
                    throw new RuntimeException('Não foi possível salvar uma das fotos recebidas.');
                }
            }

            if ($quantidadeRecebida < 1) {
                throw new RuntimeException('Os arquivos enviados não continham imagens válidas.');
            }

            $argumentoEntrada = ' --input-dir ' . escapeshellarg($pastaFotos);
        }

        $diretorioResultado = $storageRoot . DIRECTORY_SEPARATOR . $idSaida;
        if (!mkdir($diretorioResultado, 0775, true) && !is_dir($diretorioResultado)) {
            throw new RuntimeException('Não foi possível criar a pasta dos documentos gerados.');
        }

        $python = trim((string)envValue('PYTHON_LAUNCHER', 'py'));
        if ($python === '') {
            $python = 'py';
        }
        $comando = escapeshellarg($python)
            . ' -3 '
            . escapeshellarg($pythonScript)
            . $argumentoEntrada
            . ' --output-dir ' . escapeshellarg($diretorioResultado)
            . ' --width ' . escapeshellarg((string)$largura);

        $descritores = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $processo = @proc_open($comando, $descritores, $pipes, __DIR__);
        if (!is_resource($processo)) {
            throw new RuntimeException('Não foi possível iniciar o processador de fotos. Verifique a instalação do Python.');
        }

        fclose($pipes[0]);
        $saidaPython = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $erroPython = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $codigoSaida = proc_close($processo);

        if ($codigoSaida !== 0) {
            error_log('Fotos Word/PDF: ' . trim((string)$erroPython));
            $erroNormalizado = strtolower((string)$erroPython);
            if (strpos($erroNormalizado, 'dependência python ausente') !== false) {
                throw new RuntimeException('Falta instalar a dependência Python. Execute: py -3 -m pip install -r tools/requirements-fotos-word.txt');
            }
            if (strpos($erroNormalizado, 'libreoffice') !== false) {
                throw new RuntimeException('O LibreOffice não foi encontrado pelo sistema. Verifique se está instalado ou configure LIBREOFFICE_BIN no ambiente.');
            }
            throw new RuntimeException('Não foi possível gerar os documentos. Confira se Python, python-docx, Pillow e LibreOffice estão instalados.');
        }

        $estatisticas = json_decode(trim((string)$saidaPython), true);
        $docx = $diretorioResultado . DIRECTORY_SEPARATOR . 'fotos_redimensionadas.docx';
        $pdf = $diretorioResultado . DIRECTORY_SEPARATOR . 'fotos_redimensionadas.pdf';
        if (
            !is_array($estatisticas) ||
            empty($estatisticas['ok']) ||
            !is_file($docx) || filesize($docx) < 1 ||
            !is_file($pdf) || filesize($pdf) < 1
        ) {
            error_log('Fotos Word/PDF: retorno inesperado do processador. ' . (string)$saidaPython);
            throw new RuntimeException('O sistema não confirmou a criação dos arquivos Word e PDF. Tente novamente.');
        }

        $resultado = [
            'id' => $idSaida,
            'fotos' => (int)($estatisticas['photos'] ?? $quantidadeRecebida),
            'largura' => (float)($estatisticas['width_cm'] ?? $largura),
            'gerado_em' => time(),
        ];
        $_SESSION['fotos_word_output'] = $resultado;
        registrarAuditoriaSistema(
            'fotos_word',
            'geracao_documentos',
            null,
            'Fotos processadas: ' . $resultado['fotos'] . '; largura: ' . number_format($resultado['largura'], 1, ',', '') . ' cm; arquivos DOCX e PDF gerados.'
        );
        $geracaoConcluida = true;
    } catch (Throwable $e) {
        $mensagemErro = $e->getMessage();
        if ($diretorioResultado !== '' && is_dir($diretorioResultado) && !$geracaoConcluida) {
            fotosWordRemoverDiretorio($diretorioResultado);
        }
    } finally {
        if ($diretorioTemporario !== '' && is_dir($diretorioTemporario)) {
            fotosWordRemoverDiretorio($diretorioTemporario);
        }
    }
}

if ($resultado === null) {
    $sessaoResultado = $_SESSION['fotos_word_output'] ?? null;
    if (
        is_array($sessaoResultado) &&
        isset($sessaoResultado['id']) &&
        preg_match('/^[a-f0-9]{32}$/', (string)$sessaoResultado['id'])
    ) {
        $pastaSessao = $storageRoot . DIRECTORY_SEPARATOR . (string)$sessaoResultado['id'];
        if (
            is_file($pastaSessao . DIRECTORY_SEPARATOR . 'fotos_redimensionadas.docx') &&
            is_file($pastaSessao . DIRECTORY_SEPARATOR . 'fotos_redimensionadas.pdf')
        ) {
            $resultado = $sessaoResultado;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fotos para Word e PDF — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/fotos-word.css">
</head>
<body>
<?php require __DIR__ . '/includes/nav_global.php'; ?>

<main class="photo-word-page">
    <header class="photo-word-header">
        <div>
            <span class="photo-word-eyebrow">Ferramentas de documentos</span>
            <h1>Fotos para Word e PDF</h1>
            <p>Selecione uma pasta de fotos ou envie um ZIP. O sistema monta um documento com as imagens centralizadas, largura padronizada e proporção preservada.</p>
        </div>
        <div class="photo-word-header-icon" aria-hidden="true">DOC</div>
    </header>

    <?php if ($mensagemErro !== ''): ?>
        <div class="photo-word-notice error" role="alert"><?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (is_array($resultado)): ?>
        <section class="photo-word-result">
            <div class="photo-word-result-icon" aria-hidden="true">✓</div>
            <div class="photo-word-result-copy">
                <strong>Documentos gerados com sucesso</strong>
                <span><?= (int)$resultado['fotos'] ?> foto(s) processada(s) · largura de <?= htmlspecialchars(number_format((float)$resultado['largura'], 1, ',', ''), ENT_QUOTES, 'UTF-8') ?> cm</span>
            </div>
            <div class="photo-word-downloads">
                <a class="photo-word-button primary" href="fotos_word.php?baixar=docx&amp;id=<?= rawurlencode((string)$resultado['id']) ?>">Baixar Word (.docx)</a>
                <a class="photo-word-button secondary" href="fotos_word.php?baixar=pdf&amp;id=<?= rawurlencode((string)$resultado['id']) ?>">Baixar PDF</a>
            </div>
        </section>
    <?php endif; ?>

    <section class="photo-word-card">
        <div class="photo-word-card-heading">
            <div>
                <h2>Selecionar fotos</h2>
                <p>As fotos serão ordenadas pelo nome do arquivo. Os originais não serão alterados.</p>
            </div>
            <span class="photo-word-step">01 / ENVIAR</span>
        </div>

        <form method="post" enctype="multipart/form-data" class="photo-word-form" id="photoWordForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <label class="photo-word-dropzone" for="fotos">
                <span class="photo-word-upload-icon" aria-hidden="true">↑</span>
                <strong>Selecionar uma pasta do computador</strong>
                <span>JPG, PNG, GIF, BMP, WEBP ou TIFF</span>
                <input id="fotos" name="fotos[]" type="file" webkitdirectory directory multiple accept="image/jpeg,image/png,image/gif,image/bmp,image/webp,image/tiff">
                <span class="photo-word-file-summary" id="folderSummary">Nenhuma pasta selecionada</span>
            </label>

            <div class="photo-word-or"><span>ou</span></div>

            <label class="photo-word-zip">
                <span><strong>Enviar pasta compactada (.zip)</strong><small>Recomendado para muitas fotos ou quando o navegador não permite selecionar pastas.</small></span>
                <input id="arquivo_zip" name="arquivo_zip" type="file" accept=".zip,application/zip">
            </label>
            <p class="photo-word-file-summary" id="zipSummary">Nenhum arquivo ZIP selecionado</p>

            <div class="photo-word-settings">
                <div class="photo-word-width-field">
                    <label for="largura_cm">Largura de cada foto</label>
                    <div class="photo-word-width-input">
                        <input id="largura_cm" name="largura_cm" type="number" min="5" max="17.4" step="0.1" value="12" required>
                        <span>cm</span>
                    </div>
                    <small>A proporção original será mantida automaticamente.</small>
                </div>
                <div class="photo-word-output-note">
                    <span class="photo-word-output-icon" aria-hidden="true">W</span>
                    <span><strong>Word + PDF</strong><small>As imagens serão inseridas centralizadas em páginas A4, na ordem dos nomes dos arquivos.</small></span>
                </div>
            </div>

            <button type="submit" class="photo-word-button primary photo-word-submit" id="generateButton">Gerar documentos</button>
            <p class="photo-word-privacy">Até 800 fotos por envio e 1 GB no total. As imagens são otimizadas para reduzir o peso dos documentos. Os arquivos temporários são removidos; os documentos gerados ficam disponíveis para download na sessão por até 48 horas.</p>
        </form>
    </section>
</main>

<script>
(function () {
    const folder = document.getElementById('fotos');
    const zip = document.getElementById('arquivo_zip');
    const folderSummary = document.getElementById('folderSummary');
    const zipSummary = document.getElementById('zipSummary');
    const form = document.getElementById('photoWordForm');
    const button = document.getElementById('generateButton');

    folder.addEventListener('change', function () {
        const count = folder.files ? folder.files.length : 0;
        folderSummary.textContent = count ? count + ' arquivo(s) selecionado(s)' : 'Nenhuma pasta selecionada';
        if (count) {
            zip.value = '';
            zipSummary.textContent = 'Nenhum arquivo ZIP selecionado';
        }
    });
    zip.addEventListener('change', function () {
        const file = zip.files && zip.files[0];
        zipSummary.textContent = file ? file.name : 'Nenhum arquivo ZIP selecionado';
        if (file) {
            folder.value = '';
            folderSummary.textContent = 'Nenhuma pasta selecionada';
        }
    });
    form.addEventListener('submit', function () {
        button.disabled = true;
        button.textContent = 'Processando fotos...';
    });
})();
</script>
</body>
</html>
