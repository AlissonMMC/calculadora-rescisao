<?php
declare(strict_types=1);

function criarConviteUsuario(PDO $pdo, int $usuarioId, int $criadoPor): string {
    if ($usuarioId < 1) {
        throw new InvalidArgumentException('Usuário inválido.');
    }

    $horas = max(1, (int)envValue('INVITE_EXPIRATION_HOURS', 24));
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expiraEm = date('Y-m-d H:i:s', time() + ($horas * 3600));

    // Um novo convite invalida imediatamente todos os anteriores.
    $stmt = $pdo->prepare('UPDATE convites_usuarios SET usado_em = COALESCE(usado_em, CURRENT_TIMESTAMP) WHERE usuario_id = ? AND usado_em IS NULL');
    $stmt->execute([$usuarioId]);

    $stmt = $pdo->prepare(
        'INSERT INTO convites_usuarios (usuario_id, token_hash, expira_em, criado_por)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$usuarioId, $hash, $expiraEm, $criadoPor > 0 ? $criadoPor : null]);

    return $token;
}
