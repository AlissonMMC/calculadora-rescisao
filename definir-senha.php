<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$erro = '';
$sucesso = '';

function buscarConviteValido(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;

    $stmt = db()->prepare(
        'SELECT c.id AS convite_id, c.usuario_id, c.expira_em,
                u.nome, u.login, u.email, u.perfil, u.ativo, u.senha_definida
         FROM convites_usuarios c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.token_hash = ?
           AND c.usado_em IS NULL
           AND c.expira_em >= CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $convite = $stmt->fetch();

    return $convite ?: null;
}

$convite = null;
if ($token !== '') {
    try {
        $convite = buscarConviteValido($token);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            if (!$convite) {
                throw new RuntimeException('Este link de ativação é inválido, já foi utilizado ou expirou.');
            }

            if (!(int)$convite['ativo']) {
                throw new RuntimeException('Este usuário está bloqueado. Solicite ao administrador a reativação do acesso.');
            }

            $senha = (string)($_POST['senha'] ?? '');
            $confirmacao = (string)($_POST['confirmacao'] ?? '');

            if (mb_strlen($senha) < 8) {
                throw new RuntimeException('A senha deve ter pelo menos 8 caracteres.');
            }

            if ($senha !== $confirmacao) {
                throw new RuntimeException('A confirmação da senha não confere.');
            }

            $pdo = db();
            $pdo->beginTransaction();

            try {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios
                     SET senha_hash = ?, senha_definida = 1, atualizado_em = CURRENT_TIMESTAMP
                     WHERE id = ?'
                );
                $stmt->execute([
                    password_hash($senha, PASSWORD_DEFAULT),
                    (int)$convite['usuario_id']
                ]);

                $stmt = $pdo->prepare(
                    'UPDATE convites_usuarios
                     SET usado_em = CURRENT_TIMESTAMP
                     WHERE usuario_id = ? AND usado_em IS NULL'
                );
                $stmt->execute([(int)$convite['usuario_id']]);

                registrarAuditoriaSistema(
                    'usuarios',
                    'ativar_primeiro_acesso',
                    (int)$convite['usuario_id'],
                    'Senha definida pelo próprio usuário.'
                );

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            $sucesso = 'Senha definida com sucesso. Agora você já pode acessar o sistema.';
            $convite = null;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Definir senha — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/definir-senha.css">
</head>
<body>
<main class="activation-shell">
  <section class="activation-card">
    <div class="activation-brand">
      <span class="brand-mark">R$</span>
      <div>
        <strong>Folha de Cálculo</strong>
        <span>Acesso ao sistema</span>
      </div>
    </div>

    <?php if ($sucesso): ?>
      <div class="state success">
        <div class="state-icon">✓</div>
        <h1>Senha definida</h1>
        <p><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></p>
        <a class="btn" href="login.php">Ir para o login</a>
      </div>
    <?php elseif (!$convite): ?>
      <div class="state">
        <div class="state-icon">!</div>
        <h1>Link indisponível</h1>
        <p><?= htmlspecialchars($erro ?: 'O link de ativação é inválido, já foi utilizado ou expirou.', ENT_QUOTES, 'UTF-8') ?></p>
        <p class="help">Solicite ao administrador o reenvio do convite.</p>
        <a class="btn secondary" href="login.php">Voltar ao login</a>
      </div>
    <?php else: ?>
      <div class="activation-copy">
        <span class="eyebrow">Primeiro acesso</span>
        <h1>Crie sua senha</h1>
        <p>Olá, <strong><?= htmlspecialchars((string)$convite['nome'], ENT_QUOTES, 'UTF-8') ?></strong>. Defina abaixo a senha que você usará para entrar no sistema.</p>
        <div class="account-info">
          <span>Usuário</span>
          <strong><?= htmlspecialchars((string)$convite['login'], ENT_QUOTES, 'UTF-8') ?></strong>
          <span>E-mail</span>
          <strong><?= htmlspecialchars((string)$convite['email'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>

      <?php if ($erro): ?><div class="notice error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <form class="activation-form" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

        <label for="senha">Nova senha</label>
        <input id="senha" name="senha" type="password" minlength="8" required autocomplete="new-password">

        <label for="confirmacao">Confirmar senha</label>
        <input id="confirmacao" name="confirmacao" type="password" minlength="8" required autocomplete="new-password">

        <p class="password-hint">Use pelo menos 8 caracteres. Não compartilhe sua senha.</p>

        <button class="btn" type="submit">Definir senha e ativar acesso</button>
      </form>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
