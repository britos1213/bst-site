<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$turma = trim((string)(
    $_POST['turma']
    ?? $_GET['turma']
    ?? ''
));

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
     * Busca somente horários ativos da turma.
     */
    $st = $pdo->prepare("
        SELECT *
        FROM agenda_turmas
        WHERE turma = ?
        AND ativo = 1
        ORDER BY dia_semana, hora_inicio
    ");

    $st->execute([$turma]);
    $agenda = $st->fetchAll();

  

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
     * Prefixo usado para identificar Scheduler criados pelo BST.
     *
     * Exemplo:
     * BST-6ANO-
     */
   preg_match('/\d+/', $turma, $m);

$numeroTurma = $m[0] ?? '';

if ($numeroTurma === '') {
    throw new RuntimeException('Número da turma não identificado.');
}

$prefixo = 'BST-'.$numeroTurma.'ANO-';
    /*
     * Remove Scheduler antigos dessa turma.
     */
    $schedulers = $client
        ->query(new Query('/system/scheduler/print'))
        ->read();

    foreach ($schedulers as $scheduler) {


        $nome = (string)($scheduler['name'] ?? '');

        if (str_starts_with($nome, $prefixo)) {

            $id = $scheduler['.id'] ?? null;

            if ($id) {
                $q = (new Query('/system/scheduler/remove'))
                    ->equal('.id', $id);

                $client->query($q)->read();
            }
        }
    }

/*
 * Se não existem mais horários ativos,
 * os Scheduler antigos já foram removidos.
 */
if (!$agenda) {

    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' =>
            'Agenda de ALUNOS '.$turma.
            ' removida do MikroTik. Nenhum horário ativo restante.'
    ];

    header(
        'Location: ../agenda.php?turma=' .
        urlencode($turma)
    );

    exit;
}

    /*
     * MikroTik usa:
     * sun, mon, tue, wed, thu, fri, sat
     */
    $dias = [
        1 => 'mon',
        2 => 'tue',
        3 => 'wed',
        4 => 'thu',
        5 => 'fri',
        6 => 'sat',
        7 => 'sun'
    ];

    /*
     * Precisamos calcular a próxima ocorrência de cada
     * dia para preencher start-date corretamente.
     */
    $nomesDiasPHP = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday'
    ];

    date_default_timezone_set('America/Sao_Paulo');

    foreach ($agenda as $a) {

        $diaNumero = (int)$a['dia_semana'];

        if (!isset($dias[$diaNumero])) {
            continue;
        }

        $dia = $dias[$diaNumero];

        $inicio = substr(
            (string)$a['hora_inicio'],
            0,
            8
        );

        $fim = substr(
            (string)$a['hora_fim'],
            0,
            8
        );

        /*
         * Calcula a próxima data correspondente ao dia.
         */
       $agora = new DateTimeImmutable(
    'now',
    new DateTimeZone('America/Sao_Paulo')
);

$hojeNumero = (int)$agora->format('N');

$diferenca = $diaNumero - $hojeNumero;

if ($diferenca < 0) {
    $diferenca += 7;
}

/*
 * Se o agendamento for para hoje, verificamos
 * se aquele horário já passou.
 *
 * Se já passou, a primeira execução será
 * na mesma data da semana seguinte.
 */
if ($diferenca === 0) {

    $horaAtual = $agora->format('H:i:s');

    /*
     * Usamos o menor horário do período,
     * pois o Scheduler LIBERAR precisa ocorrer primeiro.
     */
    if ($inicio <= $horaAtual) {
        $diferenca = 7;
    }
}

$dataInicial = $agora->modify("+{$diferenca} days");

/*
 * Formato aceito pelo RouterOS:
 * aug/17/2026
 */
$dataExecucao = strtolower(
    $dataInicial->format('M/d/Y')

        );

        /*
         * Nome dos Scheduler.
         */
        $horaNomeInicio = str_replace(':', '', substr($inicio,0,5));
        $horaNomeFim    = str_replace(':', '', substr($fim,0,5));

        $nomeLiberar =
            $prefixo.
            strtoupper($dia).
            '-LIBERAR-'.
            $horaNomeInicio;

        $nomeBloquear =
            $prefixo.
            strtoupper($dia).
            '-BLOQUEAR-'.
            $horaNomeFim;

        /*
         * Procuramos a regra usando apenas "ALUNOS 6",
         * evitando problemas com o caractere º.
         */
        $busca = 'ALUNOS '.$numeroTurma;

        $scriptLiberar =
            ':local r [/ip firewall filter find where comment~="'.
            $busca.
            '"]; '.
            ':if ([:len $r] > 0) do={ '.
            '/ip firewall filter disable $r }';

        $scriptBloquear =
            ':local r [/ip firewall filter find where comment~="'.
            $busca.
            '"]; '.
            ':if ([:len $r] > 0) do={ '.
            '/ip firewall filter enable $r }';

        /*
         * LIBERAR
         */
        $q = (new Query('/system/scheduler/add'))
            ->equal('name', $nomeLiberar)
            ->equal('start-date', $dataExecucao)
            ->equal('start-time', $inicio)
            ->equal('interval', '7d')
            ->equal('on-event', $scriptLiberar)
            ->equal('comment', 'BST Access Manager');

        $client->query($q)->read();

        /*
         * BLOQUEAR
         */
        $q = (new Query('/system/scheduler/add'))
            ->equal('name', $nomeBloquear)
            ->equal('start-date', $dataExecucao)
            ->equal('start-time', $fim)
            ->equal('interval', '7d')
            ->equal('on-event', $scriptBloquear)
            ->equal('comment', 'BST Access Manager');

        $client->query($q)->read();
    }

    /*
     * Coloca a turma em automático.
     */
    $st = $pdo->prepare("
        UPDATE controle_turmas
        SET modo = 'automatico'
        WHERE turma = ?
    ");

    $st->execute([$turma]);

    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' =>
            'Agenda de ALUNOS '.$turma.
            ' sincronizada com o MikroTik.'
    ];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' =>
            'Erro na sincronização: '.$e->getMessage()
    ];
}

header(
    'Location: ../agenda.php?turma='.
    urlencode($turma)
);

exit;