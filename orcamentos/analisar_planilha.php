<?php
declare(strict_types=1);
require __DIR__.'/includes/lib_python.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_reply(['ok'=>false,'error'=>'Apenas POST é permitido.'],405);
    if (!isset($_FILES['source_file'])) json_reply(['ok'=>false,'error'=>'Arquivo não recebido.'],400);
    $f=$_FILES['source_file'];
    if (($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) json_reply(['ok'=>false,'error'=>'Upload inválido.'],400);
    $sheetIndex=filter_var($_POST['sheet_index']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
    if($sheetIndex===false) json_reply(['ok'=>false,'error'=>'Índice da aba não informado.'],400);
    $work=sys_get_temp_dir().DIRECTORY_SEPARATOR.'orc_analyze_'.uniqid('',true).'.xlsx';
    if(!copy((string)$f['tmp_name'],$work)) json_reply(['ok'=>false,'error'=>'Não foi possível preparar a cópia temporária.'],500);
    [$code,$out,$cmd]=run_python([__DIR__.'/python/analyze_sheet.py',$work,(string)$sheetIndex]);
    @unlink($work);
    $decoded=null;
    foreach(array_reverse(preg_split('/\r\n|\r|\n/',$out)?:[]) as $line){$x=json_decode(trim((string)$line),true);if(is_array($x)){$decoded=$x;break;}}
    if(is_array($decoded)&&!empty($decoded['ok'])) json_reply($decoded);
    json_reply(['ok'=>false,'error'=>'Não foi possível analisar a aba.','codigo_retorno'=>$code,'saida_python'=>$out?:'[vazio]','comando'=>$cmd],500);
} catch(Throwable $e){json_reply(['ok'=>false,'error'=>'Erro na análise da aba.','details'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()],500);}
