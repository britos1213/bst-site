<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$turma = trim((string)($_POST['turma'] ?? ''));

$permitidas = [
    '1º ANO',
    '2º ANO',
    '3º ANO',
    '6º ANO',
    '7º ANO',
    '8º ANO',
    '9º ANO'
];

if (!in_array($turma, $permitidas, true)) {
    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Turma inválida.'
    ];

    header('Location: ../agenda.php');
    exit;
}

try {

    $pdo = db();

    /*
     * Número da turma.
     * Ex.: "6º ANO" -> "6"
     */
    $numeroTurma = preg_replace('/\D+/', '', $turma);

    $prefixo = 'BST-'.$numeroTurma.'ANO-';

    /*
     * Conecta ao MikroTik.
     */
    $mt = require dirname(__DIR__,2).'/config/mikrotik.php';

    $client = new Client(new Config([
        'host'     => $mt['host'],
        'user'     => $mt['user'],
        'pass'     => $mt['pass'],
        'port'     => $mt['port'],
        'timeout'  => 5,
        'attempts' => 1
    ]));

    /*
     * Busca os Scheduler existentes.
     */
    $schedulers = $client
        ->query(new Query('/system/scheduler/print'))
        ->read();

    $removidos = 0;

    foreach ($schedulers as $scheduler) {

        $nome = (string)($scheduler['name'] ?? '');

        /*
         * Remove somente Scheduler pertencentes
         * à turma selecionada.
         */
        if (str_starts_with($nome, $prefixo)) {

            $id = $scheduler['.id'] ?? null;

            if ($id) {

                $q = (new Query('/system/scheduler/remove'))
                    ->equal('.id', $id);

                $client->query($q)->read();

                $removidos++;
            }
        }
    }

    /*
     * Atualiza o banco.
     */
    $st = $pdo->prepare("
        UPDATE controle_turmas
        SET modo = 'manual'
        WHERE turma = ?
    ");

    $st->execute([$turma]);

    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' =>
            'Modo manual ativado para ALUNOS '.$turma.
            '. '.$removidos.' agendamento(s) removido(s) do MikroTik.'
    ];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' =>
            'Erro ao ativar modo manual: '.$e->getMessage()
    ];
}

header(
    'Location: ../agenda.php?turma='.
    urlencode($turma)
);

exit;