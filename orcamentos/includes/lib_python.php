<?php
declare(strict_types=1);

function json_reply(array $d, int $status=200): void {
    http_response_code($status);
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Executa Python de forma independente do usuário que instalou o Python.
 * Preferimos o Python Launcher (py -3), que já foi validado no Apache.
 * Se não estiver disponível, tentamos python e depois o caminho conhecido.
 */
function run_python(array $args): array {
    $script = (string)($args[0] ?? '');
    $scriptArgs = array_slice($args, 1);

    $pythonCandidates = [
        ['cmd' => 'py -3', 'label' => 'py -3'],
        ['cmd' => 'python', 'label' => 'python'],
        ['cmd' => '"C:\\Users\\imobj\\AppData\\Local\\Programs\\Python\\Python314\\python.exe"', 'label' => 'Python314'],
    ];

    $quote = static function(string $value): string {
        // CMD/Windows: argumentos entre aspas; aspas internas são escapadas.
        return '"' . str_replace('"', '\\"', $value) . '"';
    };

    $lastCmd = '';
    $lastOut = '';
    $lastCode = 1;

    foreach ($pythonCandidates as $candidate) {
        $pieces = [$candidate['cmd'], $quote($script)];
        foreach ($scriptArgs as $arg) {
            $pieces[] = $quote((string)$arg);
        }
        $cmd = implode(' ', $pieces);
        $lastCmd = $cmd;

        $out = [];
        $code = 1;
        exec($cmd . ' 2>&1', $out, $code);
        $text = trim(implode(PHP_EOL, $out));

        $lastOut = $text;
        $lastCode = $code;

        // Sucesso real: não insistimos em outros interpretadores.
        if ($code === 0) {
            return [$code, $text, $cmd];
        }

        // Erros de aplicação também devem ser preservados; só tentamos o
        // próximo interpretador quando o Python não pôde ser iniciado.
        $joined = strtolower($text);
        $launcherMissing = str_contains($joined, 'not recognized') ||
            str_contains($joined, 'não é reconhecido') ||
            str_contains($joined, 'is not recognized') ||
            str_contains($joined, 'no such file') ||
            str_contains($joined, 'cannot find') ||
            str_contains($joined, 'não foi possível localizar');
        if (!$launcherMissing) {
            break;
        }
    }

    return [$lastCode, $lastOut, $lastCmd];
}
