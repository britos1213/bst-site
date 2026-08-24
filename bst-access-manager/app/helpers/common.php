<?php
declare(strict_types=1);

function h(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'] = ['type'=>$type,'message'=>$message];
}

function flash_get(): ?array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function log_action(PDO $pdo, ?int $adminId, string $acao, string $detalhes=''): void {
    $st = $pdo->prepare("INSERT INTO logs_admin(admin_id,acao,detalhes,ip_origem,criado_em)
                        VALUES(?,?,?,?,NOW())");
    $st->execute([$adminId,$acao,$detalhes,$_SERVER['REMOTE_ADDR'] ?? null]);
}
