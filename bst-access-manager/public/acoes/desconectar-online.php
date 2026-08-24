<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

if (!auth_has_role(['admin','coordenacao'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

$id = trim((string)($_POST['id'] ?? ''));

if ($id === '') {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Sessão inválida.'
    ];

    header('Location: ../online.php');
    exit;
}

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

    $q = (new Query('/ip/hotspot/active/remove'))
        ->equal('.id', $id);

    $client->query($q)->read();

    $_SESSION['flash'] = [
        'type' => 'approved',
        'message' => 'Usuário desconectado com sucesso.'
    ];

} catch (Throwable $e) {

    $_SESSION['flash'] = [
        'type' => 'rejected',
        'message' => 'Não foi possível desconectar o usuário.'
    ];
}

header('Location: ../online.php');
exit;