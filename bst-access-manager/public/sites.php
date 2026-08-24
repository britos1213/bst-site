<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/helpers/auth.php'; auth_require();
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/common.php';

$pdo=db();$rows=$pdo->query("SELECT * FROM sites_bloqueados ORDER BY criado_em DESC")->fetchAll();
$f=$_SESSION['flash']??null;unset($_SESSION['flash']);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sites - BST Access Manager</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><div class="content" style="max-width:1100px;margin:auto">
<h1>Bloqueio de Sites</h1><p style="color:#98a5b7">BST Access Manager Enterprise</p>
<?php if($f):?><div class="flash <?=h($f['type'])?>"><?=h($f['message'])?></div><?php endif;?>
<div class="panel"><div class="panel-head"><h2>Novo bloqueio</h2><a class="btn btn-dark" href="index.php">VOLTAR</a></div><div class="panel-body">
<form method="post" action="../modules/sites/bloquear.php">
<label>Domínio</label><input name="dominio" placeholder="Ex.: facebook.com" required>
<label>Descrição</label><input name="descricao" placeholder="Opcional">
<button class="btn btn-red" style="margin-top:10px" type="submit">BLOQUEAR</button>
</form></div></div>
<div class="panel"><div class="panel-head"><h2>Lista de bloqueios</h2></div><div class="tablewrap"><table><thead><tr><th>Domínio</th><th>Descrição</th><th>Status</th><th>Ação</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=h($r['dominio'])?></td><td><?=h($r['descricao'])?></td><td><span class="badge <?=((int)$r['ativo']===1)?'approved':'rejected'?>"><?=((int)$r['ativo']===1)?'BLOQUEADO':'REMOVIDO'?></span></td><td>
<?php if((int)$r['ativo']===1):?><form method="post" action="../modules/sites/remover.php"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button class="btn btn-red">REMOVER</button></form><?php else:?>—<?php endif;?>
</td></tr><?php endforeach;?></tbody></table></div></div>
</div></body></html>
