<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/config/database.php';

$id = (int)($_POST['id'] ?? 0);
$turma = trim((string)($_POST['turma'] ?? ''));

if ($id <= 0 || $turma === '') {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Horário inválido.'
    ];

    header('Location: ../agenda.php');
    exit;
}

try {

    $pdo = db();

    /*
     * Exclui somente o horário pertencente
     * à turma informada.
     */
    $st = $pdo->prepare("
        DELETE FROM agenda_turmas
        WHERE id = ?
        AND turma = ?
    ");

    $st->execute([
        $id,
        $turma
    ]);

    /*
     * Verifica o modo atual da turma.
     */
    $stModo = $pdo->prepare("
        SELECT modo
        FROM controle_turmas
        WHERE turma = ?
        LIMIT 1
    ");

    $stModo->execute([$turma]);

    $modo = $stModo->fetchColumn() ?: 'manual';

    /*
     * AUTOMÁTICO:
     *
     * Sempre chama a sincronização.
     *
     * Mesmo se o horário excluído for o último,
     * sincronizar-agenda.php removerá os Scheduler
     * antigos do MikroTik.
     */
    if ($modo === 'automatico') {

        header(
            'Location: sincronizar-agenda.php?turma=' .
            urlencode($turma)
        );

        exit;
    }

    /*
     * MANUAL:
     * apenas remove do banco.
     */
    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' => 'Horário excluído com sucesso.'
    ];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Não foi possível excluir o horário.'
    ];
}

header(
    'Location: ../agenda.php?turma=' .
    urlencode($turma)
);

exit;