<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/layout.php';

$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $st = db()->prepare("
        SELECT *
        FROM solicitacoes
        WHERE status = 'aprovada'
        AND (nome LIKE ? OR cpf LIKE ? OR email LIKE ?)
        ORDER BY nome
    ");
    $like = '%'.$q.'%';
    $st->execute([$like, $like, $like]);
    $rows = $st->fetchAll();
} else {
    $rows = db()->query("
        SELECT *
        FROM solicitacoes
        WHERE status = 'aprovada'
        ORDER BY nome
    ")->fetchAll();
}

page_start('Usuários', 'usuarios');
?>
<section class="panel">

<div class="panel-head">
    <h2>Usuários aprovados</h2>

    <form class="searchbar" method="get">
        <input name="q"
               value="<?=h($q)?>"
               placeholder="Pesquisar nome, CPF ou e-mail">
        <button class="btn btn-blue" type="submit">
            PESQUISAR
        </button>
    </form>
</div>

<div class="tablewrap">

<table>

<thead>
<tr>
    <th>Nome</th>
    <th>CPF</th>
    <th>Setor / Tipo</th>
    <th>Turma</th>
    <th>VLAN</th>
    <th>Telefone</th>
    <th>Status</th>
    <th>Ações</th>
</tr>
</thead>

<tbody>

<?php if (!$rows): ?>
<tr>
    <td colspan="8">Nenhum usuário aprovado encontrado.</td>
</tr>
<?php endif; ?>

<?php foreach ($rows as $r): ?>

<?php
$tipo = trim((string)($r['tipo_solicitado'] ?? ''));
if ($tipo === '') {
    $tipo = trim((string)($r['setor'] ?? ''));
}

$turma = trim((string)($r['turma'] ?? ''));

$vlan = (int)($r['vlan_aprovada'] ?? 0);
if ($vlan <= 0) {
    $vlan = (int)($r['vlan_id'] ?? 0);
}
?>

<tr>

<td>
    <strong><?=h((string)($r['nome'] ?? ''))?></strong>
    <?php if (!empty($r['email'])): ?>
        <br>
        <span style="color:#7f8da0">
            <?=h((string)$r['email'])?>
        </span>
    <?php endif; ?>
</td>

<td><?=h((string)($r['cpf'] ?? ''))?></td>

<td><?=h($tipo !== '' ? $tipo : '—')?></td>

<td><?=h($turma !== '' ? $turma : '—')?></td>

<td>
    <?php if ($vlan > 0): ?>
        <span class="badge approved">VLAN <?=$vlan?></span>
    <?php else: ?>
        <span class="badge pending">VLAN 0</span>
    <?php endif; ?>
</td>

<td><?=h((string)($r['telefone'] ?? ''))?></td>

<td>
    <span class="badge approved">APROVADO</span>
</td>

<td>
    <form method="post"
          action="/acoes/remover-hotspot.php"
          style="display:inline"
          onsubmit="return confirm('Remover este usuário do Hotspot MikroTik? Ele será desconectado e não poderá mais fazer login até novo cadastro/aprovação.')">

        <input type="hidden"
               name="cpf"
               value="<?=h((string)($r['cpf'] ?? ''))?>">

        <button class="btn btn-red" type="submit">
            REMOVER DO HOTSPOT
        </button>
    </form>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</section>

<div class="flash">
    A remoção exclui o login do MikroTik e derruba a sessão ativa, mas mantém o histórico do cadastro no banco.
</div>

<?php page_end(); ?>
