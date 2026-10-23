<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$error = ''; $ok = '';

try {
    $me = require_admin_utama();
    $csrf = csrf_token();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
        } else {
            $act = (string)($_POST['act'] ?? '');
            $id  = (int)($_POST['id'] ?? 0);
            switch ($act) {
                case 'tambah':
                    $error = (string)admin_create_user(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''),
                        (string)($_POST['name'] ?? ''), (string)($_POST['role'] ?? ROLE_PENGGUNA), (string)($_POST['jabatan'] ?? 'Anggota'));
                    if ($error === '') $ok = 'Akun berhasil dibuat.';
                    break;
                case 'sandi':
                    $error = (string)admin_set_password($id, (string)($_POST['password'] ?? ''));
                    if ($error === '') $ok = 'Kata sandi diperbarui.';
                    break;
                case 'peran':
                    $error = (string)admin_set_role($id, (string)($_POST['role'] ?? ''));
                    if ($error === '') $ok = 'Peran diperbarui.';
                    break;
                case 'jabatan':
                    $error = (string)admin_set_jabatan($id, (string)($_POST['jabatan'] ?? ''));
                    if ($error === '') $ok = 'Peran dashboard diperbarui.';
                    break;
                case 'nama':
                    $error = (string)admin_set_name($id, (string)($_POST['name'] ?? ''));
                    if ($error === '') $ok = 'Nama diperbarui.';
                    break;
                case 'hapus':
                    if ($id === $me['id']) { $error = 'Anda tidak dapat menghapus akun yang sedang dipakai.'; break; }
                    $error = (string)admin_delete_user($id);
                    if ($error === '') $ok = 'Akun dihapus.';
                    break;
                default:
                    $error = 'Aksi tidak dikenal.';
            }
        }
    }
    $users = admin_list_users();
} catch (Throwable $e) {
    error_log('[basarnas-kelola] ' . $e->getMessage());
    http_response_code(500);
    echo 'Kesalahan server: ' . $h($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Kelola Akun — Dashboard BASARNAS</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{--navy-950:#081321;--navy-900:#0B2545;--navy-700:#1C4980;--orange-500:#F2542D;--orange-600:#D9480F;
        --bg:#F3F5F9;--panel:#fff;--line:#E3E7EF;--text:#111827;--muted:#5B667A;--bad:#C0392B;--bad-bg:#FBE5E1;--ok:#0F9D58;--ok-bg:#E5F6EC}
  *{box-sizing:border-box}
  body{margin:0;font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0)}
  header{background:var(--navy-950);color:#fff;padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
  header h1{font:600 1.1rem 'Space Grotesk',sans-serif;margin:0}
  header a{color:#fff;text-decoration:none;font-size:.85rem;background:rgba(255,255,255,.12);padding:.45rem .8rem;border-radius:.5rem;margin-left:auto}
  main{max-width:980px;margin:0 auto;padding:1.25rem}
  .panel{background:var(--panel);border:1px solid var(--line);border-radius:.8rem;padding:1.1rem;margin-bottom:1.25rem}
  h2{font:600 1rem 'Space Grotesk',sans-serif;margin:0 0 .8rem}
  .msg{padding:.7rem .85rem;border-radius:.6rem;font-size:.85rem;margin-bottom:1rem}
  .err{background:var(--bad-bg);color:var(--bad)} .okm{background:var(--ok-bg);color:var(--ok)}
  label{display:block;font-size:.75rem;font-weight:600;margin-bottom:.25rem}
  input,select{width:100%;padding:.55rem .65rem;border:1px solid var(--line);border-radius:.5rem;font:inherit;font-size:.88rem;background:#fff}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.8rem;align-items:end}
  button{padding:.55rem .85rem;border:0;border-radius:.5rem;background:var(--orange-500);color:#fff;font:600 .82rem 'Inter',sans-serif;cursor:pointer}
  button:hover{background:var(--orange-600)}
  button.g{background:#E8ECF4;color:var(--navy-900)} button.g:hover{background:#dbe1ee}
  button.d{background:var(--bad-bg);color:var(--bad)} button.d:hover{background:#f5cfc9}
  table{width:100%;border-collapse:collapse;font-size:.85rem}
  th,td{text-align:left;padding:.6rem .5rem;border-bottom:1px solid var(--line);vertical-align:top}
  th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
  .badge{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.7rem;font-weight:700}
  .b-su{background:#FFE9DF;color:var(--orange-600)} .b-us{background:#E8ECF4;color:var(--navy-700)}
  .acts{display:flex;gap:.4rem;flex-wrap:wrap}
  .acts form{margin:0;display:flex;gap:.3rem}
  .acts input{width:150px}
  .wrap{overflow-x:auto}
  small{color:var(--muted)}
</style>
</head>
<body>
<header>
  <h1>Kelola Akun — Admin Utama</h1>
  <span style="font-size:.8rem;color:#93A2BE">Masuk sebagai @<?= $h($me['username']) ?></span>
  <a href="index.php">← Kembali ke Dashboard</a>
</header>
<main>
  <?php if ($error !== ''): ?><div class="msg err" role="alert"><?= $h($error) ?></div><?php endif; ?>
  <?php if ($ok !== ''): ?><div class="msg okm"><?= $h($ok) ?></div><?php endif; ?>

  <section class="panel">
    <h2>Tambah akun</h2>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="act" value="tambah">
      <div class="grid">
        <div><label>ID</label><input name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._\-]{3,32}" autocapitalize="off"></div>
        <div><label>Nama</label><input name="name" maxlength="60"></div>
        <div><label>Kata sandi (min. <?= PASSWORD_MIN ?>)</label><input type="text" name="password" required minlength="<?= PASSWORD_MIN ?>" maxlength="72" autocomplete="new-password"></div>
        <div><label>Peran dashboard</label>
          <select name="jabatan"><?php foreach (JABATAN_LIST as $j): ?><option value="<?= $h($j) ?>" <?= $j === 'Anggota' ? 'selected' : '' ?>><?= $h($j) ?></option><?php endforeach; ?></select></div>
        <div><label>Tipe akun</label>
          <select name="role"><option value="pengguna">Pengguna</option><option value="admin_utama">Admin Utama</option></select></div>
        <div><button type="submit">Buat akun</button></div>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Daftar akun (<?= count($users) ?>)</h2>
    <div class="wrap">
    <table>
      <thead><tr><th>ID</th><th>Nama</th><th>Tipe akun</th><th>Peran dashboard</th><th>Login terakhir</th><th>Tindakan</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $isSu = $u['role'] === ROLE_ADMIN_UTAMA; $self = (int)$u['id'] === $me['id']; ?>
        <tr>
          <td><b>@<?= $h($u['username']) ?></b><?= $self ? ' <small>(Anda)</small>' : '' ?></td>
          <td>
            <form method="post" class="acts" style="margin:0">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="act" value="nama"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input name="name" value="<?= $h((string)$u['display_name']) ?>" maxlength="60" style="width:150px"><button class="g" type="submit">Simpan</button>
            </form>
          </td>
          <td><span class="badge <?= $isSu ? 'b-su' : 'b-us' ?>"><?= $isSu ? 'Admin Utama' : 'Pengguna' ?></span></td>
          <td>
            <?php if ($isSu): ?><small>Admin Utama (akses penuh)</small><?php else: ?>
            <form method="post" class="acts" style="margin:0">
              <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="act" value="jabatan"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <select name="jabatan" style="width:110px"><?php foreach (JABATAN_LIST as $j): ?><option value="<?= $h($j) ?>" <?= $j === ($u['jabatan'] ?? 'Anggota') ? 'selected' : '' ?>><?= $h($j) ?></option><?php endforeach; ?></select>
              <button class="g" type="submit">Simpan</button>
            </form>
            <?php endif; ?>
          </td>
          <td><small><?= $h($u['last_login'] ? date('d/m/Y H:i', (int)strtotime((string)$u['last_login'])) : '—') ?></small></td>
          <td>
            <div class="acts">
              <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="act" value="sandi"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="text" name="password" placeholder="Sandi baru" minlength="<?= PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password">
                <button class="g" type="submit">Reset sandi</button>
              </form>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="act" value="peran"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="role" value="<?= $isSu ? 'pengguna' : 'admin_utama' ?>">
                <button class="g" type="submit" onclick="return confirm('<?= $isSu ? 'Cabut peran Admin Utama dari' : 'Jadikan Admin Utama:' ?> @<?= $h($u['username']) ?>?')"><?= $isSu ? 'Jadikan Pengguna' : 'Jadikan Admin Utama' ?></button>
              </form>
              <?php if (!$self): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="act" value="hapus"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="d" type="submit" onclick="return confirm('Hapus akun @<?= $h($u['username']) ?>? Tindakan ini tidak bisa dibatalkan.')">Hapus</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p><small>Sistem selalu menyisakan minimal satu Admin Utama. Pendaftaran mandiri tetap membuat akun berperan Pengguna.</small></p>
  </section>
</main>
</body>
</html>
