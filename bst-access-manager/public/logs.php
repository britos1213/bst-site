<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/helpers/auth.php'; auth_require();
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/layout.php';
$rows=db()->query("SELECT l.*,a.nome admin_nome FROM logs_admin l LEFT JOIN administradores a ON a.id=l.admin_id ORDER BY l.criado_em DESC LIMIT 300")->fetchAll();
page_start('Logs','logs');
?>
<section class="panel"><div class="panel-head"><h2>Auditoria administrativa</h2></div>
<div class="tablewrap"><table><thead><tr><th>Data</th><th>Administrador</th><th>Ação</th><th>Detalhes</th><th>IP</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=h($r['criado_em'])?></td><td><?=h($r['admin_nome'])?></td><td><?=h($r['acao'])?></td><td><?=h($r['detalhes'])?></td><td><?=h($r['ip_origem'])?></td></tr><?php endforeach;?>
</tbody></table></div></section>
<?php page_end(); ?>
