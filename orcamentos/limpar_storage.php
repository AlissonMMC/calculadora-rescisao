<?php
declare(strict_types=1);

function limparJobsAntigos(string $storage, int $dias = 7): void
{
    if (!is_dir($storage)) {
        return;
    }

    $limite = time() - ($dias * 86400);

    foreach (scandir($storage) ?: [] as $item) {
        if (
            $item === '.'
            || $item === '..'
            || in_array($item, ['prestadores', 'logs', '.htaccess'], true)
        ) {
            continue;
        }

        $path = $storage . DIRECTORY_SEPARATOR . $item;

        if (!is_dir($path)) {
            continue;
        }

        // Processa somente diretórios de jobs com identificadores de 24 caracteres hexadecimais.
        if (!preg_match('/^[a-f0-9]{24}$/', $item)) {
            continue;
        }

        $modificadoEm = @filemtime($path);

        if ($modificadoEm === false || $modificadoEm >= $limite) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $p) {
            $caminho = $p->getPathname();

            if ($p->isDir()) {
                @rmdir($caminho);
            } else {
                @unlink($caminho);
            }
        }

        @rmdir($path);
    }
}
