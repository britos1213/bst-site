<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/common.php';
require_once dirname(__DIR__).'/app/helpers/layout.php';

$pdo = db();

/*
 * Filtro da tela
 */
$filtro = trim((string)($_GET['status'] ?? 'pendente'));

$permitidos = [
    'pendente',
    'aprovada',
    'rejeitada',
    'todas'
];

if (!in_array($filtro, $permitidos, true)) {
    $filtro = 'pendente';
}

/*
 * Contadores
 */
$contPendentes = (int)$pdo->query("
    SELECT COUNT(*)
    FROM solicitacoes
    WHERE status = 'pendente'
")->fetchColumn();

$contAprovadas = (int)$pdo->query("
    SELECT COUNT(*)
    FROM solicitacoes
    WHERE status = 'aprovada'
")->fetchColumn();

$contRejeitadas = (int)$pdo->query("
    SELECT COUNT(*)
    FROM solicitacoes
    WHERE status = 'rejeitada'
")->fetchColumn();

$contTodas = (int)$pdo->query("
    SELECT COUNT(*)
    FROM solicitacoes
")->fetchColumn();

/*
 * Registros conforme o filtro
 */
if ($filtro === 'todas') {

    $rows = $pdo->query("
        SELECT *
        FROM solicitacoes
        ORDER BY
            CASE status
                WHEN 'pendente' THEN 0
                WHEN 'aprovada' THEN 1
                WHEN 'rejeitada' THEN 2
                ELSE 3
            END,
            criado_em DESC
    ")->fetchAll();

} else {

    $st = $pdo->prepare("
        SELECT *
        FROM solicitacoes
        WHERE status = ?
        ORDER BY criado_em DESC
    ");

    $st->execute([$filtro]);
    $rows = $st->fetchAll();
}

page_start('Solicitações', 'solicitacoes');
?>

<div style="margin-bottom:15px;display:flex;gap:8px;flex-wrap:wrap">

    <a class="btn <?=$filtro==='pendente'?'btn-blue':'btn-dark'?>"
       href="solicitacoes.php?status=pendente">
        PENDENTES (<?=$contPendentes?>)
    </a>

    <a class="btn <?=$filtro==='aprovada'?'btn-green':'btn-dark'?>"
       href="solicitacoes.php?status=aprovada">
        APROVADAS (<?=$contAprovadas?>)
    </a>

    <a class="btn <?=$filtro==='rejeitada'?'btn-red':'btn-dark'?>"
       href="solicitacoes.php?status=rejeitada">
        REJEITADAS (<?=$contRejeitadas?>)
    </a>

    <a class="btn <?=$filtro==='todas'?'btn-blue':'btn-dark'?>"
       href="solicitacoes.php?status=todas">
        TODAS (<?=$contTodas?>)
    </a>

</div>


<section class="panel">

<div class="panel-head">
    <div>
        <h2>Cadastros recebidos</h2>
        <p style="color:#94a1b3;font-size:11px;margin:4px 0 0">
            Solicitações de acesso ao Hotspot
        </p>
    </div>
</div>

<div class="tablewrap">

<table>

<thead>
<tr>
    <th>Nome</th>
    <th>CPF</th>
    <th>Tipo</th>
    <th>Turma</th>
    <th>VLAN</th>
    <th>Criado em</th>
    <th>Status</th>
    <th>Ações</th>
</tr>
</thead>

<tbody>

<?php if (!$rows): ?>

<tr>
    <td colspan="8">
        Nenhuma solicitação encontrada neste filtro.
    </td>
</tr>

<?php endif; ?>


<?php foreach ($rows as $r): ?>

<?php
/*
 * Define automaticamente o perfil e a VLAN.
 */
$tipo  = strtoupper(trim((string)($r['tipo_solicitado'] ?? '')));
$turma = strtoupper(trim((string)($r['turma'] ?? '')));

$perfilAprovar = 'VISITANTE';
$vlanAprovar   = 10;

if (str_contains($tipo, 'PROFESSOR')) {

    $perfilAprovar = 'PROFESSOR';
    $vlanAprovar   = 30;

} elseif (str_contains($tipo, 'ALUNO')) {

    $configTurmas = [
        '1º ANO' => [
            'perfil' => 'ALUNOS 1º ANO',
            'vlan'    => 70
        ],
        '2º ANO' => [
            'perfil' => 'ALUNOS 2º ANO',
            'vlan'    => 80
        ],
        '3º ANO' => [
            'perfil' => 'ALUNOS 3º ANO',
            'vlan'    => 90
        ],
        '6º ANO' => [
            'perfil' => 'ALUNOS 6º ANO',
            'vlan'    => 100
        ],
        '7º ANO' => [
            'perfil' => 'ALUNOS 7º ANO',
            'vlan'    => 110
        ],
        '8º ANO' => [
            'perfil' => 'ALUNOS 8º ANO',
            'vlan'    => 120
        ],
        '9º ANO' => [
            'perfil' => 'ALUNOS 9º ANO',
            'vlan'    => 130
        ],
    ];

    if (isset($configTurmas[$turma])) {

        $perfilAprovar = $configTurmas[$turma]['perfil'];
        $vlanAprovar   = $configTurmas[$turma]['vlan'];

    } else {

        $perfilAprovar = 'ALUNO';
        $vlanAprovar   = 0;
    }
}

/*
 * Para aprovado, mostra a VLAN efetivamente gravada.
 * Para pendente/rejeitado, mostra a VLAN calculada.
 */
if (($r['status'] ?? '') === 'aprovada') {
    $vlanExibir = (int)($r['vlan_aprovada'] ?? 0);
} else {
    $vlanExibir = $vlanAprovar;
}

$status = (string)($r['status'] ?? '');

if ($status === 'aprovada') {
    $classeStatus = 'approved';
} elseif ($status === 'rejeitada') {
    $classeStatus = 'rejected';
} else {
    $classeStatus = 'pending';
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

<td>
    <?=h((string)($r['cpf'] ?? ''))?>
</td>

<td>
    <?=h((string)($r['tipo_solicitado'] ?? $r['setor'] ?? '—'))?>
</td>

<td>
    <?=h((string)($r['turma'] ?? '—'))?>
</td>

<td>
    <?php if ($vlanExibir > 0): ?>
        <span class="badge approved">
            VLAN <?=$vlanExibir?>
        </span>
    <?php else: ?>
        <span class="badge pending">
            VLAN 0
        </span>
    <?php endif; ?>
</td>

<td>
    <?=h((string)($r['criado_em'] ?? ''))?>
</td>

<td>
    <span class="badge <?=$classeStatus?>">
        <?=h(strtoupper($status))?>
    </span>
</td>

<td>

<?php if ($status === 'pendente'): ?>

    <form method="post"
          action="/acoes/aprovar.php"
          style="display:inline">

        <input type="hidden"
               name="id"
               value="<?=h((string)$r['id'])?>">

        <input type="hidden"
               name="perfil"
               value="<?=h($perfilAprovar)?>">

        <input type="hidden"
               name="vlan"
               value="<?=$vlanAprovar?>">

        <button class="btn btn-green"
                type="submit"
                onclick="return confirm('Aprovar este usuário e liberar no MikroTik?')">
            APROVAR
        </button>

    </form>


    <form method="post"
          action="/acoes/rejeitar.php"
          style="display:inline"
          onsubmit="return confirm('Rejeitar esta solicitação?')">

        <input type="hidden"
               name="id"
               value="<?=h((string)$r['id'])?>">

        <button class="btn btn-red"
                type="submit">
            REJEITAR
        </button>

    </form>

<?php else: ?>

    —

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>

</section>

<?php page_end(); ?>
