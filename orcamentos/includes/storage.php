<?php
declare(strict_types=1);

/**
 * Área de dados persistente do módulo de orçamentos.
 * Fica fora da pasta do código para que atualizações do módulo não apaguem
 * prestadores, assinaturas, histórico, logs ou jobs em andamento.
 */
function orcamentosStorage(): string {
    static $done = false;
    static $dir = '';
    if ($done) return $dir;

    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'orcamentos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    // Migração automática de instalações anteriores (v1–v19).
    $legacy = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'legacy' . DIRECTORY_SEPARATOR . 'orcamentos-storage';
    if (is_dir($legacy)) {
        $copyFile = static function(string $from, string $to): void {
            if (is_file($from) && !is_file($to)) {
                @copy($from, $to);
            }
        };
        $copyDir = static function(string $from, string $to) use (&$copyDir): void {
            if (!is_dir($from)) return;
            if (!is_dir($to)) @mkdir($to, 0775, true);
            foreach (scandir($from) ?: [] as $item) {
                if ($item === '.' || $item === '..') continue;
                $src = $from . DIRECTORY_SEPARATOR . $item;
                $dst = $to . DIRECTORY_SEPARATOR . $item;
                if (is_dir($src)) {
                    $copyDir($src, $dst);
                } elseif (is_file($src) && !is_file($dst)) {
                    @copy($src, $dst);
                }
            }
        };
        foreach (['prestadores.json','config_gerador.json','historico_orcamentos.json'] as $name) {
            $copyFile($legacy . DIRECTORY_SEPARATOR . $name, $dir . DIRECTORY_SEPARATOR . $name);
        }
        $copyDir($legacy . DIRECTORY_SEPARATOR . 'prestadores', $dir . DIRECTORY_SEPARATOR . 'prestadores');
        $copyDir($legacy . DIRECTORY_SEPARATOR . 'logs', $dir . DIRECTORY_SEPARATOR . 'logs');
    }

    if (!is_dir($dir . DIRECTORY_SEPARATOR . 'prestadores')) @mkdir($dir . DIRECTORY_SEPARATOR . 'prestadores', 0775, true);
    if (!is_dir($dir . DIRECTORY_SEPARATOR . 'logs')) @mkdir($dir . DIRECTORY_SEPARATOR . 'logs', 0775, true);

    // Proteção adicional para a área de dados, que não precisa ser acessada diretamente pelo navegador.
    $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n", LOCK_EX);
    }

    $done = true;
    return $dir;
}
