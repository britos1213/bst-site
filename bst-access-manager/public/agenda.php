<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/app/helpers/common.php';
require_once dirname(__DIR__).'/app/helpers/layout.php';

$pdo = db();

$turmas = [
    '1º ANO',
    '2º ANO',
    '3º ANO',
    '6º ANO',
    '7º ANO',
    '8º ANO',
    '9º ANO'
];

$dias = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo'
];

$turmaSelecionada = trim((string)($_GET['turma'] ?? '6º ANO'));

if (!in_array($turmaSelecionada, $turmas, true)) {
    $turmaSelecionada = '6º ANO';
}

/* Modo atual */
$st = $pdo->prepare("
    SELECT modo
    FROM controle_turmas
    WHERE turma = ?
");
$st->execute([$turmaSelecionada]);

$modo = $st->fetchColumn() ?: 'manual';

/* Agenda cadastrada */
$st = $pdo->prepare("
    SELECT *
    FROM agenda_turmas
    WHERE turma = ?
    ORDER BY dia_semana, hora_inicio
");
$st->execute([$turmaSelecionada]);

$agenda = $st->fetchAll();

$editarId = (int)($_GET['editar'] ?? 0);
$registroEditar = null;

if ($editarId > 0) {
    $stEditar = $pdo->prepare("
        SELECT *
        FROM agenda_turmas
        WHERE id = ?
        AND turma = ?
        LIMIT 1
    ");

    $stEditar->execute([
        $editarId,
        $turmaSelecionada
    ]);

    $registroEditar = $stEditar->fetch() ?: null;
}
page_start('Agenda das Turmas', 'agenda');
?>

<section class="panel">

<div class="panel-head">
    <div>
        <h2>Agenda Semanal</h2>
        <span style="font-size:11px;color:#94a1b3">
            Programação de acesso à Internet
        </span>
    </div>
</div>

<div class="panel-body">

<form method="get">

<label>Turma</label>

<select name="turma"
        onchange="this.form.submit()"
        style="
            background-color:#ffffff !important;
            color:#111111 !important;
            border:1px solid #cbd5e1;
        ">

<?php foreach ($turmas as $t): ?>

<option value="<?=h($t)?>"
    <?=$t === $turmaSelecionada ? 'selected' : ''?>>

    ALUNOS <?=h($t)?>

</option>

<?php endforeach; ?>

</select>
<style>
select[name="turma"] {
    background-color: #ffffff !important;
    color: #111111 !important;
}

select[name="turma"] option {
    background-color: #ffffff !important;
    color: #111111 !important;
}
</style>
</form>

<div style="margin-top:15px">

<div style="margin-bottom:12px">

    <strong>Modo atual:</strong>

    <?php if ($modo === 'automatico'): ?>

        <span class="badge approved">
            ● AUTOMÁTICO ATIVO
        </span>

    <?php else: ?>

        <span class="badge pending">
            ● MODO MANUAL
        </span>

    <?php endif; ?>

</div>

    <!-- ATIVAR AUTOMÁTICO -->
    <form method="post"
          action="/acoes/sincronizar-agenda.php"
          style="display:inline">

        <input type="hidden"
               name="turma"
               value="<?=h($turmaSelecionada)?>">

        <button class="btn btn-green"
                type="submit"
                onclick="return confirm('Ativar agenda automática desta turma no MikroTik?')">
            ATIVAR AUTOMÁTICO
        </button>

    </form>


    <!-- MODO MANUAL -->
    <form method="post"
          action="/acoes/modo-manual.php"
          style="display:inline">

        <input type="hidden"
               name="turma"
               value="<?=h($turmaSelecionada)?>">

        <button class="btn btn-dark"
                type="submit"
                onclick="return confirm('Desativar a agenda automática desta turma? Os agendamentos serão removidos do MikroTik.')">
            MODO MANUAL
        </button>

    </form>
</div>  <!-- fecha área dos botões -->

</div>  <!-- fecha panel-body -->

</section>


<section class="panel">

<div class="panel-head">

<h2>
    Horários — ALUNOS <?=h($turmaSelecionada)?>
</h2>

</div>

<div class="tablewrap">
<table>

<thead>

<tr>
    <th>Dia</th>
    <th>Libera Internet</th>
    <th>Bloqueia Internet</th>
    <th>Status</th>
    <th>Ação</th>
</tr>
</thead>

<tbody>

<?php if (!$agenda): ?>

<tr>
<td colspan="5">
    Nenhum horário cadastrado para esta turma.
</td>
</tr>

<?php endif; ?>


<?php foreach ($agenda as $a): ?>


<tr>

    <td>
        <?=h($dias[(int)$a['dia_semana']] ?? '—')?>
    </td>

    <td>
        <?=h(substr((string)$a['hora_inicio'], 0, 5))?>
    </td>

    <td>
        <?=h(substr((string)$a['hora_fim'], 0, 5))?>
    </td>

    <td>

        <?php if ((int)$a['ativo'] === 1): ?>

            <span class="badge approved">
                ATIVO
            </span>

        <?php else: ?>

            <span class="badge rejected">
                DESATIVADO
            </span>

        <?php endif; ?>

    </td>

    
</a>
<a class="btn btn-dark"
   href="/agenda.php?turma=<?=urlencode($turmaSelecionada)?>&editar=<?=h($a['id'])?>">
    EDITAR
</a>
        <form method="post"
              action="/acoes/excluir-agenda.php"
              style="display:inline"
              onsubmit="return confirm('Excluir este horário?')">

            <input type="hidden"
                   name="id"
                   value="<?=h($a['id'])?>">

            <input type="hidden"
                   name="turma"
                   value="<?=h($turmaSelecionada)?>">

            <button class="btn btn-red"
                    type="submit">
                EXCLUIR
            </button>

        </form>

    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</section>


<section class="panel">

<div class="panel-head">
<h2>
    <?=$registroEditar ? 'Editar horário' : 'Adicionar horário'?>
</h2>
</div>

<div class="panel-body">

<form method="post"
      action="/acoes/salvar-agenda.php">

<input type="hidden"
       name="turma"
       value="<?=h($turmaSelecionada)?>">
<?php if ($registroEditar): ?>

<input type="hidden"
       name="id"
       value="<?=h($registroEditar['id'])?>">

<?php endif; ?>


<label>Dia da semana</label>

<select name="dia_semana" required>

<?php foreach ($dias as $numero => $nome): ?>

<option value="<?=$numero?>">
    <?=h($nome)?>
<option value="<?=$numero?>"
    <?=($registroEditar && (int)$registroEditar['dia_semana'] === $numero) ? 'selected' : ''?>>
    <?=h($nome)?>
</option>

<?php endforeach; ?>

</select>


<label style="margin-top:12px">
    Liberar Internet
</label>

<input type="time"
       name="hora_inicio"
       value="<?=$registroEditar ? h(substr((string)$registroEditar['hora_inicio'],0,5)) : ''?>"
       required>

<label style="margin-top:12px">
    Bloquear Internet
</label>

<input type="time"
       name="hora_fim"
       value="<?=$registroEditar ? h(substr((string)$registroEditar['hora_fim'],0,5)) : ''?>"
       required>

<button class="btn btn-green"
        type="submit"
        style="margin-top:15px">
    <?=$registroEditar ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR HORÁRIO'?>
</button>

    ADICIONAR HORÁRIO

</button>

</form>

</div>

</section>

<div class="flash">
A agenda ainda não está comandando automaticamente o MikroTik.
Primeiro estamos configurando e validando os horários.
</div>

<?php page_end(); ?>