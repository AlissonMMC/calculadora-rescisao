<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$usuario = exigirLoginApi();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function contestacaoAcesso(PDO $pdo, int $id, array $usuario): array {
    $stmt = $pdo->prepare('SELECT * FROM contestacoes_vistoria WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) responder(['ok'=>false,'error'=>'Contestação não encontrada.'], 404);
    if ((int)$item['usuario_id'] !== (int)$usuario['id'] && ($usuario['perfil'] ?? '') !== 'admin') {
        responder(['ok'=>false,'error'=>'Você não tem permissão para acessar esta contestação.'], 403);
    }
    return $item;
}

if ($method === 'GET') {
    $id = max(0, (int)($_GET['id'] ?? 0));
    if ($id > 0) {
        $item = contestacaoAcesso($pdo, $id, $usuario);
        $fotos = $pdo->prepare('SELECT id, comodo_indice, lado, nome_arquivo, mime_type FROM contestacoes_vistoria_fotos WHERE contestacao_id = ? ORDER BY id');
        $fotos->execute([$id]);
        $comodos = json_decode((string)$item['comodos_json'], true);
        if (!is_array($comodos)) $comodos = [];
        foreach ($fotos->fetchAll() as $foto) {
            $indice = (int)$foto['comodo_indice'];
            if (!isset($comodos[$indice]) || !is_array($comodos[$indice])) continue;
            $lado = (string)$foto['lado'];
            $comodos[$indice][$lado] = is_array($comodos[$indice][$lado] ?? null) ? $comodos[$indice][$lado] : [];
            $comodos[$indice][$lado][] = ['id'=>(int)$foto['id'],'name'=>$foto['nome_arquivo'],'url'=>'contestacao_foto.php?id='.$id.'&foto='.(int)$foto['id']];
        }
        responder(['ok'=>true,'item'=>[
            'id'=>(int)$item['id'],'nome_inquilino'=>$item['nome_inquilino'],'cpf_inquilino'=>$item['cpf_inquilino'],
            'endereco_locacao'=>$item['endereco_locacao'],'data_contestacao'=>$item['data_contestacao'],
            'numero_contrato'=>$item['numero_contrato'],'fianca'=>$item['fianca'],
            'comodos'=>$comodos
        ]]);
    }
    $stmt = $pdo->prepare('SELECT c.id,c.nome_inquilino,c.endereco_locacao,c.data_contestacao,c.numero_contrato,c.atualizado_em,u.nome AS responsavel,
        (SELECT COUNT(*) FROM contestacoes_vistoria_fotos f WHERE f.contestacao_id=c.id) AS total_fotos
        FROM contestacoes_vistoria c LEFT JOIN usuarios u ON u.id=c.usuario_id
        WHERE c.usuario_id = ? OR (? = ?) ORDER BY c.atualizado_em DESC');
    $stmt->execute([(int)$usuario['id'], (string)($usuario['perfil'] ?? ''), 'admin']);
    responder(['ok'=>true,'items'=>$stmt->fetchAll()]);
}

if ($method === 'DELETE') {
    validarCsrf();
    $id = max(0, (int)($_GET['id'] ?? 0));
    if ($id < 1) responder(['ok'=>false,'error'=>'Identificador inválido.'], 422);
    contestacaoAcesso($pdo, $id, $usuario);
    $stmt = $pdo->prepare('DELETE FROM contestacoes_vistoria WHERE id = ?');
    $stmt->execute([$id]);
    registrarAuditoriaSistema('contestacoes_vistoria','exclusao',$id,'Contestação de vistoria excluída.',(int)$usuario['id']);
    responder(['ok'=>true]);
}

if ($method !== 'POST') responder(['ok'=>false,'error'=>'Método não permitido.'],405);
validarCsrf();
$entrada = entradaJson();
$id = max(0,(int)($entrada['id'] ?? 0));
$nome = limparTexto($entrada['nome_inquilino'] ?? '',180);
$cpf = limparTexto($entrada['cpf_inquilino'] ?? '',14);
$endereco = limparTexto($entrada['endereco_locacao'] ?? '',255);
$data = limparTexto($entrada['data_contestacao'] ?? '',10);
$contrato = limparTexto($entrada['numero_contrato'] ?? '',80);
$fianca = limparTexto($entrada['fianca'] ?? '',180);
$comodos = is_array($entrada['comodos'] ?? null) ? array_values($entrada['comodos']) : [];
if ($nome==='' || $endereco==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$data) || !$comodos) {
    responder(['ok'=>false,'error'=>'Preencha inquilino, endereço, data e pelo menos um cômodo.'],422);
}
if (count($comodos)>40) responder(['ok'=>false,'error'=>'O limite é de 40 cômodos por contestação.'],422);

$comodosPersistidos = array_map(static function ($comodo) { return ['nome'=>limparTexto($comodo['nome'] ?? '',100),'descricao'=>limparTexto($comodo['descricao'] ?? '',5000)]; }, $comodos);
$pdo->beginTransaction();
try {
    if ($id > 0) {
        contestacaoAcesso($pdo,$id,$usuario);
        $stmt=$pdo->prepare('UPDATE contestacoes_vistoria SET nome_inquilino=?,cpf_inquilino=?,endereco_locacao=?,data_contestacao=?,numero_contrato=?,fianca=?,comodos_json=? WHERE id=?');
        $stmt->execute([$nome,$cpf,$endereco,$data,$contrato,$fianca,json_encode($comodosPersistidos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$id]);
        $pdo->prepare('DELETE FROM contestacoes_vistoria_fotos WHERE contestacao_id=?')->execute([$id]);
        $acao='edicao';
    } else {
        $stmt=$pdo->prepare('INSERT INTO contestacoes_vistoria (usuario_id,nome_inquilino,cpf_inquilino,endereco_locacao,data_contestacao,numero_contrato,fianca,comodos_json) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([(int)$usuario['id'],$nome,$cpf,$endereco,$data,$contrato,$fianca,json_encode($comodos,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $id=(int)$pdo->lastInsertId();
        $acao='criacao';
    }

    $insertFoto=$pdo->prepare('INSERT INTO contestacoes_vistoria_fotos (contestacao_id,comodo_indice,lado,nome_arquivo,mime_type,conteudo) VALUES (?,?,?,?,?,?)');
    $totalFotos=0;
    foreach ($comodos as $indice=>$comodo) {
        if (!is_array($comodo)) continue;
        foreach (['entrada','saida'] as $lado) {
            $lista=is_array($comodo[$lado] ?? null)?$comodo[$lado]:[];
            if (count($lista)>10) throw new RuntimeException('Cada lado de um cômodo permite até 10 fotos.');
            foreach ($lista as $foto) {
                if (!is_array($foto) || empty($foto['data'])) continue;
                if (!preg_match('#^data:image/(jpeg|png|webp);base64,#',$foto['data'],$m)) throw new RuntimeException('Uma das imagens possui formato inválido.');
                $bin=base64_decode(substr($foto['data'],strpos($foto['data'],',')+1),true);
                if ($bin===false || strlen($bin)>3*1024*1024) throw new RuntimeException('Cada imagem deve ter no máximo 3 MB após a otimização.');
                $mime='image/'.$m[1];
                $insertFoto->execute([$id,$indice,$lado,limparTexto($foto['name'] ?? 'foto',255),$mime,$bin]);
                $totalFotos++;
            }
        }
    }
    $pdo->commit();
    registrarAuditoriaSistema('contestacoes_vistoria',$acao,$id,($acao==='criacao'?'Nova':'Atualizada').' contestação de '.$nome.'; '.$totalFotos.' foto(s).',(int)$usuario['id']);
    responder(['ok'=>true,'id'=>$id,'total_fotos'=>$totalFotos]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Contestação de vistoria: '.$e->getMessage());
    $msg=$e instanceof RuntimeException?$e->getMessage():'Não foi possível salvar a contestação. Verifique o tamanho das imagens e tente novamente.';
    responder(['ok'=>false,'error'=>$msg],422);
}
