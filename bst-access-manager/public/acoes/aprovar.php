<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/app/helpers/auth.php';
auth_require();

require_once dirname(__DIR__,2).'/app/helpers/common.php';
require_once dirname(__DIR__,2).'/config/database.php';
require_once dirname(__DIR__,2).'/vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$perfil = trim((string)($_POST['perfil'] ?? ''));
$vlan = filter_input(INPUT_POST, 'vlan', FILTER_VALIDATE_INT);

if (!$id || $perfil === '' || !$vlan) {

    flash_set(
        'err',
        'Solicitação, perfil ou VLAN inválidos.'
    );

    header('Location: ../index.php');
    exit;
}

$pdo = db();

try {

    $st = $pdo->prepare("
        SELECT *
        FROM solicitacoes
        WHERE id = ?
        AND status = 'pendente'
        LIMIT 1
    ");

    $st->execute([$id]);

    $sol = $st->fetch();

    if (!$sol) {
        throw new RuntimeException(
            'Solicitação não encontrada ou já analisada.'
        );
    }

    $cpf = preg_replace(
        '/\D+/',
        '',
        (string)$sol['cpf']
    );

    $mt = require dirname(__DIR__,2).'/config/mikrotik.php';

    $client = new Client(new Config([
        'host'     => $mt['host'],
        'user'     => $mt['user'],
        'pass'     => $mt['pass'],
        'port'     => $mt['port'],
        'timeout'  => 5,
        'attempts' => 2
    ]));

    /*
     * Garante que o perfil exista no MikroTik.
     */
    $pq = (new Query('/ip/hotspot/user/profile/print'))
        ->where('name', $perfil);

    $perfilExistente = $client
        ->query($pq)
        ->read();

    if (empty($perfilExistente)) {

        $client->query(
            (new Query('/ip/hotspot/user/profile/add'))
                ->equal('name', $perfil)
        )->read();
    }

    /*
     * Procura o usuário pelo CPF.
     */
    $uq = (new Query('/ip/hotspot/user/print'))
        ->where('name', $cpf);

    $u = $client
        ->query($uq)
        ->read();

    if (empty($u) || empty($u[0]['.id'])) {
        throw new RuntimeException(
            'Usuário pendente não encontrado no MikroTik.'
        );
    }

    /*
     * Libera o usuário.
     */
    $set = (new Query('/ip/hotspot/user/set'))
        ->equal('.id', $u[0]['.id'])
        ->equal('disabled', 'no')
        ->equal('profile', $perfil)
        ->equal(
            'comment',
            'BST APROVADO | '.$perfil.' | VLAN '.$vlan
        );

    $client->query($set)->read();

    /*
     * Atualiza o banco.
     */
    $up = $pdo->prepare("
        UPDATE solicitacoes
        SET status = 'aprovada',
            perfil_aprovado = ?,
            vlan_aprovada = ?,
            analisado_em = NOW(),
            aprovado_em = NOW()
        WHERE id = ?
    ");

    $up->execute([
        $perfil,
        $vlan,
        $id
    ]);

    log_action(
        $pdo,
        (int)auth_user()['id'],
        'aprovar_solicitacao',
        'CPF '.$cpf.' | Perfil '.$perfil.' | VLAN '.$vlan
    );

    flash_set(
        'ok',
        'Usuário aprovado e liberado no MikroTik.'
    );

} catch (Throwable $e) {

    flash_set(
        'err',
        'Falha na aprovação: '.$e->getMessage()
    );
}

header('Location: ../index.php');
exit;