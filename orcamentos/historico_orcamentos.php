<?php
declare(strict_types=1);
require __DIR__.'/../api/config.php';
require __DIR__.'/includes/storage.php';
$u=usuarioAtual();
if(!$u){header('Location: ../login.php');exit;}
$f=orcamentosStorage().'/historico_orcamentos.json';
$data=is_file($f)?json_decode((string)file_get_contents($f),true):[];
if(!is_array($data))$data=[];
usort($data,fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
$isAdmin=!empty($u['is_admin'])||!empty($u['admin'])||strtolower((string)($u['perfil']??$u['role']??$u['nivel']??''))==='admin'||strtolower((string)($u['login']??''))==='admin';
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$csrf=csrfToken();
?><?php $menuPage='historico_orcamentos'; ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Histórico de Orçamentos</title><link rel="stylesheet" href="../assets/css/orcamentos/historico.css"></head><body><?php require __DIR__ . '/includes/nav.php'; ?><div class="wrap"><div class="top"><div><h1>Histórico de Orçamentos</h1><div class="muted">Registros das gerações realizadas no servidor.</div></div><div class="muted"><?=count($data)?> registro(s)</div></div><div class="table"><div class="row head"><div>Data</div><div>Locatário</div><div>Endereço</div><div>Prestadores</div><div>Ações</div></div><?php foreach(array_slice($data,0,200) as $r):?><div class="row"><div><?=h($r['created_at']??'')?></div><div><b><?=h($r['locatario']??'')?></b><div class="muted">Usuário: <?=h($r['usuario']??'')?></div></div><div><?=h($r['endereco']??'')?></div><div class="muted"><?=h(implode(' · ',array_column($r['prestadores']??[],'nome')))?></div><div class="actions-cell"><?php if(!empty($r['download'])):?><a class="btn" href="<?=h($r['download'])?>">ZIP</a><?php endif;?><?php if($isAdmin && !empty($r['job'])):?><button class="btn delete" type="button" data-job="<?=h($r['job'])?>">Excluir</button><?php endif;?></div></div><?php endforeach;?><?php if(!$data):?><div class="empty muted">Nenhum orçamento gerado ainda.</div><?php endif;?></div></div><div class="toast" id="toast"></div><script>
window.ORCAMENTOS_CSRF = <?=json_encode($csrf,JSON_UNESCAPED_UNICODE)?>;
</script>
<script src="../assets/js/orcamentos/historico.js"></script>
<script src="../assets/js/orcamentos/historico.js"></script></body></html>
