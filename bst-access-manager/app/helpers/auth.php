<?php
declare(strict_types=1);

function auth_start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function auth_require(): void {
    auth_start();

    if (empty($_SESSION['admin_id'])) {
        header('Location: /login.php');
        exit;
    }
}
function auth_user(): array {
    auth_start();

    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'nome' => $_SESSION['admin_nome'] ?? 'Administrador',
        'email' => $_SESSION['admin_email'] ?? '',
        'perfil' => $_SESSION['admin_perfil'] ?? 'consulta',
    ];
}

function auth_login(array $admin): void {
    auth_start();

    session_regenerate_id(true);

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_nome'] = $admin['nome'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_perfil'] = $admin['perfil'] ?? 'consulta';
}

function auth_has_role(array $perfis): bool {
    $u = auth_user();

    return in_array(
        $u['perfil'] ?? 'consulta',
        $perfis,
        true
    );
}

function auth_logout(): void {
    auth_start();
    session_unset();
    session_destroy();
}