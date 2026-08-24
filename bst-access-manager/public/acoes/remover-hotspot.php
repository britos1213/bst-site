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

$cpf = preg_replace(
    '/\D+/',
    '',
    (string)($_POST['cpf'] ?? '')
);

if ($cpf === '') {
    flash_set('err', 'CPF inválido.');
    header('Location: ../usuarios.php');
    exit;
}

try {

    $mt = require dirname(__DIR__,2).'/config/mikrotik.php';

    $client = new Client(new Config([
        'host'     => $mt['host'],
        'user'     => $mt['user'],
        'pass'     => $mt['pass'],
        'port'     => $mt['port'],
        'timeout'  => 5,
        'attempts' => 2
    ]));

    // Derruba sessões ativas do usuário
    $q = (new Query('/ip/hotspot/active/print'))
        ->where('user', $cpf);

    $ativos = $client->query($q)->read();

    foreach ($ativos as $ativo) {
        $idAtivo = $ativo['.id'] ?? null;

        if ($idAtivo) {
            $removeAtivo = (new Query('/ip/hotspot/active/remove'))
                ->equal('.id', $idAtivo);

            $client->query($removeAtivo)->read();
        }
    }

    // Procura o usuário cadastrado no Hotspot
    $q = (new Query('/ip/hotspot/user/print'))
        ->where('name', $cpf);

    $usuarios = $client->query($q)->read();

    if (empty($usuarios)) {
        throw new RuntimeException('Usuário não encontrado no MikroTik.');
    }

    // Remove o login do Hotspot
    foreach ($usuarios as $usuario) {
        $idUsuario = $usuario['.id'] ?? null;

        if ($idUsuario) {
            $removeUsuario = (new Query('/ip/hotspot/user/remove'))
                ->equal('.id', $idUsuario);

            $client->query($removeUsuario)->read();
        }
    }

    // Mantém o histórico no banco
    $pdo = db();

    log_action(
        $pdo,
        (int)auth_user()['id'],
        'remover_usuario_hotspot',
        'CPF '.$cpf
    );

    flash_set(
        'ok',
        'Usuário removido do Hotspot MikroTik com sucesso.'
    );

} catch (Throwable $e) {

    flash_set(
        'err',
        'Falha ao remover usuário: '.$e->getMessage()
    );
}

header('Location: ../usuarios.php');
exit;
