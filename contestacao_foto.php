<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario=exigirLoginPagina();
$id=max(0,(int)($_GET['id']??0));
$fotoId=max(0,(int)($_GET['foto']??0));
$stmt=db()->prepare('SELECT c.usuario_id,f.mime_type,f.conteudo FROM contestacoes_vistoria_fotos f JOIN contestacoes_vistoria c ON c.id=f.contestacao_id WHERE c.id=? AND f.id=? LIMIT 1');
$stmt->execute([$id,$fotoId]);
$row=$stmt->fetch();
if(!$row || ((int)$row['usuario_id']!==(int)$usuario['id'] && ($usuario['perfil']??'')!=='admin')){http_response_code(404);exit;}
header('Content-Type: '.$row['mime_type']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
echo $row['conteudo'];
