<?php
declare(strict_types=1);
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
if(empty($_SESSION['usuario_id'])){http_response_code(401);exit('Sessão expirada.');}
$job=(string)($_GET['job']??'');
if(!preg_match('/^[a-f0-9]{24}$/',$job)){http_response_code(400);exit('Identificador inválido.');}
require __DIR__.'/includes/storage.php';
$base=realpath(orcamentosStorage());
if($base===false){http_response_code(404);exit('Área de armazenamento não encontrada.');}
$jobDir=realpath($base.'/'.$job);
if($jobDir===false||dirname($jobDir)!==$base){http_response_code(404);exit('Processamento não encontrado.');}
$zip=$jobDir.'/Folha_de_Calculo_Orcamentos_'.$job.'.zip';
if(!is_file($zip)){http_response_code(404);exit('Arquivo para download não encontrado.');}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="Folha_de_Calculo_Orcamentos.zip"');
header('Content-Length: '.(string)filesize($zip));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($zip);exit;
