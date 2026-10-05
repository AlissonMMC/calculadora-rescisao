<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
if (usuarioAtual()) { header('Location: dashboard.php'); exit; }
$csrf = csrfToken();
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($_SESSION['login_bloqueado_ate']) && time() < (int)$_SESSION['login_bloqueado_ate']) {
            $restante = max(1, (int)ceil(((int)$_SESSION['login_bloqueado_ate'] - time()) / 60));
            throw new RuntimeException('Muitas tentativas inválidas. Tente novamente em ' . $restante . ' minuto(s).');
        }
        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Token de segurança inválido.');
        }
        $login = mb_substr(trim((string)($_POST['login'] ?? '')), 0, 60);
        $senha = (string)($_POST['senha'] ?? '');
        $stmt = db()->prepare('SELECT id, nome, login, email, senha_hash, senha_definida, perfil, ativo FROM usuarios WHERE login = ? OR email = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $usuario = $stmt->fetch();
        if (!$usuario || !(int)$usuario['ativo'] || !(int)($usuario['senha_definida'] ?? 1) || !password_verify($senha, $usuario['senha_hash'])) {
            registrarTentativaLogin($login, false, $usuario ? (int)$usuario['id'] : null);
            $_SESSION['login_falhas'] = ((int)($_SESSION['login_falhas'] ?? 0)) + 1;
            if ($_SESSION['login_falhas'] >= 5) {
                $_SESSION['login_bloqueado_ate'] = time() + 300;
                $_SESSION['login_falhas'] = 0;
                throw new RuntimeException('Muitas tentativas inválidas. O login foi temporariamente bloqueado por 5 minutos.');
            }
            if ($usuario && (int)$usuario['ativo'] && !(int)($usuario['senha_definida'] ?? 1)) {
                throw new RuntimeException('Seu acesso ainda não foi ativado. Verifique o e-mail enviado pelo administrador para definir sua senha.');
            }
            throw new RuntimeException('Login ou senha inválidos.');
        }
        registrarTentativaLogin($login, true, (int)$usuario['id']);
        unset($_SESSION['login_falhas'], $_SESSION['login_bloqueado_ate']);
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['ultimo_acesso'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        db()->prepare('UPDATE usuarios SET ultimo_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int)$usuario['id']]);
        registrarAuditoriaSistema('autenticacao', 'login', (int)$usuario['id'], 'Login realizado com sucesso.');
        header('Location: dashboard.php');
        exit;
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Calculadora de Rescisão</title>
<link rel="stylesheet" href="assets/css/pages/login.css">
</head>
<body>
<div class="login-shell">
  <section class="login-brand">
    <div class="brand-mark">R$</div>
    <span class="eyebrow">Sistema interno da imobiliária</span>
    <h1>Calculadora de<br>rescisão.</h1>
    <p>Faça login para acessar os cálculos, a conferência final e o histórico centralizado.</p>
    <div class="login-points"><span>✓ Histórico compartilhado</span><span>✓ Usuários e permissões</span><span>✓ Registros com responsável</span></div>
  </section>
  <section class="login-form-wrap">
    <form class="login-form" method="post" autocomplete="on">
      <h2>Entrar</h2>
      <p>Use seu usuário ou e-mail de acesso da imobiliária.</p>
      <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <div class="field"><label for="login">Usuário ou e-mail</label><input id="login" name="login" type="text" autocomplete="username" required autofocus></div>
      <div class="field"><label for="senha">Senha</label><input id="senha" name="senha" type="password" autocomplete="current-password" required></div>
      <button class="btn" type="submit">Entrar →</button>
      <div class="hint">O administrador pode criar, bloquear e redefinir usuários dentro do sistema.</div>
    </form>
  </section>
</div>
</body>
</html>
