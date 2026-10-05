<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$usuario = exigirLoginApi();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A permissão específica é validada abaixo conforme criação ou edição.
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok' => false, 'error' => 'Método não permitido.'], 405);
validarCsrf();
$entrada = entradaJson();
$id = (int)($entrada['id'] ?? 0);
$nome = limparTexto($entrada['nome'] ?? '', 150);
$endereco = limparTexto($entrada['endereco'] ?? '', 255);
$total = is_numeric($entrada['total'] ?? null) ? max(0, (float)$entrada['total']) : 0;
$totalAdm = is_numeric($entrada['totalAdm'] ?? null) ? max(0, (float)$entrada['totalAdm']) : 0;
$totalRepasse = is_numeric($entrada['totalRepasse'] ?? null) ? max(0, (float)$entrada['totalRepasse']) : 0;
$modoNome = limparTexto($entrada['modoNome'] ?? '', 50);
$status = limparTexto($entrada['status'] ?? 'Rascunho', 30);
if (!statusValido($status)) $status = 'Rascunho';
$dados = is_array($entrada['dados'] ?? null) ? $entrada['dados'] : [];
try {
    $jsonDados = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $pdo = db();
    $pdo->beginTransaction();

    if ($id > 0) {
        if (!temPermissao($usuario, 'rescisao.edit')) responder(['ok' => false, 'error' => 'Você não possui permissão para editar rescisões.'], 403);
        $stmt = $pdo->prepare('SELECT id, usuario_id, status, nome, endereco, total, total_adm, total_repasse, modo_nome, dados_json FROM historico_rescisoes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existente = $stmt->fetch();
        if (!$existente) { $pdo->rollBack(); responder(['ok' => false, 'error' => 'Rescisão não encontrada.'], 404); }
        $permitido = temPermissao($usuario, 'rescisao.edit') && (((int)$existente['usuario_id'] === (int)$usuario['id']) || perfilNormalizado($usuario) === 'admin');
        if (!$permitido) { $pdo->rollBack(); responder(['ok' => false, 'error' => 'Você não pode alterar esta rescisão.'], 403); }

        $stmt = $pdo->prepare('UPDATE historico_rescisoes SET nome = ?, endereco = ?, total = ?, total_adm = ?, total_repasse = ?, modo_nome = ?, status = ?, atualizado_em = CURRENT_TIMESTAMP, ultimo_editor_id = ? WHERE id = ?');
        $stmt->execute([$nome, $endereco, $total, $totalAdm, $totalRepasse, $modoNome, $status, (int)$usuario['id'], $id]);
        $acao = 'Edição';
        $detalhes = 'Registro atualizado';
        if ((string)$existente['status'] !== $status) {
            $acao = 'Alteração de status';
            $detalhes = 'Status: ' . ($existente['status'] ?: 'Rascunho') . ' → ' . $status;
        }
        if ($status === 'Conferido') {
            $stmt = $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ?, cobrado_em = NULL, cobrado_por = NULL WHERE id = ?');
            $stmt->execute([(int)$usuario['id'], $id]);
        } elseif ($status === 'Cobrado') {
            $stmt = $pdo->prepare('UPDATE historico_rescisoes SET cobrado_em = CURRENT_TIMESTAMP, cobrado_por = ?, conferido_em = COALESCE(conferido_em, CURRENT_TIMESTAMP), conferido_por = COALESCE(conferido_por, ?) WHERE id = ?');
            $stmt->execute([(int)$usuario['id'], (int)$usuario['id'], $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = NULL, conferido_por = NULL, cobrado_em = NULL, cobrado_por = NULL WHERE id = ?');
            $stmt->execute([$id]);
        }
        $dadosAnteriores = json_decode((string)($existente['dados_json'] ?? ''), true);
        if (!is_array($dadosAnteriores)) $dadosAnteriores = ['campos' => []];
        $camposAnteriores = is_array($dadosAnteriores['campos'] ?? null) ? $dadosAnteriores['campos'] : [];
        $camposNovos = is_array($dados['campos'] ?? null) ? $dados['campos'] : [];

        $rotulos = [
            'nomeInquilino' => 'Inquilino',
            'cpfInquilino' => 'CPF',
            'enderecoImovel' => 'Endereço',
            'numeroContrato' => 'Contrato',
            'dataInicial' => 'Data inicial',
            'dataFinal' => 'Entrega das chaves',
            'dataInicioContrato' => 'Início do contrato',
            'dataInicioAviso' => 'Início do aviso',
            'dataFimAviso' => 'Fim do aviso',
            'aluguel' => 'Aluguel',
            'iptu' => 'IPTU',
            'condominio' => 'Condomínio',
            'agua' => 'Água',
            'luz' => 'Luz',
            'internet' => 'Internet',
            'percentualAdm' => 'ADM do aluguel (%)',
            'mesesFaltantes' => 'Meses faltantes',
            'percentualProp' => 'ADM sobre multa (%)',
            'valorAluguelInteiro' => 'Aluguel inteiro + encargos',
            'manutencao' => 'Manutenção',
            'chaveiro' => 'Chaveiro',
            'seguroIncendio' => 'Seguro incêndio',
            'seguroIncendioExtras' => 'Parcelas extras do seguro incêndio',
            'seguroFianca' => 'Seguro fiança',
            'seguroFiancaExtras' => 'Parcelas extras do seguro fiança',
            'semAviso' => 'Sem aviso',
            'semMulta' => 'Sem multa',
            'semEncargos' => 'Sem encargos',
            'semManutencao' => 'Sem manutenção',
            'semChaveiro' => 'Sem chaveiro',
            'semAluguelInteiro' => 'Sem aluguel inteiro',
            'semSeguros' => 'Sem seguros',
        ];
        $formatarAuditoria = static function (string $campo, mixed $valor): string {
            if (is_bool($valor)) return $valor ? 'Sim' : 'Não';
            if ($valor === null || $valor === '') return 'vazio';
            if (is_array($valor)) return '[dados]';
            return is_scalar($valor) ? (string)$valor : '[dados]';
        };
        $alteracoes = [];
        $todosCampos = array_unique(array_merge(array_keys($camposAnteriores), array_keys($camposNovos)));
        foreach ($todosCampos as $campo) {
            $antes = $camposAnteriores[$campo] ?? null;
            $depois = $camposNovos[$campo] ?? null;
            if ((string)$antes === (string)$depois && is_bool($antes) === is_bool($depois)) continue;
            $rotulo = $rotulos[$campo] ?? $campo;
            $alteracoes[] = $rotulo . ': ' . $formatarAuditoria($campo, $antes) . ' → ' . $formatarAuditoria($campo, $depois);
        }

        if ((string)($existente['nome'] ?? '') !== $nome) {
            $alteracoes[] = 'Nome exibido: ' . (($existente['nome'] ?? '') !== '' ? $existente['nome'] : 'vazio') . ' → ' . ($nome !== '' ? $nome : 'vazio');
        }
        if ((string)($existente['endereco'] ?? '') !== $endereco) {
            $alteracoes[] = 'Endereço: ' . (($existente['endereco'] ?? '') !== '' ? $existente['endereco'] : 'vazio') . ' → ' . ($endereco !== '' ? $endereco : 'vazio');
        }
        if ((float)($existente['total'] ?? 0) !== $total) {
            $alteracoes[] = 'Total: R$ ' . number_format((float)($existente['total'] ?? 0), 2, ',', '.') . ' → R$ ' . number_format($total, 2, ',', '.');
        }
        if ((float)($existente['total_adm'] ?? 0) !== $totalAdm) {
            $alteracoes[] = 'ADM: R$ ' . number_format((float)($existente['total_adm'] ?? 0), 2, ',', '.') . ' → R$ ' . number_format($totalAdm, 2, ',', '.');
        }
        if ((float)($existente['total_repasse'] ?? 0) !== $totalRepasse) {
            $alteracoes[] = 'Repasse: R$ ' . number_format((float)($existente['total_repasse'] ?? 0), 2, ',', '.') . ' → R$ ' . number_format($totalRepasse, 2, ',', '.');
        }

        if ($alteracoes) {
            registrarAuditoria($id, (int)$usuario['id'], 'Edição', 'Alterações: ' . implode(' | ', array_slice($alteracoes, 0, 30)));
        } else {
            registrarAuditoria($id, (int)$usuario['id'], $acao, $detalhes);
        }
    } else {
        if (!temPermissao($usuario, 'rescisao.create')) responder(['ok' => false, 'error' => 'Você não possui permissão para criar rescisões.'], 403);
        $stmt = $pdo->prepare('INSERT INTO historico_rescisoes (usuario_id, ultimo_editor_id, nome, endereco, total, total_adm, total_repasse, modo_nome, status, dados_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int)$usuario['id'], (int)$usuario['id'], $nome, $endereco, $total, $totalAdm, $totalRepasse, $modoNome, $status, $jsonDados]);
        $id = (int)$pdo->lastInsertId();
        if ($status === 'Conferido') {
            $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ? WHERE id = ?')->execute([(int)$usuario['id'], $id]);
        } elseif ($status === 'Cobrado') {
            $pdo->prepare('UPDATE historico_rescisoes SET conferido_em = CURRENT_TIMESTAMP, conferido_por = ?, cobrado_em = CURRENT_TIMESTAMP, cobrado_por = ? WHERE id = ?')->execute([(int)$usuario['id'], (int)$usuario['id'], $id]);
        }
        registrarAuditoria($id, (int)$usuario['id'], 'Criação', 'Rescisão criada com status: ' . $status);
    }

    // Sempre grava a versão mais recente do JSON após a decisão de criar/editar.
    $stmt = $pdo->prepare('UPDATE historico_rescisoes SET dados_json = ? WHERE id = ?');
    $stmt->execute([$jsonDados, $id]);

    $stmt = $pdo->prepare('SELECT h.id, h.nome, h.endereco, h.total, h.total_adm, h.total_repasse, h.criado_em, h.atualizado_em, h.modo_nome, h.status, h.dados_json, u.nome AS usuario_nome, u.login AS usuario_login FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    $pdo->commit();
    responder(['ok' => true, 'item' => normalizarItemBanco($item)]);
} catch (JsonException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    responder(['ok' => false, 'error' => 'Os dados da rescisão não puderam ser serializados.'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Calculadora salvar.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'Não foi possível salvar no banco.'], 500);
}
