<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario = exigirPermissaoPagina('historico.view');
$navPage = 'historico_rescisoes';
$navBase = "";
$csrf = csrfToken();

$q = trim((string)($_GET['q'] ?? ''));
$modo = trim((string)($_GET['modo'] ?? ''));
$statusFiltro = trim((string)($_GET['status'] ?? ''));
$usuarioFiltro = max(0, (int)($_GET['usuario'] ?? 0));
$dataInicial = trim((string)($_GET['data_inicial'] ?? ''));
$dataFinal = trim((string)($_GET['data_final'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$validDate = static function(string $v): ?string { if ($v === '') return null; $d = DateTime::createFromFormat('!Y-m-d', $v); return ($d && $d->format('Y-m-d') === $v) ? $v : null; };
$dataInicial = $validDate($dataInicial) ?? '';
$dataFinal = $validDate($dataFinal) ?? '';
if ($statusFiltro !== '' && !statusValido($statusFiltro)) $statusFiltro = '';

$where=[];$params=[];
if ($q!=='') { $where[]='(h.nome LIKE ? OR h.endereco LIKE ? OR COALESCE(u.nome,\'\') LIKE ? OR COALESCE(u.login,\'\') LIKE ? OR h.modo_nome LIKE ? OR h.dados_json LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like,$like,$like,$like); }
if ($modo!=='') { $where[]='h.modo_nome = ?'; $params[]=$modo; }
if ($statusFiltro!=='') { $where[]='h.status = ?'; $params[]=$statusFiltro; }
if ($usuarioFiltro>0) { $where[]='h.usuario_id = ?'; $params[]=$usuarioFiltro; }
if ($dataInicial!=='') { $where[]='h.criado_em >= ?'; $params[]=$dataInicial.' 00:00:00'; }
if ($dataFinal!=='') { $where[]='h.criado_em < ?'; $params[]=(new DateTime($dataFinal))->modify('+1 day')->format('Y-m-d').' 00:00:00'; }
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$itens=[];$usuarios=[];$statusCounts=[];$totalRegistros=0;$valorTotal=0.0;$valorAdm=0.0;$valorRepasse=0.0;$erro='';
try{
 $pdo=db();
 $usuarios=$pdo->query('SELECT id,nome,ativo FROM usuarios ORDER BY ativo DESC,nome ASC')->fetchAll();
 $stmt=$pdo->prepare("SELECT COUNT(*) qtd,COALESCE(SUM(h.total),0) total,COALESCE(SUM(h.total_adm),0) adm,COALESCE(SUM(h.total_repasse),0) repasse FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id=h.usuario_id $whereSql");$stmt->execute($params);$res=$stmt->fetch()?:[];
 $totalRegistros=(int)($res['qtd']??0);$valorTotal=(float)($res['total']??0);$valorAdm=(float)($res['adm']??0);$valorRepasse=(float)($res['repasse']??0);
 $stmtStatus=$pdo->prepare("SELECT h.status,COUNT(*) qtd FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id=h.usuario_id $whereSql GROUP BY h.status");$stmtStatus->execute($params);foreach($stmtStatus->fetchAll() as $sc){$statusCounts[(string)$sc['status']]=(int)$sc['qtd'];}
 $totalPages=max(1,(int)ceil($totalRegistros/$perPage));$page=min($page,$totalPages);$offset=($page-1)*$perPage;
 $stmt=$pdo->prepare("SELECT h.id,h.usuario_id,h.nome,h.endereco,h.total,h.total_adm,h.total_repasse,h.criado_em,h.atualizado_em,h.modo_nome,h.status,u.nome usuario_nome,u.login usuario_login FROM historico_rescisoes h LEFT JOIN usuarios u ON u.id=h.usuario_id $whereSql ORDER BY h.id DESC LIMIT $perPage OFFSET $offset");$stmt->execute($params);$itens=$stmt->fetchAll();
}catch(Throwable $e){error_log('Calculadora historico.php: '.$e->getMessage());$erro='Não foi possível consultar o histórico central.';$totalPages=1;}
$iniciais=strtoupper(mb_substr(trim($usuario['nome']),0,1));$perfilLabel=match(perfilNormalizado($usuario)){ 'admin'=>'Administrador','financeiro'=>'Financeiro','consulta'=>'Somente consulta',default=>'Operacional' };
$podeCriar=temPermissao($usuario,'rescisao.create');$podeEditar=temPermissao($usuario,'rescisao.edit');$isAdmin=perfilNormalizado($usuario)==='admin';
$queryString=static function(array $overrides=[])use($q,$modo,$statusFiltro,$usuarioFiltro,$dataInicial,$dataFinal):string{$p=['q'=>$q,'modo'=>$modo,'status'=>$statusFiltro,'usuario'=>$usuarioFiltro?:'','data_inicial'=>$dataInicial,'data_final'=>$dataFinal];$p=array_merge($p,$overrides);return http_build_query(array_filter($p,static fn($v)=>$v!==''&&$v!==null));};
$inicioExibicao=$totalRegistros?(($page-1)*$perPage)+1:0;$fimExibicao=min($page*$perPage,$totalRegistros);
$statusClass=static function(string $st):string{return match($st){'Conferido'=>'ok','Pronto para cobrança'=>'ready','Cobrado'=>'done','Cancelado'=>'cancelled','Em conferência'=>'review',default=>'draft'};};
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Histórico — Folha de Cálculo</title>
<link rel="stylesheet" href="assets/css/pages/historico.css"></head><body><?php require __DIR__ . "/includes/nav_global.php"; ?><div class="wrap">
<?php if($erro): ?><div class="error"><?= htmlspecialchars($erro,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<section class="hero"><span class="eyebrow">Histórico central</span><h1>Encontre qualquer rescisão.</h1><p>Pesquise por inquilino, imóvel, CPF, contrato ou responsável. Filtre por critério, status e período e abra o relatório completo.</p></section>
<section class="filter-card"><div class="filter-head"><div><h2>Filtros de pesquisa</h2></div><span><?= $totalRegistros ? 'Dados consultados no banco central' : 'Nenhum resultado para os filtros atuais' ?></span></div><form method="get" class="filters"><div class="field"><label for="q">Busca</label><input id="q" name="q" type="search" value="<?= htmlspecialchars($q,ENT_QUOTES,'UTF-8') ?>" placeholder="Inquilino, imóvel, CPF, contrato ou responsável..."></div><div class="field"><label for="modo">Critério</label><select id="modo" name="modo"><option value="">Todos</option><option value="Multa por mês" <?= $modo==='Multa por mês'?'selected':'' ?>>Multa por mês</option><option value="Multa por dias" <?= $modo==='Multa por dias'?'selected':'' ?>>Multa por dias</option></select></div><div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Todos</option><?php foreach(STATUS_RESCISAO as $st): ?><option value="<?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?>" <?= $statusFiltro===$st?'selected':'' ?>><?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div><div class="field"><label for="usuario">Responsável</label><select id="usuario" name="usuario"><option value="">Todos</option><?php foreach($usuarios as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $usuarioFiltro===(int)$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nome'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></div><div class="field"><label for="data_inicial">De</label><input id="data_inicial" name="data_inicial" type="date" value="<?= htmlspecialchars($dataInicial,ENT_QUOTES,'UTF-8') ?>"></div><div class="field"><label for="data_final">Até</label><input id="data_final" name="data_final" type="date" value="<?= htmlspecialchars($dataFinal,ENT_QUOTES,'UTF-8') ?>"></div><div class="filter-actions"><a class="btn" href="historico.php">Limpar</a><button class="btn btn-primary" type="submit">Pesquisar</button></div></form></section>
<section class="stats"><article class="stat"><span>Registros encontrados</span><strong><?= number_format($totalRegistros,0,',','.') ?></strong></article><article class="stat"><span>Valor das cobranças</span><strong>R$ <?= number_format($valorTotal,2,',','.') ?></strong></article><article class="stat"><span>Total de ADM</span><strong>R$ <?= number_format($valorAdm,2,',','.') ?></strong></article><article class="stat"><span>Total a repassar</span><strong>R$ <?= number_format($valorRepasse,2,',','.') ?></strong></article></section>
<section class="status-summary"><div class="status-summary-head"><div><h2>Resumo por status</h2><span>Use os indicadores para filtrar rapidamente os registros.</span></div></div><div class="status-summary-grid"><?php foreach(STATUS_RESCISAO as $st): $count=$statusCounts[$st]??0; ?><a class="status-summary-item <?= $statusClass($st) ?>" href="?<?= htmlspecialchars($queryString(['status'=>$st,'page'=>1]),ENT_QUOTES,'UTF-8') ?>"><strong><?= $count ?></strong><span><?= htmlspecialchars($st,ENT_QUOTES,'UTF-8') ?></span></a><?php endforeach; ?></div></section>
<section class="history-card"><div class="history-head"><div><h2>Registros</h2><p><?= $totalRegistros ? "Mostrando {$inicioExibicao}–{$fimExibicao} dos resultados." : 'Ajuste os filtros ou salve uma nova rescisão.' ?></p></div><?php if($podeCriar): ?><a class="btn btn-primary" href="index.php?nova=1">＋ Nova rescisão</a><?php endif; ?></div><div class="result-note">Página <?= $page ?> de <?= $totalPages ?> · <?= number_format($totalRegistros,0,',','.') ?> registro(s)</div><div class="table-wrap"><table class="history-table"><thead><tr><th>Inquilino / imóvel</th><th>Data</th><th>Critério</th><th>Status</th><th>Responsável</th><th>Total</th><th>Ações</th></tr></thead><tbody><?php if(!$itens): ?><tr><td colspan="7"><div class="empty">Nenhuma rescisão encontrada com os filtros atuais.</div></td></tr><?php else: foreach($itens as $item): ?><tr><td><span class="name"><?= htmlspecialchars($item['nome']?:'Inquilino não informado',ENT_QUOTES,'UTF-8') ?></span><span class="address" title="<?= htmlspecialchars($item['endereco']?:'Imóvel não informado',ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($item['endereco']?:'Imóvel não informado',ENT_QUOTES,'UTF-8') ?></span></td><td class="meta"><?= htmlspecialchars(date('d/m/Y H:i',strtotime($item['criado_em'])),ENT_QUOTES,'UTF-8') ?></td><td><span class="mode"><?= htmlspecialchars($item['modo_nome']?:'Critério não informado',ENT_QUOTES,'UTF-8') ?></span></td><td><span class="status <?= $statusClass($item['status']??'Rascunho') ?>"><?= htmlspecialchars($item['status']??'Rascunho',ENT_QUOTES,'UTF-8') ?></span></td><td class="meta"><?= htmlspecialchars($item['usuario_nome']?:'—',ENT_QUOTES,'UTF-8') ?></td><td class="total">R$ <?= number_format((float)$item['total'],2,',','.') ?></td><td><div class="actions"><?php $podeEditarItem=$podeEditar && ($isAdmin || (int)$item['usuario_id']===(int)$usuario['id']); ?><?php if($podeEditarItem): ?><a class="btn btn-primary" href="index.php?historico_id=<?= (int)$item['id'] ?>">Editar</a><?php endif; ?><a class="btn" href="detalhe.php?id=<?= (int)$item['id'] ?>">Detalhes</a><?php if($isAdmin): ?><button class="btn btn-danger btn-excluir" type="button" data-id="<?= (int)$item['id'] ?>" data-nome="<?= htmlspecialchars($item['nome']?:'este cálculo',ENT_QUOTES,'UTF-8') ?>">Excluir</button><?php endif; ?></div></td></tr><?php endforeach; endif; ?></tbody></table></div><?php if($totalPages>1): ?><div class="pagination"><div class="page-info">Página <?= $page ?> de <?= $totalPages ?></div><div class="page-actions"><?php if($page>1): ?><a class="btn" href="?<?= htmlspecialchars($queryString(['page'=>$page-1]),ENT_QUOTES,'UTF-8') ?>">← Anterior</a><?php endif; ?><?php if($page<$totalPages): ?><a class="btn btn-primary" href="?<?= htmlspecialchars($queryString(['page'=>$page+1]),ENT_QUOTES,'UTF-8') ?>">Próxima →</a><?php endif; ?></div></div><?php endif; ?><div class="note">🔒 Histórico centralizado. Cada rescisão registra o responsável, status e data de atualização.</div></section>
</div><script>
window.APP_CSRF = <?= json_encode($csrf) ?>;
</script>
<script src="assets/js/pages/historico.js"></script></body></html>
