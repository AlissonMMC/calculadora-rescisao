<?php
declare(strict_types=1);

if (!function_exists('enviarEmailPrimeiroAcesso')) {
    /**
     * Envia o convite de primeiro acesso por SMTP.
     *
     * Requer "composer install" para gerar vendor/autoload.php.
     */
    function enviarEmailPrimeiroAcesso(array $usuario, string $token): void {
        $autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        if (!is_file($autoload)) {
            throw new RuntimeException('As dependências de e-mail ainda não foram instaladas. Execute "composer install" na pasta do sistema.');
        }

        require_once $autoload;

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException('A biblioteca de e-mail não está disponível. Execute "composer install".');
        }

        $host = trim((string)envValue('MAIL_HOST', ''));
        $port = max(1, (int)envValue('MAIL_PORT', 587));
        $username = trim((string)envValue('MAIL_USERNAME', ''));
        $password = (string)envValue('MAIL_PASSWORD', '');
        $encryption = strtolower(trim((string)envValue('MAIL_ENCRYPTION', 'tls')));
        $fromAddress = trim((string)envValue('MAIL_FROM_ADDRESS', $username));
        $fromName = trim((string)envValue('MAIL_FROM_NAME', 'Folha de Cálculo'));
        $appUrl = rtrim(trim((string)envValue('APP_URL', '')), '/');

        if ($host === '' || $fromAddress === '' || $appUrl === '') {
            throw new RuntimeException('Configure MAIL_HOST, MAIL_FROM_ADDRESS e APP_URL no arquivo .env.');
        }

        $email = trim((string)($usuario['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('O usuário não possui um e-mail válido para receber o convite.');
        }

        $nome = trim((string)($usuario['nome'] ?? 'Usuário')) ?: 'Usuário';
        $link = $appUrl . '/definir-senha.php?token=' . rawurlencode($token);
        $expiracao = max(1, (int)envValue('INVITE_EXPIRATION_HOURS', 24));

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = $username !== '';

        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }

        if ($encryption === 'ssl' || $encryption === 'smtps') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls' || $encryption === 'starttls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($email, $nome);
        $mail->isHTML(true);
        $mail->Subject = 'Seu acesso ao Folha de Cálculo';

        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $safeLogin = htmlspecialchars((string)($usuario['login'] ?? ''), ENT_QUOTES, 'UTF-8');

        $mail->Body = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#171717">
  <div style="max-width:620px;margin:32px auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden">
    <div style="padding:24px 28px;background:#171717;color:#fff">
      <div style="font-size:14px;letter-spacing:.08em;text-transform:uppercase;color:#f59e0b;font-weight:700">Folha de Cálculo</div>
      <h1 style="margin:8px 0 0;font-size:24px">Ative seu acesso</h1>
    </div>
    <div style="padding:28px">
      <p style="font-size:16px">Olá, <strong>{$safeNome}</strong>.</p>
      <p>Seu usuário foi criado no sistema. Para concluir o cadastro, defina sua senha pelo botão abaixo.</p>
      <p style="margin:24px 0">
        <a href="{$safeLink}" style="display:inline-block;padding:13px 20px;background:#f59e0b;color:#171717;text-decoration:none;border-radius:10px;font-weight:700">Definir minha senha</a>
      </p>
      <p style="font-size:13px;color:#6b7280">Usuário: {$safeLogin}</p>
      <p style="font-size:13px;color:#6b7280">Este link expira em {$expiracao} hora(s) e pode ser usado uma única vez.</p>
      <p style="font-size:13px;color:#6b7280;word-break:break-all">Se o botão não abrir, copie este endereço:<br>{$safeLink}</p>
    </div>
  </div>
</body>
</html>
HTML;

        $mail->AltBody = "Olá, {$nome}. Seu usuário foi criado no Folha de Cálculo. Acesse {$link} para definir sua senha. O link expira em {$expiracao} hora(s) e pode ser usado uma única vez.";
        $mail->send();
    }
}
