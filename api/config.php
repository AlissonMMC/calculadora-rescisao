<?php
declare(strict_types=1);

// Carrega configurações do arquivo .env local, quando existir.
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
             ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '') {
            putenv($key . '=' . $value);
        }
    }
}

/*
 * Configuração central da aplicação.
 *
 * Em produção, defina as variáveis de ambiente do servidor.
 * Os valores abaixo existem apenas como fallback para desenvolvimento local.
 */

function sessionTimeout(): int {
    $timeout = (int) envValue('SESSION_TIMEOUT', 28800);
    return max(300, $timeout);
}

function envValue(string $key, mixed $default = null): mixed {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

const SESSION_TIMEOUT = 28800;

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = (string) envValue('DB_HOST', '127.0.0.1');
    $name = (string) envValue('DB_NAME', 'calculadora_rescisao');
    $user = (string) envValue('DB_USER', 'root');
    $pass = (string) envValue('DB_PASS', '');

    $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
        'path' => '/',
    ]);

    session_start();
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if (!$token || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$token)) {
        responder([
            'ok' => false,
            'error' => 'Token de segurança inválido. Atualize a página e tente novamente.'
        ], 419);
    }
}

function usuarioAtual(): ?array {
    $id = (int)($_SESSION['usuario_id'] ?? 0);
    if ($id < 1) return null;

    $ultimoAcesso = (int)($_SESSION['ultimo_acesso'] ?? 0);

    if ($ultimoAcesso && (time() - $ultimoAcesso) > sessionTimeout()) {
        unset($_SESSION['usuario_id'], $_SESSION['ultimo_acesso']);
        return null;
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, nome, login, perfil, ativo
             FROM usuarios
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);

        $usuario = $stmt->fetch();

        if (!$usuario || !(int)$usuario['ativo']) {
            unset($_SESSION['usuario_id'], $_SESSION['ultimo_acesso']);
            return null;
        }

        $_SESSION['ultimo_acesso'] = time();
        return $usuario;
    } catch (Throwable $e) {
        error_log('Calculadora usuarioAtual: ' . $e->getMessage());
        return null;
    }
}

function perfilNormalizado(array $usuario): string {
    $perfil = strtolower(trim((string)($usuario['perfil'] ?? 'operacional')));
    return match ($perfil) {
        'admin', 'administrador' => 'admin',
        'financeiro' => 'financeiro',
        'consulta', 'leitura', 'somente_consulta' => 'consulta',
        default => 'operacional',
    };
}

function permissoesPerfil(string $perfil): array {
    return match ($perfil) {
        'admin' => ['*'],
        'financeiro' => [
            'dashboard.view', 'rescisao.view', 'rescisao.create', 'rescisao.edit',
            'historico.view', 'historico.edit', 'orcamentos.view', 'orcamentos.create',
            'orcamentos.edit', 'prestadores.view', 'perfil.view', 'perfil.edit'
        ],
        'consulta' => [
            'dashboard.view', 'rescisao.view', 'historico.view', 'detalhe.view',
            'orcamentos.view', 'perfil.view', 'perfil.edit'
        ],
        default => [
            'dashboard.view', 'rescisao.view', 'rescisao.create', 'rescisao.edit',
            'historico.view', 'historico.edit', 'orcamentos.view', 'orcamentos.create',
            'orcamentos.edit', 'perfil.view', 'perfil.edit'
        ],
    };
}

function temPermissao(array $usuario, string $permissao): bool {
    $perfil = perfilNormalizado($usuario);
    $lista = permissoesPerfil($perfil);
    return in_array('*', $lista, true) || in_array($permissao, $lista, true);
}

function exigirPermissaoPagina(string $permissao): array {
    $usuario = exigirLoginPagina();
    if (!temPermissao($usuario, $permissao)) {
        http_response_code(403);
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Acesso negado</title><body style="font-family:Arial,sans-serif;padding:40px"><h1>Acesso negado</h1><p>Seu perfil não possui permissão para esta área.</p><p><a href="dashboard.php">Voltar ao painel</a></p></body></html>';
        exit;
    }
    return $usuario;
}

function exigirPermissaoApi(string $permissao): array {
    $usuario = exigirLoginApi();
    if (!temPermissao($usuario, $permissao)) {
        responder(['ok' => false, 'error' => 'Você não possui permissão para esta operação.'], 403);
    }
    return $usuario;
}

function registrarAuditoriaSistema(
    string $modulo,
    string $acao,
    ?int $registroId = null,
    ?string $detalhes = null,
    ?int $usuarioId = null
): void {
    try {
        $usuarioId = $usuarioId ?? (int)($_SESSION['usuario_id'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $stmt = db()->prepare(
            'INSERT INTO auditoria_sistema
             (usuario_id, modulo, acao, registro_id, detalhes, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$usuarioId > 0 ? $usuarioId : null, $modulo, $acao, $registroId, $detalhes, $ip, $ua]);
    } catch (Throwable $e) {
        error_log('Calculadora auditoria_sistema: ' . $e->getMessage());
    }
}

function registrarTentativaLogin(string $login, bool $sucesso, ?int $usuarioId = null): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO tentativas_login
             (login_informado, usuario_id, sucesso, ip, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            mb_substr($login, 0, 190),
            $usuarioId,
            $sucesso ? 1 : 0,
            $_SERVER['REMOTE_ADDR'] ?? null,
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)
        ]);
    } catch (Throwable $e) {
        error_log('Calculadora tentativas_login: ' . $e->getMessage());
    }
}

function exigirLoginApi(): array {
    $usuario = usuarioAtual();

    if (!$usuario) {
        responder([
            'ok' => false,
            'error' => 'Sessão expirada. Faça login novamente.'
        ], 401);
    }

    return $usuario;
}

function exigirAdminApi(): array {
    $usuario = exigirLoginApi();

    if (perfilNormalizado($usuario) !== 'admin') {
        responder([
            'ok' => false,
            'error' => 'Acesso restrito ao administrador.'
        ], 403);
    }

    return $usuario;
}

function exigirLoginPagina(): array {
    $usuario = usuarioAtual();

    if (!$usuario) {
        header('Location: login.php');
        exit;
    }

    return $usuario;
}

function exigirAdminPagina(): array {
    $usuario = exigirLoginPagina();

    if ($usuario['perfil'] !== 'admin') {
        http_response_code(403);
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Acesso negado</title><body style="font-family:Arial,sans-serif;padding:40px"><h1>Acesso negado</h1><p>Somente administradores podem acessar esta página.</p><p><a href="index.php">Voltar para a calculadora</a></p></body></html>';
        exit;
    }

    return $usuario;
}

const STATUS_RESCISAO = [
    'Rascunho',
    'Em conferência',
    'Conferido',
    'Pronto para cobrança',
    'Cobrado',
    'Cancelado'
];

function statusValido(string $status): bool {
    return in_array($status, STATUS_RESCISAO, true);
}

function registrarAuditoria(
    int $historicoId,
    int $usuarioId,
    string $acao,
    ?string $detalhes = null
): void {
    if ($historicoId < 1 || $usuarioId < 1) return;

    try {
        $stmt = db()->prepare(
            'INSERT INTO auditoria_rescisoes
            (historico_id, usuario_id, acao, detalhes)
            VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $historicoId,
            $usuarioId,
            $acao,
            $detalhes
        ]);
    } catch (Throwable $e) {
        error_log('Calculadora auditoria: ' . $e->getMessage());
    }
}

function responder(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function entradaJson(): array {
    $raw = file_get_contents('php://input');

    if (!$raw) return [];

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function limparTexto(mixed $valor, int $max = 255): string {
    return mb_substr(trim((string)($valor ?? '')), 0, $max);
}

function normalizarItemBanco(array $row): array {
    $dados = json_decode($row['dados_json'] ?? '', true);

    if (!is_array($dados)) {
        $dados = [
            'modo' => '',
            'campos' => []
        ];
    }

    return [
        'id' => (int)$row['id'],
        'nome' => $row['nome'] ?? '',
        'endereco' => $row['endereco'] ?? '',
        'total' => (float)$row['total'],
        'data' => !empty($row['criado_em'])
            ? date('d/m/Y, H:i', strtotime($row['criado_em']))
            : '',
        'modoNome' => $row['modo_nome'] ?? '',
        'usuarioNome' => $row['usuario_nome'] ?? '',
        'usuarioLogin' => $row['usuario_login'] ?? '',
        'status' => $row['status'] ?? 'Rascunho',
        'atualizadoEm' => !empty($row['atualizado_em'])
            ? date('d/m/Y, H:i', strtotime($row['atualizado_em']))
            : '',
        'totalAdm' => (float)($row['total_adm'] ?? 0),
        'totalRepasse' => (float)($row['total_repasse'] ?? 0),
        'dados' => $dados,
    ];
}
