<?php
/**
 * Membuat / mengatur ulang akun Admin Utama. HANYA bisa dijalankan dari terminal (CLI).
 *
 *   php buat_admin.php                      -> buat "adminutama" dengan kata sandi acak
 *   php buat_admin.php NAMA_ID              -> buat Admin Utama dengan ID tertentu (sandi acak)
 *   php buat_admin.php NAMA_ID "sandi baru" -> pakai sandi yang Anda tentukan
 *
 * Jika ID sudah ada, akun itu dijadikan Admin Utama dan kata sandinya diganti.
 * Ini juga jalan keluar bila semua Admin Utama lupa kata sandi.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/auth.php';

$username = $argv[1] ?? 'adminutama';
$password = $argv[2] ?? null;
$generated = false;
if ($password === null) {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < 16; $i++) $password .= $abc[random_int(0, strlen($abc) - 1)];
    $generated = true;
}

$p = auth_db();
$s = $p->prepare('SELECT id FROM users WHERE username = ?');
$s->execute([$username]);
$id = $s->fetchColumn();

if ($id === false) {
    $err = admin_create_user($username, $password, 'Admin Utama', ROLE_ADMIN_UTAMA);
    $aksi = 'dibuat';
} else {
    $err = admin_set_password((int)$id, $password) ?? admin_set_role((int)$id, ROLE_ADMIN_UTAMA);
    $aksi = 'diperbarui (kata sandi diganti, peran = Admin Utama)';
}
if ($err) { fwrite(STDERR, "Gagal: $err\n"); exit(1); }

echo "Akun Admin Utama $aksi.\n";
echo "  ID         : $username\n";
echo "  Kata sandi : $password" . ($generated ? "   (dibuat acak — segera simpan, tidak ditampilkan lagi)" : '') . "\n";
