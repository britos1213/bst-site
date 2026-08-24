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

$turmas = [
    '1º ANO',
    '2º ANO',
    '3º ANO',
    '6º ANO',
    '7º ANO',
    '8º ANO',
    '9º ANO'
];

$dados = [];
$routerStatus = false;
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

    // Regras do firewall
    $regras = $client
        ->query(new Query('/ip/firewall/filter/print'))
        ->read();

    // Usuários atualmente conectados ao Hotspot
    $ativos = $client
        ->query(new Query('/ip/hotspot/active/print'))
        ->read();

    foreach ($turmas as $turma) {

        $comentario = 'BLOQUEIO INTERNET - ALUNOS '.$turma;

        $regraEncontrada = null;

        foreach ($regras as $regra) {
            if (($regra['comment'] ?? '') === $comentario) {
                $regraEncontrada = $regra;
                break;
            }
        }

        if ($regraEncontrada === null) {

            $dados[] = [
                'turma'      => $turma,
                'status'     => 'SEM REGRA',
                'bloqueada'  => null,
                'conectados' => 0
            ];

            continue;
        }

        /*
         * Regra DROP:
         *
         * disabled=true  -> regra desligada -> INTERNET LIBERADA
         * disabled=false -> regra ativa     -> INTERNET BLOQUEADA
         */

        $disabled = ($regraEncontrada['disabled'] ?? 'false') === 'true';
        $bloqueada = !$disabled;

        /*
         * Por enquanto não vamos tentar associar cada conexão
         * à turma até validarmos como os perfis aparecem no
         * /ip/hotspot/active do seu MikroTik.
         */
        $conectados = 0;

        $dados[] = [
            'turma'      => $turma,
            'status'     => $bloqueada ? 'BLOQUEADA' : 'LIBERADA',
            'bloqueada'  => $bloqueada,
            'conectados' => $conectados
        ];
    }

} catch (Throwable $e) {

    $erro = $e->getMessage();
}

page_start('Turmas e Internet', 'turmas');
?>

<section class="panel">

<div class="panel-head">
    <div>
        <h2>Controle de Internet por Turma</h2>
        <span style="font-size:11px;color:#94a1b3">
            Estado atual consultado diretamente no MikroTik
        </span>
    </div>

    <span class="badge <?=$routerStatus ? 'approved' : 'rejected'?>">
        <?=$routerStatus ? 'MIKROTIK ONLINE' : 'MIKROTIK OFFLINE'?>
    </span>
</div>

<?php if (!$routerStatus): ?>

<div class="flash rejected">
    Não foi possível consultar o MikroTik.
</div>

<?php else: ?>

<div class="tablewrap">

<table>

<thead>
<tr>
    <th>Turma</th>
    <th>Internet</th>
    <th>Conectados</th>
    <th>Controle</th>
</tr>
</thead>

<tbody>

<?php foreach ($dados as $t): ?>

<tr>

<td>
    <strong>ALUNOS <?=$t['turma']?></strong>
</td>

<td>

<?php if ($t['status'] === 'LIBERADA'): ?>

    <span class="badge approved">
        ● LIBERADA
    </span>

<?php elseif ($t['status'] === 'BLOQUEADA'): ?>

    <span class="badge rejected">
        ● BLOQUEADA
    </span>

<?php else: ?>

    <span class="badge pending">
        SEM REGRA
    </span>

<?php endif; ?>

</td>

<td>
    <?=$t['conectados']?>
</td>

<td>

<td>

<form method="post"
      action="acoes/controle-turma.php"
      style="display:inline">

    <input type="hidden"
           name="turma"
           value="<?=h($t['turma'])?>">

    <input type="hidden"
           name="acao"
           value="liberar">

    <button class="btn btn-green"
            type="submit"
            onclick="return confirm('Liberar internet para ALUNOS <?=h($t['turma'])?>?')">
        LIBERAR
    </button>
</form>


<form method="post"
      action="acoes/controle-turma.php"
      style="display:inline">

    <input type="hidden"
           name="turma"
           value="<?=h($t['turma'])?>">

    <input type="hidden"
           name="acao"
           value="bloquear">

    <button class="btn btn-red"
            type="submit"
            onclick="return confirm('Bloquear internet para ALUNOS <?=h($t['turma'])?>?')">
        BLOQUEAR
    </button>
</form>

<!-- AUTOMÁTICO -->
    <a class="btn btn-dark"
       href="/agenda.php?turma=<?=urlencode($t['turma'])?>">
        AUTOMÁTICO
    </a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>

<div class="flash">
    Nesta primeira etapa os controles estão desativados.
    Estamos apenas conferindo o estado real das regras do MikroTik.
</div>


<div class="modal">

   

   

</div>

</div>

<script>


    document.getElementById('modalAutomatico').style.display = 'none';
}

</script>
<?php page_end(); ?>