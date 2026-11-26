<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';
$usuario=exigirLoginPagina();
$id=max(0,(int)($_GET['id']??0));
$stmt=db()->prepare('SELECT * FROM contestacoes_vistoria WHERE id=? LIMIT 1');
$stmt->execute([$id]);$item=$stmt->fetch();
if(!$item || ((int)$item['usuario_id']!==(int)$usuario['id'] && ($usuario['perfil']??'')!=='admin')){http_response_code(404);exit('Contestação não encontrada.');}
$comodos=json_decode((string)$item['comodos_json'],true);if(!is_array($comodos))$comodos=[];
$stmt=db()->prepare('SELECT id,comodo_indice,lado FROM contestacoes_vistoria_fotos WHERE contestacao_id=? ORDER BY id');$stmt->execute([$id]);$fotos=$stmt->fetchAll();
$porComodo=[];foreach($fotos as $f){$porComodo[(int)$f['comodo_indice']][$f['lado']][]=(int)$f['id'];}
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contestação de vistoria #<?= $id ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#e9e9e9;color:#171717;font:10pt Arial,sans-serif}.toolbar{position:sticky;top:0;background:#171717;color:#fff;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;z-index:5}.toolbar button{background:#f97316;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:bold;cursor:pointer}.sheet{width:210mm;min-height:297mm;margin:16px auto;background:#fff;padding:14mm 15mm;box-shadow:0 4px 24px #0002}.doc-title{text-align:center;border-bottom:2px solid #f97316;padding-bottom:12px;margin-bottom:18px}.doc-title h1{font-size:18pt;margin:0 0 5px}.doc-title p{margin:0;color:#666;font-size:8pt;letter-spacing:1px}.details{display:grid;grid-template-columns:1fr 1fr;gap:9px 18px;border:1px solid #ddd;border-radius:7px;padding:12px;margin-bottom:18px}.detail small{display:block;color:#777;font-size:7pt;text-transform:uppercase;margin-bottom:3px}.detail strong{font-size:9pt;overflow-wrap:anywhere}.detail.address{grid-column:1/-1}.room{border:1px solid #d9d9d9;border-radius:7px;padding:10px;margin:0 0 14px;break-inside:avoid;page-break-inside:avoid}.room h2{font-size:11pt;margin:0 0 9px;padding-bottom:7px;border-bottom:1px solid #eee}.columns{display:grid;grid-template-columns:1fr 1fr;gap:10px}.side-title{font-size:7pt;font-weight:bold;letter-spacing:.5px;margin-bottom:5px}.entry{color:#16804a}.exit{color:#c65a16}.photos{display:grid;grid-template-columns:1fr 1fr;gap:5px}.photos img{width:100%;height:52mm;object-fit:contain;border:1px solid #eee;border-radius:3px}.no-photo{height:22mm;display:grid;place-items:center;background:#f7f7f7;color:#888;font-size:8pt;border:1px dashed #ccc}.description{margin-top:9px;border-top:1px solid #eee;padding-top:8px;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.4}.description strong{display:block;font-size:7pt;color:#666;text-transform:uppercase;margin-bottom:4px}.footer{text-align:center;color:#888;font-size:7pt;margin-top:18px}@page{size:A4;margin:0}@media print{body{background:#fff}.toolbar{display:none}.sheet{width:auto;min-height:0;margin:0;padding:13mm 14mm;box-shadow:none}.room{break-inside:avoid;page-break-inside:avoid}.sheet{break-after:page;page-break-after:always}.sheet:last-child{break-after:auto;page-break-after:auto}}
</style></head><body><div class="toolbar"><span>Pré-visualização da contestação · PDF</span><button type="button" onclick="window.print()">Baixar / Salvar em PDF</button></div>
<main class="sheet"><header class="doc-title"><h1>CONTESTAÇÃO DE VISTORIA</h1><p>COMPARATIVO ENTRE LAUDO DE ENTRADA E LAUDO DE SAÍDA</p></header>
<section class="details">
<div class="detail"><small>Inquilino</small><strong><?= $esc($item['nome_inquilino']) ?></strong></div>
<div class="detail"><small>CPF</small><strong><?= $esc($item['cpf_inquilino']?:'Não informado') ?></strong></div>
<div class="detail"><small>Número do contrato</small><strong><?= $esc($item['numero_contrato']?:'Não informado') ?></strong></div>
<div class="detail"><small>Fiança</small><strong><?= $esc($item['fianca']?:'Não informada') ?></strong></div>
<div class="detail"><small>Data da contestação</small><strong><?= $esc(date('d/m/Y',strtotime($item['data_contestacao']))) ?></strong></div>
<div class="detail address"><small>Endereço da locação</small><strong><?= $esc($item['endereco_locacao']) ?></strong></div>
</section>
<?php foreach($comodos as $i=>$comodo): $nome=trim((string)($comodo['nome']??''))?:'Cômodo '.($i+1); ?>
<section class="room"><h2><?= sprintf('%02d',$i+1) ?> — <?= $esc($nome) ?></h2><div class="columns">
<?php foreach(['entrada'=>'LAUDO DE ENTRADA','saida'=>'LAUDO DE SAÍDA'] as $lado=>$titulo): ?><div><div class="side-title <?= $lado==='entrada'?'entry':'exit' ?>"><?= $titulo ?></div><div class="photos">
<?php $ids=$porComodo[$i][$lado]??[]; if(!$ids): ?><div class="no-photo">Nenhuma foto adicionada</div><?php else: foreach($ids as $fid): ?><img src="contestacao_foto.php?id=<?= $id ?>&foto=<?= $fid ?>" alt="Foto <?= $esc($lado) ?>"><?php endforeach; endif; ?>
</div></div><?php endforeach; ?></div><div class="description"><strong>Descrição / justificativa</strong><?= nl2br($esc($comodo['descricao']??'—')) ?></div></section>
<?php endforeach; ?><div class="footer">Documento gerado pelo sistema Folha de Cálculo · Contestação #<?= $id ?></div></main></body></html>
