<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/config/database.php';

$turma = trim((string)($_POST['turma'] ?? ''));
$id    = (int)($_POST['id'] ?? 0);

$dia   = (int)($_POST['dia_semana'] ?? 0);

$inicio = trim((string)($_POST['hora_inicio'] ?? ''));
$fim    = trim((string)($_POST['hora_fim'] ?? ''));

$permitidas = [
    '1º ANO',
    '2º ANO',
    '3º ANO',
    '6º ANO',
    '7º ANO',
    '8º ANO',
    '9º ANO'
];

if (
    !in_array($turma, $permitidas, true) ||
    $dia < 1 ||
    $dia > 7 ||
    $inicio === '' ||
    $fim === ''
) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Dados da agenda inválidos.'
    ];

    header('Location: ../agenda.php');
    exit;
}

/*
 * Nesta primeira versão não permitimos horário final
 * menor ou igual ao inicial.
 */
if ($fim <= $inicio) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'O horário de bloqueio deve ser posterior ao horário de liberação.'
    ];

    header(
        'Location: ../agenda.php?turma='.
        urlencode($turma)
    );

    exit;
}

try {

    $pdo = db();

  if ($id > 0) {

    // EDITAR horário existente
    $st = $pdo->prepare("
        UPDATE agenda_turmas
        SET dia_semana = ?,
            hora_inicio = ?,
            hora_fim = ?
        WHERE id = ?
        AND turma = ?
    ");

    $st->execute([
        $dia,
        $inicio,
        $fim,
        $id,
        $turma
    ]);

    $mensagemSucesso = 'Horário alterado com sucesso.';

} else {

    // ADICIONAR novo horário
    $st = $pdo->prepare("
        INSERT INTO agenda_turmas
        (
            turma,
            dia_semana,
            hora_inicio,
            hora_fim,
            ativo
        )
        VALUES (?, ?, ?, ?, 1)
    ");

    $st->execute([
        $turma,
        $dia,
        $inicio,
        $fim
    ]);

    $mensagemSucesso = 'Horário adicionado com sucesso.';
}

/*
 * Verifica se a turma está em modo automático.
 */
$stModo = $pdo->prepare("
    SELECT modo
    FROM controle_turmas
    WHERE turma = ?
");

$stModo->execute([$turma]);

$modo = $stModo->fetchColumn() ?: 'manual';

/*
 * Se estiver automático, sincroniza novamente
 * todos os horários dessa turma com o MikroTik.
 */
if ($modo === 'automatico') {

    header(
        'Location: sincronizar-agenda.php?turma=' .
        urlencode($turma)
    );

    exit;
}
    $_SESSION['flash'] = [
    'type' => 'approved',
    'message' => $mensagemSucesso
];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Não foi possível salvar o horário.'
    ];
}

header(
    'Location: ../agenda.php?turma='.
    urlencode($turma)
);

exit;