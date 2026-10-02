<?php
declare(strict_types=1);

require __DIR__ . '/api/config.php';

$usuario = exigirLoginPagina();
$navPage = 'perfil';
$navBase = '';
$csrf = csrfToken();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validarCsrf();
        $acao = (string)($_POST['acao'] ?? '');
        $senhaAtual = (string)($_POST['senha_atual'] ?? '');

        if ($senhaAtual === '') {
            throw new RuntimeException('Informe sua senha atual para confirmar a alteração.');
        }

        $stmt = db()->prepare('SELECT id, senha_hash FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$usuario['id']]);
        $registro = $stmt->fetch();

        if (!$registro || !password_verify($senhaAtual, (string)$registro['senha_hash'])) {
            throw new RuntimeException('A senha atual está incorreta.');
        }

        if ($acao === 'email') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail válido.');
            }

            if ($email !== '') {
                $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ? LIMIT 1');
                $stmt->execute([$email, (int)$usuario['id']]);
                if ($stmt->fetch()) {
                    throw new RuntimeException('Este e-mail já está vinculado a outro usuário.');
                }
            }

            $stmt = db()->prepare('UPDATE usuarios SET email = ? WHERE id = ?');
            $stmt->execute([$email !== '' ? $email : null, (int)$usuario['id']]);
            $mensagem = 'E-mail de acesso atualizado com sucesso.';
        } elseif ($acao === 'senha') {
            $novaSenha = (string)($_POST['nova_senha'] ?? '');
            $confirmacao = (string)($_POST['confirmar_senha'] ?? '');

            if (mb_strlen($novaSenha) < 8) {
                throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');
            }

            if ($novaSenha !== $confirmacao) {
                throw new RuntimeException('A confirmação da nova senha não confere.');
            }

            if (password_verify($novaSenha, (string)$registro['senha_hash'])) {
                throw new RuntimeException('A nova senha precisa ser diferente da senha atual.');
            }

            $stmt = db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($novaSenha, PASSWORD_DEFAULT), (int)$usuario['id']]);

            // Invalida o identificador da sessão após uma troca de senha.
            session_regenerate_id(true);
            $mensagem = 'Senha alterada com sucesso.';
        } else {
            throw new RuntimeException('Ação inválida.');
        }

        $usuario = exigirLoginPagina();
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

try {
    $stmt = db()->prepare('SELECT id, nome, login, email, perfil FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$usuario['id']]);
    $usuario = $stmt->fetch() ?: $usuario;
} catch (Throwable $e) {
    // Compatibilidade: se a migração de e-mail ainda não foi executada,
    // a página continua acessível e orienta o usuário no campo.
    $usuario['email'] = $usuario['email'] ?? '';
}

$perfilLabel = ($usuario['perfil'] ?? 'usuario') === 'admin' ? 'Administrador' : 'Usuário';
$initial = strtoupper(function_exists('mb_substr') ? mb_substr((string)$usuario['nome'], 0, 1, 'UTF-8') : substr((string)$usuario['nome'], 0, 1));
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gerenciar perfil — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/perfil.css">
</head>
<body>
<?php require __DIR__ . '/includes/nav_global.php'; ?>

<main class="profile-page">
    <header class="profile-header">
        <div>
            <span class="profile-eyebrow">Conta e segurança</span>
            <h1>Gerenciar perfil</h1>
            <p>Atualize seu e-mail de acesso e mantenha sua senha sempre protegida.</p>
        </div>
        <div class="profile-identity">
            <span class="profile-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
            <div>
                <strong><?= htmlspecialchars((string)$usuario['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars($perfilLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </header>

    <?php if ($mensagem): ?><div class="profile-notice success"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="profile-notice error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="profile-grid">
        <article class="profile-card">
            <div class="profile-card-icon">@</div>
            <div class="profile-card-heading">
                <h2>E-mail de acesso</h2>
                <p>Use um endereço de e-mail válido para identificar sua conta.</p>
            </div>

            <form method="post" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="acao" value="email">

                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" autocomplete="email" value="<?= htmlspecialchars((string)($usuario['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="seuemail@empresa.com.br">

                <label for="senha_email">Senha atual</label>
                <input id="senha_email" name="senha_atual" type="password" autocomplete="current-password" required placeholder="Confirme sua senha">

                <button class="profile-btn primary" type="submit">Salvar e-mail</button>
            </form>
        </article>

        <article class="profile-card">
            <div class="profile-card-icon">•••</div>
            <div class="profile-card-heading">
                <h2>Alterar senha</h2>
                <p>Escolha uma senha com pelo menos 8 caracteres.</p>
            </div>

            <form method="post" class="profile-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="acao" value="senha">

                <label for="senha_atual">Senha atual</label>
                <input id="senha_atual" name="senha_atual" type="password" autocomplete="current-password" required>

                <label for="nova_senha">Nova senha</label>
                <input id="nova_senha" name="nova_senha" type="password" minlength="8" autocomplete="new-password" required>

                <label for="confirmar_senha">Confirmar nova senha</label>
                <input id="confirmar_senha" name="confirmar_senha" type="password" minlength="8" autocomplete="new-password" required>

                <button class="profile-btn primary" type="submit">Alterar senha</button>
            </form>
        </article>
    </section>

    <section class="profile-account">
        <div>
            <span class="profile-eyebrow">Sessão</span>
            <h2>Encerrar acesso</h2>
            <p>Ao sair, sua sessão atual será encerrada neste dispositivo.</p>
        </div>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button class="profile-btn danger" type="submit">Sair da conta</button>
        </form>
    </section>

    <p class="profile-back"><a href="dashboard.php">← Voltar ao painel</a></p>
</main>
</body>
</html>
