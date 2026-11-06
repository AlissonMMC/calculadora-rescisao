<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
function cnpjValido(string $cnpj): bool {
    if (strlen($cnpj) !== 14 || preg_match('/^(\\d)\\1{13}$/', $cnpj)) return false;
    $calcularDigito = static function (string $base, array $pesos): int {
        $soma = 0;
        foreach ($pesos as $i => $peso) $soma += (int)$base[$i] * $peso;
        $resto = $soma % 11;
        return $resto < 2 ? 0 : 11 - $resto;
    };
    $primeiro = $calcularDigito(substr($cnpj, 0, 12), [5,4,3,2,9,8,7,6,5,4,3,2]);
    $segundo = $calcularDigito(substr($cnpj, 0, 12) . $primeiro, [6,5,4,3,2,9,8,7,6,5,4,3,2]);
    return $primeiro === (int)$cnpj[12] && $segundo === (int)$cnpj[13];
}
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo === 'GET') {
    exigirLoginApi();
    try {
        $stmt = db()->query('SELECT id, razao_social, cnpj, endereco, ativo FROM empresas WHERE ativo = 1 ORDER BY razao_social');
        responder(['ok' => true, 'empresas' => $stmt->fetchAll()]);
    } catch (Throwable $e) {
        error_log('Empresas listar: ' . $e->getMessage());
        responder(['ok' => false, 'error' => 'Não foi possível carregar as empresas.'], 500);
    }
}
if ($metodo !== 'POST') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
$usuario = exigirAdminApi();
validarCsrf();
$entrada = entradaJson();
$acao = (string)($entrada['acao'] ?? '');
try {
    $pdo = db();
    if ($acao === 'salvar') {
        $id = (int)($entrada['id'] ?? 0);
        $razao = limparTexto($entrada['razao_social'] ?? '', 190);
        $cnpj = preg_replace('/\D+/', '', (string)($entrada['cnpj'] ?? ''));
        $endereco = limparTexto($entrada['endereco'] ?? '', 500);
        if ($razao === '' || $endereco === '' || !cnpjValido($cnpj)) {
            responder(['ok' => false, 'error' => 'Informe razão social, CNPJ válido e endereço completo.'], 422);
        }
        $cnpj = substr($cnpj,0,2).'.'.substr($cnpj,2,3).'.'.substr($cnpj,5,3).'/'.substr($cnpj,8,4).'-'.substr($cnpj,12,2);
        $stmt = $pdo->prepare('SELECT id FROM empresas WHERE cnpj = ? AND id <> ? LIMIT 1');
        $stmt->execute([$cnpj, $id]);
        if ($stmt->fetch()) responder(['ok' => false, 'error' => 'Já existe uma empresa cadastrada com este CNPJ.'], 409);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE empresas SET razao_social=?, cnpj=?, endereco=?, atualizado_por=? WHERE id=?');
            $stmt->execute([$razao, $cnpj, $endereco, (int)$usuario['id'], $id]);
            $check = $pdo->prepare('SELECT id FROM empresas WHERE id=?'); $check->execute([$id]);
            if (!$check->fetch()) responder(['ok'=>false,'error'=>'Empresa não encontrada.'],404);
            registrarAuditoriaSistema('empresas','edicao',$id,'Cadastro da empresa atualizado.',(int)$usuario['id']);
        } else {
            $stmt = $pdo->prepare('INSERT INTO empresas (razao_social, cnpj, endereco, criado_por, atualizado_por) VALUES (?,?,?,?,?)');
            $stmt->execute([$razao,$cnpj,$endereco,(int)$usuario['id'],(int)$usuario['id']]);
            $id = (int)$pdo->lastInsertId();
            registrarAuditoriaSistema('empresas','criacao',$id,'Empresa cadastrada: '.$razao,(int)$usuario['id']);
        }
        responder(['ok'=>true,'id'=>$id]);
    }
    if ($acao === 'status') {
        $id = (int)($entrada['id'] ?? 0);
        if ($id < 1) responder(['ok'=>false,'error'=>'Empresa inválida.'],422);
        $stmt = $pdo->prepare('SELECT ativo, razao_social FROM empresas WHERE id=? LIMIT 1');
        $stmt->execute([$id]); $empresa = $stmt->fetch();
        if (!$empresa) responder(['ok'=>false,'error'=>'Empresa não encontrada.'],404);
        $novo = (int)$empresa['ativo'] ? 0 : 1;
        $pdo->prepare('UPDATE empresas SET ativo=?, atualizado_por=? WHERE id=?')->execute([$novo,(int)$usuario['id'],$id]);
        registrarAuditoriaSistema('empresas',$novo?'ativacao':'desativacao',$id,'Empresa '.($novo?'ativada: ':'desativada: ').$empresa['razao_social'],(int)$usuario['id']);
        responder(['ok'=>true,'ativo'=>$novo]);
    }
    responder(['ok'=>false,'error'=>'Ação inválida.'],400);
} catch (Throwable $e) {
    error_log('Empresas API: '.$e->getMessage());
    responder(['ok'=>false,'error'=>'Não foi possível concluir a operação. Verifique se a migração 005 foi aplicada.'],500);
}
