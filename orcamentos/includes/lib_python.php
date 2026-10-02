<?php
declare(strict_types=1);

function json_reply(array $d, int $status = 200): void {
    http_response_code($status);

    echo json_encode(
        $d,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/**
 * Executa Python de forma independente do usuário que instalou o Python.
 *
 * Ordem de tentativa:
 * 1. PYTHON_BIN, se configurado
 * 2. Python Launcher (py -3)
 * 3. python
 * 4. python3
 *
 * Não utiliza caminhos específicos de usuários do Windows.
 */
function run_python(array $args): array {
    $script = (string)($args[0] ?? '');
    $scriptArgs = array_slice($args, 1);

    $pythonBin = getenv('PYTHON_BIN');

    $pythonCandidates = [
        ...(
            $pythonBin
                ? [
                    [
                        'cmd' => '"' . $pythonBin . '"',
                        'label' => 'PYTHON_BIN'
                    ]
                ]
                : []
        ),
        [
            'cmd' => 'py -3',
            'label' => 'py -3'
        ],
        [
            'cmd' => 'python',
            'label' => 'python'
        ],
        [
            'cmd' => 'python3',
            'label' => 'python3'
        ]
    ];

    $quote = static function(string $value): string {
        // CMD/Windows: argumentos entre aspas; aspas internas são escapadas.
        return '"' . str_replace('"', '\\"', $value) . '"';
    };

    $lastCmd = '';
    $lastOut = '';
    $lastCode = 1;

    foreach ($pythonCandidates as $candidate) {
        $pieces = [
            $candidate['cmd'],
            $quote($script)
        ];

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

        // Só tentamos o próximo interpretador quando o Python
        // não pôde ser iniciado.
        $joined = strtolower($text);

        $launcherMissing =
            str_contains($joined, 'not recognized') ||
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