<?php
declare(strict_types=1);

$navBase = (string)($navBase ?? '');
$navPage = (string)($navPage ?? 'dashboard');
$navUser = is_array($usuario ?? null) ? $usuario : (is_array($u ?? null) ? $u : (function_exists('usuarioAtual') ? (usuarioAtual() ?: []) : []));
$perfil = strtolower((string)($navUser['perfil'] ?? $navUser['role'] ?? $navUser['nivel'] ?? ''));
$isAdmin = !empty($navUser['is_admin']) || !empty($navUser['admin']) || $perfil === 'admin' || strtolower((string)($navUser['login'] ?? '')) === 'admin';
$userName = trim((string)($navUser['nome'] ?? $navUser['login'] ?? 'Usuário')) ?: 'Usuário';
$initial = strtoupper(function_exists('mb_substr') ? mb_substr($userName, 0, 1, 'UTF-8') : substr($userName, 0, 1));
$profileLabel = $isAdmin ? 'Administrador' : 'Usuário';

$links = [
    ['key'=>'dashboard','label'=>'Início','href'=>$navBase.'dashboard.php','icon'=>'home'],
    ['key'=>'rescisao','label'=>'Nova rescisão','href'=>$navBase.'index.php?nova=1','icon'=>'calc'],
    ['key'=>'gerador','label'=>'Gerador de orçamentos','href'=>$navBase.'orcamentos/','icon'=>'file'],
    ['key'=>'historico_rescisoes','label'=>'Rescisões','href'=>$navBase.'historico.php','icon'=>'clock'],
    ['key'=>'historico_orcamentos','label'=>'Orçamentos','href'=>$navBase.'orcamentos/historico_orcamentos.php','icon'=>'archive'],
];
if ($isAdmin) {
    $links[] = ['key'=>'prestadores','label'=>'Prestadores','href'=>$navBase.'orcamentos/prestadores.php','icon'=>'users'];
    $links[] = ['key'=>'monitoramento','label'=>'Monitoramento','href'=>$navBase.'orcamentos/monitoramento.php','icon'=>'pulse'];
    $links[] = ['key'=>'usuarios','label'=>'Usuários','href'=>$navBase.'usuarios.php','icon'=>'shield'];
}
function navIcon(string $name): string {
    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
        'calc' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2M8 19h2M14 19h2"/>',
        'file' => '<path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'archive' => '<path d="M4 7h16l-1 13H5L4 7Z"/><path d="M6 4h12l1 3H5l1-3ZM9 11h6"/>',
        'users' => '<path d="M16 21v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 19.5V21"/><circle cx="10" cy="8" r="3.5"/><path d="M16 5a3.5 3.5 0 0 1 0 6.5M17 15.5h1.5A3.5 3.5 0 0 1 22 19v2"/>',
        'pulse' => '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
        'shield' => '<path d="M12 3 20 6v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$name] ?? '').'</svg>';
}
?>
<?php
$appPath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (basename($appPath) === 'orcamentos') {
    $appPath = dirname($appPath);
}
$appPath = $appPath === '/' ? '' : $appPath;
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appPath . '/assets/css/core/nav-global.css', ENT_QUOTES, 'UTF-8') ?>">
<header class="global-nav-shell">
<nav class="global-nav" aria-label="Navegação principal">
    <a class="global-brand" href="<?= htmlspecialchars($navBase.'dashboard.php',ENT_QUOTES,'UTF-8') ?>" title="Folha de Cálculo">
        <span class="global-brand-mark">R$</span>
        <span class="global-brand-copy"><strong>Folha de Cálculo</strong><span>Gestão imobiliária</span></span>
    </a>
    <div class="global-nav-main">
        <?php foreach($links as $link): $active=$navPage===$link['key']; ?>
            <a class="global-nav-link <?= $active?'active':'' ?>" href="<?= htmlspecialchars($link['href'],ENT_QUOTES,'UTF-8') ?>" <?= $active?'aria-current="page"':'' ?>><?= navIcon($link['icon']) ?><span><?= htmlspecialchars($link['label'],ENT_QUOTES,'UTF-8') ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="global-nav-actions">
        <div class="global-user" title="<?= htmlspecialchars($userName.' · '.$profileLabel,ENT_QUOTES,'UTF-8') ?>"><span class="global-avatar"><?= htmlspecialchars($initial,ENT_QUOTES,'UTF-8') ?></span><span class="global-user-copy"><strong><?= htmlspecialchars($userName,ENT_QUOTES,'UTF-8') ?></strong><span><?= htmlspecialchars($profileLabel,ENT_QUOTES,'UTF-8') ?></span></span></div>
        <button class="global-theme" id="globalThemeToggle" type="button" aria-label="Alternar tema" title="Alternar tema"><svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/></svg><svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.7 6.7 0 0 0 9.8 9.8Z"/></svg><span>Tema</span></button>
        <form method="post" action="<?= htmlspecialchars($navBase.'logout.php',ENT_QUOTES,'UTF-8') ?>" style="margin:0"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf ?? ''),ENT_QUOTES,'UTF-8') ?>"><button class="global-logout" type="submit" title="Sair"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-7"/></svg><span>Sair</span></button></form>
    </div>
</nav>
<script src="<?= htmlspecialchars($appPath . '/assets/js/core/nav-global.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</header>
