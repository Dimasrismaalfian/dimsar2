<?php
/**
 * Pustaka autentikasi Dashboard BASARNAS.
 * - Akun disimpan di data/auth.db (terpisah dari basarnas.db, sehingga data akun tidak ikut
 *   hilang saat database dashboard direset).
 * - Kata sandi disimpan sebagai hash (password_hash), tidak pernah sebagai teks biasa.
 * - Sesi PHP dengan cookie HttpOnly + SameSite, token CSRF, dan pembatasan percobaan login.
 */
declare(strict_types=1);

/* Cadangan bila ekstensi mbstring tidak aktif di server. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int { return (int)preg_match_all('/./us', $s); }
}

/* ============================ PENGATURAN ============================ */
/** true = siapa pun yang bisa membuka halaman login boleh membuat akun sendiri. */
const REGISTRASI_TERBUKA = true;
/** Jika diisi, pendaftar harus memasukkan kode ini (mis. 'SAR-JKT-2026'). Kosongkan = tanpa kode. */
const KODE_PENDAFTARAN = '';
/** Lama sesi login tanpa aktivitas (detik). */
const SESI_TIMEOUT = 8 * 3600;
/** Batas gagal login per ID+alamat IP dalam jendela waktu, lalu dikunci sementara. */
const LOGIN_MAKS_GAGAL = 5;
const LOGIN_JENDELA_DETIK = 600;

const AUTH_DB_FILE = __DIR__ . '/data/auth.db';
const USERNAME_REGEX = '/^[A-Za-z0-9._-]{3,32}$/';
const PASSWORD_MIN = 8;
/** Pilihan jabatan/peran yang dipilih saat mendaftar (terikat ke akun). */
const JABATAN_LIST = ['KKS', 'KSD', 'KSO', 'KSB', 'Admin', 'Anggota'];

function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('BASARNASSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

function auth_db(): PDO
{
    static $p = null;
    if ($p instanceof PDO) return $p;
    $dir = dirname(AUTH_DB_FILE);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException("Folder 'data' tidak dapat dibuat. Beri izin tulis pada folder aplikasi.");
    }
    if (!is_writable($dir)) {
        throw new RuntimeException("Folder 'data' tidak dapat ditulis. Beri izin tulis (chmod 775) untuk user web server.");
    }
    $p = new PDO('sqlite:' . AUTH_DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $p->exec('PRAGMA busy_timeout = 8000');
    $p->exec('CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
        display_name  TEXT,
        password_hash TEXT NOT NULL,
        created_at    TEXT NOT NULL,
        last_login    TEXT
    )');
    // Migrasi: kolom peran akun ('admin_utama' atau 'pengguna')
    $cols = array_column($p->query('PRAGMA table_info(users)')->fetchAll(), 'name');
    if (!in_array('role', $cols, true)) {
        $p->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'pengguna'");
    }
    if (!in_array('jabatan', $cols, true)) {
        $p->exec("ALTER TABLE users ADD COLUMN jabatan TEXT NOT NULL DEFAULT 'Anggota'");
    }
    $p->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        key TEXT NOT NULL,
        ts  INTEGER NOT NULL
    )');
    $p->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts(key, ts)');
    return $p;
}

/* ============================ CSRF ============================ */
function csrf_token(): string
{
    auth_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_valid(?string $t): bool
{
    auth_session_start();
    return is_string($t) && $t !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/* ============================ STATUS LOGIN ============================ */
function current_user(): ?array
{
    auth_session_start();
    if (empty($_SESSION['uid'])) return null;
    $last = (int)($_SESSION['last_active'] ?? 0);
    if ($last && time() - $last > SESI_TIMEOUT) {
        auth_logout();
        return null;
    }
    if (!defined('NO_TOUCH')) $_SESSION['last_active'] = time(); // polling sinkronisasi tidak memperpanjang sesi
    return ['id' => (int)$_SESSION['uid'], 'username' => (string)$_SESSION['uname'], 'name' => (string)$_SESSION['dname'],
            'role' => (string)($_SESSION['urole'] ?? 'pengguna'), 'jabatan' => (string)($_SESSION['ujab'] ?? 'Anggota')];
}

/** Halaman HTML: arahkan ke login. API (json=true): balas 401. */
function require_login(bool $json = false): array
{
    $u = current_user();
    if ($u) return $u;
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => false, 'error' => 'Sesi berakhir, silakan login kembali', 'login' => true]);
        exit;
    }
    header('Cache-Control: no-store');
    header('Location: login.php');
    exit;
}

function auth_logout(): void
{
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $c = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $c['path'],
            'secure' => $c['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

/* ============================ PEMBATASAN LOGIN ============================ */
function attempt_key(string $username): string
{
    return hash('sha256', strtolower($username) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}
function login_locked(string $username): int
{
    $p = auth_db();
    $p->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([time() - LOGIN_JENDELA_DETIK]);
    $s = $p->prepare('SELECT COUNT(*) c, MIN(ts) m FROM login_attempts WHERE key = ? AND ts >= ?');
    $s->execute([attempt_key($username), time() - LOGIN_JENDELA_DETIK]);
    $r = $s->fetch();
    if ((int)$r['c'] >= LOGIN_MAKS_GAGAL) return max(1, (int)$r['m'] + LOGIN_JENDELA_DETIK - time());
    return 0;
}
function login_fail_record(string $username): void
{
    auth_db()->prepare('INSERT INTO login_attempts (key, ts) VALUES (?,?)')->execute([attempt_key($username), time()]);
}
function login_fail_clear(string $username): void
{
    auth_db()->prepare('DELETE FROM login_attempts WHERE key = ?')->execute([attempt_key($username)]);
}

/* ============================ DAFTAR & MASUK ============================ */
function start_session_for(array $u): void
{
    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['uname'] = (string)$u['username'];
    $_SESSION['dname'] = (string)($u['display_name'] !== null && $u['display_name'] !== '' ? $u['display_name'] : $u['username']);
    $_SESSION['urole'] = (string)($u['role'] ?? 'pengguna');
    $_SESSION['ujab'] = (string)($u['jabatan'] ?? 'Anggota');
    $_SESSION['last_active'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/** @return string|null pesan galat, atau null jika berhasil (sekaligus langsung login). */
function register_user(string $username, string $password, string $password2, string $name, string $kode, string $jabatan = ''): ?string
{
    if (!REGISTRASI_TERBUKA) return 'Pendaftaran akun sedang ditutup.';
    if (KODE_PENDAFTARAN !== '' && !hash_equals(KODE_PENDAFTARAN, $kode)) return 'Kode pendaftaran salah.';
    if (!preg_match(USERNAME_REGEX, $username)) {
        return 'ID harus 3–32 karakter: huruf, angka, titik (.), garis bawah (_) atau tanda hubung (-), tanpa spasi.';
    }
    if (strlen($password) < PASSWORD_MIN) return 'Kata sandi minimal ' . PASSWORD_MIN . ' karakter.';
    if (strlen($password) > 72) return 'Kata sandi maksimal 72 karakter.';
    if (strcasecmp($password, $username) === 0) return 'Kata sandi tidak boleh sama dengan ID.';
    if ($password !== $password2) return 'Konfirmasi kata sandi tidak sama.';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') return 'Nama lengkap wajib diisi.';
    if (mb_strlen($name) > 60) return 'Nama maksimal 60 karakter.';
    if (!in_array($jabatan, JABATAN_LIST, true)) return 'Pilih peran terlebih dahulu.';

    $p = auth_db();
    if (name_taken($name)) return 'Nama "' . $name . '" sudah terdaftar. Satu nama hanya bisa memiliki satu akun; hubungi administrator bila Anda lupa kata sandi.';
    $s = $p->prepare('SELECT 1 FROM users WHERE username = ?');
    $s->execute([$username]);
    if ($s->fetchColumn()) return 'ID "' . $username . '" sudah dipakai. Pilih ID lain.';

    $now = date('c');
    try {
        $p->prepare('INSERT INTO users (username, display_name, password_hash, created_at, last_login, jabatan) VALUES (?,?,?,?,?,?)')
          ->execute([$username, $name, password_hash($password, PASSWORD_DEFAULT), $now, $now, $jabatan]);
    } catch (PDOException $e) {
        return 'ID "' . $username . '" sudah dipakai. Pilih ID lain.';
    }
    $id = (int)$p->lastInsertId();
    start_session_for(['id' => $id, 'username' => $username, 'display_name' => $name, 'role' => ROLE_PENGGUNA, 'jabatan' => $jabatan]);
    return null;
}

/** @return string|null pesan galat, atau null jika berhasil. */
function login_user(string $username, string $password): ?string
{
    $wait = login_locked($username);
    if ($wait > 0) return 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . (int)ceil($wait / 60) . ' menit.';

    $p = auth_db();
    $s = $p->prepare('SELECT * FROM users WHERE username = ?');
    $s->execute([$username]);
    $u = $s->fetch();
    // verifikasi tetap dijalankan walau ID tidak ada, agar waktu respons seragam
    $hash = $u ? (string)$u['password_hash'] : '$2y$10$usesomesillystringforsalt.invalidinvalidinvalidinvalidinva';
    $ok = password_verify($password, $hash);
    if (!$u || !$ok) {
        login_fail_record($username);
        return 'ID atau kata sandi salah.';
    }
    login_fail_clear($username);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $p->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    $p->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([date('c'), $u['id']]);
    start_session_for($u);
    return null;
}


/* ============================ ADMIN UTAMA & KELOLA AKUN ============================ */
const ROLE_ADMIN_UTAMA = 'admin_utama';
const ROLE_PENGGUNA    = 'pengguna';

function is_admin_utama(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u !== null && ($u['role'] ?? '') === ROLE_ADMIN_UTAMA;
}

/** Halaman khusus Admin Utama. Selain itu diarahkan ke dashboard (HTML) / 403 (JSON). */
function require_admin_utama(): array
{
    $u = require_login();
    // Peran dibaca ulang dari database, bukan hanya dari sesi, agar pencabutan peran langsung berlaku.
    $s = auth_db()->prepare('SELECT role FROM users WHERE id = ?');
    $s->execute([$u['id']]);
    $role = $s->fetchColumn();
    if ($role !== ROLE_ADMIN_UTAMA) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Akses ditolak. Halaman ini hanya untuk Admin Utama.';
        exit;
    }
    $_SESSION['urole'] = ROLE_ADMIN_UTAMA;
    $u['role'] = ROLE_ADMIN_UTAMA;
    return $u;
}

function admin_utama_count(): int
{
    return (int)auth_db()->query("SELECT COUNT(*) FROM users WHERE role = '" . ROLE_ADMIN_UTAMA . "'")->fetchColumn();
}

function admin_list_users(): array
{
    return auth_db()->query('SELECT id, username, display_name, role, jabatan, created_at, last_login FROM users ORDER BY (role = \'' . ROLE_ADMIN_UTAMA . '\') DESC, username COLLATE NOCASE')->fetchAll();
}

function validate_new_password(string $username, string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN) return 'Kata sandi minimal ' . PASSWORD_MIN . ' karakter.';
    if (strlen($password) > 72) return 'Kata sandi maksimal 72 karakter.';
    if (strcasecmp($password, $username) === 0) return 'Kata sandi tidak boleh sama dengan ID.';
    return null;
}

/** Buat akun (oleh Admin Utama / skrip CLI). Tidak mengubah sesi siapa pun. @return string|null galat */
function admin_create_user(string $username, string $password, string $name, string $role, string $jabatan = 'Anggota'): ?string
{
    if (!preg_match(USERNAME_REGEX, $username)) return 'ID harus 3–32 karakter: huruf, angka, titik, garis bawah atau tanda hubung.';
    if ($err = validate_new_password($username, $password)) return $err;
    if (!in_array($role, [ROLE_ADMIN_UTAMA, ROLE_PENGGUNA], true)) return 'Peran tidak dikenal.';
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (mb_strlen($name) > 60) return 'Nama maksimal 60 karakter.';
    if (!in_array($jabatan, JABATAN_LIST, true)) return 'Peran tidak dikenal.';
    if ($name !== '' && name_taken($name)) return 'Nama "' . $name . '" sudah terdaftar.';
    $p = auth_db();
    $s = $p->prepare('SELECT 1 FROM users WHERE username = ?');
    $s->execute([$username]);
    if ($s->fetchColumn()) return 'ID "' . $username . '" sudah dipakai.';
    $p->prepare('INSERT INTO users (username, display_name, password_hash, created_at, role, jabatan) VALUES (?,?,?,?,?,?)')
      ->execute([$username, $name !== '' ? $name : null, password_hash($password, PASSWORD_DEFAULT), date('c'), $role, $jabatan]);
    return null;
}

function admin_set_password(int $id, string $password): ?string
{
    $p = auth_db();
    $s = $p->prepare('SELECT username FROM users WHERE id = ?');
    $s->execute([$id]);
    $un = $s->fetchColumn();
    if ($un === false) return 'Akun tidak ditemukan.';
    if ($err = validate_new_password((string)$un, $password)) return $err;
    $p->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    // buka kunci percobaan login gagal untuk ID ini
    $p->exec("DELETE FROM login_attempts");
    return null;
}

function admin_set_role(int $id, string $role): ?string
{
    if (!in_array($role, [ROLE_ADMIN_UTAMA, ROLE_PENGGUNA], true)) return 'Peran tidak dikenal.';
    $p = auth_db();
    $s = $p->prepare('SELECT role FROM users WHERE id = ?');
    $s->execute([$id]);
    $cur = $s->fetchColumn();
    if ($cur === false) return 'Akun tidak ditemukan.';
    if ($cur === ROLE_ADMIN_UTAMA && $role !== ROLE_ADMIN_UTAMA && admin_utama_count() <= 1) {
        return 'Tidak bisa mencabut peran Admin Utama terakhir. Jadikan akun lain Admin Utama terlebih dahulu.';
    }
    $p->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
    return null;
}

function admin_delete_user(int $id): ?string
{
    $p = auth_db();
    $s = $p->prepare('SELECT role FROM users WHERE id = ?');
    $s->execute([$id]);
    $cur = $s->fetchColumn();
    if ($cur === false) return 'Akun tidak ditemukan.';
    if ($cur === ROLE_ADMIN_UTAMA && admin_utama_count() <= 1) return 'Tidak bisa menghapus Admin Utama terakhir.';
    $p->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    return null;
}

function admin_set_name(int $id, string $name): ?string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (mb_strlen($name) > 60) return 'Nama maksimal 60 karakter.';
    if ($name !== '' && name_taken($name, $id)) return 'Nama "' . $name . '" sudah dipakai akun lain.';
    auth_db()->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([$name !== '' ? $name : null, $id]);
    return null;
}


/** Apakah nama (tanpa membedakan huruf besar/kecil & spasi ganda) sudah dipakai akun lain? */
function name_taken(string $name, int $exceptId = 0): bool
{
    $norm = static fn(string $x): string => mb_strtolower_safe(trim(preg_replace('/\s+/u', ' ', $x) ?? ''));
    $target = $norm($name);
    if ($target === '') return false;
    foreach (auth_db()->query('SELECT id, display_name FROM users WHERE display_name IS NOT NULL')->fetchAll() as $r) {
        if ((int)$r['id'] !== $exceptId && $norm((string)$r['display_name']) === $target) return true;
    }
    return false;
}
function mb_strtolower_safe(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function admin_set_jabatan(int $id, string $jabatan): ?string
{
    if (!in_array($jabatan, JABATAN_LIST, true)) return 'Peran tidak dikenal.';
    $p = auth_db();
    $s = $p->prepare('SELECT 1 FROM users WHERE id = ?');
    $s->execute([$id]);
    if (!$s->fetchColumn()) return 'Akun tidak ditemukan.';
    $p->prepare('UPDATE users SET jabatan = ? WHERE id = ?')->execute([$jabatan, $id]);
    return null;
}
