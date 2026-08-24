<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__).'/app/helpers/common.php';
require_once dirname(__DIR__).'/app/helpers/layout.php';
require_once dirname(__DIR__).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$routerStatus = false;
$ativos = [];
$perfis = [];
$erro = '';

try {

    $mt = require dirname(__DIR__).'/config/mikrotik.php';

    $client = new Client(new Config([
        'host'     => $mt['host'],
        'user'     => $mt['user'],
        'pass'     => $mt['pass'],
        'port'     => $mt['port'],
        'timeout'  => 4,
        'attempts' => 1
    ]));

    $routerStatus = true;

    /*
     * Usuários conectados agora.
     */
    $ativos = $client
        ->query(new Query('/ip/hotspot/active/print'))
        ->read();

    /*
     * Busca os usuários cadastrados para descobrir
     * o Profile de cada usuário.
     */
    $usuarios = $client
        ->query(new Query('/ip/hotspot/user/print'))
        ->read();

    foreach ($usuarios as $u) {

        $nome = (string)($u['name'] ?? '');

        if ($nome !== '') {
            $perfis[$nome] = $u['profile'] ?? 'default';
        }
    }

} catch (Throwable $e) {

    $erro = $e->getMessage();
}

page_start('Usuários Online', 'online');
?>

<section class="panel">

<div class="panel-head">

    <div>
        <h2>Usuários Online</h2>

        <span style="font-size:11px;color:#94a1b3">
            Sessões ativas no Hotspot MikroTik
        </span>
    </div>

    <span class="badge <?=$routerStatus ? 'approved' : 'rejected'?>">
        <?=$routerStatus ? '● MIKROTIK ONLINE' : '● MIKROTIK OFFLINE'?>
    </span>

</div>


<?php if (!$routerStatus): ?>

<div class="panel-body">

    <div class="flash rejected">
        Não foi possível consultar o MikroTik.
        <?=h($erro)?>
    </div>

</div>

<?php else: ?>

<div class="panel-body">

    <strong>
        <?=count($ativos)?>
    </strong>
    usuário(s) conectado(s)

</div>


<div class="tablewrap">

<table>

<thead>
<tr>
    <th>Usuário</th>
    <th>Perfil</th>
    <th>IP</th>
    <th>MAC</th>
    <th>Uptime</th>
    <th>Login</th>
    <th>Ação</th>
</tr>
</thead>

<tbody>

<?php if (!$ativos): ?>

<tr>
    <td colspan="7">
        Nenhum usuário conectado no momento.
    </td>
</tr>

<?php endif; ?>


<?php foreach ($ativos as $a): ?>

<?php
    $usuario = (string)($a['user'] ?? '');
    $idAtivo = (string)($a['.id'] ?? '');
?>

<tr>

<td>
    <strong><?=h($usuario)?></strong>
</td>

<td>
    <span class="badge pending">
        <?=h($perfis[$usuario] ?? '—')?>
    </span>
</td>

<td>
    <?=h($a['address'] ?? '—')?>
</td>

<td>
    <?=h($a['mac-address'] ?? '—')?>
</td>

<td>
    <?=h($a['uptime'] ?? '—')?>
</td>

<td>
    <?=h($a['login-by'] ?? '—')?>
</td>

<td>

<?php if (auth_has_role(['admin','coordenacao'])): ?>

<form method="post"
      action="/acoes/desconectar-online.php"
      style="display:inline"
      onsubmit="return confirm('Desconectar <?=h($usuario)?> do Hotspot?')">

    <input type="hidden"
           name="id"
           value="<?=h($idAtivo)?>">

    <button class="btn btn-red"
            type="submit">
        DESCONECTAR
    </button>

</form>

<?php else: ?>

<span style="color:#94a1b3">
    Somente consulta
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

<?php page_end(); ?>