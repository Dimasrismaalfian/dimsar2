<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

header('Cache-Control: no-store');
// Keluar hanya lewat POST + token CSRF agar tidak bisa dipicu dari situs lain.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_valid($_POST['csrf'] ?? null)) {
    auth_logout();
    header('Location: login.php?keluar=1');
    exit;
}
header('Location: index.php');
