<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario = exigirPermissaoPagina('detalhe.view');
$navPage = 'historico_rescisoes';
$navBase = "";
$csrf = csrfToken();
$id = max(0, (int)($_GET['id'] ?? 0));
if ($id < 1) { header('Location: historico.php'); exit; }
$row = null; $audit = []; $erro = '';
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT h.*, u.nome AS usuario_nome, u.login AS usuario_login, c.nome AS conferido_nome, b.nome AS cobrado_nome FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id=h.usuario_id LEFT JOIN usuarios c ON c.id=h.conferido_por LEFT JOIN usuarios b ON b.id=h.cobrado_por WHERE h.id=? LIMIT 1');
    $stmt->execute([$id]); $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo 'Registro não encontrado.'; exit; }
    $stmt = $pdo->prepare('SELECT a.*, u.nome AS usuario_nome FROM auditoria_rescisoes a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE a.historico_id=? ORDER BY a.id DESC LIMIT 100');
    $stmt->execute([$id]); $audit = $stmt->fetchAll();
} catch(Throwable $e){ $erro='Não foi possível carregar o relatório.'; }
$dados = json_decode($row['dados_json'] ?? '', true); if (!is_array($dados)) $dados=[]; $campos = $dados['campos'] ?? []; if (!is_array($campos)) $campos=[];
$nome = trim((string)($row['nome'] ?? '')); $endereco=trim((string)($row['endereco'] ?? '')); $modo=trim((string)($row['modo_nome'] ?? '')) ?: 'Critério não informado';
$labels=[
 'cpfInquilino'=>'CPF','numeroContrato'=>'Contrato','dataInicial'=>'Data inicial','dataFinal'=>'Entrega das chaves','aluguel'=>'Aluguel mensal','iptu'=>'IPTU','condominio'=>'Condomínio','agua'=>'Água','luz'=>'Luz','internet'=>'Internet','percentualAdm'=>'ADM do aluguel (%)','dataInicioAviso'=>'Início do aviso','dataFimAviso'=>'Fim do aviso','mesesFaltantes'=>'Meses faltantes','dataInicioContrato'=>'Início do contrato','percentualProp'=>'ADM sobre multa (%)','valorAluguelInteiro'=>'Aluguel inteiro + encargos','manutencao'=>'Manutenção','chaveiro'=>'Chaveiro','seguroIncendio'=>'Seguro incêndio','seguroFianca'=>'Seguro fiança','assuntoEmail'=>'Assunto do e-mail'
];
$currency=['aluguel','iptu','condominio','agua','luz','internet','valorAluguelInteiro','manutencao','chaveiro','seguroIncendio','seguroFianca'];
$dateFields=['dataInicial','dataFinal','dataInicioAviso','dataFimAviso','dataInicioContrato'];
$fmt=static function($id,$v)use($currency,$dateFields){ if ($v===null||$v==='') return ''; if(in_array($id,$dateFields,true)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$v)) return date('d/m/Y',strtotime((string)$v)); if(in_array($id,$currency,true)&&is_numeric($v)) return 'R$ '.number_format((float)$v,2,',','.'); return (string)$v; };
$groups=[
 'Identificação'=>['cpfInquilino','numeroContrato','assuntoEmail'],
 'Valores e critérios'=>['aluguel','iptu','condominio','agua','luz','internet','percentualAdm','mesesFaltantes','dataInicioContrato','percentualProp','dataInicial','dataFinal'],
 'Aviso prévio'=>['dataInicioAviso','dataFimAviso'],
 'Cobranças adicionais'=>['valorAluguelInteiro','manutencao','chaveiro','seguroIncendio','seguroFianca'],
];
$status=(string)($row['status']??'Rascunho');
$podeEditar = ((int)($row['usuario_id'] ?? 0) === (int)$usuario['id']) || $usuario['perfil'] === 'admin';
$statusClass=match($status){'Conferido'=>'ok','Pronto para cobrança'=>'ready','Cobrado'=>'done','Cancelado'=>'cancelled','Em conferência'=>'review',default=>'draft'};
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rescisão #<?= $id ?> — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/detalhe.css"></head><body><?php require __DIR__ . "/includes/nav_global.php"; ?><div class="wrap">
<?php if($erro): ?><div class="error"><?= htmlspecialchars($erro,ENT_QUOTES,'UTF-8') ?></div><?php else: ?>
<section class="hero"><div class="hero-content"><span class="eyebrow">Rescisão #<?= $id ?></span><h1><?= htmlspecialchars($nome?:'Inquilino não informado',ENT_QUOTES,'UTF-8') ?></h1><p><?= htmlspecialchars($endereco?:'Imóvel não informado',ENT_QUOTES,'UTF-8') ?></p><div class="hero-meta"><span class="meta-chip"><?= htmlspecialchars($modo,ENT_QUOTES,'UTF-8') ?></span><span class="meta-chip">Criado em <?= htmlspecialchars(date('d/m/Y H:i',strtotime($row['criado_em'])),ENT_QUOTES,'UTF-8') ?></span><span class="meta-chip">Responsável: <?= htmlspecialchars($row['usuario_nome']?:'—',ENT_QUOTES,'UTF-8') ?></span></div><div class="hero-actions no-print"><a class="btn btn-primary" href="index.php?historico_id=<?= $id ?>">✎ Editar rescisão</a><a class="btn" href="historico.php">← Voltar ao histórico</a></div></div></section>
<div class="layout"><main>
<section class="card"><div class="status-wrap"><div><span class="status-label">Status da rescisão</span><strong><span class="status <?= $statusClass ?>"><?= htmlspecialchars($status,ENT_QUOTES,'UTF-8') ?></span></strong></div><select id="statusSelect" class="no-print" <?php if(!$podeEditar): ?>disabled title="Somente o responsável ou administrador pode alterar o status."<?php endif; ?>><?php foreach(STATUS_RESCISAO as $st): ?><option value="<?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?>" <?= $status===$st?'selected':'' ?>><?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div>
<h2>Dados da rescisão</h2><p class="sub">Todos os dados abaixo foram armazenados no momento do lançamento.</p>
<?php foreach($groups as $group=>$keys): $shown=[]; foreach($keys as $key){$v=$campos[$key]??''; if($v!=='' && $v!==null) $shown[$key]=$v;} if(!$shown) continue; ?>
<div class="data-group"><div class="group-title"><?= htmlspecialchars($group,ENT_QUOTES,'UTF-8') ?></div><div class="data-grid">
<?php foreach($shown as $key=>$v): if(in_array($key,['semAviso','semMulta','semEncargos','semManutencao','semChaveiro','semAluguelInteiro','semSeguros'],true)) continue; $label=$labels[$key]??$key; if($key==='valorAluguelInteiro' && !empty($campos['semAluguelInteiro'])) continue; if(in_array($key,['iptu','condominio','agua','luz','internet'],true)&&!empty($campos['semEncargos'])) continue; if(in_array($key,['manutencao'],true)&&!empty($campos['semManutencao'])) continue; if(in_array($key,['chaveiro'],true)&&!empty($campos['semChaveiro'])) continue; if(in_array($key,['seguroIncendio','seguroFianca'],true)&&!empty($campos['semSeguros'])) continue; ?>
<div class="data-item"><div class="data-label"><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></div><div class="data-value"><?= htmlspecialchars($fmt($key,$v),ENT_QUOTES,'UTF-8') ?></div></div>
<?php endforeach; ?></div></div><?php endforeach; ?></section>
</main>
<aside>
<section class="card"><h2>Resumo financeiro</h2><p class="sub">Totais gravados no histórico.</p><div class="side-stat"><span>Total cobrado</span><strong>R$ <?= number_format((float)$row['total'],2,',','.') ?></strong></div><div class="side-stat"><span>Total de ADM</span><strong>R$ <?= number_format((float)($row['total_adm']??0),2,',','.') ?></strong></div><div class="side-stat"><span>Total a repassar</span><strong>R$ <?= number_format((float)($row['total_repasse']??0),2,',','.') ?></strong></div><div class="side-stat"><span>Última atualização</span><strong><?= htmlspecialchars(date('d/m/Y H:i',strtotime($row['atualizado_em'])),ENT_QUOTES,'UTF-8') ?></strong></div><?php if(!empty($row['conferido_em'])): ?><div class="side-stat"><span>Conferido por</span><strong><?= htmlspecialchars($row['conferido_nome']??'—',ENT_QUOTES,'UTF-8') ?></strong></div><?php endif; ?><?php if(!empty($row['cobrado_em'])): ?><div class="side-stat"><span>Cobrado por</span><strong><?= htmlspecialchars($row['cobrado_nome']??'—',ENT_QUOTES,'UTF-8') ?></strong></div><?php endif; ?><div class="print-note">Na impressão, controles internos e ações de edição ficam ocultos.</div></section>
<section class="card audit"><h2>Auditoria</h2><p class="sub">Registro das principais ações realizadas.</p><?php if(!$audit): ?><div class="print-note">Nenhuma ação registrada.</div><?php else: foreach($audit as $a): ?><div class="audit-row"><strong><?= htmlspecialchars($a['acao'],ENT_QUOTES,'UTF-8') ?></strong><small><?= htmlspecialchars($a['usuario_nome']?:'Usuário',ENT_QUOTES,'UTF-8') ?> · <?= htmlspecialchars(date('d/m/Y H:i',strtotime($a['criado_em'])),ENT_QUOTES,'UTF-8') ?><?= !empty($a['detalhes'])?' · '.htmlspecialchars($a['detalhes'],ENT_QUOTES,'UTF-8'):'' ?></small></div><?php endforeach; endif; ?></section>
</aside></div>
<?php endif; ?></div>
<script>
window.APP_CSRF = <?= json_encode($csrf) ?>;
window.APP_ID = <?= $id ?>;
</script>
<script src="assets/js/pages/detalhe.js"></script>
<script src="assets/js/pages/detalhe.js"></script>
</body></html>
