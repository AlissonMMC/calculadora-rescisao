<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
require __DIR__ . '/includes/convites.php';
require __DIR__ . '/includes/mailer.php';
$usuarioLogado = exigirAdminPagina();
$usuario = $usuarioLogado;
$navPage = 'usuarios';
$navBase = "";
$mensagem = '';
$erro = '';

function redirecionarComMensagem(string $tipo, string $texto): never {
    $param = $tipo === 'ok' ? 'ok' : 'erro';
    header('Location: usuarios.php?' . $param . '=' . rawurlencode($texto));
    exit;
}

if (!empty($_GET['ok'])) $mensagem = (string)$_GET['ok'];
if (!empty($_GET['erro'])) $erro = (string)$_GET['erro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validarCsrf();
        $acao = (string)($_POST['acao'] ?? '');
        $pdo = db();

        if ($acao === 'criar') {
            $nome = limparTexto($_POST['nome'] ?? '', 120);
            $login = mb_substr(strtolower(trim((string)($_POST['login'] ?? ''))), 0, 60);
            $email = mb_substr(strtolower(trim((string)($_POST['email'] ?? ''))), 0, 190);
            $perfisPermitidos = ['admin', 'financeiro', 'operacional', 'consulta'];
            $perfil = (string)($_POST['perfil'] ?? 'operacional');
            if (!in_array($perfil, $perfisPermitidos, true)) $perfil = 'operacional';

            if ($nome === '' || $login === '' || $email === '') {
                throw new RuntimeException('Preencha nome, usuário e e-mail.');
            }
            if (!preg_match('/^[a-z0-9._-]{3,60}$/', $login)) {
                throw new RuntimeException('O usuário deve ter de 3 a 60 caracteres usando letras, números, ponto, hífen ou sublinhado.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail válido.');
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE login = ?');
            $stmt->execute([$login]);
            if ((int)$stmt->fetchColumn() > 0) throw new RuntimeException('Esse usuário já existe.');

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) throw new RuntimeException('Esse e-mail já está cadastrado.');

            // A senha é definida pelo próprio usuário. O hash aleatório impede login
            // até que o convite seja consumido com sucesso.
            $senhaTemporaria = bin2hex(random_bytes(32));
            $senhaHash = password_hash($senhaTemporaria, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO usuarios (nome, login, email, senha_hash, senha_definida, perfil, ativo)
                     VALUES (?, ?, ?, ?, 0, ?, 1)'
                );
                $stmt->execute([$nome, $login, $email, $senhaHash, $perfil]);
                $novoId = (int)$pdo->lastInsertId();

                $token = criarConviteUsuario($pdo, $novoId, (int)$usuarioLogado['id']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            try {
                enviarEmailPrimeiroAcesso([
                    'id' => $novoId,
                    'nome' => $nome,
                    'login' => $login,
                    'email' => $email,
                    'perfil' => $perfil
                ], $token);

                registrarAuditoriaSistema('usuarios', 'criar_usuario', $novoId, 'Perfil: ' . $perfil . '; convite enviado por e-mail.');
                redirecionarComMensagem('ok', 'Usuário criado e convite enviado por e-mail.');
            } catch (Throwable $e) {
                registrarAuditoriaSistema('usuarios', 'criar_usuario', $novoId, 'Perfil: ' . $perfil . '; convite criado, mas o e-mail não pôde ser enviado.');
                error_log('Folha de Cálculo convite usuário: ' . $e->getMessage());
                redirecionarComMensagem('erro', 'Usuário criado, mas não foi possível enviar o e-mail. Verifique a configuração SMTP e use "Reenviar convite".');
            }
        }

        if ($acao === 'reenviar_convite') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) throw new RuntimeException('Usuário inválido.');

            $stmt = $pdo->prepare('SELECT id, nome, login, email, perfil, ativo, senha_definida FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $alvo = $stmt->fetch();

            if (!$alvo) throw new RuntimeException('Usuário não encontrado.');
            if (!(int)$alvo['ativo']) throw new RuntimeException('Ative o usuário antes de enviar um novo convite.');
            if ((int)$alvo['senha_definida'] === 1) throw new RuntimeException('Esse usuário já definiu a senha.');

            $token = criarConviteUsuario($pdo, $id, (int)$usuarioLogado['id']);
            try {
                enviarEmailPrimeiroAcesso($alvo, $token);
                registrarAuditoriaSistema('usuarios', 'reenviar_convite', $id, 'Novo convite enviado por e-mail.');
                redirecionarComMensagem('ok', 'Convite reenviado com sucesso.');
            } catch (Throwable $e) {
                error_log('Folha de Cálculo reenvio convite: ' . $e->getMessage());
                redirecionarComMensagem('erro', 'Não foi possível enviar o convite. Verifique a configuração SMTP.');
            }
        }

        if ($acao === 'alternar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id < 1) throw new RuntimeException('Usuário inválido.');
            if ($id === (int)$usuarioLogado['id']) throw new RuntimeException('Você não pode bloquear o próprio usuário.');
            $stmt = $pdo->prepare('SELECT id, ativo, perfil FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $alvo = $stmt->fetch();
            if (!$alvo) throw new RuntimeException('Usuário não encontrado.');
            if ($alvo['perfil'] === 'admin' && (int)$alvo['ativo'] === 1) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil='admin' AND ativo=1");
                if ((int)$stmt->fetchColumn() <= 1) throw new RuntimeException('É necessário manter pelo menos um administrador ativo.');
            }
            $stmt = $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');
            $stmt->execute([(int)$alvo['ativo'] ? 0 : 1, $id]);
            registrarAuditoriaSistema('usuarios', (int)$alvo['ativo'] ? 'bloquear_usuario' : 'ativar_usuario', $id);
            redirecionarComMensagem('ok', (int)$alvo['ativo'] ? 'Usuário bloqueado.' : 'Usuário ativado.');
        }

        if ($acao === 'senha') {
            $id = (int)($_POST['id'] ?? 0);
            $novaSenha = (string)($_POST['nova_senha'] ?? '');
            if ($id < 1 || mb_strlen($novaSenha) < 6) throw new RuntimeException('A nova senha deve ter pelo menos 8 caracteres.');
            $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $id]);
            registrarAuditoriaSistema('usuarios', 'redefinir_senha', $id);
            redirecionarComMensagem('ok', 'Senha redefinida com sucesso.');
        }

        throw new RuntimeException('Ação inválida.');
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$usuarios = [];
try {
    $usuarios = db()->query('SELECT id, nome, login, email, perfil, ativo, senha_definida, criado_em, ultimo_login FROM usuarios ORDER BY ativo DESC, nome ASC')->fetchAll();
} catch (Throwable $e) {
    $erro = 'Não foi possível carregar os usuários.';
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuários — Calculadora de Rescisão</title>
<link rel="stylesheet" href="assets/css/pages/usuarios.css">
</head>
<body>
<?php require __DIR__ . "/includes/nav_global.php"; ?>
<div class="wrap">
<?php if ($mensagem): ?><div class="notice ok"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="notice err"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <div class="grid">
    <section class="card">
      <h2>Novo usuário</h2><p class="sub">Cadastre o acesso e envie um convite para o próprio usuário criar a senha.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="criar">
        <div class="field"><label for="nome">Nome completo</label><input id="nome" name="nome" type="text" required></div>
        <div class="field"><label for="login">Usuário</label><input id="login" name="login" type="text" pattern="[a-z0-9._-]{3,60}" required></div>
        <div class="field"><label for="email">E-mail de acesso</label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" required></div>
        <div class="field"><label for="perfil">Perfil</label><select id="perfil" name="perfil"><option value="operacional">Operacional</option><option value="financeiro">Financeiro</option><option value="consulta">Somente consulta</option><option value="admin">Administrador</option></select></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Criar usuário</button></div>
      </form>
      <p class="footnote">O usuário receberá um link para definir a própria senha. O convite expira conforme a configuração do sistema e pode ser reenviado enquanto o acesso ainda não foi ativado.</p>
    </section>
    <section class="card">
      <h2>Usuários cadastrados</h2><p class="sub">Controle de acesso do sistema.</p>
      <div class="table-wrap"><table class="table"><thead><tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th>Criado em</th><th>Ações</th></tr></thead><tbody>
      <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><strong><?= htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8') ?></strong></td>
          <td><?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($u['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="role"><?= htmlspecialchars(match ($u['perfil']) { 'admin' => 'Administrador', 'financeiro' => 'Financeiro', 'consulta' => 'Somente consulta', default => 'Operacional' }, ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><span class="status <?= (int)$u['ativo'] ? ((int)$u['senha_definida'] ? 'active' : 'pending') : 'off' ?>"><?= (int)$u['ativo'] ? ((int)$u['senha_definida'] ? 'Ativo' : 'Aguardando ativação') : 'Bloqueado' ?></span></td>
          <td><?= !empty($u['ultimo_login']) ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?></td><td><?= !empty($u['criado_em']) ? date('d/m/Y H:i', strtotime($u['criado_em'])) : '—' ?></td>
          <td>
            <div class="row-actions">
              <?php if ((int)$u['id'] !== (int)$usuarioLogado['id']): ?>
              <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="alternar"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn" type="submit"><?= (int)$u['ativo'] ? 'Bloquear' : 'Ativar' ?></button></form>
              <?php endif; ?>
              <form method="post" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="senha"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input class="small-input" name="nova_senha" type="password" minlength="8" placeholder="Nova senha" required><button class="btn" type="submit">Redefinir</button></form>
              <?php if ((int)$u['senha_definida'] === 0 && !empty($u['email'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="reenviar_convite"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn" type="submit">Reenviar convite</button></form><?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </section>
  </div>
</div>
</body></html>
