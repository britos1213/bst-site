<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/app/helpers/common.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$turma = trim((string)($_POST['turma'] ?? ''));
$acao  = trim((string)($_POST['acao'] ?? ''));

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
    header('Location: ../../public/turmas.php');
    exit;
}

if (!in_array($acao, ['liberar', 'bloquear'], true)) {
    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Ação inválida.'
    ];
    header('Location: ../../public/turmas.php');
    exit;
}

$comentario = 'BLOQUEIO INTERNET - ALUNOS '.$turma;

try {

    $mt = require dirname(__DIR__,2).'/config/mikrotik.php';

    $client = new Client(new Config([
        'host'     => $mt['host'],
        'user'     => $mt['user'],
        'pass'     => $mt['pass'],
        'port'     => $mt['port'],
        'timeout'  => 4,
        'attempts' => 1
    ]));

    $query = (new Query('/ip/firewall/filter/print'))
        ->where('comment', $comentario);

    $regras = $client->query($query)->read();

    if (empty($regras)) {
        throw new RuntimeException('Regra da turma não encontrada no MikroTik.');
    }

    $id = $regras[0]['.id'] ?? null;

    if (!$id) {
        throw new RuntimeException('ID da regra não encontrado.');
    }

    if ($acao === 'bloquear') {

        $query = (new Query('/ip/firewall/filter/enable'))
            ->equal('.id', $id);

        $client->query($query)->read();

        $mensagem = 'Internet do '.$turma.' bloqueada com sucesso.';

    } else {

        $query = (new Query('/ip/firewall/filter/disable'))
            ->equal('.id', $id);

        $client->query($query)->read();

        $mensagem = 'Internet do '.$turma.' liberada com sucesso.';
    }

    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' => $mensagem
    ];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Erro ao alterar o MikroTik: '.$e->getMessage()
    ];
}

header('Location: ../turmas.php');
exit;