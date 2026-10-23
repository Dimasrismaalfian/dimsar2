<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$error = '';
$info = '';
$tab = (($_GET['tab'] ?? '') === 'daftar' && REGISTRASI_TERBUKA) ? 'daftar' : 'masuk';
$old = ['username' => '', 'name' => '', 'jabatan' => ''];

try {
    if (current_user()) { header('Location: index.php'); exit; }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $act = (string)($_POST['act'] ?? '');
        $old['username'] = trim((string)($_POST['username'] ?? ''));
        $old['name'] = trim((string)($_POST['name'] ?? ''));
        $old['jabatan'] = (string)($_POST['jabatan'] ?? '');
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
            $tab = $act === 'daftar' ? 'daftar' : 'masuk';
        } elseif ($act === 'daftar') {
            $tab = 'daftar';
            $error = (string)register_user($old['username'], (string)($_POST['password'] ?? ''),
                (string)($_POST['password2'] ?? ''), $old['name'], (string)($_POST['kode'] ?? ''), $old['jabatan']);
            if ($error === '') { header('Location: index.php'); exit; }
        } else {
            $tab = 'masuk';
            $error = (string)login_user($old['username'], (string)($_POST['password'] ?? ''));
            if ($error === '') { header('Location: index.php'); exit; }
        }
    }
    if (isset($_GET['keluar'])) $info = 'Anda telah keluar.';
} catch (Throwable $e) {
    error_log('[basarnas-auth] ' . $e->getMessage());
    $error = 'Kesalahan server: ' . $e->getMessage();
}
$csrf = csrf_token();
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$perluKode = KODE_PENDAFTARAN !== '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Masuk — Dashboard BASARNAS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --navy-950:#081321; --navy-900:#0B2545; --navy-800:#13315C; --navy-700:#1C4980;
    --orange-600:#D9480F; --orange-500:#F2542D; --orange-400:#FF7A45;
    --bg:#F3F5F9; --panel:#fff; --line:#E3E7EF; --text:#111827; --muted:#5B667A;
    --bad:#C0392B; --bad-bg:#FBE5E1; --ok:#0F9D58; --ok-bg:#E5F6EC;
  }
  *{box-sizing:border-box}
  body{margin:0; min-height:100vh; font-family:'Inter',system-ui,sans-serif; color:var(--text); background:var(--bg);
       display:flex; padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0);}
  .side{flex:1.1; background:linear-gradient(160deg,var(--navy-950),var(--navy-800)); color:#fff; padding:3rem; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden;}
  .side::after{content:""; position:absolute; right:-120px; bottom:-120px; width:380px; height:380px; border-radius:50%; border:60px solid rgba(242,84,45,.16);}
  .brand{display:flex; align-items:center; gap:.7rem}
  .logo{width:42px; height:42px; border-radius:10px; background:var(--orange-500); display:flex; align-items:center; justify-content:center; font:700 .95rem 'Space Grotesk',sans-serif;}
  .brand b{font:600 1.05rem 'Space Grotesk',sans-serif; display:block; line-height:1.1}
  .brand span{font-size:.75rem; color:#93A2BE}
  .side h2{font:600 2rem/1.2 'Space Grotesk',sans-serif; margin:0 0 .8rem; max-width:20ch}
  .side p{color:#A9B7D0; margin:0; max-width:42ch; line-height:1.6; font-size:.95rem}
  .side small{color:#7284A3; font-size:.75rem; position:relative; z-index:1}
  .side > *{position:relative; z-index:1}
  .main{flex:1; display:flex; align-items:center; justify-content:center; padding:2rem 1.25rem}
  .card{width:100%; max-width:410px}
  .card h1{font:600 1.5rem 'Space Grotesk',sans-serif; margin:0 0 .3rem}
  .card .sub{color:var(--muted); font-size:.88rem; margin:0 0 1.4rem}
  .tabs{display:flex; background:#E8ECF4; border-radius:.7rem; padding:.25rem; margin-bottom:1.4rem}
  .tabs a{flex:1; text-align:center; padding:.55rem; border-radius:.5rem; font-size:.85rem; font-weight:600; color:var(--muted); text-decoration:none}
  .tabs a.on{background:#fff; color:var(--navy-900); box-shadow:0 1px 3px rgba(11,37,69,.12)}
  label{display:block; font-size:.78rem; font-weight:600; margin:0 0 .35rem}
  .f{margin-bottom:1rem}
  .f small{display:block; color:var(--muted); font-size:.72rem; margin-top:.3rem}
  select{width:100%; padding:.7rem .85rem; border:1px solid var(--line); border-radius:.6rem; font:inherit; font-size:.92rem; background:#fff; color:var(--text)}
  select:focus{outline:2px solid rgba(44,95,158,.35); border-color:var(--navy-700)}
  input[type=text],input[type=password]{width:100%; padding:.7rem .85rem; border:1px solid var(--line); border-radius:.6rem; font:inherit; font-size:.92rem; background:#fff; color:var(--text)}
  input:focus{outline:2px solid rgba(44,95,158,.35); border-color:var(--navy-700)}
  .pw{position:relative}
  .pw input{padding-right:3.6rem}
  .pw button{position:absolute; right:.4rem; top:50%; transform:translateY(-50%); background:none; border:0; color:var(--navy-700); font:600 .75rem 'Inter',sans-serif; cursor:pointer; padding:.35rem .5rem}
  .meter{height:4px; background:#E3E7EF; border-radius:4px; margin-top:.45rem; overflow:hidden}
  .meter i{display:block; height:100%; width:0; background:var(--bad); transition:width .2s, background .2s}
  .btn{width:100%; padding:.8rem; border:0; border-radius:.6rem; background:var(--orange-500); color:#fff; font:600 .92rem 'Inter',sans-serif; cursor:pointer; margin-top:.3rem}
  .btn:hover{background:var(--orange-600)}
  .msg{padding:.7rem .85rem; border-radius:.6rem; font-size:.84rem; margin-bottom:1rem; line-height:1.45}
  .msg.err{background:var(--bad-bg); color:var(--bad)}
  .msg.ok{background:var(--ok-bg); color:var(--ok)}
  .foot{margin-top:1.4rem; font-size:.78rem; color:var(--muted); text-align:center}
  .hide{display:none}
  @media (max-width:860px){ .side{display:none} body{background:#fff} }
</style>
</head>
<body>
  <section class="side">
    <div class="brand"><div class="logo">SAR</div><div><b>BASARNAS</b><span>Dashboard Program</span></div></div>
    <div>
      <h2>Kelola Dashboard Badan Pencarian Dan Pertolongan Jakarta</h2>
      <p>Kantor Pencarian Dan Pertolongan Jakarta Kelas A</p>
    </div>
    <small>Bagian Sumber Daya</small>
  </section>

  <main class="main">
    <div class="card">
      <h1 id="judul"><?= $tab === 'daftar' ? 'Buat akun' : 'Selamat datang' ?></h1>
      <p class="sub" id="sub"><?= $tab === 'daftar' ? 'Tentukan ID dan kata sandi Anda sendiri.' : 'Masuk untuk melanjutkan ke dashboard.' ?></p>

      <?php if (REGISTRASI_TERBUKA): ?>
      <div class="tabs" role="tablist">
        <a href="login.php" class="<?= $tab === 'masuk' ? 'on' : '' ?>" data-tab="masuk">Masuk</a>
        <a href="login.php?tab=daftar" class="<?= $tab === 'daftar' ? 'on' : '' ?>" data-tab="daftar">Daftar</a>
      </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?><div class="msg err" role="alert"><?= $h($error) ?></div><?php endif; ?>
      <?php if ($info !== ''): ?><div class="msg ok"><?= $h($info) ?></div><?php endif; ?>

      <!-- MASUK -->
      <form method="post" action="login.php" id="formMasuk" class="<?= $tab === 'masuk' ? '' : 'hide' ?>" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="act" value="masuk">
        <div class="f">
          <label for="m_user">ID</label>
          <input type="text" id="m_user" name="username" value="<?= $tab === 'masuk' ? $h($old['username']) : '' ?>" autocomplete="username" autocapitalize="off" spellcheck="false" required autofocus>
        </div>
        <div class="f">
          <label for="m_pass">Kata sandi</label>
          <div class="pw"><input type="password" id="m_pass" name="password" autocomplete="current-password" required><button type="button" data-toggle="m_pass">Lihat</button></div>
        </div>
        <button class="btn" type="submit">Masuk</button>
      </form>

      <!-- DAFTAR -->
      <?php if (REGISTRASI_TERBUKA): ?>
      <form method="post" action="login.php?tab=daftar" id="formDaftar" class="<?= $tab === 'daftar' ? '' : 'hide' ?>" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="act" value="daftar">
        <div class="f">
          <label for="d_user">ID yang diinginkan</label>
          <input type="text" id="d_user" name="username" value="<?= $tab === 'daftar' ? $h($old['username']) : '' ?>" autocomplete="username" autocapitalize="off" spellcheck="false" minlength="3" maxlength="32" pattern="[A-Za-z0-9._\-]{3,32}" required>
          <small>3–32 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.</small>
        </div>
        <div class="f">
          <label for="d_name">Nama lengkap</label>
          <input type="text" id="d_name" name="name" value="<?= $tab === 'daftar' ? $h($old['name']) : '' ?>" maxlength="60" autocomplete="name" required>
          <small>Satu nama hanya dapat memiliki satu akun.</small>
        </div>
        <div class="f">
          <label for="d_jabatan">Peran</label>
          <select id="d_jabatan" name="jabatan" required>
            <option value="" disabled <?= $old['jabatan'] === '' ? 'selected' : '' ?>>Pilih peran…</option>
            <?php foreach (JABATAN_LIST as $j): ?>
            <option value="<?= $h($j) ?>" <?= $old['jabatan'] === $j ? 'selected' : '' ?>><?= $h($j) ?></option>
            <?php endforeach; ?>
          </select>
          <small>Peran menentukan fitur yang dapat Anda gunakan di dashboard.</small>
        </div>
        <div class="f">
          <label for="d_pass">Kata sandi</label>
          <div class="pw"><input type="password" id="d_pass" name="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>" maxlength="72" required><button type="button" data-toggle="d_pass">Lihat</button></div>
          <div class="meter"><i id="meter"></i></div>
          <small id="meterTxt">Minimal <?= PASSWORD_MIN ?> karakter. Campurkan huruf besar/kecil, angka, dan simbol agar lebih kuat.</small>
        </div>
        <div class="f">
          <label for="d_pass2">Ulangi kata sandi</label>
          <div class="pw"><input type="password" id="d_pass2" name="password2" autocomplete="new-password" maxlength="72" required><button type="button" data-toggle="d_pass2">Lihat</button></div>
        </div>
        <?php if ($perluKode): ?>
        <div class="f">
          <label for="d_kode">Kode pendaftaran</label>
          <input type="text" id="d_kode" name="kode" autocomplete="off" required>
          <small>Minta kode ini kepada administrator.</small>
        </div>
        <?php endif; ?>
        <button class="btn" type="submit">Buat akun &amp; masuk</button>
      </form>
      <?php endif; ?>

      <div class="foot">Lupa kata sandi? Hubungi administrator untuk mengatur ulang akun Anda.</div>
    </div>
  </main>

<script>
document.querySelectorAll('[data-toggle]').forEach(b => b.addEventListener('click', () => {
  const i = document.getElementById(b.dataset.toggle);
  const show = i.type === 'password';
  i.type = show ? 'text' : 'password';
  b.textContent = show ? 'Sembunyi' : 'Lihat';
}));
// Pindah tab tanpa muat ulang
document.querySelectorAll('.tabs a').forEach(a => a.addEventListener('click', e => {
  e.preventDefault();
  const t = a.dataset.tab;
  document.querySelectorAll('.tabs a').forEach(x => x.classList.toggle('on', x === a));
  document.getElementById('formMasuk').classList.toggle('hide', t !== 'masuk');
  document.getElementById('formDaftar').classList.toggle('hide', t !== 'daftar');
  document.getElementById('judul').textContent = t === 'daftar' ? 'Buat akun' : 'Selamat datang';
  document.getElementById('sub').textContent = t === 'daftar' ? 'Tentukan ID dan kata sandi Anda sendiri.' : 'Masuk untuk melanjutkan ke dashboard.';
  document.querySelectorAll('.msg').forEach(m => m.remove());
  history.replaceState(null, '', t === 'daftar' ? 'login.php?tab=daftar' : 'login.php');
  (document.getElementById(t === 'daftar' ? 'd_user' : 'm_user')).focus();
}));
// Pengukur kekuatan kata sandi + cek konfirmasi
const dp = document.getElementById('d_pass'), dp2 = document.getElementById('d_pass2');
if (dp) {
  const meter = document.getElementById('meter'), txt = document.getElementById('meterTxt');
  dp.addEventListener('input', () => {
    const v = dp.value; let s = 0;
    if (v.length >= <?= PASSWORD_MIN ?>) s++;
    if (v.length >= 12) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const lv = [['0%','#C0392B',''],['25%','#C0392B','Lemah'],['45%','#D9480F','Cukup'],['65%','#B7791F','Baik'],['85%','#0F9D58','Kuat'],['100%','#0F9D58','Sangat kuat']][v ? s : 0];
    meter.style.width = lv[0]; meter.style.background = lv[1];
    if (v) txt.textContent = 'Kekuatan: ' + lv[2];
  });
  const chk = () => dp2.setCustomValidity(dp2.value && dp.value !== dp2.value ? 'Kata sandi tidak sama' : '');
  dp.addEventListener('input', chk); dp2.addEventListener('input', chk);
}
</script>
</body>
</html>
