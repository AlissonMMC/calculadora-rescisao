<?php
declare(strict_types=1);
require __DIR__.'/../api/config.php';
$u=usuarioAtual();if(!$u){header('Location: ../login.php');exit;}
function run_cmd(string $cmd):string{ $out=[];$code=1;exec($cmd.' 2>&1',$out,$code);return trim(implode(PHP_EOL,$out)); }
$python='C:\Users\imobj\AppData\Local\Programs\Python\Python314\python.exe';
$lo='C:\Program Files\LibreOffice\program\soffice.com';
?><?php $menuPage='monitoramento'; ?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Diagnóstico do gerador</title><body><div style="max-width:980px;margin:0 auto 50px;padding:0 16px"><?php require __DIR__ . '/includes/nav.php'; ?><div style="font-family:Inter,Arial,sans-serif;margin-top:22px;background:#fff;border:1px solid #e5e5e5;border-radius:18px;padding:22px;box-shadow:0 15px 40px rgba(0,0,0,.06)"><h1>Diagnóstico do Gerador de Orçamentos</h1><p><b>PHP:</b> <?=htmlspecialchars(PHP_VERSION)?></p><p><b>ZIP:</b> <?=class_exists('ZipArchive')?'OK':'NÃO ENCONTRADO'?></p><p><b>Python:</b> <?=is_file($python)?htmlspecialchars(run_cmd('"'.$python.'" --version')):'NÃO ENCONTRADO'?></p><p><b>openpyxl:</b> <?=is_file($python)?htmlspecialchars(run_cmd('"'.$python.'" -c "import openpyxl; print(openpyxl.__version__)"')):'—'?></p><p><b>LibreOffice:</b> <?=is_file($lo)?htmlspecialchars(run_cmd('"'.$lo.'" --version')):'NÃO ENCONTRADO'?></p></div></div></body></html>
