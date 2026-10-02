<?php
declare(strict_types=1);
$navBase = '../';
$navPage = (string)($menuPage ?? 'gerador');
$usuario = is_array($usuario ?? null) ? $usuario : (is_array($u ?? null) ? $u : []);
require __DIR__ . '/../../includes/nav_global.php';
