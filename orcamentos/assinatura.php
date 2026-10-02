<?php
declare(strict_types=1);
require __DIR__ . '/../api/config.php';
require __DIR__ . '/includes/storage.php';
if(!usuarioAtual()){http_response_code(401);exit;}
$id=(string)($_GET['id']??'');if(!preg_match('/^\d+$/',$id)){http_response_code(400);exit;}
$dir=orcamentosStorage().'/prestadores';$found=null;foreach(['png','jpg','jpeg'] as $e){$p=$dir.'/'.$id.'.'.$e;if(is_file($p)){$found=$p;break;}}
if(!$found){http_response_code(404);exit;}
$mime=str_ends_with(strtolower($found),'.png')?'image/png':'image/jpeg';header('Content-Type: '.$mime);header('Cache-Control: private, max-age=3600');readfile($found);
