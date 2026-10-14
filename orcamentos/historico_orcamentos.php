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
function dataHistoricoTs($v): int {
    $v=trim((string)$v);
    foreach(['!d/m/Y H:i:s','!d/m/Y H:i','!Y-m-d H:i:s','!Y-m-d\\TH:i:s'] as $fmt){
        $dt=DateTime::createFromFormat($fmt,$v);
        if($dt instanceof DateTime)return $dt->getTimestamp();
    }
    $ts=strtotime($v);
    return $ts===false?0:$ts;
}
$busca=trim((string)($_GET['q']??''));
$de=trim((string)($_GET['de']??''));
$ate=trim((string)($_GET['ate']??''));
$desdeTs=$de!==''?strtotime($de.' 00:00:00'):false;
$ateTs=$ate!==''?strtotime($ate.' 23:59:59'):false;
$filtrados=array_values(array_filter($data,function($r)use($busca,$desdeTs,$ateTs){
    $prestadores=implode(' ',array_column(is_array($r['prestadores']??null)?$r['prestadores']:[],'nome'));
    $haystack=mb_strtolower(implode(' ',[(string)($r['locatario']??''),(string)($r['endereco']??''),(string)($r['usuario']??''),$prestadores]),'UTF-8');
    if($busca!=='' && !str_contains($haystack,mb_strtolower($busca,'UTF-8')))return false;
    $ts=dataHistoricoTs($r['created_at']??'');
    if($desdeTs!==false && $ts<$desdeTs)return false;
    if($ateTs!==false && $ts>$ateTs)return false;
    return true;
}));
$csrf=csrfToken();
?><?php $menuPage='historico_orcamentos'; ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Histórico de Orçamentos</title><link rel="stylesheet" href="../assets/css/orcamentos/historico.css"></head><body><?php require __DIR__ . '/includes/nav.php'; ?>
<main class="wrap">
  <header class="top"><div><span class="eyebrow">GERENCIAMENTO</span><h1>Histórico de Orçamentos</h1><p class="subtitle">Consulte, filtre e gerencie as gerações realizadas no servidor.</p></div><div class="result-count"><strong><?=count($filtrados)?></strong><span>de <?=count($data)?> registros</span></div></header>
  <form class="filters" method="get" action="historico_orcamentos.php">
    <div class="filter-search"><label for="q">Pesquisar</label><input id="q" name="q" type="search" value="<?=h($busca)?>" placeholder="Locatário, endereço ou prestador"></div>
    <div class="filter-date"><label for="de">Data inicial</label><input id="de" name="de" type="date" value="<?=h($de)?>"></div>
    <div class="filter-date"><label for="ate">Data final</label><input id="ate" name="ate" type="date" value="<?=h($ate)?>"></div>
    <div class="filter-actions"><button class="btn primary" type="submit">Filtrar</button><a class="btn" href="historico_orcamentos.php">Limpar</a></div>
  </form>
  <section class="table-wrap" aria-label="Histórico de orçamentos"><div class="table-scroll"><table><thead><tr><th>Data</th><th>Locatário / usuário</th><th>Endereço</th><th>Prestadores</th><th>Ações</th></tr></thead><tbody>
  <?php foreach(array_slice($filtrados,0,200) as $r): ?><tr><td class="date-cell"><?=h($r['created_at']??'—')?></td><td><strong><?=h($r['locatario']??'—')?></strong><span class="secondary-line">Usuário: <?=h($r['usuario']??'—')?></span></td><td><?=h($r['endereco']??'—')?></td><td class="providers"><?=h(implode(' · ',array_column(is_array($r['prestadores']??null)?$r['prestadores']:[],'nome'))?:'—')?></td><td><div class="actions-cell"><?php if(!empty($r['download'])): ?><a class="btn" href="<?=h($r['download'])?>">Baixar ZIP</a><?php endif; ?><?php if($isAdmin&&!empty($r['job'])): ?><button class="btn danger delete" type="button" data-job="<?=h($r['job'])?>">Excluir</button><?php endif; ?></div></td></tr><?php endforeach; ?>
  <?php if(!$filtrados): ?><tr><td colspan="5"><div class="empty"><strong>Nenhum orçamento encontrado</strong><span>Altere os filtros ou limpe a pesquisa para ver outros registros.</span></div></td></tr><?php endif; ?>
  </tbody></table></div><?php if(count($filtrados)>200): ?><div class="table-note">Exibindo os 200 registros mais recentes correspondentes aos filtros.</div><?php endif; ?></section>
</main><div class="toast" id="toast" role="status" aria-live="polite"></div><script>window.ORCAMENTOS_CSRF = <?=json_encode($csrf,JSON_UNESCAPED_UNICODE)?>;</script><script src="../assets/js/orcamentos/historico.js"></script>
<script>document.querySelectorAll('.delete').forEach(b=>b.addEventListener('click',()=>{setTimeout(()=>{const row=b.closest('tr');if(row&&!row.isConnected)return;},0)}));</script>
</body></html>