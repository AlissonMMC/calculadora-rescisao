<?php
declare(strict_types=1);
require __DIR__.'/includes/lib_python.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_reply(['ok'=>false,'error'=>'Apenas POST é permitido.'],405);
    }
    if (!isset($_FILES['source_file'])) {
        json_reply(['ok'=>false,'error'=>'Nenhum arquivo foi enviado.'],400);
    }
    $f=$_FILES['source_file'];
    if (($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) {
        json_reply(['ok'=>false,'error'=>'Upload inválido.','codigo'=>$f['error']??null],400);
    }
    if (strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION))!=='xlsx') {
        json_reply(['ok'=>false,'error'=>'O arquivo precisa ser .xlsx.'],400);
    }

    $src=(string)$f['tmp_name'];
    $work=sys_get_temp_dir().DIRECTORY_SEPARATOR.'orc_analyze_all_'.uniqid('',true).'.xlsx';
    try {
        if (!is_uploaded_file($src) && !is_file($src)) {
            json_reply(['ok'=>false,'error'=>'O PHP recebeu o arquivo, mas o arquivo temporário não está acessível.','tmp'=>$src],500);
        }
        if (!copy($src,$work)) {
            json_reply(['ok'=>false,'error'=>'Não foi possível preparar a cópia temporária.','tmp'=>$work],500);
        }

        // Uma única chamada ao Python: lê e analisa todas as abas de uma vez.
        [$code,$out,$cmd]=run_python([__DIR__.'/python/analyze_all.py',$work]);
    } finally {
        @unlink($work);
    }

    $decoded=null;
    foreach(array_reverse(preg_split('/\r\n|\r|\n/',$out)?:[]) as $line){
        $x=json_decode(trim((string)$line),true);
        if(is_array($x)){$decoded=$x;break;}
    }

    if(is_array($decoded)&&!empty($decoded['ok'])) {
        json_reply($decoded);
    }

    json_reply([
        'ok'=>false,
        'error'=>'Não foi possível analisar a planilha.',
        'codigo_retorno'=>$code,
        'saida_python'=>$out?:'[vazio]',
        'comando'=>$cmd,
        'arquivo_nome'=>(string)$f['name'],
        'arquivo_tamanho'=>(int)$f['size']
    ],500);
} catch(Throwable $e) {
    json_reply([
        'ok'=>false,
        'error'=>'Erro no leitor/analisador da planilha.',
        'details'=>$e->getMessage(),
        'file'=>$e->getFile(),
        'line'=>$e->getLine()
    ],500);
}
