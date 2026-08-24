<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/config/database.php';

$turma = trim((string)($_POST['turma'] ?? ''));
$modo  = trim((string)($_POST['modo'] ?? ''));

$turmas = [
    '1º ANO',
    '2º ANO',
    '3º ANO',
    '6º ANO',
    '7º ANO',
    '8º ANO',
    '9º ANO'
];

if (
    !in_array($turma, $turmas, true) ||
    !in_array($modo, ['manual', 'automatico'], true)
) {
    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Dados inválidos.'
    ];

    header('Location: ../agenda.php');
    exit;
}

try {

    $pdo = db();

    $st = $pdo->prepare("
        UPDATE controle_turmas
        SET modo = ?
        WHERE turma = ?
    ");

    $st->execute([$modo, $turma]);

    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' => $modo === 'automatico'
            ? 'Modo automático ativado para ALUNOS '.$turma.'.'
            : 'Modo manual ativado para ALUNOS '.$turma.'.'
    ];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Não foi possível alterar o modo da turma.'
    ];
}

header(
    'Location: ../agenda.php?turma=' .
    urlencode($turma)
);

exit;