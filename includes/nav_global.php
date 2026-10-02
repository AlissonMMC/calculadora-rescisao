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

$linksPrimary = array_values(array_filter($links, static function (array $link): bool {
    return in_array($link['key'], ['dashboard', 'rescisao', 'historico_rescisoes'], true);
}));

$linksOrcamentos = array_values(array_filter($links, static function (array $link): bool {
    return in_array($link['key'], ['gerador', 'historico_orcamentos'], true);
}));

$linksAdmin = array_values(array_filter($links, static function (array $link): bool {
    return in_array($link['key'], ['prestadores', 'monitoramento', 'usuarios'], true);
}));

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

function renderNavLinks(array $items, string $active): void {
    foreach ($items as $item) {
        $key = (string)($item['key'] ?? '');
        $label = (string)($item['label'] ?? '');
        $href = (string)($item['href'] ?? '#');
        $icon = (string)($item['icon'] ?? '');
        $isActive = $key === $active;
        ?>
        <a class="global-nav-link<?= $isActive ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
            <?= navIcon($icon) ?>
            <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php
    }
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

<div class="global-mobilebar">
    <button class="global-sidebar-toggle global-mobile-toggle" id="globalSidebarToggleMobile" type="button" aria-label="Abrir menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <a class="global-mobile-brand" href="<?= htmlspecialchars($navBase.'dashboard.php', ENT_QUOTES, 'UTF-8') ?>"><span class="global-brand-mark">R$</span><span>Folha de Cálculo</span></a>
    <span class="global-mobile-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
</div>
<div class="global-sidebar-backdrop" id="globalSidebarBackdrop"></div>

<header class="global-topbar" aria-label="Barra superior">
    <div class="global-topbar-left">
        <span class="global-topbar-context">Painel de gestão</span>
        <span class="global-topbar-divider"></span>
        <span class="global-topbar-page"><?= htmlspecialchars(ucfirst($navPage), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="global-profile-menu">
        <button class="global-topbar-user" id="globalProfileToggle" type="button" aria-expanded="false" aria-haspopup="true">
            <span class="global-topbar-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="global-topbar-user-copy">
                <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars($profileLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
            <span class="global-topbar-chevron">⌄</span>
        </button>
        <div class="global-profile-dropdown" id="globalProfileDropdown" hidden>
            <div class="global-profile-dropdown-head">
                <span class="global-topbar-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                <div><strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars($profileLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
            <a class="global-profile-item" href="<?= htmlspecialchars($navBase.'perfil.php', ENT_QUOTES, 'UTF-8') ?>">
                <span class="profile-item-icon">⚙</span>
                <span><strong>Gerenciar perfil</strong><small>E-mail e senha de acesso</small></span>
            </a>
            <div class="global-profile-divider"></div>
            <form method="post" action="<?= htmlspecialchars($navBase.'logout.php', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <button class="global-profile-item logout" type="submit">
                    <span class="profile-item-icon">↪</span>
                    <span><strong>Sair</strong><small>Encerrar sessão</small></span>
                </button>
            </form>
        </div>
    </div>
</header>

<aside class="global-sidebar" id="globalSidebar" aria-label="Navegação principal">
    <div class="global-sidebar-top">
        <div class="global-sidebar-brand-row">
            <a class="global-brand" href="<?= htmlspecialchars($navBase.'dashboard.php', ENT_QUOTES, 'UTF-8') ?>" title="Folha de Cálculo">
                <span class="global-brand-mark">R$</span>
                <span class="global-brand-copy"><strong>Folha de Cálculo</strong><span>Gestão imobiliária</span></span>
            </a>
            <button class="global-sidebar-toggle global-collapse-toggle" id="globalSidebarToggle" type="button" aria-label="Recolher menu" aria-expanded="true" title="Recolher menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 6-6 6 6 6"/></svg>
            </button>
        </div>

        <div class="global-sidebar-section">
            <span class="global-sidebar-label">Principal</span>
            <nav class="global-nav-main" aria-label="Principal"><?php renderNavLinks($linksPrimary, $navPage); ?></nav>
        </div>

        <div class="global-sidebar-section">
            <span class="global-sidebar-label">Orçamentos</span>
            <nav class="global-nav-main" aria-label="Orçamentos"><?php renderNavLinks($linksOrcamentos, $navPage); ?></nav>
        </div>

        <?php if ($isAdmin): ?>
        <div class="global-sidebar-section">
            <span class="global-sidebar-label">Administração</span>
            <nav class="global-nav-main" aria-label="Administração"><?php renderNavLinks($linksAdmin, $navPage); ?></nav>
        </div>
        <?php endif; ?>
    </div>

    <div class="global-sidebar-bottom">
        <button class="global-theme" id="globalThemeToggle" type="button" aria-label="Alternar tema" title="Alternar tema">
            <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/></svg>
            <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.7 6.7 0 0 0 9.8 9.8Z"/></svg>
            <span>Tema</span>
        </button>

        <div class="global-user" title="<?= htmlspecialchars($userName.' · '.$profileLabel, ENT_QUOTES, 'UTF-8') ?>">
            <span class="global-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="global-user-copy"><strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars($profileLabel, ENT_QUOTES, 'UTF-8') ?></span></span>
        </div>

        <form method="post" action="<?= htmlspecialchars($navBase.'logout.php', ENT_QUOTES, 'UTF-8') ?>" class="global-logout-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrf ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <button class="global-logout" type="submit" title="Sair"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-7"/></svg><span>Sair</span></button>
        </form>
    </div>
</aside>

<script src="<?= htmlspecialchars($appPath . '/assets/js/core/nav-global.js', ENT_QUOTES, 'UTF-8') ?>"></script>
