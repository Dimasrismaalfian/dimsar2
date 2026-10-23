<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/auth.php';
try {
    $USER = require_login();
    $CSRF = csrf_token();
    $roleStmt = auth_db()->prepare('SELECT role, jabatan FROM users WHERE id = ?');
    $roleStmt->execute([$USER['id']]);
    $roleRow = $roleStmt->fetch() ?: ['role' => ROLE_PENGGUNA, 'jabatan' => 'Anggota'];
    $IS_SU = ($roleRow['role'] === ROLE_ADMIN_UTAMA);
    $MY_ROLE = $IS_SU ? 'Admin Utama' : (in_array($roleRow['jabatan'], JABATAN_LIST, true) ? $roleRow['jabatan'] : 'Anggota');
    $INITIAL_REV = get_rev(pdo()); // dibaca sebelum data agar perubahan di sela-sela tetap terdeteksi polling
    $INITIAL_DB = load_db();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Dashboard tidak dapat memuat database.\n\n" . $e->getMessage() . "\n\nPastikan ekstensi PHP pdo_sqlite aktif dan folder 'data' dapat ditulis.";
    exit;
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Dashboard Program BASARNAS</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --navy-950:#081321; --navy-900:#0B2545; --navy-800:#13315C; --navy-700:#1C4980; --navy-600:#2C5F9E;
    --orange-600:#D9480F; --orange-500:#F2542D; --orange-400:#FF7A45; --orange-100:#FFE7DB;
    --bg:#F3F5F9; --panel:#FFFFFF; --line:#E3E7EF; --text:#111827; --muted:#5B667A;
    --ok:#0F9D58; --ok-bg:#E5F6EC; --warn:#B7791F; --warn-bg:#FDF3DA; --bad:#C0392B; --bad-bg:#FBE5E1; --info:#2C5F9E; --info-bg:#E7EEF9;
    box-sizing:border-box;
    padding-top:env(safe-area-inset-top,0px);
    padding-bottom:env(safe-area-inset-bottom,0px);
  }
  *{box-sizing:border-box}
  html{scroll-padding-top:env(safe-area-inset-top,0px); scroll-behavior:smooth;}
  body{background:var(--bg); color:var(--text); font-family:'Inter',ui-sans-serif,system-ui,sans-serif; margin:0;}
  .font-display{font-family:'Space Grotesk',ui-sans-serif,system-ui,sans-serif;}
  ::-webkit-scrollbar{height:8px;width:8px}
  ::-webkit-scrollbar-thumb{background:var(--line); border-radius:8px}
  .panel{background:var(--panel); border:1px solid var(--line);}
  .badge{display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .6rem; border-radius:999px; font-size:.72rem; font-weight:600; white-space:nowrap;}
  .b-ok{background:var(--ok-bg); color:var(--ok);}
  .b-warn{background:var(--warn-bg); color:var(--warn);}
  .b-bad{background:var(--bad-bg); color:var(--bad);}
  .b-info{background:var(--info-bg); color:var(--info);}
  .navlink{display:flex; align-items:center; gap:.65rem; padding:.55rem .7rem; border-radius:.6rem; font-size:.855rem; color:#C9D6EE; cursor:pointer; transition:background .12s;}
  .navlink:hover{background:rgba(255,255,255,.06);}
  .navlink.active{background:var(--orange-500); color:#fff;}
  .navlink .ico{width:1.55rem; height:1.55rem; border-radius:.4rem; display:flex; align-items:center; justify-content:center; font-size:.68rem; font-weight:700; background:rgba(255,255,255,.1); color:#fff; flex-shrink:0;}
  .navlink.active .ico{background:rgba(255,255,255,.25);}
  input[type=text],input[type=date],input[type=time],input[type=tel],input[type=email],input[type=url],input[type=number],select,textarea{
    width:100%; padding:.5rem .65rem; border-radius:.5rem; border:1px solid var(--line); background:var(--bg); color:var(--text); font-size:.85rem; outline:none;
  }
  input:focus,select:focus,textarea:focus{border-color:var(--orange-500); box-shadow:0 0 0 3px var(--orange-100);}
  .btn{display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .9rem; border-radius:.55rem; font-size:.82rem; font-weight:600; cursor:pointer; border:1px solid transparent; transition:opacity .12s;}
  .btn:hover{opacity:.88}
  .btn-primary{background:var(--orange-500); color:#fff;}
  .btn-navy{background:var(--navy-800); color:#fff;}
  .btn-ghost{background:transparent; color:var(--text); border-color:var(--line);}
  .colhead{background:transparent; border:1px dashed transparent; border-radius:.4rem; padding:.15rem .4rem; font:inherit; letter-spacing:inherit; text-transform:inherit; color:inherit; cursor:pointer; white-space:nowrap;}
  .colhead:hover{border-color:var(--line); background:var(--bg); color:var(--text);}
  #colMenu{position:fixed; z-index:9999; width:15rem; background:var(--panel); border:1px solid var(--line); border-radius:.6rem; box-shadow:0 10px 30px rgba(0,0,0,.18); padding:.6rem; display:none; flex-direction:column; gap:.45rem;}
  #colMenu.show{display:flex;}
  .dd{position:relative; display:inline-block;}
  .dd-menu{display:none; position:absolute; right:0; top:calc(100% + .3rem); min-width:9rem; background:var(--panel); border:1px solid var(--line); border-radius:.55rem; box-shadow:0 8px 24px rgba(0,0,0,.14); padding:.25rem; z-index:50;}
  .dd.open .dd-menu{display:block;}
  .dd-item{display:flex; align-items:center; gap:.5rem; width:100%; text-align:left; padding:.45rem .65rem; border-radius:.4rem; font-size:.8rem; font-weight:500; color:var(--text); background:transparent; border:0; cursor:pointer;}
  .dd-item:hover{background:var(--bg);}
  .btn-ok{background:var(--ok); color:#fff;}
  .btn-bad{background:var(--bad); color:#fff;}
  table{border-collapse:collapse; width:100%;}
  th{text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; color:var(--muted); padding:.55rem .7rem; border-bottom:1px solid var(--line); white-space:nowrap;}
  td{padding:.6rem .7rem; border-bottom:1px solid var(--line); font-size:.82rem; vertical-align:top;}
  tr:last-child td{border-bottom:none;}
  .tablewrap{overflow-x:auto; max-width:100%; -webkit-overflow-scrolling:touch; overscroll-behavior-x:contain; scrollbar-width:thin; scrollbar-color:var(--line) transparent;}
  .scrolltop{overflow-x:auto; overflow-y:hidden; height:14px; margin-bottom:.35rem; position:sticky; bottom:0; background:var(--panel); border:0; box-shadow:none; z-index:20; padding-bottom:env(safe-area-inset-bottom,0px); -webkit-overflow-scrolling:touch; overscroll-behavior-x:contain; scrollbar-width:thin; scrollbar-color:var(--line) transparent;}
  .scrolltop > div{height:1px;}
  .scrolltop.hide{display:none;}
  /* Nama kegiatan: maksimal 3 baris, sisanya dipotong dengan (...) */
  /* Tabel Data Peserta Uji Periodik: kolom rapi, nilai tidak terpotong, nama & jabatan tampil utuh */
  .rekap-uji{min-width:920px;}
  .rekap-uji th, .rekap-uji td{vertical-align:middle;}
  .rekap-uji th.c-num{text-align:center; white-space:normal; line-height:1.2; min-width:4.6rem;}
  .rekap-uji td.c-no{width:2.5rem; text-align:center; color:var(--muted);}
  .rekap-uji td.c-nama{min-width:13rem; font-weight:600; line-height:1.35;}
  .rekap-uji td.c-jab{min-width:17rem; line-height:1.35;}
  .rekap-uji td.c-num{text-align:center; white-space:nowrap; font-variant-numeric:tabular-nums;}
  .rekap-uji td.c-akhir{font-weight:700;}
  .rekap-uji tbody tr:hover td{background:var(--bg);}
  .clamp3{display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:3; line-clamp:3; overflow:hidden; text-overflow:ellipsis; word-break:break-word;}
  /* Daftar "Menunggu Persetujuan": bila lebih dari 6 item, tinggi dibatasi (±6 item) dan bisa di-scroll vertikal */
  .menunggu-scroll{max-height:324px; overflow-y:auto; overflow-x:hidden; padding-right:.35rem; overscroll-behavior:contain; -webkit-overflow-scrolling:touch; scrollbar-width:thin; scrollbar-color:var(--line) transparent;}
  .modal-backdrop{overscroll-behavior:contain; -webkit-overflow-scrolling:touch; position:fixed; inset:0; background:rgba(8,19,33,.55); display:flex; align-items:flex-start; justify-content:center; padding:3vh 1rem; overflow-y:auto; z-index:50;}
  .toast{position:fixed; bottom:calc(1.2rem + env(safe-area-inset-bottom,0px)); left:50%; transform:translateX(-50%); background:var(--navy-900); color:#fff; padding:.65rem 1.1rem; border-radius:.6rem; font-size:.83rem; z-index:100; box-shadow:0 8px 24px rgba(0,0,0,.25);}
  .barrow{display:grid; grid-template-columns:150px 1fr 40px; align-items:center; gap:.6rem; font-size:.78rem; padding:.28rem 0;}
  .bartrack{height:9px; border-radius:999px; background:var(--line); overflow:hidden;}
  .barfill{height:100%; border-radius:999px; background:linear-gradient(90deg,var(--navy-700),var(--orange-500));}
  #sidebar{min-height:0; height:100vh; position:sticky; top:0; overflow:hidden; display:flex; flex-direction:column;}
  #navlist{min-height:0; flex:1 1 auto; overflow-y:auto; overscroll-behavior:contain; -webkit-overflow-scrolling:touch;}
  @media(min-width:901px){
    body.sb-collapsed #sidebar{display:none;}
  }
  @media(max-width:900px){
    #sidebar{position:fixed; left:0; top:0; bottom:0; z-index:40; transform:translateX(-100%); transition:transform .18s;}
    #sidebar.open{transform:translateX(0);}
    #scrim{display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:35;}
    #scrim.open{display:block;}
  }
</style>
</head>
<body>

<div id="scrim" onclick="toggleSidebar(false)"></div>

<div class="flex min-h-screen">
  <!-- SIDEBAR -->
  <aside id="sidebar" style="background:var(--navy-950);" class="w-[260px] flex-shrink-0 flex flex-col" >
    <div class="px-4 pt-5 pb-4" style="padding-top:calc(1.25rem + env(safe-area-inset-top,0px));">
      <div class="flex items-center gap-2.5">
        <div style="background:var(--orange-500);" class="w-9 h-9 rounded-lg flex items-center justify-center text-white font-display font-bold text-sm">SAR</div>
        <div class="leading-tight">
          <div class="text-white font-display font-semibold text-sm">BASARNAS</div>
          <div class="text-[11px]" style="color:#93A2BE;">Dashboard Program</div>
        </div>
      </div>
    </div>
    <nav id="navlist" class="flex-1 overflow-y-auto px-3 space-y-0.5 pb-4"></nav>
    <div class="px-4 py-3 text-[11px] border-t" style="border-color:rgba(255,255,255,.08); color:#7284A3;">Data tersimpan di database server</div>
  </aside>

  <!-- MAIN -->
  <div class="flex-1 min-w-0 flex flex-col">
    <header class="panel flex items-center gap-3 px-4 py-3 sticky top-0 z-30" style="padding-top:calc(.75rem + env(safe-area-inset-top,0px));">
      <button class="btn btn-ghost !px-2.5" onclick="toggleSidebar()" aria-label="Menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
      </button>
      <div class="min-w-0">
        <h1 id="pageTitle" class="font-display font-semibold text-base sm:text-lg truncate">Dashboard</h1>
        <p id="pageSub" class="text-xs" style="color:var(--muted);">Ringkasan program dan kegiatan SAR</p>
      </div>
      <div class="ml-auto flex items-center gap-2">
        <?php if ($IS_SU): ?>
        <a href="kelola_akun.php" class="btn btn-ghost !px-2.5 text-xs" title="Kelola akun pengguna" style="text-decoration:none;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span class="hidden sm:inline">Kelola Akun</span>
        </a>
        <?php endif; ?>
        <div class="flex items-center gap-2 pl-2 ml-1 border-l" style="border-color:var(--line);">
          <div class="hidden sm:block text-right leading-tight min-w-0">
            <div class="text-xs font-semibold truncate" style="max-width:140px;"><?= htmlspecialchars($USER['name'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="text-[11px]" style="color:var(--muted);">@<?= htmlspecialchars($USER['username'], ENT_QUOTES, 'UTF-8') ?> · <b><?= htmlspecialchars($MY_ROLE, ENT_QUOTES, 'UTF-8') ?></b></div>
          </div>
          <form method="post" action="logout.php" onsubmit="return keluarAman(event)" class="m-0">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn btn-ghost !px-2.5" title="Keluar" aria-label="Keluar">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
              <span class="hidden sm:inline">Keluar</span>
            </button>
          </form>
        </div>
      </div>
    </header>

    <main id="app" class="p-4 sm:p-6 flex-1 min-w-0"></main>
  </div>
</div>

<div id="modalRoot"></div>
<div id="toastRoot"></div>

<script>window.__INITIAL_REV__ = <?= (int)$INITIAL_REV ?>;</script>
<script>window.__INITIAL_DB__ = <?= json_encode($INITIAL_DB, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;</script>
<script>
/* ============================= STATE & DATA ============================= */
const IS_SU = <?= $IS_SU ? 'true' : 'false' ?>; // akun Admin Utama (ditentukan server)
const STORAGE_KEY = 'basarnas_dashboard_v1';
const currentRole = <?= json_encode($MY_ROLE, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; // peran terikat ke akun (dipilih saat daftar), tidak bisa diganti dari header
const currentUserName = <?= json_encode((string)$USER['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; // nama akun yang sedang login
/* Jejak pelaku: {nama, peran} -> "Nama sebagai (Peran)" */
function pelaku(){ return {nama: currentUserName, peran: currentRole}; }
function pelakuTeks(p){
  const arr = Array.isArray(p) ? p : (p ? [p] : []);
  const t = arr.filter(x=>x && (x.nama || x.peran)).map(x=>(x.nama || '-') + ' sebagai (' + (x.peran || '-') + ')');
  return t.length ? t.join('; ') : '-';
}
/* Data lama (sebelum ada jejak pelaku): peran diambil dari teks keterangan persetujuan bila ada */
function disetujuOlehKegiatan(k){
  if(k.disetujui_oleh && (!Array.isArray(k.disetujui_oleh) || k.disetujui_oleh.length)) return k.disetujui_oleh;
  const m = /(?:Disetujui|Ditolak) oleh ([^:]+?)(?: pada|:|\.|$)/.exec(k.keterangan_persetujuan || '');
  if(m) return {nama:'-', peran:m[1].trim()};
  const a = /\(ACC ([^)]+)\)/.exec(k.keterangan_persetujuan || '');
  return a ? a[1].split(',').map(r=>({nama:'-', peran:r.trim()})) : null;
}
let currentView = 'dashboard';
let search = {};

function uid(prefix, arr){
  const n = (arr.length ? Math.max(...arr.map(x=>parseInt((x.id||'0').split('-').pop())||0)) : 0) + 1;
  return prefix + '-' + String(n).padStart(3,'0');
}
function todayISO(){ return new Date().toISOString().slice(0,10); }
/* Tampilan tanggal di Dashboard: tanggal-bulan-tahun (data tetap tersimpan yyyy-mm-dd) */
function tglDMY(v){
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v==null?'':v).trim());
  return m ? m[3]+'-'+m[2]+'-'+m[1] : v;
}
/* Rentang tanggal kegiatan: "dd-mm-yyyy" atau "dd-mm-yyyy s.d. dd-mm-yyyy" bila berlangsung lebih dari sehari */
function tglRentang(k){
  const a = String(k.tanggal||'').slice(0,10), b = String(k.tanggal_selesai||'').slice(0,10);
  return (b && b > a) ? tglDMY(a)+' s.d. '+tglDMY(b) : tglDMY(a);
}
function dashVal(f, row){ const v = fieldValue(f,row); return f.type==='date' ? tglDMY(v) : v; }
function dashCell(key, f, row){ return f.type==='date' ? esc(tglDMY(fieldValue(f,row))) : cellHtml(key,f,row); }
function nowStr(){ return new Date().toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'}); }
function esc(s){ return (s===undefined||s===null) ? '' : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* ---- Module field definitions (10 kelas program generik) ---- */
const GENERIC_MODULES = [
{key:'sgts', label:'Sar Go To School', prefix:'SGS', fields:[
  {key:'nama_sekolah', label:'Nama Sekolah', type:'text', required:true, col:1},
  {key:'alamat_sekolah', label:'Alamat Sekolah', type:'text'},
  {key:'tanggal', label:'Tanggal Kegiatan', type:'date', col:1},
  {key:'waktu', label:'Waktu', type:'time'},
  {key:'jumlah_peserta', label:'Jumlah Peserta', type:'number'},
  {key:'materi', label:'Materi', type:'text'},
  {key:'penanggung_jawab', label:'Penanggung Jawab', type:'text', col:1},
  {key:'narasumber', label:'Narasumber', type:'text'},
  {key:'anggota_bertugas', label:'Anggota Bertugas', type:'text'},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status Kegiatan', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1},
]},
{key:'bimtek', label:'Bimtek', prefix:'BMT', fields:[
  {key:'nama_bimtek', label:'Nama Bimtek', type:'text', required:true, col:1},
  {key:'jenis_bimtek', label:'Jenis Bimtek', type:'text'},
  {key:'materi', label:'Materi', type:'text'},
  {key:'tanggal', label:'Tanggal', type:'date', col:1},
  {key:'waktu', label:'Waktu', type:'time'},
  {key:'lokasi', label:'Lokasi', type:'text', col:1},
  {key:'penyelenggara', label:'Penyelenggara', type:'text'},
  {key:'narasumber', label:'Narasumber', type:'text'},
  {key:'peserta', label:'Peserta', type:'text'},
  {key:'jumlah_peserta', label:'Jumlah Peserta', type:'number'},
  {key:'petugas', label:'Anggota/Petugas', type:'text'},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1},
]},
{key:'potensi', label:'Potensi', prefix:'POT', fields:[
  {key:'nama_potensi', label:'Nama Potensi', type:'text', required:true, col:1},
  {key:'kategori', label:'Kategori', type:'select', options:['TNI/Polri','Pemerintah','Perusahaan','Organisasi'], col:1},
  {key:'nama_kontak', label:'Nama Kontak', type:'text'},
  {key:'nomor_kontak', label:'Nomor Kontak', type:'tel'},
  {key:'alamat', label:'Alamat', type:'text'},
  {key:'wilayah', label:'Wilayah', type:'text', col:1},
  {key:'kompetensi', label:'Kompetensi', type:'text'},
  {key:'jumlah_tersertifikasi', label:'Tersertifikasi', type:'number', col:1},
  {key:'jumlah_belum_sertifikasi', label:'Belum Tersertifikasi', type:'number', col:1},
  {key:'jumlah_total', label:'Seluruh Anggota', type:'computed', computeFrom:['jumlah_tersertifikasi','jumlah_belum_sertifikasi'], col:1},
  {key:'status', label:'Status Potensi', type:'select', options:['Aktif','Nonaktif'], col:1, isStatus:1},
  {key:'alut_sapras', label:'Alut & Sapras', type:'text'},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
]},
{key:'narasumber', label:'Narasumber', prefix:'NRS', fields:[
  {key:'nama', label:'Nama Narasumber', type:'text', required:true, col:1},
  {key:'instansi', label:'Instansi', type:'text', col:1},
  {key:'jabatan', label:'Jabatan', type:'text'},
  {key:'keahlian', label:'Keahlian', type:'text', col:1},
  {key:'nomor_kontak', label:'Nomor Kontak', type:'tel'},
  {key:'email', label:'Email', type:'email'},
  {key:'materi', label:'Materi', type:'text'},
  {key:'kegiatan_terkait', label:'Kegiatan Terkait', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
]},
{key:'diklat', label:'Diklat', prefix:'DKL', fields:[
  {key:'nama_diklat', label:'Nama Diklat', type:'text', required:true, col:1},
  {key:'jenis_diklat', label:'Jenis Diklat', type:'text'},
  {key:'materi', label:'Materi', type:'text'},
  {key:'tanggal_mulai', label:'Tanggal Mulai', type:'date', col:1},
  {key:'tanggal_selesai', label:'Tanggal Selesai', type:'date'},
  {key:'lokasi', label:'Lokasi', type:'text', col:1},
  {key:'penyelenggara', label:'Penyelenggara', type:'text'},
  {key:'instruktur', label:'Instruktur', type:'text'},
  {key:'peserta', label:'Peserta', type:'text'},
  {key:'jumlah_peserta', label:'Jumlah Peserta', type:'number'},
  {key:'anggota_bertugas', label:'Anggota Bertugas', type:'text'},
  {key:'sertifikat', label:'Sertifikat', type:'text'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1},
]},
{key:'rescuer', label:'Rescuer', prefix:'RSC', fields:[
  {key:'nama_anggota', label:'Nama Anggota', type:'text', required:true, col:1, derivedFrom:{ref:'anggota', idKey:'anggota_id', field:'nama'}},
  {key:'nip', label:'NIP/ID Anggota', type:'text', col:1, derivedFrom:{ref:'anggota', idKey:'anggota_id', field:'nip'}},
  {key:'jabatan', label:'Jabatan', type:'text'},
  {key:'kompetensi', label:'Kompetensi', type:'text'},
  {key:'spesialisasi', label:'Spesialisasi', type:'text'},
  {key:'sertifikasi', label:'Sertifikasi', type:'text'},
  {key:'masa_berlaku', label:'Masa Berlaku Sertifikasi', type:'date'},
  {key:'status', label:'Status Aktif', type:'select', options:['Aktif','Nonaktif'], col:1, isStatus:1},
  {key:'riwayat', label:'Riwayat Penugasan', type:'textarea'},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
]},
/* Pembinaan Tenaga SAR: satu tabel data dengan tiga tab/kategori (Pembinaan, Pembelajaran, Samapta).
   Kategori disimpan di kolom 'kategori_pembinaan' (data lama tanpa kategori = Pembinaan).
   Field ber-'kat' hanya tampil di kategorinya; 'labels' mengganti label per kategori;
   'col' = kolom di tabel Dashboard; 'tabCols' = kolom tabel di halaman modul per tab. */
{key:'pembinaan', label:'Pembinaan Tenaga SAR', prefix:'PBN',
  tabs:['Pembelajaran','Samapta'],
  tabCols:{
    'Pembelajaran':['nama_kegiatan','materi','tanggal','lokasi','jumlah_peserta','dokumentasi'],
    'Samapta':['nama_kegiatan','materi','tanggal','lokasi','jumlah_peserta','dokumentasi'],
  },
  fields:[
  {key:'nama_kegiatan', label:'Nama Kegiatan', type:'text', required:true, col:1},
  {key:'kategori_pembinaan', label:'Kategori', type:'text', col:1, noForm:1},
  {key:'jenis_pembelajaran', label:'Jenis', type:'select', options:['Teori','Praktek','Latihan'], kat:'Pembelajaran'},
  {key:'tanggal', label:'Tanggal', type:'date', col:1},
  {key:'lokasi', label:'Lokasi', type:'select', options:['KPP Jakarta','USS Bekasi','USS Bogor','Pos Sar Sukabumi','Kep Seribu','Dermaga Pantai Mutiara','KN SAR Ganesha','KN SAR Antasena']},
  {key:'materi', label:'Materi', labels:{Samapta:'Materi / Program Latihan'}, type:'text'},
  {key:'peserta', label:'Peserta', labels:{Samapta:'Nama Anggota'}, type:'text'},
  {key:'jumlah_peserta', label:'Jumlah Peserta', labels:{Pembelajaran:'Jumlah Anggota'}, type:'number'},
  {key:'jumlah_jam', label:'Jumlah Jam Pembelajaran', type:'number', kat:'Pembelajaran', placeholder:'Contoh: 8'},
  {key:'hasil', label:'Hasil', labels:{Pembelajaran:'Hasil Pembelajaran'}, type:'textarea', kat:'Pembelajaran'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', labels:{Pembelajaran:'Dokumentasi (file apa saja)', Samapta:'Dokumentasi (file apa saja)'}, type:'text'},
]},
{key:'pemberdayaan', label:'Pemberdayaan Masyarakat', prefix:'PBM', fields:[
  {key:'nama_kegiatan', label:'Nama Kegiatan', type:'text', required:true, col:1},
  {key:'sasaran', label:'Kelompok/Masyarakat Sasaran', type:'text', col:1},
  {key:'lokasi', label:'Lokasi', type:'text', col:1},
  {key:'tanggal', label:'Tanggal', type:'date', col:1},
  {key:'jumlah_peserta', label:'Jumlah Peserta', type:'number'},
  {key:'materi', label:'Materi', type:'text'},
  {key:'narasumber', label:'Narasumber', type:'text'},
  {key:'petugas', label:'Petugas', type:'text'},
  {key:'hasil', label:'Hasil Kegiatan', type:'textarea'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1},
]},
{key:'uji_periodik', label:'Uji Periodik', prefix:'UPR', fields:[
  {key:'nama_uji', label:'Nama Uji Periodik', type:'text', required:true, col:1},
  {key:'jenis_uji', label:'Jenis Uji', type:'text'},
  {key:'materi', label:'Materi', type:'text'},
  {key:'tanggal_mulai', label:'Tanggal Mulai', type:'date', col:1},
  {key:'tanggal_selesai', label:'Tanggal Selesai', type:'date'},
  {key:'lokasi', label:'Lokasi', type:'text', col:1},
  {key:'penyelenggara', label:'Penyelenggara', type:'text'},
  {key:'penguji', label:'Penguji', type:'text'},
  {key:'peserta', label:'Peserta', type:'text'},
  {key:'jumlah_peserta', label:'Jumlah Peserta', type:'number'},
  {key:'anggota_bertugas', label:'Anggota Bertugas', type:'text'},
  {key:'sertifikat', label:'Sertifikat', type:'text'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1, autoFromKegiatan:1},
]},
{key:'uji_pemeliharaan', label:'Uji Pembinaan', prefix:'UPM', fields:[
  {key:'nama_peralatan', label:'Nama Peralatan', type:'text', required:true, col:1},
  {key:'nomor_inventaris', label:'Nomor Inventaris', type:'text', col:1},
  {key:'jenis_peralatan', label:'Jenis Peralatan', type:'text'},
  {key:'tanggal_pemeriksaan', label:'Tanggal Pemeriksaan', type:'date', col:1},
  {key:'kondisi', label:'Kondisi', type:'select', options:['Baik','Rusak Ringan','Rusak Berat'], col:1},
  {key:'jenis_pemeliharaan', label:'Jenis Pemeliharaan', type:'text'},
  {key:'petugas', label:'Petugas', type:'text'},
  {key:'hasil', label:'Hasil Pemeriksaan', type:'textarea'},
  {key:'tindakan', label:'Tindakan', type:'textarea'},
  {key:'jadwal_berikutnya', label:'Jadwal Pemeliharaan Berikutnya', type:'date'},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Baik','Perlu Perbaikan','Selesai Diperbaiki'], col:1, isStatus:1},
]},
{key:'lainnya', label:'Lainnya', prefix:'LNY', fields:[
  {key:'nama_kegiatan', label:'Nama Kegiatan', type:'text', required:true, col:1},
  {key:'jenis_kegiatan', label:'Jenis Kegiatan', type:'text'},
  {key:'tanggal', label:'Tanggal', type:'date', col:1},
  {key:'waktu', label:'Waktu', type:'time'},
  {key:'lokasi', label:'Lokasi', type:'text', col:1},
  {key:'penyelenggara', label:'Penyelenggara', type:'text'},
  {key:'penanggung_jawab', label:'Penanggung Jawab', type:'text'},
  {key:'peserta', label:'Peserta', type:'text'},
  {key:'jumlah_peserta', label:'Jumlah Peserta', type:'number'},
  {key:'anggota_bertugas', label:'Anggota Bertugas', type:'text'},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
  {key:'dokumentasi', label:'Dokumentasi (tautan)', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Direncanakan','Berlangsung','Selesai'], col:1, isStatus:1},
]},
];

const ANGGOTA_FIELDS = [
  {key:'nama', label:'Nama Lengkap', type:'text', required:true, col:1},
  {key:'nip', label:'NIP/ID', type:'text', col:1},
  {key:'jabatan', label:'Jabatan', type:'text', col:1},
  {key:'unit', label:'Unit', type:'text', col:1},
  {key:'whatsapp', label:'Nomor WhatsApp', type:'tel', required:true, col:1},
  {key:'email', label:'Email', type:'email'},
  {key:'kompetensi', label:'Kompetensi', type:'text'},
  {key:'spesialisasi', label:'Spesialisasi', type:'text'},
  {key:'sertifikasi', label:'Sertifikasi', type:'text'},
  {key:'status', label:'Status Anggota', type:'select', options:['Aktif','Nonaktif'], col:1, isStatus:1},
  {key:'keterangan', label:'Keterangan', type:'textarea'},
];

/* Modul inventaris: berdiri sendiri (bukan jenis Kegiatan/Tugas), dikelola langsung lewat tombol Tambah/Ubah/Hapus. */
const EXTRA_MODULES = [
{key:'peralatan', label:'Peralatan', prefix:'PRL', fields:[
  {key:'nama_barang', label:'Nama Barang', type:'text', required:true, col:1},
  {key:'kode_rak', label:'Kode Rak', type:'text', col:1},
  {key:'lokasi', label:'Lokasi', type:'select', options:['KPP Jakarta','USS Bekasi','USS Bogor','Pos Sukabumi'], col:1},
  {key:'kategori', label:'Kategori', type:'select', options:['Palsar Darat','Palsar Air','Medis','Komunikasi','Palsar Udara'], col:1},
  {key:'jumlah_baik', label:'Jumlah Baik', type:'number', col:1},
  {key:'jumlah_rusak', label:'Jumlah Rusak', type:'number', col:1},
  {key:'jumlah', label:'Jumlah', type:'computed', computeFrom:['jumlah_baik','jumlah_rusak'], col:1},
  {key:'tersedia', label:'Tersedia', type:'computed', computeFrom:['jumlah_baik'], minusBorrowed:1, col:1},
  {key:'keterangan', label:'Ket', type:'textarea', col:1},
]},
{key:'kendaraan', label:'Kendaraan', prefix:'KDR', fields:[
  {key:'nama_kendaraan', label:'Nama Kendaraan', type:'text', required:true, col:1},
  {key:'sapras', label:'Sapras', type:'hidden'},
  {key:'jenis_kendaraan', label:'Jenis Kendaraan', type:'select', options:['Mobil','Motor','Truk','Ambulans','Kapal','RIB / Perahu Karet'], col:1},
  {key:'nomor_polisi', label:'Nomor Polisi / Registrasi', type:'text', col:1},
  {key:'merk_tipe', label:'Merk / Tipe', type:'text'},
  {key:'kondisi', label:'Kondisi', type:'select', options:['Serviceable','Unserviceable','Scrap'], col:1},
  {key:'lokasi', label:'Lokasi / Pangkalan', type:'select', options:['KPP Jakarta','USS Bekasi','USS Bogor','Pos SAR Sukabumi']},
  {key:'tanggal', label:'Tanggal', type:'date', col:1},
  {key:'km_saat_ini', label:'KM Saat Ini', type:'number', decimal:1, darat:1, placeholder:'mis. 45000'},
  {key:'bbm', label:'BBM', type:'number', decimal:1, darat:1, placeholder:'mis. 40'},
  {key:'endurance', label:'Endurance', type:'number', laut:1, unit:'Jam', placeholder:'mis. 12 (jam)'},
  {key:'speed', label:'Speed (knot/km per jam)', type:'number', laut:1, unit:'knot', placeholder:'mis. 25 (knot)'},
  {key:'range', label:'Range (nm/km)', type:'number', laut:1, unit:'nm', placeholder:'mis. 300 (nm)'},
  {key:'konsumsi_bbm', label:'Konsumsi Bahan Bakar (liter/jam)', type:'number', laut:1, unit:'liter/jam', placeholder:'mis. 80 (liter/jam)'},
  {key:'cadangan_bbm', label:'Cadangan Bahan Bakar (liter)', type:'number', laut:1, unit:'liter', placeholder:'mis. 500 (liter)'},
  {key:'persen_bbm', label:'Persentase Bahan Bakar (%)', type:'number', laut:1, unit:'%', placeholder:'mis. 75 (%)'},
  {key:'kecepatan_angin', label:'Kecepatan Angin', type:'number', laut:1, unit:'knot', placeholder:'mis. 15 (knot)'},
  {key:'arah_angin', label:'Arah Angin', type:'text', laut:1, placeholder:'mis. Barat Laut'},
  {key:'keterangan', label:'Ket', type:'textarea', col:1},
]},
{key:'peminjaman', label:'Peminjaman', prefix:'PMJ', fields:[
  {key:'nama_peminjam', label:'Nama Peminjam', type:'text', required:true, col:1},
  {key:'jenis_barang', label:'Jenis', type:'select', options:['Peralatan','Kendaraan'], col:1},
  {key:'nama_barang', label:'Nama Peralatan / Kendaraan', type:'text', required:true, col:1},
  {key:'jumlah', label:'Jumlah', type:'number'},
  {key:'tanggal', label:'Tanggal Pinjam', type:'date', col:1},
  {key:'tanggal_kembali_rencana', label:'Rencana Kembali', type:'date'},
  {key:'tanggal_kembali', label:'Tanggal Dikembalikan', type:'date'},
  {key:'keperluan', label:'Keperluan / Kegiatan', type:'text'},
  {key:'penanggung_jawab', label:'Penanggung Jawab', type:'text'},
  {key:'status', label:'Status', type:'select', options:['Dipinjam','Dikembalikan','Terlambat'], col:1, isStatus:1},
  {key:'keterangan', label:'Ket', type:'textarea', col:1},
]},
/* Pengajuan: dua kategori (Kebutuhan & Pemeliharaan) untuk Peralatan / Kendaraan, dengan ACC/Tolak pimpinan.
   Kunci data internal tetap 'pemeliharaan' agar data lama tidak hilang. Field ber-kat hanya tampil di kategorinya. */
{key:'pemeliharaan', label:'Pengajuan', prefix:'PGJ', fields:[
  {key:'kategori_pengajuan', label:'Kategori', type:'text', col:1, noForm:1},
  {key:'jenis_barang', label:'Jenis', type:'select', options:['Peralatan','Kendaraan'], col:1},
  {key:'nama_barang', label:'Nama Peralatan / Kendaraan', labelK:'Nama Barang yang Dibutuhkan', type:'text', required:true, col:1},
  {key:'tanggal', label:'Tanggal Pengajuan', type:'date', col:1},
  {key:'jumlah_kebutuhan', label:'Jumlah Dibutuhkan', type:'number', kat:'Kebutuhan'},
  {key:'estimasi_biaya', label:'Estimasi Biaya (Rp)', type:'number', decimal:1, kat:'Kebutuhan', placeholder:'mis. 5000000'},
  {key:'tingkat_urgensi', label:'Tingkat Urgensi', type:'select', options:['Rendah','Sedang','Tinggi'], kat:'Kebutuhan'},
  {key:'alasan', label:'Alasan / Spesifikasi yang Dibutuhkan', type:'textarea', kat:'Kebutuhan'},
  {key:'jumlah', label:'Jumlah Unit (khusus peralatan)', type:'number', kat:'Pemeliharaan'},
  {key:'jenis_pemeliharaan', label:'Jenis Pemeliharaan', type:'select', options:['Perawatan Rutin','Servis Berkala','Perbaikan','Pemeriksaan / Inspeksi','Penggantian Suku Cadang','Kalibrasi'], kat:'Pemeliharaan'},
  {key:'tanggal_selesai', label:'Tanggal Selesai', type:'date', kat:'Pemeliharaan'},
  {key:'pelaksana', label:'Petugas / Bengkel', type:'text', kat:'Pemeliharaan'},
  {key:'uraian', label:'Uraian Pekerjaan / Tindakan', type:'textarea', kat:'Pemeliharaan'},
  {key:'suku_cadang', label:'Suku Cadang / Bahan yang Digunakan', type:'text', kat:'Pemeliharaan'},
  {key:'km_saat_ini', label:'KM Saat Pemeliharaan (khusus kendaraan darat)', type:'number', decimal:1, placeholder:'mis. 45000', kat:'Pemeliharaan'},
  {key:'biaya', label:'Biaya (Rp)', type:'number', decimal:1, placeholder:'mis. 1500000', kat:'Pemeliharaan'},
  {key:'kondisi_setelah', label:'Kondisi Setelah Pemeliharaan', type:'select', options:['Baik / Layak Pakai','Perlu Tindak Lanjut','Rusak'], kat:'Pemeliharaan'},
  {key:'jadwal_berikutnya', label:'Jadwal Pemeliharaan Berikutnya', type:'date', kat:'Pemeliharaan'},
  {key:'status', label:'Status Pekerjaan', type:'select', options:['Dijadwalkan','Sedang Dikerjakan','Selesai'], col:1, isStatus:1, kat:'Pemeliharaan'},
  {key:'status_persetujuan', label:'Persetujuan', type:'text', col:1, noForm:1, noDetail:1},
  {key:'keterangan', label:'Ket', type:'textarea'},
]},
];
const TABLE_MODULES = GENERIC_MODULES.concat(EXTRA_MODULES);

const NAV_ORDER = [
  {key:'dashboard', label:'Dashboard', short:'DB'},
  {key:'kegiatan', label:'Kegiatan/Tugas', short:'KT'},
  {key:'persuratan', label:'Persuratan', short:'PS'},
  ...TABLE_MODULES.map(m=>({key:m.key, label:m.label, short:m.prefix.slice(0,2)})),
  {key:'anggota', label:'Daftar Anggota', short:'AG'},
];

/* ============================= SEED DATA ============================= */
function seed(){
  const anggota = [
    {id:'AG-001', nama:'DESIANA KARTIKA BAHARI S.E, M.H.', nip:'197812092010122001', jabatan:'Kepala Kantor', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pembina Tk. I (IV/b); TMT Golongan: 01/04/2026; TMT Jabatan: 10/01/2024'},
    {id:'AG-002', nama:'ABDULLAH KOMAENY S.H., M.H.', nip:'198305222009121001', jabatan:'Kepala Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pembina (IV/a); TMT Golongan: 01/01/2026; TMT Jabatan: 13/11/2025'},
    {id:'AG-003', nama:'AKHMAD RIZKIANSAH S.Kom.,M.Sc.', nip:'198003012010121001', jabatan:'Kepala Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Tk. I (III/d); TMT Golongan: 01/04/2024; TMT Jabatan: 15/01/2025'},
    {id:'AG-004', nama:'DIDIT PERMANA S.Kom.', nip:'197708162009121003', jabatan:'Kepala Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Tk. I (III/d); TMT Golongan: 01/10/2022; TMT Jabatan: 15/01/2025'},
    {id:'AG-005', nama:'ENGKOS', nip:'198204102025211066', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-006', nama:'SIGIT DJOKO PRASETYO', nip:'198101262025211029', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-007', nama:'ARIF TIRTA PRAWITA', nip:'199309182025211070', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-008', nama:'SAIMIMAN', nip:'199808082025211041', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-009', nama:'NICO ALFACHREZA DARMAWAN', nip:'200105242025211010', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-010', nama:'FATHUR WARDHANI', nip:'199905152025211023', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-011', nama:'GANJAR SUWARDI H', nip:'199409152025211049', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-012', nama:'TAUFIK HIDAYAT', nip:'200004132025211024', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'TMT Jabatan: 01/10/2025'},
    {id:'AG-013', nama:'ESA ASEP SAEPUDIN', nip:'199412122025211012', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-014', nama:'FONDA SURYO TOSAN AJI', nip:'199010072025211032', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-015', nama:'RIANTO PRIMADANI', nip:'199704032025211005', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-016', nama:'WILLYAMSYAH IRTANTA', nip:'199608192025211003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-017', nama:'ARIS USDOKO', nip:'197806232025211007', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-018', nama:'DEDE IRAWAN', nip:'198107102025211005', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-019', nama:'HAERUL UMAM', nip:'199504232025211009', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-020', nama:'SEHAT AGUSTINUS RIZALDI ROY SITEPU', nip:'198308142025211024', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-021', nama:'SYUKRIA NOFRIZAL', nip:'199211212025211009', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-022', nama:'WISHNU YUDHANTO', nip:'200111262025211002', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-023', nama:'ARIP RACHMAN HAKIM', nip:'199208212025211014', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025'},
    {id:'AG-024', nama:'BAMBANG KUSWITO', nip:'199701162025211026', jabatan:'Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: V; TMT Golongan: 01/10/2025; TMT Jabatan: 01/10/2025'},
    {id:'AG-025', nama:'ROMY BATARA, S.E.', nip:'197801072005041002', jabatan:'Analis Pengelolaan Keuangan APBN Ahli Muda', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Tk. I (III/d); TMT Golongan: 01/10/2018; TMT Jabatan: 01/04/2021'},
    {id:'AG-026', nama:'DJENAL ARIFIN, S.E.', nip:'197508272009121001', jabatan:'Penata Kelola Pencarian dan Pertolongan Ahli Muda', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Tk. I (III/d); TMT Golongan: 01/04/2022; TMT Jabatan: 27/06/2023'},
    {id:'AG-027', nama:'DEVY HIDAYANTI S.E., M.A.', nip:'198712162010122005', jabatan:'Penelaah Teknis Kebijakan Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Tk. I (III/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/11/2025'},
    {id:'AG-028', nama:'EDWARD RAMADHANA, S.Kom.', nip:'198207092005021001', jabatan:'Pengelola Pencarian dan Pertolongan Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata (III/c); TMT Golongan: 01/04/2023; TMT Jabatan: 01/05/2025'},
    {id:'AG-029', nama:'ASEP SUPRIYATNA, S.E.', nip:'198411072005021001', jabatan:'Analis Pengelolaan Keuangan APBN Ahli Muda', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata (III/c); TMT Golongan: 01/04/2023; TMT Jabatan: 15/01/2025'},
    {id:'AG-030', nama:'TEUKU SUFRIADI RAMADHANI S.T., M.Si.', nip:'197410012007121001', jabatan:'Markonis Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata (III/c); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-031', nama:'MOHAMMAD IHWAN S.E.', nip:'197405302007121001', jabatan:'Mualim I Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata (III/c); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-032', nama:'ENDRA RADENTA BARUS A.Md.', nip:'197409272008121001', jabatan:'Mualim I Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata (III/c); TMT Golongan: 01/04/2025; TMT Jabatan: 01/05/2025'},
    {id:'AG-033', nama:'HARDIANSYAH PUTRA S.E.', nip:'198512152010121003', jabatan:'Pranata Pencarian dan Pertolongan Penyelia Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata (III/c); TMT Golongan: 01/04/2026; TMT Jabatan: 17/12/2025'},
    {id:'AG-034', nama:'NURUL KARLINA S.Sos.', nip:'198605052006042001', jabatan:'Pranata Hubungan Masyakarat Ahli Pertama Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2017; TMT Jabatan: 01/12/2024'},
    {id:'AG-035', nama:'MUHAMMAD ARIEF BUDIMAN', nip:'197206011999031002', jabatan:'Operator Layanan Operasional Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2020; TMT Jabatan: 01/05/2025'},
    {id:'AG-036', nama:'DITO PEBYAN PRASETYO A.MK', nip:'198701232007121002', jabatan:'Perawat Mahir Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2022; TMT Jabatan: 01/03/2019'},
    {id:'AG-037', nama:'DIAN RAHMANTO, A.Md.', nip:'198012022007121001', jabatan:'Pengelola Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2022; TMT Jabatan: 01/05/2025'},
    {id:'AG-038', nama:'TETTY ROSMAULI S.E.', nip:'198704142007122001', jabatan:'Penata Kelola Pencarian dan Pertolongan Ahli Pertama Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2023; TMT Jabatan: 27/06/2023'},
    {id:'AG-039', nama:'STEFANUS HARRENDRA OUCTHO FRENGKY S.AP.', nip:'198810282010121002', jabatan:'Pranata Pencarian dan Pertolongan Penyelia Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2024; TMT Jabatan: 09/09/2026'},
    {id:'AG-040', nama:'IRINNE IZDIHAR S.I.Kom.', nip:'199503122019022002', jabatan:'Pranata Humas Ahli Pertama Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2024; TMT Jabatan: 01/01/2021'},
    {id:'AG-041', nama:'ERDI JATMIKO S.M.', nip:'198802262007121001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-042', nama:'PASUNDANU KRESNA S.Tr.Pel.', nip:'199609242020121003', jabatan:'Nakhoda Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-043', nama:'IMAM HANAGIA S.M.', nip:'198209132005021002', jabatan:'Mualim II Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2025; TMT Jabatan: 01/05/2025'},
    {id:'AG-044', nama:'APRIANTO PRAPTOMO S.E.', nip:'198604132007121001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2025; TMT Jabatan: 01/10/2023'},
    {id:'AG-045', nama:'DARMAWAN', nip:'198302082007121002', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/06/2026; TMT Jabatan: 10/10/2023'},
    {id:'AG-046', nama:'CAHYADI ARI KUSUMO S.M.', nip:'198805212009121003', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-047', nama:'IWAN SUBEKTI S.E.', nip:'197203252006041005', jabatan:'Mualim III Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/10/2022; TMT Jabatan: 01/05/2026'},
    {id:'AG-048', nama:'M. IRVAN KRISTIANTO A.Md.Pjk.', nip:'198605192010121002', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/02/2024; TMT Jabatan: 25/10/2023'},
    {id:'AG-049', nama:'FABER SINAGA', nip:'198804062008121001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-050', nama:'ANDRIAS RESTIYANTO', nip:'198805122007121001', jabatan:'Nakhkoda Kapal Kelas IV Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2026'},
    {id:'AG-051', nama:'DWI NUR ARIANI', nip:'198811292007122001', jabatan:'Pengelola Pencarian dan Pertolongan Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-052', nama:'MAYSENO ADI NUGROHO', nip:'198205152007121002', jabatan:'Pengelola Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-053', nama:'AZIZ KURNIAWAN', nip:'198009222007121002', jabatan:'Mandor Mesin Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-054', nama:'HOIRIYAH S.E.', nip:'198908162009122001', jabatan:'Arsiparis Mahir Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/02/2025; TMT Jabatan: 21/11/2024'},
    {id:'AG-055', nama:'YUNITA SARI A.Md.', nip:'199106272010122002', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/01/2026; TMT Jabatan: 13/11/2025'},
    {id:'AG-056', nama:'VIDI OCTAVNUARI', nip:'199101082009121001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/03/2026; TMT Jabatan: 17/12/2025'},
    {id:'AG-057', nama:'SURYO ADIANTO', nip:'199103162014021001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/09/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-058', nama:'AHMAD NUR IMAN', nip:'199010122010121001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/09/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-059', nama:'ZAINUDIN', nip:'198203282009121002', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Penata Muda (III/a); TMT Golongan: 01/09/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-060', nama:'RAMLI PRASETIO, A.Md.', nip:'198410252007121003', jabatan:'Pranata Hubungan Masyarakat Terampil Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2012; TMT Jabatan: 01/01/2018'},
    {id:'AG-061', nama:'SAHAT PARULIAN', nip:'198903082009121002', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2021; TMT Jabatan: 01/10/2023'},
    {id:'AG-062', nama:'FAJAR LAKSANA', nip:'199205022010121002', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2021; TMT Jabatan: 01/10/2023'},
    {id:'AG-063', nama:'MUHAMMAD FAZRY M. ASLAM', nip:'199201262010121001', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2021; TMT Jabatan: 01/10/2023'},
    {id:'AG-064', nama:'ENDAH WATI A.Md.Pjk.', nip:'198808082010122003', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2022; TMT Jabatan: 01/10/2023'},
    {id:'AG-065', nama:'ZULFIKRI BINIS', nip:'199003242009121001', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2022; TMT Jabatan: 09/09/2026'},
    {id:'AG-066', nama:'BOBY YOENARTHA PUTRA', nip:'198806082009121003', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2022; TMT Jabatan: 01/10/2023'},
    {id:'AG-067', nama:'DEVI DWI HARINI A.Md.', nip:'198809212010122001', jabatan:'Pengolah Data dan Informasi Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2022; TMT Jabatan: 01/05/2025'},
    {id:'AG-068', nama:'DIAN HERDANINGSIH', nip:'199106062010122001', jabatan:'Operator Layanan Operasional Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2022; TMT Jabatan: 01/08/2026'},
    {id:'AG-069', nama:'MUHAMMAD GUFRAN', nip:'199205052010121001', jabatan:'Juru Minyak Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/05/2025'},
    {id:'AG-070', nama:'AHMAD KOSASIH', nip:'198306132010121002', jabatan:'Juru Minyak Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/05/2025'},
    {id:'AG-071', nama:'I PUTU SUWARTIKA', nip:'198901182010121001', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-072', nama:'WAHYU SUWIGNYO A.Md.Kom.', nip:'198912292014021002', jabatan:'Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 09/09/2026'},
    {id:'AG-073', nama:'RIO SARDITYO UTOMO', nip:'198911252009121002', jabatan:'Pranata Keuangan APBN Terampil Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2023; TMT Jabatan: 01/04/2021'},
    {id:'AG-074', nama:'CANDRA AGUS SETIYAWAN A.Md.', nip:'199408232019021001', jabatan:'Nakhoda Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2023; TMT Jabatan: 01/05/2025'},
    {id:'AG-075', nama:'DONNY PRATAMA', nip:'199203292010121002', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-076', nama:'RAINALDO SITEPU A.Md.', nip:'199704132020121001', jabatan:'Pengelola Pencarian dan Pertolongan Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-077', nama:'ALI MASHAR A.Md.', nip:'199011032020121002', jabatan:'Kepala Kamar Mesin Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-078', nama:'MEGAWATI', nip:'199207262010122001', jabatan:'Operator Layanan Operasional Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2025; TMT Jabatan: 01/08/2026'},
    {id:'AG-079', nama:'GUSTI WISNU WARDHANA PUTRA', nip:'199010232010121001', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/03/2026; TMT Jabatan: 01/07/2025'},
    {id:'AG-080', nama:'AULIA SHOLIHANTO', nip:'199003022009121002', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/03/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-081', nama:'MUHAMMAD RIZALDY FITRAH A.Md.', nip:'199702162017121004', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-082', nama:'PAMBUDI NURWIDJAYA', nip:'199407272017121007', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-083', nama:'SURYA ANGGA TIRTA SRIDARMAWAN A.Md.Pjk.', nip:'199807112017121009', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-084', nama:'CIGRA APTA LABAN PONJOT', nip:'199404102017121003', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-085', nama:'FANDU FAISAL', nip:'199511082017121006', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-086', nama:'DHINAR CAHYO PUTRA', nip:'199707182017121005', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-087', nama:'LINTANG AFI YUDHISTIRA', nip:'199501052017121006', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-088', nama:'RIFAN GUSRIANTO', nip:'199304042017121009', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-089', nama:'PRAYOGA NURBIYANTO', nip:'199803032017121005', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023'},
    {id:'AG-090', nama:'RESTI NINGSIH', nip:'199402202014022001', jabatan:'Operator Layanan Operasional', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/02/2025; TMT Jabatan: 01/08/2026'},
    {id:'AG-091', nama:'MC RYAN CHRISTIAN SENDUK', nip:'199108302009121001', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/09/2025; TMT Jabatan: 01/10/2023'},
    {id:'AG-092', nama:'MUHAMMAD ALVINDRA HUSSEN', nip:'199606252017121007', jabatan:'Juru Minyak Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025'},
    {id:'AG-093', nama:'MUNARWI ATFAN', nip:'198606232017121002', jabatan:'Kepala Kamar Mesin Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025'},
    {id:'AG-094', nama:'SIGIT PRASETYO', nip:'199702202017121005', jabatan:'Markonis Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025'},
    {id:'AG-095', nama:'SUMARNA', nip:'199401212017121003', jabatan:'Juru Minyak Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025'},
    {id:'AG-096', nama:'IRFAN KELIREY', nip:'199403272017121007', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/06/2026; TMT Jabatan: 01/10/2023'},
    {id:'AG-097', nama:'MUHAMAD TAJUL ARIFIN A.Md.Kep.', nip:'200010082025061004', jabatan:'Pengelola Layanan Kesehatan Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/06/2026; TMT Jabatan: 01/06/2025'},
    {id:'AG-098', nama:'MUHAMAD AMIRUL MUZAYIN A.Md.T.', nip:'199005122025061002', jabatan:'Pranata Sumber Daya Manusia Aparatur Terampil Subbagian Umum', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur (II/c); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-099', nama:'YOGA ADITYA SAPUTRA NUGROHO', nip:'199606022020121005', jabatan:'Mandor Mesin Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2026'},
    {id:'AG-100', nama:'FAJRI FITRAH HERMAWAN', nip:'199701182020121003', jabatan:'Kelasi Kapal Kelas II', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-101', nama:'HENGKI MADIYANTO', nip:'199211072020121004', jabatan:'Kelasi Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025'},
    {id:'AG-102', nama:'ROHMAN SYAH', nip:'200105162020121002', jabatan:'Tugas Belajar Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/12/2022'},
    {id:'AG-103', nama:'HERLAN SOULTAN ABDUL ALBAR', nip:'199012032009121002', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/09/2025; TMT Jabatan: 01/05/2024'},
    {id:'AG-104', nama:'AFIANI KUSUMA PRADANI', nip:'199606272015032001', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/09/2025; TMT Jabatan: 01/10/2023'},
    {id:'AG-105', nama:'LUTHFI ALMASYAH', nip:'200011022020121006', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 01/10/2023'},
    {id:'AG-106', nama:'NASRUL MAULANA WAHID', nip:'199711202020121001', jabatan:'Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 09/09/2026'},
    {id:'AG-107', nama:'MUHAMMAD RIZKY', nip:'200101152020121003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 01/10/2023'},
    {id:'AG-108', nama:'HUSNI FAJRI', nip:'199504222020121002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 01/10/2023'},
    {id:'AG-109', nama:'ARRIZAL AULIA AMIR', nip:'199802192022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-110', nama:'RESTU MANDALA', nip:'199810182022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-111', nama:'DWI ANGGA', nip:'199607252022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-112', nama:'FARHAN NAUFAL', nip:'200112282022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-113', nama:'FARHAN ADITYA FA\'IQ SUPRIATNO', nip:'200208082022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-114', nama:'HENRI OKTA NUGRAHA', nip:'199910052022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-115', nama:'MUHAMMAD GIFARI', nip:'200201152022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/08/2026'},
    {id:'AG-116', nama:'DINO PRASETYO', nip:'199612022022031001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023'},
    {id:'AG-117', nama:'FEBRIA SANDY NUR ISLAMIYAH', nip:'200302102022032001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/05/2026'},
    {id:'AG-118', nama:'NAFISA ZAINAL IZZAH', nip:'200403042025062001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2025; TMT Jabatan: 01/06/2025'},
    {id:'AG-119', nama:'MUHAMAD RIZQY AULIA', nip:'200605072025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-120', nama:'MUHAMMAD MIFTAHUL HAQ', nip:'200205222025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-121', nama:'RAMADHAN SYAHWA', nip:'200012152025061004', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-122', nama:'YUSUF WILDAN AULA', nip:'200003302025061005', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-123', nama:'RICKO YOGA SAPUTRA', nip:'200405272025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-124', nama:'LA ODE MUH. ALFI SYHAR', nip:'200206172025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-125', nama:'MUH. KHOIRUL RIZAL', nip:'200206032025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-126', nama:'YUDHISTIRA ALLANDIO BHASKARA', nip:'200308282025061001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-127', nama:'NAILA APRILIYAH', nip:'200604122025062001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-128', nama:'MOCH LUKMAN HAKIM', nip:'200001132025061005', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-129', nama:'LAZUARDI SATRIA IKRAM', nip:'200203062025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-130', nama:'DEFITS RIZAL PRAYOGO', nip:'200001062025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-131', nama:'ALIM MUHAMMAD FATHIR', nip:'200502172025061001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-132', nama:'CALISTA DWI DAMAYANTI', nip:'200602182025062002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-133', nama:'ANGGI AZELIKHA RUSTANTI', nip:'200506242025062001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-134', nama:'KELVIN ALDI NUGROHO', nip:'200405102025061004', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-135', nama:'ABDI ABDULLAH MAGROBI', nip:'200107222025061004', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-136', nama:'RHEYNANDA DIAZ YUDHISTIRA', nip:'200008102025061005', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-137', nama:'DANI TRISNA ARDIANSAH', nip:'200505252025061001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-138', nama:'CHANDA VERTIKA MAULIZA', nip:'200507032025062003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-139', nama:'KHAIRIL MUTTAQIN', nip:'200603302025061001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-140', nama:'ZULFIQAR FARIZ RAMADHAN', nip:'199912282025061005', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-141', nama:'JUANETA ARI SOFYANTI', nip:'200207072025062002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-142', nama:'NAUFAL AZMI', nip:'200207142025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-143', nama:'RENI NUR HAFIFAH', nip:'200305262025062001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-144', nama:'ALDILLAH FATHUR RIZKY', nip:'200307152025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-145', nama:'M. AL GUFRON', nip:'200306042025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-146', nama:'MUHAMAD FAQIH', nip:'200203212025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-147', nama:'MOCHAMMAD AGUNG HAMZAH WICAKSONO', nip:'200107262025061003', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-148', nama:'FIRMANS ZANNUBA IRROCHIM', nip:'199909182025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-149', nama:'CHATRYA WAHYU SHODIQ IHSANUDDIN', nip:'200307232025061001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-150', nama:'HAFID HUDAYA', nip:'200411122025061001', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
    {id:'AG-151', nama:'NADHIF DWI RENDRAGRAHA', nip:'200205162025061002', jabatan:'Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan', unit:'Kantor Pencarian dan Pertolongan Kelas A Jakarta', whatsapp:'', email:'', kompetensi:'', spesialisasi:'', sertifikasi:'', status:'Aktif', keterangan:'Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026'},
  ];

  const kegiatan = [
    {id:'KGT-001', nomor:'001/KGT/2026', jenis:'sgts', jenis_label:'Sar Go To School', nama:'Sosialisasi SAR SDN 05 Tangerang', tanggal:'2026-09-28', waktu:'08:00', lokasi:'SDN 05 Tangerang', penanggung_jawab:'Bambang Setiawan', deskripsi:'Edukasi keselamatan air dan simulasi evakuasi dasar untuk siswa kelas 4-6.', jumlah_dibutuhkan:3, status_persetujuan:'Menunggu Persetujuan', keterangan_persetujuan:'', anggota_ids:[], status_pelaksanaan:'Draft', status_wa:'Belum Terkirim', wa_log:[], dokumentasi:'', file_pdf:null, dibuat: nowStr(), diperbarui: nowStr()},
    {id:'KGT-002', nomor:'002/KGT/2026', jenis:'bimtek', jenis_label:'Bimtek', nama:'Bimtek Water Rescue Regu 2', tanggal:'2026-09-25', waktu:'09:00', lokasi:'Dermaga Tanjung Priok', penanggung_jawab:'Andi Wijaya', deskripsi:'Bimbingan teknis penyelamatan di perairan untuk regu 2.', jumlah_dibutuhkan:4, status_persetujuan:'Disetujui', keterangan_persetujuan:'Disetujui oleh KSO — sesuai jadwal latihan rutin.', anggota_ids:[], status_pelaksanaan:'Dalam Pelaksanaan', status_wa:'Belum Terkirim', wa_log:[], dokumentasi:'', file_pdf:null, dibuat: nowStr(), diperbarui: nowStr()},
    {id:'KGT-003', nomor:'003/KGT/2026', jenis:'uji_periodik', jenis_label:'Uji Periodik', nama:'Uji Periodik Peralatan Selam', tanggal:'2026-09-18', waktu:'13:00', lokasi:'Gudang Peralatan Pusat', penanggung_jawab:'Fajar Nugroho', deskripsi:'Pengujian berkala tabung selam dan regulator sebelum operasi.', jumlah_dibutuhkan:2, status_persetujuan:'Disetujui', keterangan_persetujuan:'Disetujui oleh KKS.', anggota_ids:[], status_pelaksanaan:'Selesai', status_wa:'Belum Terkirim', wa_log:[], dokumentasi:'', file_pdf:null, dibuat: nowStr(), diperbarui: nowStr()},
    {id:'KGT-004', nomor:'004/KGT/2026', jenis:'diklat', jenis_label:'Diklat', nama:'Diklat Dasar Rescuer Angkatan 12', tanggal:'2026-09-10', waktu:'08:00', lokasi:'Pusdiklat BASARNAS', penanggung_jawab:'Hendra Gunawan', deskripsi:'Pengajuan diklat dasar ditolak karena bentrok dengan jadwal siaga nasional.', jumlah_dibutuhkan:6, status_persetujuan:'Ditolak', keterangan_persetujuan:'Ditolak oleh KSD — bentrok dengan status siaga nasional pada tanggal terkait.', anggota_ids:[], status_pelaksanaan:'Draft', status_wa:'Belum Terkirim', wa_log:[], dokumentasi:'', file_pdf:null, dibuat: nowStr(), diperbarui: nowStr()},
  ];

  const db = { anggota, kegiatan };
  TABLE_MODULES.forEach(m=>{ db[m.key] = sampleFor(m); });
  return db;
}

function sampleFor(m){
  const base = {id: uid(m.prefix, []), status: (m.fields.find(f=>f.isStatus)||{}).options ? m.fields.find(f=>f.isStatus).options[0] : undefined};
  const rows = [];
  const text = {
    sgts:[{nama_sekolah:'SMPN 12 Bekasi', alamat_sekolah:'Jl. Raya Bekasi Timur', tanggal:'2026-08-14', waktu:'09:00', jumlah_peserta:120, materi:'Pengenalan SAR & Water Safety', penanggung_jawab:'Siti Rahmawati', narasumber:'Andi Wijaya', anggota_bertugas:'AG-002, AG-003', keterangan:'Kegiatan tahunan', dokumentasi:'', status:'Selesai'}],
    bimtek:[{nama_bimtek:'Bimtek Vertical Rescue', jenis_bimtek:'Teknis', materi:'Teknik SRT & Anchor', tanggal:'2026-08-20', waktu:'08:30', lokasi:'Gedung Latihan BASARNAS', penyelenggara:'Bidang Operasi', narasumber:'Fajar Nugroho', peserta:'Regu 3', jumlah_peserta:8, petugas:'AG-005', keterangan:'', dokumentasi:'', status:'Selesai'}],
    potensi:[{nama_potensi:'Komunitas Selam Jakarta', kategori:'Organisasi', nama_kontak:'Rudi Hartono', nomor_kontak:'+62 813-2000-0001', alamat:'Jakarta Utara', wilayah:'DKI Jakarta', kompetensi:'Selam dasar-lanjut', jumlah_tersertifikasi:14, jumlah_belum_sertifikasi:6, jumlah_total:20, status:'Aktif', alut_sapras:'2 unit perahu karet, alat selam lengkap', keterangan:'Mitra potensial SAR', dokumentasi:''}],
    narasumber:[{nama:'Dr. Arief Kurniawan', instansi:'Kemenkes RI', jabatan:'Dokter Spesialis Emergency', keahlian:'Medical Emergency Response', nomor_kontak:'+62 811-3000-0001', email:'arief.k@kemenkes.go.id', materi:'Penanganan Korban Bencana', kegiatan_terkait:'Diklat, Bimtek', status:'Direncanakan', keterangan:''}],
    diklat:[{nama_diklat:'Diklat Dasar Rescuer Angkatan 11', jenis_diklat:'Diklat Dasar', materi:'Dasar-dasar SAR', tanggal_mulai:'2026-07-01', tanggal_selesai:'2026-07-14', lokasi:'Pusdiklat BASARNAS', penyelenggara:'Pusdiklat', instruktur:'Bambang Setiawan', peserta:'20 calon rescuer', jumlah_peserta:20, anggota_bertugas:'AG-001', sertifikat:'Diterbitkan', dokumentasi:'', status:'Selesai'}],
    rescuer:[{anggota_id:'AG-033', jabatan:'Kepala Regu', kompetensi:'Water Rescue', spesialisasi:'Selam', sertifikasi:'BRLS', masa_berlaku:'2027-03-01', status:'Aktif', riwayat:'12 penugasan tahun ini', keterangan:''}],
    pembinaan:[{nama_kegiatan:'Pembinaan Potensi SAR Wilayah Barat', jenis_pembinaan:'Rutin', sasaran:'Kelompok Potensi SAR', tanggal:'2026-08-05', lokasi:'Balai Desa Cengkareng', materi:'Standar Prosedur Evakuasi', pembina:'Bambang Setiawan', peserta:'25 orang', anggota_bertugas:'AG-001, AG-005', hasil:'Peserta memahami SOP dasar', dokumentasi:'', status:'Selesai'}],
    pemberdayaan:[{nama_kegiatan:'Pemberdayaan Nelayan Tangguh Bencana', sasaran:'Kelompok Nelayan Muara Angke', lokasi:'Muara Angke', tanggal:'2026-08-22', jumlah_peserta:40, materi:'Mitigasi Bencana Laut', narasumber:'Dr. Arief Kurniawan', petugas:'AG-004', hasil:'40 nelayan terlatih dasar mitigasi', dokumentasi:'', status:'Selesai'}],
    uji_periodik:[{nama_uji:'Uji Periodik Kompetensi Selam Angkatan 1', jenis_uji:'Uji Kompetensi', materi:'Penyelaman dan penyelamatan air', tanggal_mulai:'2026-08-01', tanggal_selesai:'2026-08-02', lokasi:'Gudang Peralatan Pusat', penyelenggara:'Kantor SAR Jakarta', penguji:'Fajar Nugroho', peserta:'Tim Rescuer Selam', jumlah_peserta:'', anggota_bertugas:'', sertifikat:'', dokumentasi:'', status:'Layak'}],
    uji_pemeliharaan:[{nama_peralatan:'Perahu Karet RIB-02', nomor_inventaris:'INV-PRH-002', jenis_peralatan:'Perahu', tanggal_pemeriksaan:'2026-08-10', kondisi:'Baik', jenis_pemeliharaan:'Perawatan rutin', petugas:'Hendra Gunawan', hasil:'Mesin dan lambung dalam kondisi baik', tindakan:'Ganti oli mesin', jadwal_berikutnya:'2026-11-10', keterangan:'', dokumentasi:'', status:'Baik'}],
  };
  (text[m.key]||[]).forEach(row=>{ rows.push(Object.assign({id: uid(m.prefix, rows)}, row)); });
  return rows;
}

/* Dideklarasikan sebelum load(): migrasi data saat halaman dimuat memanggil persist() */
let _syncSaving = 0;
let _persistSnap = null, _persistTimer = null, _persistChain = Promise.resolve(), _persistPending = false;
let DB = load();
function migrateDB(db){
  let changed = false;
  // Perbaikan data Rescuer: nama/NIP sekarang diambil dari Daftar Anggota lewat
  // anggota_id. Kalau ada data Rescuer yang belum punya anggota_id, atau
  // anggota_id-nya tidak cocok dengan Daftar Anggota yang tersimpan (mis. dari
  // versi data lama), tautkan ulang ke anggota yang benar-benar ada.
  if(Array.isArray(db.rescuer)){
    const anggotaIds = new Set((db.anggota||[]).map(a=>a.id));
    db.rescuer.forEach(r=>{
      if(!r.anggota_id || !anggotaIds.has(r.anggota_id)){
        if(db.anggota && db.anggota.length){ r.anggota_id = db.anggota[0].id; changed = true; }
      }
    });
  }
  // Bersihkan data Diklat yang tidak lengkap (Nama Diklat kosong), mis. baris
  // kosong yang sempat tersimpan tanpa nama.
  if(Array.isArray(db.diklat)){
    const before = db.diklat.length;
    db.diklat = db.diklat.filter(d => d.nama_diklat && String(d.nama_diklat).trim() !== '');
    if(db.diklat.length !== before) changed = true;
  }
  // Matriks peserta lama Uji Periodik (MFR, JR, HART, dst.) dihapus; peserta kini otomatis dari 4 card kategori.
  if(Array.isArray(db.uji_periodik)){
    db.uji_periodik.forEach(r=>{
      if('peserta_matrix' in r){ delete r.peserta_matrix; changed = true; }
      if('kolom_peserta' in r){ delete r.kolom_peserta; changed = true; }
    });
  }
  if(db.kolom_peserta && 'uji_periodik' in db.kolom_peserta){ delete db.kolom_peserta.uji_periodik; changed = true; }
  // Uji Periodik kini berformat seperti Diklat: petakan field lama (peralatan) ke field baru.
  if(Array.isArray(db.uji_periodik)){
    db.uji_periodik.forEach(r=>{
      if(!r.nama_uji && r.nama_peralatan){
        r.nama_uji = r.nama_peralatan;
        if(!r.jenis_uji && r.jenis_peralatan) r.jenis_uji = r.jenis_peralatan;
        if(!r.tanggal_mulai && r.tanggal_uji) r.tanggal_mulai = r.tanggal_uji;
        if(!r.penguji && r.petugas) r.penguji = r.petugas;
        changed = true;
      }
    });
  }
  // Surat Masuk (alur awal Kegiatan/Tugas): sediakan daftar kosong bila belum ada.
  if(!Array.isArray(db.surat_masuk)){ db.surat_masuk = []; changed = true; }
  // Persuratan: Surat Keluar (Surat Masuk diambil dari db.surat_masuk milik Kegiatan/Tugas).
  if(!Array.isArray(db.surat_keluar)){ db.surat_keluar = []; changed = true; }
  // Data hasil Import Excel tidak memerlukan persetujuan atasan: yang masih "Menunggu Persetujuan" dianggap selesai (tanpa ACC). Data lain tidak diubah.
  db.surat_masuk.forEach(r=>{
    if(r && r.sumber==='Import Excel' && r.status==='Menunggu Persetujuan'){
      r.status = 'Disetujui'; r.tanpa_acc = true;
      r.keterangan_persetujuan = 'Data hasil Import Excel — tidak memerlukan persetujuan atasan.'; changed = true;
    }
  });
  if(Array.isArray(db.kegiatan)) db.kegiatan.forEach(r=>{
    if(r && r.sumber==='Import Excel' && r.status_persetujuan==='Menunggu Persetujuan'){
      r.status_persetujuan = 'Disetujui'; r.tanpa_acc = true;
      r.keterangan_persetujuan = 'Data hasil Import Excel — tidak memerlukan persetujuan atasan.'; changed = true;
    }
  });
  // Modul inventaris baru (Peralatan, Kendaraan, Peminjaman): sediakan daftar kosong bila belum ada.
  EXTRA_MODULES.forEach(m=>{ if(!Array.isArray(db[m.key])){ db[m.key] = []; changed = true; } });
  // Pengajuan: data lama (modul Pemeliharaan) dianggap kategori Pemeliharaan yang sudah disetujui.
  db.pemeliharaan.forEach(r=>{
    if(!r.kategori_pengajuan){ r.kategori_pengajuan = 'Pemeliharaan'; changed = true; }
    if(!r.status_persetujuan){ r.status_persetujuan = 'Disetujui'; changed = true; }
  });
  // Modul "Pembinaan SAR" diganti nama menjadi "Pembinaan Tenaga SAR": perbarui label yang tersimpan di data lama.
  const lblPbn = (GENERIC_MODULES.find(m=>m.key==='pembinaan')||{}).label;
  ['kegiatan','surat_masuk'].forEach(k=>{
    if(!Array.isArray(db[k])) return;
    db[k].forEach(r=>{ if(r && r.jenis==='pembinaan' && r.jenis_label!==lblPbn){ r.jenis_label = lblPbn; changed = true; } });
  });
  // Pembinaan Tenaga SAR tab Pembelajaran: Pengajar/Fasilitator & Anggota Bertugas dihilangkan, bersihkan data lama.
  if(Array.isArray(db.pembinaan)){
    db.pembinaan.forEach(r=>{
      if(pbnKat(r)!=='Pembelajaran') return;
      ['pengajar','anggota_bertugas','metode'].forEach(k=>{ if(k in r){ delete r[k]; changed = true; } });
    });
  }
  // Absensi Pembinaan Tenaga SAR: sediakan daftar dan sinkronkan nama anggota dari data Samapta yang sudah ada.
  if(!Array.isArray(db.pembinaan_absensi)){ db.pembinaan_absensi = []; changed = true; }
  if(pbnAbsSync(db)) changed = true;
  // Modul Lainnya (data lama belum punya daftar ini): sediakan daftar kosong.
  if(!Array.isArray(db.lainnya)){ db.lainnya = []; changed = true; }
  // Kegiatan yang ditolak: pelaksanaan Draft, dan data di modul jenis terkait dihapus.
  if(Array.isArray(db.kegiatan)){
    db.kegiatan.forEach(k=>{
      if(k.status_persetujuan!=='Ditolak') return;
      if(k.status_pelaksanaan!=='Draft'){ k.status_pelaksanaan = 'Draft'; changed = true; }
      // Data modul hasil kegiatan yang ditolak dihapus dari modul jenisnya (kegiatan tetap ada).
      GENERIC_MODULES.forEach(m=>{
        const arr = db[m.key]; if(!Array.isArray(arr)) return;
        const sisa = arr.filter(r=>!((m.key===k.jenis && k.linked_record_id && r.id===k.linked_record_id) || r.dari_kegiatan_id===k.id));
        if(sisa.length!==arr.length){ db[m.key] = sisa; changed = true; }
      });
      if(k.linked_record_id){ k.linked_record_id = ''; changed = true; }
    });
  }
  // Status Narasumber kini mengikuti Kegiatan/Tugas (Direncanakan/Berlangsung/Selesai): petakan data lama.
  if(Array.isArray(db.narasumber)){
    const pel = {'Draft':'Direncanakan','Dalam Pelaksanaan':'Berlangsung','Selesai':'Selesai'};
    db.narasumber.forEach(r=>{
      if(['Direncanakan','Berlangsung','Selesai'].includes(r.status)) return;
      const kg = (db.kegiatan||[]).find(k=>(r.dari_kegiatan_id && k.id===r.dari_kegiatan_id) || (k.jenis==='narasumber' && k.linked_record_id===r.id));
      r.status = (kg && pel[kg.status_pelaksanaan]) || 'Direncanakan';
      changed = true;
    });
  }
  // Kondisi Kendaraan kini Serviceable / Unserviceable / Scrap: petakan data lama.
  if(Array.isArray(db.kendaraan)){
    const kMap = {'Baik':'Serviceable','Rusak Ringan':'Unserviceable','Rusak Berat':'Scrap'};
    db.kendaraan.forEach(r=>{ if(kMap[r.kondisi]){ r.kondisi = kMap[r.kondisi]; changed = true; } });
  }
  // Peralatan format baru (Nama Barang, Kode Rak, Jumlah Baik/Rusak): petakan data lama.
  if(Array.isArray(db.peralatan)){
    const katMap = {'SAR Air':'Palsar Air','SAR Darat':'Palsar Darat','Selam':'Palsar Air','Penyelamatan Vertikal':'Palsar Darat','Lainnya':'Palsar Darat'};
    db.peralatan.forEach(r=>{
      if('nama_barang' in r) return;
      const jml = parseFloat(r.jumlah)||0;
      r.nama_barang = r.nama_peralatan || '';
      r.kode_rak = r.lokasi_penyimpanan || '';
      r.kategori = katMap[r.kategori] || r.kategori || 'Palsar Darat';
      r.jumlah_baik = (r.kondisi==='Baik') ? jml : 0;
      r.jumlah_rusak = (r.kondisi==='Baik') ? 0 : jml;
      r.jumlah = jml;
      changed = true;
    });
    // Data Peralatan yang belum punya Lokasi: isi default KPP Jakarta.
    db.peralatan.forEach(r=>{ if(!r.lokasi){ r.lokasi = 'KPP Jakarta'; changed = true; } });
  }
  return changed;
}
function load(){
  // Data dimuat dari database SQL oleh PHP (window.__INITIAL_DB__)
  const init = window.__INITIAL_DB__;
  if(init && typeof init==='object' && Array.isArray(init.anggota)){
    if(migrateDB(init)) persist(init);
    return init;
  }
  const fresh = seed();
  migrateDB(fresh);
  persist(fresh);
  return fresh;
}
/* Simpan ke database lewat api.php (digabung/ditunda 300 ms, berurutan) */
const API_URL = 'api.php';
function persist(db){
  _persistSnap = db || DB;
  _persistPending = true;
  clearTimeout(_persistTimer);
  _persistTimer = setTimeout(flushPersist, 300);
}
function flushPersist(){
  clearTimeout(_persistTimer);
  if(!_persistPending) return _persistChain;
  _persistPending = false;
  const body = JSON.stringify(_persistSnap);
  _syncSaving++;
  _persistChain = _persistChain.then(() => fetch(API_URL + '?action=save', {
      method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'basarnas'}, body
    }).then(r => r.json().catch(()=>({ok:false})).then(j => {
      if(r.status === 401){ location.href = 'login.php'; throw new Error(j.error || 'Sesi berakhir'); }
      if(!r.ok || !j.ok) throw new Error(j.error || ('HTTP '+r.status));
      // Revisi hasil simpanan sendiri tidak dianggap "perubahan orang lain"
      if(j.rev && j.rev - 1 === SYNC_REV) SYNC_REV = j.rev;
      // Ada data yang sudah diputuskan (ACC/Tolak) oleh pihak lain lebih dulu: server menolak menimpanya, muat ulang tampilan
      if(j.konflik){ try{ toast('Data ini sudah di-ACC/ditolak lebih dulu oleh pihak lain. Tampilan diperbarui.'); }catch(e){} SYNC_REV = -1; setTimeout(syncTick, 300); }
    }))
  ).catch(err => { console.error(err); try{ toast('Gagal menyimpan ke server: ' + err.message); }catch(e){} })
   .finally(() => { _syncSaving--; });
  return _persistChain;
}
window.addEventListener('beforeunload', ev => { if(_persistPending){ flushPersist(); ev.preventDefault(); ev.returnValue = ''; } });

/* ============================= SINKRONISASI OTOMATIS =============================
   Browser menanyakan nomor revisi ke server tiap beberapa detik (api.php?action=sync).
   Jika ada perubahan dari pengguna/perangkat lain, data dimuat ulang dan tampilan
   diperbarui tanpa refresh. Pemuatan ditunda selama pengguna sedang mengisi form/modal
   atau masih ada perubahan lokal yang belum tersimpan, supaya ketikan tidak hilang. */
const SYNC_INTERVAL = 4000;
let SYNC_REV = window.__INITIAL_REV__ || 0, _syncBusy = false, _syncDeferred = null, _syncWarned = false;
function syncUserBusy(){
  if(_persistPending) return true;
  const m = document.getElementById('modalRoot');
  if(m && m.innerHTML.trim() !== '') return true;
  const a = document.activeElement;
  return !!(a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) && !a.readOnly && !a.disabled && a.type !== 'checkbox' && a.type !== 'button');
}
function syncApply(j){
  const fresh = j.db;
  if(!fresh || !Array.isArray(fresh.anggota)) return;
  const migrated = migrateDB(fresh);
  DB = fresh;
  SYNC_REV = j.rev;
  _syncDeferred = null; _syncWarned = false;
  const y = window.scrollY;
  try{ render(currentView); }catch(e){ console.error(e); }
  window.scrollTo(0, y);
  if(migrated) persist(DB);
}
async function syncTick(){
  if(_syncBusy || document.hidden) return;
  _syncBusy = true;
  try{
    const since = _syncDeferred ? _syncDeferred.rev : SYNC_REV;
    const r = await fetch(API_URL + '?action=sync&since=' + since, {cache:'no-store'});
    if(r.status === 401){ location.href = 'login.php'; return; }
    const j = await r.json();
    if(!r.ok || !j.ok) throw new Error(j.error || ('HTTP '+r.status));
    if(j.changed) _syncDeferred = j;
    if(_syncDeferred){
      if(!syncUserBusy() && _syncSaving === 0) syncApply(_syncDeferred);
      else if(!_syncWarned){ _syncWarned = true; toast('Ada pembaruan dari pengguna lain, akan dimuat setelah form ditutup'); }
    }
  }catch(e){ console.warn('sync:', e.message); }
  finally{ _syncBusy = false; }
}
setInterval(syncTick, SYNC_INTERVAL);
document.addEventListener('visibilitychange', () => { if(!document.hidden) syncTick(); });
window.addEventListener('focus', syncTick);

/* Keluar: pastikan perubahan terakhir sudah tersimpan sebelum sesi diakhiri */
function keluarAman(ev){
  const f = ev.target;
  ev.preventDefault();
  Promise.resolve(flushPersist()).catch(()=>{}).then(() => { _persistPending = false; f.submit(); });
  return false;
}

/* ============================= SIDEBAR ============================= */
const SIDEBAR_KEY = 'basarnas_sidebar_collapsed';
(function initSidebar(){
  try{ if(localStorage.getItem(SIDEBAR_KEY)==='1') document.body.classList.add('sb-collapsed'); }catch(e){}
})();
function toggleSidebar(force){
  // Desktop: sidebar dibuka/ditutup (disembunyikan) dan pilihan diingat
  if(window.innerWidth > 900){
    if(force === false) return; // klik menu di desktop tidak menutup sidebar
    const collapsed = force !== undefined ? !force : !document.body.classList.contains('sb-collapsed');
    document.body.classList.toggle('sb-collapsed', collapsed);
    try{ localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); }catch(e){}
    return;
  }
  // Mobile: sidebar geser masuk dengan latar gelap
  const sb = document.getElementById('sidebar'), sc = document.getElementById('scrim');
  const open = force !== undefined ? force : !sb.classList.contains('open');
  sb.classList.toggle('open', open); sc.classList.toggle('open', open);
}

/* ============================= TOAST / MODAL ============================= */
function toast(msg){
  const el = document.createElement('div');
  el.className='toast'; el.textContent = msg;
  document.getElementById('toastRoot').appendChild(el);
  setTimeout(()=>el.remove(), 2600);
}
function openModal(html, wide){
  document.getElementById('modalRoot').innerHTML = `
    <div class="modal-backdrop" onclick="if(event.target===this)closeModal()">
      <div class="panel rounded-xl w-full ${wide?'max-w-3xl':'max-w-lg'} shadow-2xl">${html}</div>
    </div>`;
}
let _modalCloseHook = null;
function closeModal(){
  document.getElementById('modalRoot').innerHTML='';
  if(_modalCloseHook){ const h = _modalCloseHook; _modalCloseHook = null; try{ h(); }catch(e){ console.error(e); } }
}

/* ============================= ROLE / NAV ============================= */
function isSuper(){ return IS_SU && currentRole === 'Admin Utama'; } // Admin Utama: akses penuh semua fitur
function isApprover(){ return currentRole !== 'Anggota'; }

/* Peran Anggota hanya boleh membuka modul berikut; modul lain menampilkan keterangan akses ditolak. */
const ANGGOTA_MODUL = ['kegiatan','pembinaan','peralatan','kendaraan','peminjaman','pemeliharaan']; // pemeliharaan = menu "Pengajuan"
const PESAN_AKSES_ANGGOTA = 'Hanya lihat: Anggota tidak dapat menambah, mengubah, atau menghapus data modul ini';
function isAnggotaOnly(){ return currentRole === 'Anggota' && !isSuper(); }
function bolehAkses(view){ return true; } // semua modul dapat dilihat; modul terkunci bersifat lihat saja untuk Anggota
/* Modul di luar ANGGOTA_MODUL: Anggota hanya boleh MELIHAT (tidak bisa tambah/ubah/hapus/simpan). */
function modulReadOnly(key){ return isAnggotaOnly() && !ANGGOTA_MODUL.includes(key); }
/* Modul yang disembunyikan total dari role Anggota */
function modulTersembunyi(key){ return isAnggotaOnly() && key==='anggota'; }
function guardRO(key){
  if(modulReadOnly(key)){ toast('Mode lihat saja: peran Anggota tidak dapat mengubah data modul ini'); return true; }
  return false;
}
function awalView(){ return isAnggotaOnly() ? 'kegiatan' : 'dashboard'; }

function renderSidebar(){
  document.getElementById('navlist').innerHTML = NAV_ORDER.filter(n=>!modulTersembunyi(n.key)).map(n => {
    const kunci = modulReadOnly(n.key);
    return `
    <div class="navlink ${currentView===n.key?'active':''}" style="${kunci?'opacity:.55;':''}" title="${kunci?esc(PESAN_AKSES_ANGGOTA):''}" onclick="render('${n.key}'); toggleSidebar(false);">
      <span class="ico">${n.short}</span><span class="truncate">${esc(n.label)}</span>${kunci?'<span class="ml-auto text-[11px]" aria-hidden="true">👁</span>':''}
    </div>`;
  }).join('');
}

/* ============================= ROUTER ============================= */
function render(view){
  if(modulTersembunyi(view)) view = awalView();
  currentView = view;
  renderSidebar();
  const titles = {dashboard:['Dashboard','Ringkasan program dan kegiatan SAR'], kegiatan:['Kegiatan/Tugas','Surat masuk, ACC pimpinan, penugasan & info WhatsApp'], persuratan:['Persuratan','Surat masuk dan surat keluar'], anggota:['Daftar Anggota','Master data personel SAR']};
  const gm = TABLE_MODULES.find(m=>m.key===view);
  const t = titles[view] || (gm ? [gm.label, 'Data program ' + gm.label] : ['Dashboard','']);
  document.getElementById('pageTitle').textContent = t[0];
  document.getElementById('pageSub').textContent = t[1];

  if(view==='dashboard') renderDashboard();
  else if(view==='kegiatan') renderKegiatan();
  else if(view==='persuratan') renderPersuratan();
  else if(view==='anggota') renderGenericTable('anggota', ANGGOTA_FIELDS, 'Anggota', true);
  else if(gm) renderGenericTable(gm.key, gm.fields, gm.label, false);
  if(modulReadOnly(view)) tampilBannerLihatSaja();
}
function tampilBannerLihatSaja(){
  const app = document.getElementById('app'); if(!app) return;
  app.insertAdjacentHTML('afterbegin', `<div class="rounded-lg px-3 py-2 mb-3 text-xs flex items-center gap-2" style="background:var(--info-bg); color:var(--info);" role="status"><span aria-hidden="true">👁</span><span><strong>Mode lihat saja.</strong> Anda dapat melihat data modul ini, tetapi tidak dapat menambah, mengubah, atau menghapus.</span></div>`);
}

/* ============================= DASHBOARD ============================= */
// Periode (bulan + tahun) per modul. Default: tahun berjalan, semua bulan. Data dihitung berdasarkan tanggal
// kegiatannya; data tanpa tanggal (mis. Potensi, Narasumber) memakai bulan & tahun saat data diinput.
const BULAN_NAMA = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
let periode = {};
function getPeriode(key){ return periode[key] || (periode[key] = {y:new Date().getFullYear(), m:0}); }
function recPeriode(r){
  for(const v of [r.tanggal, r.tanggal_mulai, r.tanggal_pemeriksaan]){
    const m = String(v==null ? '' : v).match(/((?:19|20)\d{2})-(\d{1,2})/);
    if(m) return {y:parseInt(m[1],10), m:parseInt(m[2],10)};
  }
  if(r.tahun_input) return {y:parseInt(r.tahun_input,10), m:parseInt(r.bulan_input,10)||new Date().getMonth()+1};
  const now = new Date();
  return {y:now.getFullYear(), m:now.getMonth()+1}; // data lama tanpa tanggal
}
function inPeriode(r, key){
  const p = getPeriode(key), rp = recPeriode(r);
  return (!p.y || rp.y===p.y) && (!p.m || rp.m===p.m);
}
function periodeTahunList(key){
  const set = new Set([new Date().getFullYear(), getPeriode(key).y].filter(Boolean));
  const src = key==='dashboard' ? GENERIC_MODULES.map(m=>DB[m.key]||[]).concat([DB.kegiatan||[]]) : [DB[key]||[]];
  src.forEach(arr=>arr.forEach(r=>set.add(recPeriode(r).y)));
  return Array.from(set).sort((a,b)=>b-a);
}
function periodeSelectHtml(key){
  const p = getPeriode(key);
  return `<span class="inline-flex items-center gap-1.5">
    <select style="width:auto;" aria-label="Bulan" onchange="setPeriode('${key}','m',this.value)">
      <option value="0" ${!p.m?'selected':''}>Semua Bulan</option>
      ${BULAN_NAMA.map((n,i)=>`<option value="${i+1}" ${p.m===i+1?'selected':''}>${n}</option>`).join('')}
    </select>
    <select style="width:auto;" aria-label="Tahun" onchange="setPeriode('${key}','y',this.value)">
      <option value="0" ${!p.y?'selected':''}>Semua Tahun</option>
      ${periodeTahunList(key).map(y=>`<option value="${y}" ${p.y===y?'selected':''}>${y}</option>`).join('')}
    </select>
  </span>`;
}
function setPeriode(key, field, val){ getPeriode(key)[field] = parseInt(val,10) || 0; render(currentView); }
/* ---- Filter rentang tanggal (Dari - Sampai) untuk tiap tabel "Data per Modul" di Dashboard ----
   Bila rentang diisi, rentang itu menggantikan filter Bulan/Tahun untuk tabel tersebut (berlaku juga ke tombol Unduh).
   Tanggal acuan: tanggal kegiatan/surat/pinjam/mulai dst. Modul tanpa kolom tanggal memakai tanggal data diinput. */
let dashRange = {};
function getDashRange(key){ return dashRange[key] || (dashRange[key] = {from:'', to:''}); }
function dashRangeAktif(key){ const g = dashRange[key]; return !!(g && (g.from || g.to)); }
function recRangeTanggal(key, r){
  if(key==='kendaraan_darat' || key==='kendaraan_laut') key = 'kendaraan';
  const iso = v => /^\d{4}-\d{2}-\d{2}/.test(String(v==null?'':v)) ? String(v).slice(0,10) : '';
  const meta = TABLE_MODULES.find(m=>m.key===key);
  let a = '', b = '';
  if(key==='kegiatan'){ a = iso(r.tanggal); b = iso(r.tanggal_selesai); }
  else if(key==='surat_masuk') a = iso(r.tanggal_surat);
  else if(meta){
    const df = meta.fields.find(f=>f.type==='date');
    if(df){ a = iso(r[df.key]); if(df.key==='tanggal_mulai') b = iso(r.tanggal_selesai); }
  }
  if(!a) a = iso(r.tanggal_input);
  if(!a){
    const p = recPeriode(r), pad = n => String(n).padStart(2,'0');
    a = p.y+'-'+pad(p.m)+'-01'; b = p.y+'-'+pad(p.m)+'-'+pad(new Date(p.y, p.m, 0).getDate());
  }
  return [a, (b && b >= a) ? b : a];
}
function inDashRange(key, r){
  const g = dashRange[key]; if(!g || (!g.from && !g.to)) return true;
  const [a, b] = recRangeTanggal(key, r);
  return (!g.from || b >= g.from) && (!g.to || a <= g.to);
}
/* Penyaring baris tabel Dashboard: rentang tanggal bila diisi, jika tidak pakai aturan lama (periode / stok) */
function dashLolos(key, r, stok, prRec){
  if(dashRangeAktif(key)) return inDashRange(key, r);
  return stok ? true : inPeriode(prRec || r, 'dashboard');
}
function dashRangeHtml(key){
  const g = getDashRange(key);
  return `<span class="inline-flex flex-wrap items-center gap-1 text-xs" style="color:var(--muted);">
    <span>Dari</span>
    <input type="date" style="width:auto;" aria-label="Dari tanggal" value="${esc(g.from)}" ${g.to?`max="${esc(g.to)}"`:''} onchange="setDashRange('${key}','from',this.value)">
    <span>s/d</span>
    <input type="date" style="width:auto;" aria-label="Sampai tanggal" value="${esc(g.to)}" ${g.from?`min="${esc(g.from)}"`:''} onchange="setDashRange('${key}','to',this.value)">
    ${dashRangeAktif(key) ? `<button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" onclick="resetDashRange('${key}')">Reset</button>` : ''}
  </span>`;
}
function setDashRange(key, field, val){
  const g = getDashRange(key); g[field] = val || '';
  if(g.from && g.to && g.from > g.to){ const t = g.from; g.from = g.to; g.to = t; }
  const y = window.scrollY; render(currentView); window.scrollTo(0, y);
}
function resetDashRange(key){
  dashRange[key] = {from:'', to:''};
  const y = window.scrollY; render(currentView); window.scrollTo(0, y);
}
/* ---- Grafik dashboard (SVG murni, tanpa library) ---- */
const CH_FONT = "font-family:Inter,system-ui,sans-serif;";
function chEmpty(msg){ return `<div class="text-sm py-10 text-center" style="color:var(--muted);">${esc(msg)}</div>`; }
function chLineSvg(labels, values){
  const W=640, H=260, L=38, R=16, T=22, B=34, pw=W-L-R, ph=H-T-B;
  const max = Math.max(...values, 1), step = Math.max(1, Math.ceil(max/4)), top = step*Math.ceil(max/step);
  const xs = i => L + (labels.length>1 ? pw*i/(labels.length-1) : pw/2);
  const ys = v => T + ph - (v/top)*ph;
  let grid=''; for(let v=0; v<=top; v+=step){ grid += `<line x1="${L}" y1="${ys(v)}" x2="${W-R}" y2="${ys(v)}" style="stroke:var(--line);"/><text x="${L-8}" y="${ys(v)+4}" text-anchor="end" font-size="11" style="${CH_FONT}fill:var(--muted);">${v}</text>`; }
  const pts = values.map((v,i)=>`${xs(i)},${ys(v)}`).join(' ');
  const area = `${xs(0)},${ys(0)} ${pts} ${xs(values.length-1)},${ys(0)}`;
  return `<svg viewBox="0 0 ${W} ${H}" width="100%" role="img" aria-label="Grafik garis jumlah kegiatan per bulan">
    ${grid}
    <polygon points="${area}" style="fill:var(--orange-500);opacity:.10;"/>
    <polyline points="${pts}" fill="none" style="stroke:var(--orange-500);" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
    ${values.map((v,i)=>`<g><circle cx="${xs(i)}" cy="${ys(v)}" r="4" style="fill:#fff;stroke:var(--orange-500);" stroke-width="2"/><title>${esc(labels[i])}: ${v} kegiatan</title>${v>0?`<text x="${xs(i)}" y="${ys(v)-9}" text-anchor="middle" font-size="11" font-weight="600" style="${CH_FONT}fill:var(--navy-800);">${v}</text>`:''}</g>`).join('')}
    ${labels.map((l,i)=>`<text x="${xs(i)}" y="${H-10}" text-anchor="middle" font-size="11" style="${CH_FONT}fill:var(--muted);">${esc(l)}</text>`).join('')}
    <text x="${L-26}" y="${T-8}" font-size="10" style="${CH_FONT}fill:var(--muted);">Jumlah</text>
  </svg>`;
}
function chDonutHtml(items){
  const total = items.reduce((t,i)=>t+i.n,0);
  const R=62, C=2*Math.PI*R; let off=0;
  const segs = total ? items.filter(i=>i.n>0).map(i=>{
    const len = C*i.n/total, seg = `<circle cx="90" cy="90" r="${R}" fill="none" stroke="${i.color}" stroke-width="26" stroke-dasharray="${len} ${C-len}" stroke-dashoffset="${-off}" transform="rotate(-90 90 90)"><title>${esc(i.tip || (i.label+': '+i.n))}</title></circle>`;
    off += len; return seg;
  }).join('') : '';
  return `<svg viewBox="0 0 180 180" width="100%" style="max-width:190px;display:block;margin:0 auto;" role="img">
      <circle cx="90" cy="90" r="${R}" fill="none" style="stroke:var(--line);" stroke-width="26"/>${segs}
      <text x="90" y="92" text-anchor="middle" font-size="26" font-weight="700" style="${CH_FONT}fill:var(--navy-800);">${total}</text>
      <text x="90" y="110" text-anchor="middle" font-size="10" style="${CH_FONT}fill:var(--muted);">Total</text>
    </svg>
    <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 mt-3 text-xs">
      ${items.map(i=>`<span class="inline-flex items-center gap-1.5"${i.tip?` title="${esc(i.tip)}" style="cursor:help;"`:''}><span style="width:10px;height:10px;border-radius:3px;background:${i.color};display:inline-block;"></span><span style="color:var(--muted);">${esc(i.label)}</span> <strong>${i.n}</strong>${total?` <span style="color:var(--muted);">(${Math.round(i.n*100/total)}%)</span>`:''}</span>`).join('')}
    </div>`;
}
function chBarSvg(cats, series){
  const W=640, H=250, L=38, R=16, T=22, B=40, pw=W-L-R, ph=H-T-B;
  const max = Math.max(1, ...series.flatMap(s=>s.values)), step = Math.max(1, Math.ceil(max/4)), top = step*Math.ceil(max/step);
  const ys = v => T + ph - (v/top)*ph;
  const gw = pw/cats.length, bw = Math.min(34, (gw*0.7)/series.length);
  let grid=''; for(let v=0; v<=top; v+=step){ grid += `<line x1="${L}" y1="${ys(v)}" x2="${W-R}" y2="${ys(v)}" style="stroke:var(--line);"/><text x="${L-8}" y="${ys(v)+4}" text-anchor="end" font-size="11" style="${CH_FONT}fill:var(--muted);">${v}</text>`; }
  const bars = cats.map((c,ci)=>{
    const gx = L + gw*ci + gw/2 - (bw*series.length)/2;
    return series.map((sr,si)=>{ const v=sr.values[ci], x=gx+bw*si, y=ys(v);
      return `<g><rect x="${x}" y="${y}" width="${bw-3}" height="${Math.max(0,ys(0)-y)}" rx="3" style="fill:${sr.colors ? sr.colors[ci] : sr.color};"><title>${esc(c)} · ${esc(sr.name)}: ${v}</title></rect>${v>0?`<text x="${x+(bw-3)/2}" y="${y-5}" text-anchor="middle" font-size="11" font-weight="600" style="${CH_FONT}fill:var(--navy-800);">${v}</text>`:''}</g>`; }).join('')
      + `<text x="${L+gw*ci+gw/2}" y="${H-20}" text-anchor="middle" font-size="11" style="${CH_FONT}fill:var(--muted);">${esc(c)}</text>`;
  }).join('');
  return `<svg viewBox="0 0 ${W} ${H}" width="100%" role="img" aria-label="Grafik batang">${grid}${bars}</svg>
    <div class="flex justify-center gap-4 mt-1 text-xs">${series.filter(sr=>!sr.colors).map(sr=>`<span class="inline-flex items-center gap-1.5"><span style="width:10px;height:10px;border-radius:3px;background:${sr.color};display:inline-block;"></span><span style="color:var(--muted);">${esc(sr.name)}</span></span>`).join('')}</div>`;
}
function dashChartsHtml(o){
  const p = getPeriode('dashboard');
  const bulanShort = BULAN_NAMA.map(n=>n.slice(0,3));
  const perBulan = Array(12).fill(0);
  (DB.kegiatan||[]).forEach(k=>{ const rp=recPeriode(k); if(!p.y || rp.y===p.y) perBulan[rp.m-1]++; });
  const totalLine = perBulan.reduce((a,b)=>a+b,0);
  const persetujuan = ['Menunggu Persetujuan','Disetujui','Ditolak'].map((st,i)=>({
    label:st, n:o.kegiatanTahun.filter(k=>k.status_persetujuan===st).length,
    color:['var(--warn)','var(--ok)','var(--bad)'][i] }));
  const kats = ['TNI/Polri','Pemerintah','Perusahaan','Organisasi'];
  const potKat = kats.map(k=>o.potensiRows.filter(r=>r.kategori===k));
  const sum = (arr,f)=>arr.reduce((t,r)=>t+(parseFloat(r[f])||0),0);
  const potTotal = sum(o.potensiRows,'jumlah_tersertifikasi') + sum(o.potensiRows,'jumlah_belum_sertifikasi');
  const card = (title, sub, body, span) => `<div class="panel rounded-xl p-4 ${span||''}">
      <div class="mb-2"><h3 class="font-display font-semibold text-sm">${title}</h3><div class="text-[11px]" style="color:var(--muted);">${sub}</div></div>${body}</div>`;
  const periodeTxt = (p.m ? BULAN_NAMA[p.m-1]+' ' : '') + (p.y || 'Semua Tahun');
  // Peralatan per Lokasi (stok: tidak difilter periode)
  const lokasiList = EXTRA_MODULES.find(m=>m.key==='peralatan').fields.find(f=>f.key==='lokasi').options;
  const prlRows = DB.peralatan || [];
  const prlKatList = EXTRA_MODULES.find(m=>m.key==='peralatan').fields.find(f=>f.key==='kategori').options;
  const prlKatColor = ['var(--navy-800)','var(--orange-500)','var(--ok)','var(--warn)','#7C5CBF'];
  const prlSeries = prlKatList.map((k,i)=>({
    name:k, color:prlKatColor[i%prlKatColor.length],
    values:lokasiList.map(l=>prlRows.filter(r=>(r.lokasi||'KPP Jakarta')===l && r.kategori===k).reduce((t,r)=>t+peralatanJumlah(r),0))
  }));
  const prlTotal = prlSeries.reduce((t,s)=>t+s.values.reduce((a,b)=>a+b,0),0);
  return `<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    ${card('Kegiatan/Tugas per Bulan', 'Jumlah kegiatan tiap bulan · '+(p.y ? 'Tahun '+p.y : 'Semua tahun digabung'), totalLine ? chLineSvg(bulanShort, perBulan) : chEmpty('Belum ada kegiatan pada tahun ini.'), 'lg:col-span-2')}
    ${card('Status Persetujuan Kegiatan', 'Periode '+esc(periodeTxt), chDonutHtml(persetujuan))}
    ${card('Potensi SAR per Kategori', 'Tersertifikasi vs belum tersertifikasi · Periode '+esc(periodeTxt), potTotal ? chBarSvg(kats, [
      {name:'Tersertifikasi', color:'var(--navy-800)', values:potKat.map(a=>sum(a,'jumlah_tersertifikasi'))},
      {name:'Belum', color:'var(--orange-500)', values:potKat.map(a=>sum(a,'jumlah_belum_sertifikasi'))}]) : chEmpty('Belum ada data potensi pada periode ini.'), 'lg:col-span-2')}
    ${card('Hasil Uji Periodik', o.ujiNama ? esc(o.ujiNama)+' · '+o.ujiPeserta+' peserta' : 'Belum ada kegiatan', chDonutHtml([
      {label:'Lulus', n:o.lulus, color:'var(--ok)'}, {label:'Tidak Lulus', n:o.tidakLulus, color:'var(--bad)'}]))}
    ${card('Peralatan per Kategori di Tiap Lokasi', 'Jumlah unit peralatan (baik + rusak) menurut kategori, per lokasi', prlTotal ? chBarSvg(lokasiList, prlSeries) : chEmpty('Belum ada data peralatan.'), 'lg:col-span-2')}
    ${(()=>{ const kk = kendaraanKondisiChart(); return card('Kendaraan per Kondisi', 'Jumlah kendaraan menurut kondisi · '+(kendaraanLokasiFilter||'Semua Lokasi')+' · total '+kk.total+' kendaraan', kk.body, 'lg:col-span-1'); })()}
  </div>`;
}
/* Grafik Kendaraan: X = Kondisi, Y = Jumlah (stok, tidak difilter periode) */
let kendaraanLokasiFilter = '';
function kendaraanLokasiOpsi(){
  const set = new Set(LOKASI_KENDARAAN.concat(LOKASI_LAUT_EXTRA));
  (DB.kendaraan||[]).forEach(r=>{ if(r.lokasi) set.add(r.lokasi); });
  return Array.from(set);
}
function setKendaraanLokasi(v){ kendaraanLokasiFilter = v; render(currentView); }
function kendaraanLokasiSelectHtml(){
  return `<div class="flex items-center gap-2 mb-3">
    <span class="text-xs font-medium" style="color:var(--muted);">Lokasi</span>
    <select style="width:auto;flex:1;" aria-label="Filter Lokasi Kendaraan" onchange="setKendaraanLokasi(this.value)">
      <option value="">Semua Lokasi</option>
      ${kendaraanLokasiOpsi().map(o=>`<option value="${esc(o)}" ${kendaraanLokasiFilter===o?'selected':''}>${esc(o)}</option>`).join('')}
    </select>
  </div>`;
}
/* Grafik Kendaraan: X = Kondisi, Y = Jumlah (stok, tidak difilter periode; bisa difilter Lokasi) */
function kendaraanKondisiChart(){
  const kondisi = ['Serviceable','Unserviceable','Scrap'];
  const rows = (DB.kendaraan || []).filter(r=>!kendaraanLokasiFilter || r.lokasi===kendaraanLokasiFilter);
  const vals = kondisi.map(k=>rows.filter(r=>r.kondisi===k).length);
  const total = vals.reduce((x,y)=>x+y,0);
  const chart = total
    ? chDonutHtml(kondisi.map((k,i)=>{
        const list = rows.filter(r=>r.kondisi===k).map(r=>{
          const no = r.nomor_polisi || '-';
          return '• '+(r.nama_kendaraan||'-')+' ('+(saprasKategori(r)==='Sapras Laut' ? 'No Lambung ' : 'No Polisi ')+no+')';
        });
        return {label:k, n:vals[i], color:['var(--ok)','var(--warn)','var(--bad)'][i],
          tip: k+': '+vals[i]+(list.length ? '\n'+list.join('\n') : '')};
      }))
    : chEmpty(kendaraanLokasiFilter ? 'Belum ada kendaraan di '+kendaraanLokasiFilter+'.' : 'Belum ada data kendaraan.');
  return {total, body: kendaraanLokasiSelectHtml() + chart};
}
function renderDashboard(){
  const inThn = r => inPeriode(r, 'dashboard');
  const counts = GENERIC_MODULES.map(m=>({label:m.label, n:(DB[m.key]||[]).filter(inThn).length}));
  const kegiatanTahun = (DB.kegiatan||[]).filter(inThn);
  const kegiatanCount = kegiatanTahun.length;
  const anggotaCount = DB.anggota.length;
  const anggotaAktif = DB.anggota.filter(a=>a.status==='Aktif').length;
  const menunggu = kegiatanTahun.filter(k=>k.status_persetujuan==='Menunggu Persetujuan');
  const maxN = Math.max(kegiatanCount, anggotaCount, ...counts.map(c=>c.n), 1);

  const potensiRows = (DB.potensi || []).filter(inThn);
  const potensiTersertifikasi = potensiRows.reduce((s,r)=>s+(parseFloat(r.jumlah_tersertifikasi)||0),0);
  const potensiBelumSertifikasi = potensiRows.reduce((s,r)=>s+(parseFloat(r.jumlah_belum_sertifikasi)||0),0);

  const ujiPeriodikRows = (DB.uji_periodik || []).filter(inThn);
  // Kegiatan uji periodik terbaru (tanggal mulai terbaru; jika sama/kosong, data yang paling akhir diinput)
  const ujiTerbaru = ujiPeriodikRows.reduce((best, r)=>{
    if(!best) return r;
    return String(r.tanggal_mulai||'') >= String(best.tanggal_mulai||'') ? r : best;
  }, null);
  let ujiPeriodikLulus = 0, ujiPeriodikTidakLulus = 0, ujiPesertaTotal = 0;
  if(ujiTerbaru){
    const ujiRekap = ujiPesertaRekap(ujiTerbaru.id);
    ujiPesertaTotal = ujiRekap.length;
    ujiRekap.filter(o=>o.akhir!==null).forEach(o=>{
      if(o.predikat==='Cukup' || o.predikat==='Baik' || o.predikat==='Sangat Baik') ujiPeriodikLulus++;
      else ujiPeriodikTidakLulus++;
    });
  }

  const cardData = [
    {label:'Kegiatan/Tugas', n:kegiatanCount},
    ...counts.filter(c=>c.label!=='Rescuer'),
    {label:'Daftar Anggota', n:anggotaCount},
  ];

  // Peralatan: total unit + rincian per kategori (stok, tidak difilter periode).
  const peralatanRows = DB.peralatan || [];
  const peralatanKatField = EXTRA_MODULES.find(m=>m.key==='peralatan').fields.find(f=>f.key==='kategori');
  const peralatanPerKat = peralatanKatField.options.map(k=>({
    label:k,
    n:peralatanRows.filter(r=>r.kategori===k).reduce((s,r)=>s+peralatanJumlah(r),0)
  }));
  const peralatanTotal = peralatanRows.reduce((s,r)=>s+peralatanJumlah(r),0);
  const gridData = cardData.slice(0,-1).concat([{label:'Peralatan', n:peralatanTotal}, {label:'Surat Masuk', n:(DB.surat_masuk||[]).length}, {label:'Surat Keluar', n:suratKeluarSemua().length}], cardData.slice(-1)).filter(c=>!(isAnggotaOnly() && ['Daftar Anggota','Surat Masuk','Surat Keluar'].includes(c.label)));

  document.getElementById('app').innerHTML = `
    <div class="flex items-center justify-end gap-2 mb-3">
      <span class="text-xs font-medium" style="color:var(--muted);">Periode</span>
      ${periodeSelectHtml('dashboard')}
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
      ${gridData.map((c,i)=>`
        <div class="panel rounded-xl p-4">
          <div class="text-xs mb-1" style="color:var(--muted);">${esc(c.label)}</div>
          <div class="font-display font-bold text-2xl" style="color:${i%3===0?'var(--orange-500)':'var(--navy-800)'}">${c.n}</div>
          ${c.label==='Peralatan' ? `
            <div class="mt-2 pt-2 border-t text-[11px] space-y-0.5" style="border-color:var(--line);">
              ${peralatanPerKat.map(k=>`
                <div class="flex items-center justify-between gap-2">
                  <span style="color:var(--muted);">${esc(k.label)}</span>
                  <span class="font-semibold" style="color:var(--navy-800)">${k.n}</span>
                </div>`).join('')}
            </div>` : ''}
          ${c.label==='Potensi' ? `
            <div class="flex items-center gap-2 mt-2 pt-2 border-t text-[11px]" style="border-color:var(--line);">
              <span style="color:var(--muted);">Tersertifikasi</span>
              <span class="font-semibold" style="color:var(--navy-800)">${potensiTersertifikasi}</span>
              <span style="color:var(--line);">|</span>
              <span style="color:var(--muted);">Belum</span>
              <span class="font-semibold" style="color:var(--orange-500)">${potensiBelumSertifikasi}</span>
            </div>` : ''}
          ${c.label==='Uji Periodik' ? `
            <div class="text-[11px] mt-1 truncate" style="color:var(--muted);" title="Kegiatan terbaru">${ujiTerbaru ? esc(ujiTerbaru.nama_uji||'-') : 'Belum ada kegiatan'}</div>
            <div class="text-[11px] mt-0.5" style="color:var(--muted);">Peserta <span class="font-semibold" style="color:var(--navy-800)">${ujiPesertaTotal}</span></div>
            <div class="flex items-center gap-2 mt-2 pt-2 border-t text-[11px]" style="border-color:var(--line);">
              <span style="color:var(--muted);">Lulus</span>
              <span class="font-semibold" style="color:var(--navy-800)">${ujiPeriodikLulus}</span>
              <span style="color:var(--line);">|</span>
              <span style="color:var(--muted);">Tidak Lulus</span>
              <span class="font-semibold" style="color:var(--orange-500)">${ujiPeriodikTidakLulus}</span>
            </div>` : ''}
        </div>`).join('')}
    </div>

    ${dashChartsHtml({kegiatanTahun, potensiRows, lulus:ujiPeriodikLulus, tidakLulus:ujiPeriodikTidakLulus, ujiNama:ujiTerbaru ? (ujiTerbaru.nama_uji||'-') : '', ujiPeserta:ujiPesertaTotal})}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
      <div class="panel rounded-xl p-4 lg:col-span-3">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-display font-semibold text-sm">Jumlah Data per Program</h3>
        </div>
        ${cardData.filter(c=>!(isAnggotaOnly() && c.label==='Daftar Anggota')).map(c=>`
          <div class="barrow">
            <span class="truncate" style="color:var(--muted)">${esc(c.label)}</span>
            <div class="bartrack"><div class="barfill" style="width:${Math.max(4,(c.n/maxN)*100)}%"></div></div>
            <span class="text-right font-semibold">${c.n}</span>
          </div>`).join('')}
      </div>

      <div class="panel rounded-xl p-4 lg:col-span-2">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-display font-semibold text-sm">Menunggu Persetujuan</h3>
          <span class="badge b-warn">${menunggu.length}</span>
        </div>
        ${menunggu.length ? `<div ${menunggu.length > 6 ? 'class="menunggu-scroll"' : ''}>` + menunggu.slice().reverse().map(k=>`
          <div class="flex items-start justify-between gap-2 py-2 border-b last:border-0" style="border-color:var(--line);">
            <div class="min-w-0">
              <div class="text-sm font-medium truncate">${esc(k.nama)}</div>
              <div class="text-xs" style="color:var(--muted);">${esc(k.jenis_label)} · ${esc(tglRentang(k))}</div>
            </div>
            <button class="btn btn-ghost !py-1 !px-2 text-xs flex-shrink-0" onclick="openKegiatanDetail('${k.id}')">Detail</button>
          </div>`).join('') + `</div>` : `<p class="text-sm" style="color:var(--muted);">Tidak ada kegiatan yang menunggu persetujuan.</p>`}
        ${isAnggotaOnly() ? '' : `<div class="mt-3 pt-3 border-t text-xs" style="border-color:var(--line); color:var(--muted);">
          Anggota aktif: <strong style="color:var(--text)">${anggotaAktif}</strong> dari ${anggotaCount}
        </div>`}
      </div>
    </div>
    ${dashNotifHtml(kegiatanTahun)}
    ${dashTablesHtml()}
  `;
}

/* ---- Notifikasi tindak lanjut Kegiatan/Tugas (setelah Surat Masuk/Kegiatan di-ACC) ----
   1) Disetujui tetapi belum ada anggota bertugas yang dipilih.
   2) Anggota sudah dipilih tetapi Sprin belum diupload.
   Kegiatan yang sudah Selesai tidak ditampilkan. Mengikuti filter Periode dashboard. */
function dashNotifPanel(title, items, emptyText, subFn, headExtra, id){
  return `<div class="panel rounded-xl p-4" ${id?`id="${id}"`:''}>
    <div class="flex items-center justify-between gap-2 mb-3">
      <div class="flex items-center gap-2 flex-wrap min-w-0">
        <h3 class="font-display font-semibold text-sm">${esc(title)}</h3>
        ${headExtra||''}
      </div>
      <span class="badge ${items.length ? 'b-warn' : 'b-ok'}">${items.length}</span>
    </div>
    ${items.length ? `<div ${items.length > 6 ? 'class="menunggu-scroll"' : ''}>` + items.slice().reverse().map(k=>`
      <div class="flex items-start justify-between gap-2 py-2 border-b last:border-0" style="border-color:var(--line);">
        <div class="min-w-0">
          <div class="text-sm font-medium truncate">${esc(k.nama)}</div>
          <div class="text-xs" style="color:var(--muted);">${subFn(k)}</div>
        </div>
        <button class="btn btn-ghost !py-1 !px-2 text-xs flex-shrink-0" onclick="openKegiatanDetail('${k.id}')">Detail</button>
      </div>`).join('') + `</div>` : `<p class="text-sm" style="color:var(--muted);">${esc(emptyText)}</p>`}
  </div>`;
}
/* Panel "Sprin Belum Dibuat": filter keterangan (Belum/Sedang Di Proses) + kirim daftar ke grup WhatsApp. */
let sprinPanelFilter = '';
let sprinPanelItems = [];
function sprinPanelFiltered(){
  return sprinPanelFilter ? sprinPanelItems.filter(k=>sprinStatusLabel(k)===sprinPanelFilter) : sprinPanelItems;
}
function sprinPanelHtml(){
  const items = sprinPanelFiltered();
  const sub = k => `${esc(k.jenis_label)} · ${esc(tglRentang(k))} · ${(k.anggota_ids||[]).length} anggota · ${esc(sprinStatusLabel(k))}`;
  const ctrl = `
    <select style="width:auto;" class="!py-1 text-xs" aria-label="Filter keterangan Sprin" onchange="setSprinPanelFilter(this.value)">
      <option value="" ${sprinPanelFilter===''?'selected':''}>Semua</option>
      <option value="Belum Di Proses" ${sprinPanelFilter==='Belum Di Proses'?'selected':''}>Belum Di Proses</option>
      <option value="Sedang Di Proses" ${sprinPanelFilter==='Sedang Di Proses'?'selected':''}>Sedang Di Proses</option>
    </select>
    <button class="btn !py-1 !px-2 text-xs inline-flex items-center gap-1" style="background:#25D366;color:#fff;" title="Buka WhatsApp dan pilih grup" aria-label="Kirim lewat WhatsApp" onclick="kirimSprinKeGrup()" ${items.length?'':'disabled'}>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm0 2a8 8 0 1 1-4.2 14.8l-.4-.2-2.4.6.7-2.3-.2-.4A8 8 0 0 1 12 4zm-3 3.500c-.2 0-.5.1-.7.4-.3.300-.9.900-.9 2.100s.9 2.400 1 2.600c.1.200 1.800 2.800 4.400 3.800 2.200.8 2.600.7 3.100.6.5-.1 1.500-.6 1.700-1.200.2-.6.2-1.100.1-1.200-.1-.1-.3-.2-.6-.3l-1.800-.9c-.2-.1-.4-.1-.6.100l-.8 1c-.1.200-.3.200-.5.100-.9-.4-2-1.100-2.800-2.400-.1-.2 0-.3.100-.5l.4-.5.3-.5v-.5l-.8-1.900c-.2-.5-.4-.4-.6-.4z"/></svg>
      WA
    </button>`;
  return dashNotifPanel('Sprin Belum Dibuat', items,
    sprinPanelFilter ? `Tidak ada Sprin berstatus "${sprinPanelFilter}".` : 'Semua kegiatan yang sudah ada anggotanya sudah memiliki Sprin.',
    sub, ctrl, 'panel-sprin-belum');
}
function setSprinPanelFilter(v){
  sprinPanelFilter = v;
  const el = document.getElementById('panel-sprin-belum');
  if(el) el.outerHTML = sprinPanelHtml();
}
/* Membuka WhatsApp (aplikasi/WhatsApp Web) dengan pesan sudah terisi; grup tujuan dipilih manual oleh pengirim. */
function kirimSprinKeGrup(){
  const items = sprinPanelFiltered();
  if(!items.length){ toast('Tidak ada Sprin untuk dikirim'); return; }
  const daftar = arr => arr.slice().reverse().map((k,i)=>`${i+1}. ${k.nama} (${tglRentang(k)})`).join('\n');
  const sedang = items.filter(k=>sprinStatusLabel(k)==='Sedang Di Proses');
  const belum  = items.filter(k=>sprinStatusLabel(k)!=='Sedang Di Proses');
  const blok = [];
  if(sprinPanelFilter==='Belum Di Proses'){
    blok.push(`*Sprin Belum Di Buat*\n\n${daftar(belum)}`);
  }else{
    if(sedang.length) blok.push(`*Sprin Sedang Di Proses*\n${daftar(sedang)}`);
    if(belum.length)  blok.push(`*Sprin Belum Dibuat*\n${daftar(belum)}`);
  }
  const w = window.open('https://wa.me/?text=' + encodeURIComponent(blok.join('\n\n')), '_blank', 'noopener');
  if(!w) toast('Pop-up diblokir browser, izinkan pop-up lalu coba lagi');
}
function dashNotifHtml(kegiatanList){
  const aktif = kegiatanList.filter(k=>k.status_persetujuan==='Disetujui' && k.status_pelaksanaan!=='Selesai');
  const belumAnggota = aktif.filter(k=>!(k.anggota_ids||[]).length);
  sprinPanelItems = aktif.filter(k=>(k.anggota_ids||[]).length && !k.sprin);
  const sub = k => `${esc(k.jenis_label)} · ${esc(tglRentang(k))}`;
  return `<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    ${dashNotifPanel('Belum Ada Anggota Bertugas', belumAnggota, 'Semua kegiatan yang disetujui sudah memiliki anggota bertugas.', sub)}
    ${sprinPanelHtml()}
  </div>`;
}

/* ---- Filter Lokasi Peralatan (dipakai di Dashboard & halaman modul Peralatan) ---- */
let lokasiFilter = '';
function lokasiSelectHtml(){
  const opts = EXTRA_MODULES.find(m=>m.key==='peralatan').fields.find(f=>f.key==='lokasi').options;
  return `<select style="width:auto;" aria-label="Filter Lokasi" onchange="setLokasiFilter(this.value)">
    <option value="">Semua Lokasi</option>
    ${opts.map(o=>`<option value="${esc(o)}" ${lokasiFilter===o?'selected':''}>${esc(o)}</option>`).join('')}
  </select>`;
}
function setLokasiFilter(v){ lokasiFilter = v; render(currentView); }
let kategoriFilter = '';
function kategoriSelectHtml(){
  const opts = EXTRA_MODULES.find(m=>m.key==='peralatan').fields.find(f=>f.key==='kategori').options;
  return `<select style="width:auto;" aria-label="Filter Kategori" onchange="setKategoriFilter(this.value)">
    <option value="">Semua Kategori</option>
    ${opts.map(o=>`<option value="${esc(o)}" ${kategoriFilter===o?'selected':''}>${esc(o)}</option>`).join('')}
  </select>`;
}
function setKategoriFilter(v){ kategoriFilter = v; render(currentView); }
function peralatanFilterHtml(){ return kategoriSelectHtml() + lokasiSelectHtml(); }
function peralatanCocok(r){
  return (!lokasiFilter || (r.lokasi||'KPP Jakarta')===lokasiFilter) && (!kategoriFilter || r.kategori===kategoriFilter);
}

/* ---- Ringkasan jumlah Peralatan per Kategori (mengikuti filter Lokasi; klik untuk memfilter Kategori) ---- */
function peralatanKategoriRingkasHtml(){
  const kats = EXTRA_MODULES.find(m=>m.key==='peralatan').fields.find(f=>f.key==='kategori').options;
  const rows = (DB.peralatan||[]).filter(r=>!lokasiFilter || (r.lokasi||'KPP Jakarta')===lokasiFilter);
  const num = v => parseFloat(v)||0;
  const items = kats.map(k=>{
    const rs = rows.filter(r=>r.kategori===k);
    return {k, baik:rs.reduce((t,r)=>t+num(r.jumlah_baik),0), rusak:rs.reduce((t,r)=>t+num(r.jumlah_rusak),0), jenis:rs.length};
  });
  const total = items.reduce((t,i)=>t+i.baik+i.rusak,0);
  return `<div class="mb-3">
    <div class="text-[11px] mb-1.5" style="color:var(--muted);">Jumlah peralatan per kategori${lokasiFilter ? ' · '+esc(lokasiFilter) : ' · Semua Lokasi'} — total <strong style="color:var(--navy-800)">${total}</strong> unit. Klik kategori untuk memfilter tabel.</div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
      ${items.map(i=>{
        const aktif = kategoriFilter===i.k;
        return `<button type="button" onclick="setKategoriFilter(${aktif?"''":"'"+esc(i.k)+"'"})" class="rounded-lg p-2.5 text-left" style="cursor:pointer; border:1px solid ${aktif?'var(--orange-500)':'var(--line)'}; background:${aktif?'var(--orange-100)':'var(--bg)'};">
          <div class="text-[11px]" style="color:var(--muted);">${esc(i.k)}</div>
          <div class="font-display font-bold text-lg" style="color:var(--navy-800);">${i.baik+i.rusak}</div>
          <div class="text-[10px]" style="color:var(--muted);">Baik ${i.baik} · Rusak ${i.rusak} · ${i.jenis} jenis</div>
        </button>`;}).join('')}
    </div>
  </div>`;
}

/* ---- Unduh tabel per modul di Dashboard (PDF / Excel), mengikuti filter yang sedang aktif ---- */
/* Kolom tabel Kendaraan di Dashboard per kategori Sapras (Darat / Laut) */
function kendaraanDashCols(kat){
  const m = TABLE_MODULES.find(x=>x.key==='kendaraan');
  return m.fields.filter(f=>f.type!=='hidden' && (f.col || f.key==='lokasi' || (kat==='Sapras Laut' && f.laut) || (kat==='Sapras Darat' && f.darat)));
}
function dashTableData(key){
  if(key==='kendaraan_darat' || key==='kendaraan_laut'){
    const kat = key==='kendaraan_darat' ? 'Sapras Darat' : 'Sapras Laut';
    const cols = kendaraanDashCols(kat);
    const rows = (DB.kendaraan||[]).filter(r=>saprasKategori(r)===kat && dashLolos(key,r,true)).slice().reverse();
    return {title:kat,
      head:['ID'].concat(cols.map(c=>labelKendaraan(c, kat==='Sapras Laut'))),
      body:rows.map(r=>[r.id].concat(cols.map(c=>dashVal(c,r))))};
  }
  if(key==='kegiatan'){
    const rows = (DB.kegiatan||[]).filter(k=>dashLolos('kegiatan',k,false)).slice().reverse();
    return {title:'Kegiatan/Tugas',
      head:['ID','Nama Kegiatan','Jenis','Tanggal','Lokasi','Persetujuan','Pelaksanaan'],
      body:rows.map(k=>[k.id,k.nama,k.jenis_label,tglRentang(k),k.lokasi,k.status_persetujuan,k.status_pelaksanaan])};
  }
  if(key==='surat_masuk'){
    const rows = (DB.surat_masuk||[]).filter(s=>dashLolos('surat_masuk', s, false, {tanggal:s.tanggal_surat})).slice().reverse();
    return {title:'Surat Masuk',
      head:['ID','Nomor Surat','Asal Surat','Perihal','Jenis','Tgl Surat','Persetujuan'],
      body:rows.map(s=>[s.id,s.nomor_surat,s.asal_surat,s.perihal,s.jenis_label,tglDMY(s.tanggal_surat),s.status])};
  }
  if(key==='anggota'){
    const cols = ANGGOTA_FIELDS.filter(f=>f.col && !f.isStatus);
    const sf = ANGGOTA_FIELDS.find(f=>f.isStatus);
    return {title:'Daftar Anggota', head:cols.map(c=>c.label).concat('Status'),
      body:(DB.anggota||[]).filter(r=>dashLolos('anggota',r,true)).map(r=>cols.map(c=>r[c.key]).concat(r[sf.key]))};
  }
  const m = TABLE_MODULES.find(x=>x.key===key); if(!m) return null;
  let rows = (DB[key]||[]).filter(r=>dashLolos(key, r, ['peralatan','kendaraan'].includes(key))).slice().reverse();
  if(key==='peralatan') rows = rows.filter(peralatanCocok);
  const cols = m.fields.filter(f=>f.col && !f.isStatus);
  const sf = m.fields.find(f=>f.isStatus);
  return {title:m.label,
    head:['ID'].concat(cols.map(c=>c.label), sf ? ['Status'] : []),
    body:rows.map(r=>[r.id].concat(cols.map(c=>dashVal(c,r)), sf ? [statusFieldValue(key, sf, r)] : []))};
}
function exportDashTable(key, fmt){
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  const d = dashTableData(key); if(!d) return;
  if(!d.body.length){ toast('Belum ada data '+d.title+' untuk diunduh'); return; }
  if(key==='surat_masuk' && fmt==='excel'){ exportSuratMasukExcel(); return; }
  const body = d.body.map(r=>r.map(v=>(v===undefined||v===null) ? '' : v));
  const fname = ('Data_'+d.title).replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_');
  try{
    if(fmt==='excel'){
      if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
      const ws = XLSX.utils.aoa_to_sheet([d.head, ...body]);
      ws['!cols'] = d.head.map((h,i)=>({wch: Math.min(45, Math.max(String(h).length, ...body.map(r=>String(r[i]).length))+2)}));
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, d.title.replace(/[\\/?*\[\]:]/g,'').slice(0,31));
      XLSX.writeFile(wb, fname+'.xlsx');
    } else {
      if(!(window.jspdf && window.jspdf.jsPDF)){ toast('Library PDF belum termuat, periksa koneksi internet'); return; }
      const doc = new window.jspdf.jsPDF({orientation: d.head.length>6 ? 'landscape' : 'portrait', unit:'pt', format:'a4'});
      doc.setFontSize(13); doc.text('Data '+d.title, 40, 40);
      doc.setFontSize(9); doc.text('Dicetak '+nowStr(), 40, 54);
      doc.autoTable({
        head:[d.head], body, startY:64,
        styles:{fontSize:8, cellPadding:3, valign:'middle'},
        headStyles:{fillColor:[19,49,92]}
      });
      doc.save(fname+'.pdf');
    }
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}

/* ---- Unduh Excel Surat Masuk: format rekap (kuning, 10 kolom, lampiran berupa tautan) ---- */
function exportSuratMasukExcel(){
  if(typeof ExcelJS==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const rows = (DB.surat_masuk||[]).filter(s=>dashLolos('surat_masuk', s, false, {tanggal:s.tanggal_surat})).slice().reverse();
  const tgl = iso => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso||'');
    return m ? (+m[3]) + ' ' + BULAN_NAMA[+m[2]-1] + ' ' + m[1] : '-';
  };
  const jam = w => /^\d{1,2}:\d{2}/.test(w||'') ? w.slice(0,5).replace(':','.') : '00.00';
  const head = ['No.','Tanggal Masuk','Tanggal Naskah','Dari','Batas Waktu','Nomor Surat','Hal','Lampiran Surat Masuk','Status','Tambahan Instruksi KKS'];
  const widths = [6.83,22.83,22.83,32.83,26.83,22.83,32.83,26.83,18.83,26.83];
  const thin = {style:'thin', color:{argb:'FF000000'}};
  const border = {top:thin, left:thin, bottom:thin, right:thin};
  try{
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Surat Masuk');
    ws.columns = widths.map(w=>({width:w}));
    const hr = ws.addRow(head);
    hr.height = 26;
    hr.eachCell(c=>{
      c.font = {name:'Calibri', size:11, bold:true, color:{argb:'FF000000'}};
      c.fill = {type:'pattern', pattern:'solid', fgColor:{argb:'FFFFFF00'}};
      c.alignment = {horizontal:'center', vertical:'middle', wrapText:true};
      c.border = border;
    });
    rows.forEach((s,i)=>{
      const f = s.file_pdf || null;
      const nama = f ? f.name : '-';
      const url = f && (f.url || f.link || f.path);
      const r = ws.addRow([
        i+1,
        'Tanggal disposisi: ' + tgl(s.tanggal_diterima),
        'Tanggal naskah: ' + tgl(s.tanggal_surat),
        'Dari : ' + (s.asal_surat || '-'),
        'Batas: ' + tgl(s.tanggal_kegiatan) + ' pukul ' + jam(s.waktu) + ' WIB',
        'Nomor naskah: ' + (s.nomor_surat || '-'),
        'Hal : ' + (s.perihal || '-'),
        nama,
        s.status || '-',
        s.keterangan_persetujuan || '-'
      ]);
      r.eachCell({includeEmpty:true}, (c,col)=>{
        const center = col===1 || col===9;
        c.font = {name:'Calibri', size:10.5, color:{argb:'FF000000'}};
        c.alignment = {horizontal:center?'center':'left', vertical:'top', wrapText:true};
        c.border = border;
      });
      const lc = r.getCell(8);
      lc.font = {name:'Calibri', size:10.5, underline:'single', color:{argb:'FF1155CC'}};
      if(f && /^https?:\/\//i.test(url||'')) lc.value = {text:nama, hyperlink:url, tooltip:'Buka PDF Surat Masuk'};
    });
    wb.xlsx.writeBuffer().then(buf=>{
      const blob = new Blob([buf], {type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = 'Rekap_Surat_Masuk.xlsx';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(()=>URL.revokeObjectURL(a.href), 2000);
    }).catch(e=>{ console.error(e); toast('Gagal mengunduh file'); });
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}

/* ---- Tabel data per modul di Dashboard ---- */
function dashTablesHtml(){
  const STOK = ['peralatan','kendaraan']; // stok/inventaris: tidak difilter periode
  const th = t => `<th>${esc(t)}</th>`;
  const wrap = (title, count, goto, head, body, extra, above, bukaJs) => `
    <div class="panel rounded-xl p-4 mb-4">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <h3 class="font-display font-semibold text-sm">${esc(title)}</h3>
        <span class="badge b-info">${count} data</span>
        <div class="ml-auto flex items-center gap-2">
          ${dashRangeHtml(goto)}${extra || ''}
          <div class="dd">
            <button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" onclick="toggleUnduhMenu(event, this)">Unduh <span aria-hidden="true">&#9662;</span></button>
            <div class="dd-menu">
              <button type="button" class="dd-item" onclick="exportDashTable('${goto}','pdf')">PDF</button>
              <button type="button" class="dd-item" onclick="exportDashTable('${goto}','excel')">Excel</button>
            </div>
          </div>
          <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="${bukaJs || `render('${goto}')`}">Buka Modul</button>
        </div>
      </div>
      ${above || ''}
      <div class="tablewrap" style="max-height:300px; overflow-y:auto;">
        <table><thead><tr>${head}</tr></thead><tbody>${dashRangeAktif(goto) ? String(body).replace('Belum ada data.', 'Tidak ada data pada rentang tanggal tersebut.') : body}</tbody></table>
      </div>
    </div>`;
  const empty = n => `<tr><td colspan="${n}" class="text-center py-5" style="color:var(--muted);">Belum ada data.</td></tr>`;

  // Kegiatan/Tugas
  const keg = (DB.kegiatan||[]).filter(k=>dashLolos('kegiatan',k,false)).slice().reverse();
  let html = wrap('Kegiatan/Tugas', keg.length, 'kegiatan',
    ['ID','Nama Kegiatan','Jenis','Tanggal','Lokasi','Persetujuan','Pelaksanaan'].map(th).join(''),
    keg.length ? keg.map(k=>`<tr style="cursor:pointer;" onclick="openKegiatanDetail('${k.id}')">
      <td class="font-medium">${esc(k.id)}</td><td><div class="clamp3" title="${esc(k.nama)}">${esc(k.nama)}</div></td><td>${esc(k.jenis_label)}</td><td>${esc(tglRentang(k))}</td><td>${esc(k.lokasi)}</td>
      <td>${statusBadge(k.status_persetujuan)}</td><td>${statusBadge(k.status_pelaksanaan)}</td></tr>`).join('') : empty(7));

  // Modul program + inventaris (Rescuer dilewati: datanya diambil dari Daftar Anggota)
  TABLE_MODULES.filter(m=>m.key!=='rescuer').forEach(m=>{
    if(m.key==='kendaraan'){
      ['Sapras Darat','Sapras Laut'].forEach(kat=>{
        const laut = kat==='Sapras Laut';
        const kc = kendaraanDashCols(kat);
        const kr = (DB.kendaraan||[]).filter(r=>saprasKategori(r)===kat && dashLolos(laut ? 'kendaraan_laut' : 'kendaraan_darat', r, true)).slice().reverse();
        html += wrap(kat, kr.length, laut ? 'kendaraan_laut' : 'kendaraan_darat',
          th('ID') + kc.map(c=>th(labelKendaraan(c, laut))).join(''),
          kr.length ? kr.map(r=>`<tr style="cursor:pointer;" onclick="openGenericDetail('kendaraan','${r.id}')">
            <td class="font-medium">${esc(r.id)}</td>${kc.map(c=>`<td>${dashCell('kendaraan',c,r)}</td>`).join('')}</tr>`).join('') : empty(1+kc.length),
          '', '', `saprasFilter='${kat}'; render('kendaraan')`);
      });
      return;
    }
    let rows = (DB[m.key]||[]).filter(r=>dashLolos(m.key, r, STOK.includes(m.key))).slice().reverse();
    if(m.key==='peralatan') rows = rows.filter(peralatanCocok);
    const cols = m.fields.filter(f=>f.col && !f.isStatus);
    const sf = m.fields.find(f=>f.isStatus);
    const n = 1 + cols.length + (sf?1:0);
    html += wrap(m.label, rows.length, m.key,
      th('ID') + cols.map(c=>th(c.label)).join('') + (sf ? th('Status') : ''),
      rows.length ? rows.map(r=>`<tr style="cursor:pointer;" onclick="openGenericDetail('${m.key}','${r.id}')">
        <td class="font-medium">${esc(r.id)}</td>${cols.map(c=>`<td>${dashCell(m.key,c,r)}</td>`).join('')}
        ${sf ? `<td>${statusBadge(statusFieldValue(m.key, sf, r))}</td>` : ''}</tr>`).join('') : empty(n),
      m.key==='peralatan' ? peralatanFilterHtml() : '',
      m.key==='peralatan' ? peralatanKategoriRingkasHtml() : '');
  });

  // Surat Masuk (tepat di atas Daftar Anggota)
  const sml = (DB.surat_masuk||[]).filter(s=>dashLolos('surat_masuk', s, false, {tanggal:s.tanggal_surat})).slice().reverse();
  html += wrap('Surat Masuk', sml.length, 'surat_masuk',
    ['ID','Nomor Surat','Asal Surat','Perihal','Jenis','Tgl Surat','Persetujuan'].map(th).join(''),
    sml.length ? sml.map(sm=>`<tr style="cursor:pointer;" onclick="kegTab='surat'; openSuratDetail('${sm.id}')">
      <td class="font-medium">${esc(sm.id)}</td><td>${esc(sm.nomor_surat)}</td><td>${esc(sm.asal_surat)||'-'}</td><td>${esc(sm.perihal)}</td>
      <td>${esc(sm.jenis_label)}</td><td>${esc(tglDMY(sm.tanggal_surat))||'-'}</td>
      <td>${suratPersetujuanHtml(sm)}<div class="text-[11px]" style="color:var(--muted);">${esc(suratAccInfo(sm))}</div></td></tr>`).join('') : empty(7),
    '', '', `kegTab='surat'; render('kegiatan')`);

  // Daftar Anggota
  const ang = (DB.anggota || []).filter(r=>dashLolos('anggota', r, true));
  const aCols = ANGGOTA_FIELDS.filter(f=>f.col && !f.isStatus);
  const aSf = ANGGOTA_FIELDS.find(f=>f.isStatus);
  if(!isAnggotaOnly()) html += wrap('Daftar Anggota', ang.length, 'anggota',
    aCols.map(c=>th(c.label)).join('') + th('Status'),
    ang.length ? ang.map(r=>`<tr style="cursor:pointer;" onclick="openGenericDetail('anggota','${r.id}')">
      ${aCols.map(c=>`<td>${esc(r[c.key])}</td>`).join('')}<td>${statusBadge(r[aSf.key])}</td></tr>`).join('') : empty(aCols.length+1));

  return `<div class="mt-6"><h2 class="font-display font-semibold text-base mb-3">Data per Modul</h2>${html}</div>`;
}

/* ============================= GENERIC CRUD ============================= */
/* Stok Peralatan: Jumlah = Jumlah Baik + Jumlah Rusak; Tersedia = Jumlah - yang sedang dipinjam (otomatis dari modul Peminjaman). */
function normNama(s){ return String(s||'').trim().toLowerCase().replace(/\s+/g,' '); }
function peralatanJumlah(row){ return (parseFloat(row.jumlah_baik)||0) + (parseFloat(row.jumlah_rusak)||0); }
function peralatanDipinjam(nama, exceptId){
  const n = normNama(nama); if(!n) return 0;
  return (DB.peminjaman||[]).reduce((sum,p)=>{
    if(p.jenis_barang!=='Peralatan' || normNama(p.nama_barang)!==n) return sum;
    if(exceptId && p.id===exceptId) return sum;
    if(p.status==='Dikembalikan' || p.tanggal_kembali) return sum; // sudah kembali
    return sum + (parseFloat(p.jumlah)||1);
  }, 0);
}
/* Unit Peralatan yang sedang dalam pemeliharaan (status "Sedang Dikerjakan") tidak tersedia untuk dipinjam. */
function peralatanDipelihara(nama, exceptId){
  const n = normNama(nama); if(!n) return 0;
  return (DB.pemeliharaan||[]).reduce((sum,r)=>{
    if(!pgjAktif(r) || r.jenis_barang!=='Peralatan' || normNama(r.nama_barang)!==n || r.status!=='Sedang Dikerjakan') return sum;
    if(exceptId && r.id===exceptId) return sum;
    return sum + (parseFloat(r.jumlah)||1);
  }, 0);
}
function kendaraanDipelihara(nama){
  const n = normNama(nama); if(!n) return false;
  return (DB.pemeliharaan||[]).some(r=>pgjAktif(r) && r.jenis_barang==='Kendaraan' && normNama(r.nama_barang)===n && r.status==='Sedang Dikerjakan');
}
/* Kondisi Kendaraan mengikuti Pemeliharaan: dikerjakan => Unserviceable; selesai & Baik => Serviceable; selain itu Unserviceable. Scrap tidak diubah. */
function syncKendaraanDariPemeliharaan(rec){
  if(!rec || rec.jenis_barang!=='Kendaraan' || !pgjAktif(rec)) return;
  const k = (DB.kendaraan||[]).find(x=>normNama(x.nama_kendaraan)===normNama(rec.nama_barang));
  if(!k || k.kondisi==='Scrap') return;
  let baru = k.kondisi;
  if(rec.status==='Sedang Dikerjakan') baru = 'Unserviceable';
  else if(rec.status==='Selesai') baru = rec.kondisi_setelah==='Baik / Layak Pakai' ? 'Serviceable' : (rec.kondisi_setelah ? 'Unserviceable' : k.kondisi);
  if(baru!==k.kondisi){ k.kondisi = baru; k.diperbarui_oleh = pelaku(); k.diperbarui_waktu = nowStr(); }
}
function peralatanBaik(row){ return parseFloat(row.jumlah_baik)||0; }
function peralatanTersedia(row){ return Math.max(0, peralatanBaik(row) - peralatanDipinjam(row.nama_barang) - peralatanDipelihara(row.nama_barang)); } // hanya unit kondisi Baik; unit rusak tidak dihitung
/* Badge warna kondisi Kendaraan: Serviceable hijau, Unserviceable kuning, Scrap merah */
function kondisiKendaraanBadge(v){
  const cls = {'Serviceable':'b-ok','Unserviceable':'b-warn','Scrap':'b-bad'}[v];
  return cls ? `<span class="badge ${cls}">${esc(v)}</span>` : esc(v);
}
function cellHtml(key, f, row){
  if(key==='pembinaan' && f.key==='kategori_pembinaan') return esc(pbnKat(row));
  if(key==='pembinaan' && f.key==='dokumentasi') return pbnDokDetailHtml(row);
  if(key==='pemeliharaan' && (f.key==='estimasi_biaya' || f.key==='biaya')){ const v = parseFloat(row[f.key]); return isNaN(v) ? '' : esc('Rp ' + v.toLocaleString('id-ID')); }
  if(key==='kendaraan' && f.key==='kondisi') return kondisiKendaraanBadge(row.kondisi);
  if(f.type==='date') return esc(tglDMY(fieldValue(f,row)));
  return esc(fieldValue(f,row));
}
function fieldValue(f, row){
  if(row && f.key==='kategori_pembinaan') return pbnKat(row);
  if(row && f.type==='computed' && f.computeFrom && f.computeFrom.includes('jumlah_baik')){
    return f.minusBorrowed ? peralatanTersedia(row) : peralatanJumlah(row);
  }
  if(f.derivedFrom && row){
    const ref = (DB[f.derivedFrom.ref]||[]).find(x=>x.id===row[f.derivedFrom.idKey]);
    return ref ? (ref[f.derivedFrom.field] || '') : '';
  }
  // Petugas / Anggota Bertugas: tampilkan nama depan anggota yang ditugaskan di
  // Kegiatan/Tugas asal data ini. Kalau belum ada anggota yang ditugaskan, pakai isian biasa.
  if(row && (f.key==='petugas' || f.key==='anggota_bertugas') && row.dari_kegiatan_id){
    const kg = (DB.kegiatan||[]).find(x=>x.id===row.dari_kegiatan_id);
    const names = kg && Array.isArray(kg.anggota_ids)
      ? kg.anggota_ids.map(aid=>{ const a=(DB.anggota||[]).find(x=>x.id===aid); return a ? namaDepan(a.nama) : ''; }).filter(Boolean)
      : [];
    if(names.length) return names.join(', ');
  }
  const val = row && row[f.key] !== undefined ? row[f.key] : '';
  // Tambahkan satuan (mis. 8 Jam, 40 knot) bila field punya unit dan nilainya terisi
  return (f.unit && val!=='' && val!==null) ? val+' '+f.unit : val;
}
// Nama depan dari nama lengkap: lewati gelar di depan (Dr., Ir., H., dst), huruf awal kapital.
function namaDepan(full){
  const parts = String(full||'').trim().split(/\s+/).filter(Boolean);
  const gelar = /^(dr|drs|dra|ir|h|hj|prof|kh)\.?,?$/i;
  const first = parts.find(t=>!gelar.test(t)) || parts[0] || '';
  const clean = first.replace(/,+$/,'');
  return clean.charAt(0).toUpperCase() + clean.slice(1).toLowerCase();
}
function getAnggotaKegiatanCount(anggotaId){ return (DB.kegiatan||[]).filter(k=>(k.anggota_ids||[]).includes(anggotaId)).length; }
const RESCUER_JENIS_COLS = [
  {key:'sgts', label:'SGTS'},
  {key:'bimtek', label:'Bimtek'},
  {key:'narasumber', label:'Narasumber'},
  {key:'diklat', label:'Diklat'},
  {key:'pembinaan', label:'Pembinaan Tenaga SAR'},
  {key:'pemberdayaan', label:'PM'},
  {key:'uji_periodik', label:'UDIK'},
  {key:'lainnya', label:'Lainnya'},
];
function getAnggotaKegiatanCountByJenis(anggotaId, jenisKey){
  return (DB.kegiatan||[]).filter(k=>k.jenis===jenisKey && (k.anggota_ids||[]).includes(anggotaId)).length;
}

/* ---- Matrix peserta Diklat (Dikdas..Instruktur + Diklat Lain per orang) ---- */
const DIKLAT_PESERTA_COLS = [
  {key:'dikdas', label:'Dikdas'},
  {key:'mfr', label:'MFR'},
  {key:'jr', label:'JR'},
  {key:'hart', label:'HART'},
  {key:'wr', label:'WR'},
  {key:'cssr', label:'CSSR'},
  {key:'csr', label:'CSR'},
  {key:'uwt', label:'UWT'},
  {key:'var', label:'VAR'},
  {key:'hr', label:'HR'},
  {key:'sarplan', label:'Sarplan'},
  {key:'smc', label:'SMC'},
  {key:'bsg', label:'BSG'},
  {key:'instruktur', label:'Instruktur'},
];
const PESERTA_MODULES = {
  diklat:       {label:'Diklat',       nameKey:'nama_diklat', lainLabel:'Diklat Lain'},
  uji_periodik: {label:'Uji Periodik', nameKey:'nama_uji',    lainLabel:'Nilai Keseluruhan'},
};
let pesertaKey = 'diklat'; // modul yang sedang dipakai editor peserta
let diklatPesertaRows = [];
function newDiklatPesertaRow(){
  const r = {anggota_id:'', nama:'', jabatan:'', diklat_lain_teks:'', diklat_lain_pdf:null};
  getPesertaCols(pesertaKey).forEach(c=>r[c.key]=(pesertaKey==='uji_periodik' ? '' : false));
  return r;
}
function onDiklatPesertaNamaChange(select, i){
  if(!diklatPesertaRows[i]) return;
  const ang = (DB.anggota||[]).find(a=>a.id===select.value);
  diklatPesertaRows[i].anggota_id = select.value;
  diklatPesertaRows[i].nama = ang ? ang.nama : '';
  diklatPesertaRows[i].jabatan = ang ? (ang.jabatan||'') : '';
  refreshDiklatPesertaEditor();
}
function addDiklatPesertaRow(){
  diklatPesertaRows.push(newDiklatPesertaRow());
  refreshDiklatPesertaEditor();
}
function removeDiklatPesertaRow(i){
  diklatPesertaRows.splice(i,1);
  refreshDiklatPesertaEditor();
}
let diklatPesertaContext = 'modal'; // 'modal' (form Tambah/Ubah) atau 'main' (panel halaman utama)
function diklatPesertaWrapId(){ return diklatPesertaContext==='main' ? 'diklat_peserta_wrap_main' : (diklatPesertaContext==='daftar' ? 'diklat_peserta_wrap_daftar' : 'diklat_peserta_wrap'); }
function refreshDiklatPesertaEditor(){
  const wrap = document.getElementById(diklatPesertaWrapId());
  if(wrap) wrap.innerHTML = diklatPesertaEditorHtml();
}
function diklatPesertaEditorHtml(){
  if(pesertaKey==='diklat' && diklatPesertaContext==='main') return diklatRekapEditorHtml();
  return `
    <div class="flex items-center justify-between mb-2">
      <label class="block text-xs font-medium" style="color:var(--muted);">Data Peserta ${esc(PESERTA_MODULES[pesertaKey].label)}</label>
      <button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" onclick="addDiklatPesertaRow()">+ Tambah Peserta</button>
    </div>
    <div class="tablewrap" style="max-height:360px; overflow-y:auto;">
      <table>
        <thead><tr>
          <th>Nama</th><th>Jabatan</th>${pesertaKey==='uji_periodik' ? '<th class="text-center">JK</th>' : ''}
          ${getPesertaCols(pesertaKey).map((c,ci)=> (pesertaKey==='uji_periodik' && !ujiColsLocked)
            ? `<th class="text-center"><button type="button" class="colhead" title="Klik untuk ubah / hapus / tambah kolom" onclick="openColMenu(event,'${pesertaKey}',${ci})">${esc(c.label)} <span aria-hidden="true">&#9662;</span></button></th>`
            : `<th class="text-center">${esc(c.label)}</th>`).join('')}
          <th>${esc(PESERTA_MODULES[pesertaKey].lainLabel)}</th><th></th>
        </tr></thead>
        <tbody>
          ${diklatPesertaRows.length ? diklatPesertaRows.map((r,i)=>`
            <tr>
              <td>
                <select style="min-width:160px;" onchange="onDiklatPesertaNamaChange(this,${i})">
                  <option value="">-- Pilih Anggota --</option>
                  ${(DB.anggota||[]).map(a=>`<option value="${esc(a.id)}" ${r.anggota_id===a.id?'selected':''}>${esc(a.nama)}</option>`).join('')}
                </select>
              </td>
              <td><input type="text" style="min-width:120px;" value="${esc(r.jabatan)}" oninput="diklatPesertaRows[${i}].jabatan=this.value"></td>
              ${pesertaKey==='uji_periodik' ? `<td class="text-center"><select style="min-width:4.5rem;" onchange="diklatPesertaRows[${i}].jk=this.value">
                <option value="" ${!r.jk?'selected':''}>-</option>
                <option value="L" ${r.jk==='L'?'selected':''}>L</option>
                <option value="P" ${r.jk==='P'?'selected':''}>P</option>
              </select></td>` : ''}
              ${getPesertaCols(pesertaKey).map(c=>`<td class="text-center" style="min-width:5.5rem;">${pesertaCellHtml(r,i,c)}</td>`).join('')}
              <td id="dkl_lain_${diklatPesertaContext}_${i}" style="min-width:${pesertaKey==='diklat'?'250px':'auto'};">${pesertaLainHtml(r,i)}</td>
              <td><button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="removeDiklatPesertaRow(${i})">Hapus</button></td>
            </tr>`).join('') : `<tr><td colspan="${getPesertaCols(pesertaKey).length+(pesertaKey==='uji_periodik'?5:4)}" class="text-center py-4" style="color:var(--muted);">Belum ada peserta. Klik "+ Tambah Peserta".</td></tr>`}
        </tbody>
      </table>
    </div>`;
}
/* ---- Kolom peserta: Uji Periodik bisa ubah nama / hapus / tambah kolom ---- */
// Kolom Uji Periodik bersifat PER DATA UJI: tiap data punya kolomnya sendiri.
// Setelah "Simpan Data Peserta", kolom data tersebut terkunci dan tidak ikut berubah
// ketika kolom data uji lain diubah.
let ujiWorkCols = null;      // kolom yang sedang dipakai editor
let ujiColsLocked = false;   // true bila kolom data yang dibuka sudah tersimpan
function defaultUjiCols(){
  return DIKLAT_PESERTA_COLS.map(c=>({key:c.key, label:c.label}));
}
function loadUjiCols(row){
  if(row && Array.isArray(row.kolom_peserta) && row.kolom_peserta.length){
    ujiWorkCols = JSON.parse(JSON.stringify(row.kolom_peserta));
    ujiColsLocked = true;
  } else if(row && Array.isArray(row.peserta_matrix) && row.peserta_matrix.length){
    // data lama yang sudah tersimpan sebelum kolom dibuat per-data: pakai kolom lama, kunci
    const legacy = DB.kolom_peserta && Array.isArray(DB.kolom_peserta.uji_periodik) && DB.kolom_peserta.uji_periodik.length
      ? DB.kolom_peserta.uji_periodik : defaultUjiCols();
    ujiWorkCols = JSON.parse(JSON.stringify(legacy));
    ujiColsLocked = true;
  } else {
    ujiWorkCols = defaultUjiCols();
    ujiColsLocked = false;
  }
}
function getPesertaCols(key){
  if(key==='uji_periodik'){
    if(!ujiWorkCols) loadUjiCols(null);
    return ujiWorkCols;
  }
  return DIKLAT_PESERTA_COLS;
}
let colMenuState = null;
function ensureColMenu(){
  let m = document.getElementById('colMenu');
  if(m) return m;
  m = document.createElement('div');
  m.id = 'colMenu';
  m.addEventListener('click', e=>e.stopPropagation());
  document.body.appendChild(m);
  document.addEventListener('click', closeColMenu);
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeColMenu(); });
  window.addEventListener('resize', closeColMenu);
  return m;
}
function closeColMenu(){
  const m = document.getElementById('colMenu');
  if(m) m.classList.remove('show');
  colMenuState = null;
}
function openColMenu(ev, key, idx, anchorEl){
  if(ev) ev.stopPropagation();
  if(ujiColsLocked){ toast('Kolom data ini sudah disimpan dan tidak dapat diubah'); return; }
  const cols = getPesertaCols(key);
  const c = cols[idx]; if(!c) return;
  const m = ensureColMenu();
  colMenuState = {key, idx};
  m.innerHTML = `
    <label class="text-[11px] font-medium" style="color:var(--muted);">Nama kolom</label>
    <input type="text" id="colMenuInput" value="${esc(c.label)}" maxlength="30" onkeydown="if(event.key==='Enter'){ colMenuRename(); }">
    <button type="button" class="btn btn-primary !py-1 !px-2 text-xs" style="justify-content:center;" onclick="colMenuRename()">Simpan nama</button>
    <button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" style="justify-content:center;" onclick="colMenuAdd()">+ Tambah kolom di sebelah kanan</button>
    <button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" style="justify-content:center; color:var(--bad);" onclick="colMenuDelete()">Hapus kolom</button>`;
  const el = anchorEl || (ev && ev.currentTarget);
  const r = el.getBoundingClientRect();
  m.classList.add('show');
  const w = m.offsetWidth;
  m.style.left = Math.max(8, Math.min(r.left + r.width/2 - w/2, window.innerWidth - w - 8)) + 'px';
  m.style.top = Math.min(r.bottom + 6, window.innerHeight - m.offsetHeight - 8) + 'px';
  const inp = document.getElementById('colMenuInput');
  if(inp){ inp.focus(); inp.select(); }
}
function colMenuRename(){
  if(!colMenuState) return;
  const {key, idx} = colMenuState;
  const v = (document.getElementById('colMenuInput').value||'').trim();
  if(!v){ toast('Nama kolom tidak boleh kosong'); return; }
  getPesertaCols(key)[idx].label = v;
  closeColMenu(); refreshDiklatPesertaEditor();
  toast('Nama kolom diubah (klik Simpan Data Peserta untuk menyimpan)');
}
function colMenuAdd(){
  if(!colMenuState) return;
  const {key, idx} = colMenuState;
  const cols = getPesertaCols(key);
  cols.splice(idx+1, 0, {key:'x_'+Date.now().toString(36), label:'Kolom Baru'});
  closeColMenu(); refreshDiklatPesertaEditor();
  // langsung buka menu kolom baru agar bisa diberi nama
  const ths = document.querySelectorAll('#'+diklatPesertaWrapId()+' .colhead');
  if(ths[idx+1]) openColMenu(null, key, idx+1, ths[idx+1]);
}
function colMenuDelete(){
  if(!colMenuState) return;
  const {key, idx} = colMenuState;
  const cols = getPesertaCols(key);
  if(cols.length<=1){ toast('Minimal harus ada satu kolom'); return; }
  if(!confirm('Hapus kolom "'+cols[idx].label+'"? Ceklis pada kolom ini tidak akan ditampilkan lagi.')) return;
  cols.splice(idx,1);
  closeColMenu(); refreshDiklatPesertaEditor();
  toast('Kolom dihapus (klik Simpan Data Peserta untuk menyimpan data peserta)');
}

/* ---- Ceklis kompetensi + upload PDF per kolom ---- */
function ujiNilaiRataRata(r, cols){
  const nums = (cols || getPesertaCols('uji_periodik'))
    .map(c=>r[c.key])
    .filter(v=>typeof v!=='boolean' && v!=null && String(v).trim()!=='')
    .map(v=>parseFloat(String(v).replace(',','.')))
    .filter(n=>isFinite(n));
  if(!nums.length) return null;
  return nums.reduce((a,b)=>a+b,0)/nums.length;
}
function ujiNilaiRataRataText(r, cols){
  const a = ujiNilaiRataRata(r, cols);
  return a===null ? '-' : a.toLocaleString('id-ID',{maximumFractionDigits:2});
}
function onUjiNilaiInput(el, i, key){
  let v = el.value.replace(/[^0-9.,]/g,'');
  const m = v.search(/[.,]/);
  if(m>=0) v = v.slice(0,m+1) + v.slice(m+1).replace(/[.,]/g,'');
  if(v!==el.value) el.value = v;
  if(diklatPesertaRows[i]) diklatPesertaRows[i][key] = v;
  const avg = document.getElementById(`uji_avg_${diklatPesertaContext}_${i}`);
  if(avg && diklatPesertaRows[i]) avg.textContent = ujiNilaiRataRataText(diklatPesertaRows[i]);
}
function pesertaCellHtml(r, i, c){
  if(pesertaKey==='uji_periodik'){
    const val = (typeof r[c.key]==='boolean' || r[c.key]==null) ? '' : r[c.key];
    return `<input type="text" inputmode="decimal" placeholder="0" style="width:4.5rem; text-align:center;" value="${esc(val)}" oninput="onUjiNilaiInput(this,${i},'${c.key}')">`;
  }
  const pdf = r[c.key+'_pdf'];
  const id = `dkl_cell_${diklatPesertaContext}_${i}_${c.key}`;
  return `<div id="${id}" style="display:flex; flex-direction:column; align-items:center; gap:.3rem;">
    <input type="checkbox" style="width:1rem;height:1rem;" ${r[c.key]?'checked':''} onchange="onPesertaCheck(this,${i},'${c.key}')">
    ${(pesertaKey==='diklat' && r[c.key]) ? `
      <input type="file" accept="application/pdf,.pdf" style="display:none;" onchange="handlePesertaColPdf(this,${i},'${c.key}')">
      <button type="button" class="btn btn-ghost !py-0.5 !px-2 text-[11px]" style="border-radius:999px;" onclick="this.previousElementSibling.click()">Upload PDF</button>
      ${pdf ? `<a href="#" class="text-[11px]" style="color:var(--orange-500); text-decoration:underline;" title="${esc(pdf.name)}" onclick="viewPesertaColPdf(event,${i},'${c.key}')">Lihat PDF</a>` : ''}
    ` : ''}
  </div>`;
}
function rerenderPesertaCell(i, key){
  const r = diklatPesertaRows[i]; if(!r) return;
  const c = getPesertaCols(pesertaKey).find(x=>x.key===key);
  const el = document.getElementById(`dkl_cell_${diklatPesertaContext}_${i}_${key}`);
  if(el && el.parentElement) el.parentElement.innerHTML = pesertaCellHtml(r, i, c);
}
function onPesertaCheck(cb, i, key){
  const r = diklatPesertaRows[i]; if(!r) return;
  r[key] = cb.checked;
  if(!cb.checked) r[key+'_pdf'] = null; // PDF ikut dihapus bila ceklis dilepas
  rerenderPesertaCell(i, key);
}
function handlePesertaColPdf(input, i, key){
  const file = input.files && input.files[0];
  const r = diklatPesertaRows[i];
  if(!file || !r) return;
  if(file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')){
    toast('File harus berformat PDF'); input.value=''; return;
  }
  if(file.size > 10*1024*1024){
    toast('Ukuran file maksimal 10MB'); input.value=''; return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    r[key+'_pdf'] = { name:file.name, size:file.size, data:reader.result };
    rerenderPesertaCell(i, key);
    toast('PDF terpilih: '+file.name+' (jangan lupa klik Simpan Data Peserta)');
  };
  reader.onerror = () => toast('Gagal membaca file PDF');
  reader.readAsDataURL(file);
}
function openPdfDataUrl(dataUrl){
  try{
    const b64 = dataUrl.split(',')[1];
    const bin = atob(b64); const arr = new Uint8Array(bin.length);
    for(let k=0;k<bin.length;k++) arr[k]=bin.charCodeAt(k);
    window.open(URL.createObjectURL(new Blob([arr],{type:'application/pdf'})), '_blank');
  }catch(e){ window.open(dataUrl, '_blank'); }
}
function viewPesertaColPdf(ev, i, key){
  ev.preventDefault();
  const pdf = diklatPesertaRows[i] && diklatPesertaRows[i][key+'_pdf'];
  if(pdf) openPdfDataUrl(pdf.data);
}
function viewPesertaColPdfSaved(ev, rowId, pKey, idx, key){
  ev.preventDefault(); ev.stopPropagation();
  const row = (DB[pKey]||[]).find(x=>x.id===rowId);
  const pdf = row && row.peserta_matrix && row.peserta_matrix[idx] && row.peserta_matrix[idx][key+'_pdf'];
  if(pdf) openPdfDataUrl(pdf.data);
}
/* ---- Diklat Lain: satu orang bisa punya banyak isian + PDF (khusus modul Diklat) ---- */
function getDiklatLainList(r){
  if(!Array.isArray(r.diklat_lain_list)){
    r.diklat_lain_list = (r.diklat_lain_teks || r.diklat_lain_pdf)
      ? [{teks:r.diklat_lain_teks||'', pdf:r.diklat_lain_pdf||null}]
      : [{teks:'', pdf:null}];
  }
  if(!r.diklat_lain_list.length) r.diklat_lain_list.push({teks:'', pdf:null});
  return r.diklat_lain_list;
}
// Daftar Diklat Lain: bila lebih dari 2 entri, tinggi area dibatasi tepat 2 entri lalu scroll vertikal
function fitDiklatLainScroll(){
  document.querySelectorAll('.dkl-lain-scroll').forEach(el=>{
    el.style.maxHeight = 'none';
    const k = el.children;
    if(k.length<3) return;
    const h = k[1].getBoundingClientRect().bottom - k[0].getBoundingClientRect().top;
    if(h>0) el.style.maxHeight = Math.ceil(h) + 'px';
  });
}
window.addEventListener('resize', fitDiklatLainScroll);
function pesertaLainHtml(r, i){
  if(pesertaKey==='uji_periodik'){
    return `<span id="uji_avg_${diklatPesertaContext}_${i}" class="font-semibold" style="display:inline-block; min-width:4rem; text-align:center;">${ujiNilaiRataRataText(r)}</span>`;
  }
  if(pesertaKey!=='diklat'){
    return `
      <input type="text" placeholder="Nama ${esc(PESERTA_MODULES[pesertaKey].lainLabel.toLowerCase())}" style="min-width:150px;" value="${esc(r.diklat_lain_teks)}" oninput="diklatPesertaRows[${i}].diklat_lain_teks=this.value">
      <input type="file" accept="application/pdf,.pdf" style="margin-top:.3rem; font-size:11px;" onchange="handleDiklatPesertaPdfChange(this,${i})">
      <div id="dkl_peserta_pdf_info_${diklatPesertaContext}_${i}" class="text-[11px] mt-0.5" style="color:var(--muted);">${r.diklat_lain_pdf ? `Terpilih: ${esc(r.diklat_lain_pdf.name)}` : ''}</div>`;
  }
  const list = getDiklatLainList(r);
  if(list.length>2) setTimeout(fitDiklatLainScroll, 0);
  return `<div style="display:flex; flex-direction:column; gap:.55rem;">
    <div ${list.length>2?'class="dkl-lain-scroll"':''} style="display:flex; flex-direction:column; gap:.55rem; ${list.length>2?'overflow-y:auto; padding-right:.35rem;':''}">
    ${list.map((it,j)=>`
      <div style="display:flex; flex-direction:column; gap:.3rem; ${j>0?'padding-top:.55rem; border-top:1px dashed var(--line);':''}">
        <div style="display:flex; gap:.35rem; align-items:center;">
          <input type="text" placeholder="Nama diklat lain" style="min-width:150px;" value="${esc(it.teks)}" oninput="diklatPesertaRows[${i}].diklat_lain_list[${j}].teks=this.value">
          <button type="button" class="btn btn-ghost !py-0.5 !px-2 text-[11px]" style="color:var(--bad);" title="Hapus isian ini" onclick="removeDiklatLain(${i},${j})">Hapus</button>
        </div>
        <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
          <input type="file" accept="application/pdf,.pdf" style="display:none;" onchange="handleDiklatLainPdf(this,${i},${j})">
          <button type="button" class="btn btn-ghost !py-0.5 !px-2 text-[11px]" style="border-radius:999px;" onclick="this.previousElementSibling.click()">Upload PDF</button>
          ${it.pdf ? `<a href="#" class="text-[11px]" style="color:var(--orange-500); text-decoration:underline;" title="${esc(it.pdf.name)}" onclick="viewDiklatLainPdf(event,${i},${j})">Lihat PDF</a>` : ''}
        </div>
      </div>`).join('')}
    </div>
    <div><button type="button" class="btn btn-ghost !py-0.5 !px-2 text-[11px]" onclick="addDiklatLain(${i})">+ Tambah Diklat Lain</button></div>
  </div>`;
}
function rerenderDiklatLain(i){
  const el = document.getElementById(`dkl_lain_${diklatPesertaContext}_${i}`);
  if(el && diklatPesertaRows[i]) el.innerHTML = pesertaLainHtml(diklatPesertaRows[i], i);
}
function addDiklatLain(i){
  const r = diklatPesertaRows[i]; if(!r) return;
  getDiklatLainList(r).push({teks:'', pdf:null});
  rerenderDiklatLain(i);
}
function removeDiklatLain(i, j){
  const r = diklatPesertaRows[i]; if(!r) return;
  getDiklatLainList(r).splice(j,1);
  rerenderDiklatLain(i);
}
function handleDiklatLainPdf(input, i, j){
  const file = input.files && input.files[0];
  const r = diklatPesertaRows[i];
  if(!file || !r) return;
  if(file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')){
    toast('File harus berformat PDF'); input.value=''; return;
  }
  if(file.size > 10*1024*1024){
    toast('Ukuran file maksimal 10MB'); input.value=''; return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    const list = getDiklatLainList(r);
    if(!list[j]) return;
    list[j].pdf = { name:file.name, size:file.size, data:reader.result };
    rerenderDiklatLain(i);
    toast('PDF terpilih: '+file.name+' (jangan lupa klik Simpan Data Peserta)');
  };
  reader.onerror = () => toast('Gagal membaca file PDF');
  reader.readAsDataURL(file);
}
function viewDiklatLainPdf(ev, i, j){
  ev.preventDefault();
  const r = diklatPesertaRows[i];
  const it = r && getDiklatLainList(r)[j];
  if(it && it.pdf) openPdfDataUrl(it.pdf.data);
}
function viewDiklatLainPdfSaved(ev, rowId, pKey, idx, j){
  ev.preventDefault(); ev.stopPropagation();
  const row = (DB[pKey]||[]).find(x=>x.id===rowId);
  const r = row && row.peserta_matrix && row.peserta_matrix[idx];
  const it = r && getDiklatLainList(r)[j];
  if(it && it.pdf) openPdfDataUrl(it.pdf.data);
}
function diklatLainTeksJoined(r){
  if((pesertaKey||'diklat')==='diklat' && Array.isArray(r.diklat_lain_list))
    return r.diklat_lain_list.map(x=>(x.teks||'').trim()).filter(Boolean).join('; ');
  return r.diklat_lain_teks||'';
}
function handleDiklatPesertaPdfChange(input, i){
  const infoEl = document.getElementById(`dkl_peserta_pdf_info_${diklatPesertaContext}_${i}`);
  const file = input.files && input.files[0];
  if(!diklatPesertaRows[i]) return;
  if(!file){ diklatPesertaRows[i].diklat_lain_pdf = null; if(infoEl) infoEl.textContent=''; return; }
  if(file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')){
    toast('File harus berformat PDF');
    input.value = '';
    return;
  }
  if(file.size > 10*1024*1024){
    toast('Ukuran file maksimal 10MB');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    diklatPesertaRows[i].diklat_lain_pdf = { name: file.name, size: file.size, data: reader.result };
    if(infoEl) infoEl.textContent = `Terpilih: ${file.name} (${(file.size/1024).toFixed(0)} KB)`;
  };
  reader.onerror = () => { toast('Gagal membaca file PDF'); };
  reader.readAsDataURL(file);
}

let diklatMainSelectedIds = {};
function onDiklatMainSelectChange(id){
  diklatPesertaContext = 'main';
  diklatMainSelectedIds[pesertaKey] = id;
  const row = (DB[pesertaKey]||[]).find(r=>r.id===id);
  diklatPesertaRows = row && Array.isArray(row.peserta_matrix) ? JSON.parse(JSON.stringify(row.peserta_matrix)) : [];
  if(pesertaKey==='uji_periodik') loadUjiCols(row);
  refreshDiklatPesertaEditor();
}
function saveDiklatMainPeserta(){ if(guardRO('diklat')) return;
  if(pesertaKey==='diklat'){ saveDiklatRekap(); toast('Rekap diklat anggota disimpan'); return; }
  const row = (DB[pesertaKey]||[]).find(r=>r.id===diklatMainSelectedIds[pesertaKey]);
  if(!row){ toast('Pilih data '+PESERTA_MODULES[pesertaKey].label+' terlebih dahulu'); return; }
  row.peserta_matrix = diklatPesertaRows.map(r=>Object.assign({}, r));
  if(pesertaKey==='uji_periodik'){
    row.kolom_peserta = JSON.parse(JSON.stringify(getPesertaCols('uji_periodik')));
    ujiColsLocked = true;
  }
  persist();
  toast('Data peserta '+PESERTA_MODULES[pesertaKey].label.toLowerCase()+' disimpan');
  if(pesertaKey==='uji_periodik') refreshDiklatPesertaEditor();
}

/* ============ Rekap Peserta Diklat (seperti Excel) + Import CSV ============
   Tabel awalnya kosong. Nama peserta berasal dari file CSV/Excel yang diimpor,
   disimpan di DB.diklat_rekap = {rows:[{nama, jabatan, nipgol, <ceklis>, diklat_lain_list}]}. */
let diklatRekapQuery = '';
let _rekapRowsRef = null; // baris editor Rekap Diklat Anggota (dipisah dari editor Daftar Peserta Diklat)
function diklatNipGol(r){ return r.nipgol || ''; }
function diklatRekapSaved(){
  const d = DB.diklat_rekap;
  return (d && !Array.isArray(d) && Array.isArray(d.rows)) ? d.rows : [];
}
function diklatRekapRows(){
  pesertaKey = 'diklat';
  return diklatRekapSaved().map(x=>Object.assign(newDiklatPesertaRow(), JSON.parse(JSON.stringify(x))));
}
function ensureDiklatRekapRows(){
  if(pesertaKey!=='diklat' || diklatPesertaContext!=='main'){
    pesertaKey = 'diklat'; diklatPesertaContext = 'main';
    diklatPesertaRows = diklatRekapRows();
  }
}
function serializeRekapRows(src){
  return src.map(r=>{
    const o = {nama:r.nama||'', jabatan:r.jabatan||'', nipgol:r.nipgol||''};
    if(r.anggota_id) o.anggota_id = r.anggota_id;
    DIKLAT_PESERTA_COLS.forEach(c=>{
      o[c.key] = !!r[c.key];
      if(r[c.key] && r[c.key+'_pdf']) o[c.key+'_pdf'] = r[c.key+'_pdf'];
    });
    const lain = (Array.isArray(r.diklat_lain_list) ? r.diklat_lain_list : []).filter(x=>(x.teks && String(x.teks).trim()) || x.pdf);
    if(lain.length) o.diklat_lain_list = lain;
    return o;
  });
}
function saveDiklatRekap(){ if(guardRO('diklat')) return;
  ensureDiklatRekapRows();
  DB.diklat_rekap = {rows: serializeRekapRows(diklatPesertaRows)};
  persist();
}
function diklatRekapRowText(r){ return normSearch([r.nama, r.jabatan, r.nipgol].join(' ')); }
function diklatRekapEditorHtml(){
  const cols = DIKLAT_PESERTA_COLS;
  const q = normSearch(diklatRekapQuery);
  return `
    <div class="tablewrap" style="max-height:520px; overflow-y:auto;">
      <table>
        <thead>
          <tr>
            <th rowspan="2" class="text-center">No</th>
            <th rowspan="2">Nama</th>
            <th rowspan="2">Jabatan</th>
            <th rowspan="2">NIP/Golongan</th>
            <th colspan="${cols.length}" class="text-center">Kualifikasi (Diklat Internal/Tahun)</th>
            <th rowspan="2">Diklat Lain</th>
            <th rowspan="2" class="text-center">Aksi</th>
          </tr>
          <tr>${cols.map(c=>`<th class="text-center">${esc(c.label)}</th>`).join('')}</tr>
        </thead>
        <tbody>
          ${diklatPesertaRows.length ? diklatPesertaRows.map((r,i)=>{
            const hide = q && !diklatRekapRowText(r).includes(q);
            return `<tr data-dkl-row="${i}" ${hide?'style="display:none;"':''}>
              <td class="text-center">${i+1}</td>
              <td class="font-medium" style="min-width:180px;">${esc(r.nama)}</td>
              <td style="min-width:200px;">${esc(r.jabatan)||'-'}</td>
              <td style="min-width:210px;">${esc(diklatNipGol(r))||'-'}</td>
              ${cols.map(c=>`<td class="text-center" style="min-width:5.5rem;">${pesertaCellHtml(r,i,c)}</td>`).join('')}
              <td id="dkl_lain_main_${i}" style="min-width:250px;">${pesertaLainHtml(r,i)}</td>
              <td class="text-center"><button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="hapusRekapPeserta(${i})">Hapus</button></td>
            </tr>`;}).join('') : `<tr><td colspan="${cols.length+6}" class="text-center py-6" style="color:var(--muted);">Belum ada data peserta. Klik "Import Excel" untuk memasukkan data dari file Excel.</td></tr>`}
        </tbody>
      </table>
    </div>`;
}
function hapusRekapPeserta(i){ if(guardRO('diklat')) return;
  useRekapDiklat();
  const r = diklatPesertaRows[i]; if(!r) return;
  if(!confirm('Hapus "' + (r.nama||'peserta ini') + '" dari Rekap Diklat Anggota?')) return;
  diklatPesertaRows.splice(i,1);
  DB.diklat_rekap = {rows: serializeRekapRows(diklatPesertaRows)};
  persist();
  const wrap = document.getElementById('diklat_peserta_wrap_main');
  if(wrap) wrap.innerHTML = diklatRekapEditorHtml();
  const cnt = document.getElementById('rekap_count'); if(cnt) cnt.textContent = diklatPesertaRows.length + ' peserta';
  toast('Peserta dihapus dari Rekap Diklat Anggota');
}
function filterDiklatRekap(v){
  diklatRekapQuery = v;
  const q = normSearch(v);
  document.querySelectorAll('#diklat_peserta_wrap_main tr[data-dkl-row]').forEach(tr=>{
    const r = diklatPesertaRows[+tr.dataset.dklRow];
    tr.style.display = (!q || (r && diklatRekapRowText(r).includes(q))) ? '' : 'none';
  });
}
function diklatRekapPanelHtml(){
  pesertaKey = 'diklat'; diklatPesertaContext = 'main';
  diklatPesertaRows = diklatRekapRows();
  _rekapRowsRef = diklatPesertaRows;
  return `
    <div class="panel rounded-xl p-4 mt-4" id="rekap_panel_diklat">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-display font-semibold text-sm">Rekap Diklat Anggota</span>
          <input type="search" placeholder="Cari nama / jabatan..." style="min-width:200px;" value="${esc(diklatRekapQuery)}" oninput="filterDiklatRekap(this.value)">
          <span class="text-xs" id="rekap_count" style="color:var(--muted);">${diklatPesertaRows.length} peserta</span>
        </div>
        <div class="flex items-center gap-2">
          ${isSuper() ? `<input type="file" id="diklat_import_file" accept=".xlsx,.xls,.csv" style="display:none;" onchange="handleDiklatImportFile(this)">
          <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="document.getElementById('diklat_import_file').click()">Import Excel</button>` : ''}
          <div class="dd">
            <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="toggleUnduhMenu(event, this)">Unduh <span aria-hidden="true">&#9662;</span></button>
            <div class="dd-menu">
              <button type="button" class="dd-item" onclick="pesertaKey='diklat'; exportPeserta('pdf')">PDF</button>
              <button type="button" class="dd-item" onclick="pesertaKey='diklat'; exportPeserta('excel')">Excel</button>
            </div>
          </div>
          <button class="btn btn-primary !py-1.5 !px-3 text-xs" onclick="pesertaKey='diklat'; saveDiklatMainPeserta()">Simpan Rekap</button>
        </div>
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">${isSuper() ? 'Daftar peserta muncul setelah Anda mengimpor file Excel (.xlsx) (kolom: NO, NAMA, JABATAN, NIP/GOLONGAN, DIKDAS … INSTRUKTUR, DIKLAT LAIN; tanda ceklis berupa √ / V / ✓).' : 'Daftar peserta rekap diklat diimpor oleh Admin Utama.'}</p>
      <div id="diklat_peserta_wrap_main">${diklatRekapEditorHtml()}</div>
    </div>`;
}

/* ---- Daftar Peserta Diklat: editor peserta langsung di tabel, per Nama Diklat ----
   Editor memakai fungsi editor peserta yang sama dengan form Diklat (konteks 'daftar'),
   tetapi barisnya terpisah dari Rekap Diklat Anggota. Konteks aktif diganti otomatis saat
   pengguna berinteraksi di salah satu panel (lihat listener di bawah). Perubahan tersimpan otomatis. */
let daftarRows = [];
let _daftarRowId = '', _daftarDirty = false, _daftarTimer = null;
function daftarDiklatSelId(){
  const rows = DB.diklat || [];
  if(!rows.length) return '';
  if(!diklatMainSelectedIds.diklat || !rows.some(r=>r.id===diklatMainSelectedIds.diklat)) diklatMainSelectedIds.diklat = rows[rows.length-1].id;
  return diklatMainSelectedIds.diklat;
}
function useDaftarDiklat(){ pesertaKey = 'diklat'; diklatPesertaContext = 'daftar'; diklatPesertaRows = daftarRows; }
function useRekapDiklat(){ if(!_rekapRowsRef) return; pesertaKey = 'diklat'; diklatPesertaContext = 'main'; diklatPesertaRows = _rekapRowsRef; }
function daftarLoadRows(){
  const row = (DB.diklat||[]).find(r=>r.id===daftarDiklatSelId());
  pesertaKey = 'diklat';
  daftarRows = row && Array.isArray(row.peserta_matrix) ? JSON.parse(JSON.stringify(row.peserta_matrix)) : [];
  if(!daftarRows.length) daftarRows.push(newDiklatPesertaRow()); // baris kosong siap diisi
  _daftarRowId = row ? row.id : ''; _daftarDirty = false;
}
function syncDaftarToDb(){
  clearTimeout(_daftarTimer);
  if(!_daftarDirty) return;
  const row = (DB.diklat||[]).find(r=>r.id===_daftarRowId);
  if(!row) return;
  const rows = daftarRows.filter(r=>r.anggota_id || (r.nama && String(r.nama).trim()));
  row.peserta_matrix = rows.map(r=>Object.assign({}, r));
  row.jumlah_peserta = rows.length;
  row.diperbarui_oleh = pelaku(); row.diperbarui_waktu = nowStr();
  _daftarDirty = false;
  persist();
}
function setDaftarDiklat(id){
  syncDaftarToDb();
  diklatMainSelectedIds.diklat = id;
  daftarLoadRows();
  const el = document.getElementById('daftar_diklat_body');
  if(el) el.innerHTML = daftarDiklatBodyHtml();
  useDaftarDiklat();
  if(typeof enhanceTableScroll==='function') enhanceTableScroll();
}
function daftarDiklatBodyHtml(){
  const row = (DB.diklat||[]).find(r=>r.id===daftarDiklatSelId());
  if(!row) return '';
  useDaftarDiklat();
  const tgl = row.tanggal_mulai ? (tglDMY(row.tanggal_mulai) + (row.tanggal_selesai && row.tanggal_selesai!==row.tanggal_mulai ? ' s.d. '+tglDMY(row.tanggal_selesai) : '')) : '-';
  return `
    <p class="text-xs mb-3" style="color:var(--muted);">Tanggal: <strong style="color:var(--text);">${esc(tgl)}</strong> · Lokasi: <strong style="color:var(--text);">${esc(row.lokasi)||'-'}</strong> · Status: ${statusBadge(statusFieldValue('diklat', (DIKLAT_STATUS_FIELD()||{}), row))||'-'}</p>
    <p class="text-[11px] mb-2" style="color:var(--muted);">Pilih nama anggota, lalu centang kualifikasi. Klik <strong>Simpan Data</strong> agar peserta otomatis masuk / diperbarui di Rekap Diklat Anggota.</p>
    <div id="diklat_peserta_wrap_daftar">${diklatPesertaEditorHtml()}</div>`;
}
function DIKLAT_STATUS_FIELD(){ const m = TABLE_MODULES.find(x=>x.key==='diklat'); return m ? m.fields.find(f=>f.isStatus) : null; }
function daftarDiklatPanelHtml(){
  const rows = DB.diklat || [];
  if(!rows.length){
    return `<div class="panel rounded-xl p-4 mt-4"><p class="text-sm font-display font-semibold mb-1">Daftar Peserta Diklat</p><p class="text-sm" style="color:var(--muted);">Belum ada data Diklat. Tambahkan data Diklat terlebih dahulu.</p></div>`;
  }
  const sel = daftarDiklatSelId();
  daftarLoadRows();
  return `
    <div class="panel rounded-xl p-4 mt-4" id="daftar_panel_diklat">
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <span class="font-display font-semibold text-sm">Daftar Peserta Diklat</span>
        <select id="daftar_diklat_select" style="width:auto; min-width:220px;" aria-label="Pilih Nama Diklat" onchange="setDaftarDiklat(this.value)">
          ${rows.map(r=>`<option value="${esc(r.id)}" ${r.id===sel?'selected':''}>${esc(r.nama_diklat||r.id)}</option>`).join('')}
        </select>
        <button type="button" class="btn btn-primary !py-1.5 !px-3 text-xs ml-auto" onclick="simpanDaftarDiklat()">Simpan Data</button>
      </div>
      <div id="daftar_diklat_body">${daftarDiklatBodyHtml()}</div>
    </div>`;
}
/* ---- Simpan Data: simpan Daftar Peserta Diklat + gabungkan otomatis ke Rekap Diklat Anggota ----
   - nama sudah ada di rekap (dicocokkan lewat nama) -> kualifikasi yang dicentang ditambahkan / diperbarui
   - nama belum ada -> baris baru dibuat otomatis di rekap
   Nama diklat TIDAK dimasukkan ke rekap (hanya data peserta & kualifikasi). */
function golonganAnggota(a){
  const m = /Golongan:\s*([^;]+)/i.exec((a && a.keterangan) || '');
  return m ? m[1].trim() : '';
}
function gabungDaftarKeRekap(diklatRow, list){
  if(!_rekapRowsRef) _rekapRowsRef = diklatRekapRows();
  const rekap = _rekapRowsRef;
  const namaDiklat = String(diklatRow.nama_diklat || diklatRow.id || '').trim();
  let diperbarui = 0, ditambah = 0;
  list.forEach(d=>{
    const ang = (DB.anggota||[]).find(a=>a.id===d.anggota_id);
    const nama = String((ang ? ang.nama : d.nama) || '').trim();
    if(!nama) return;
    const kunci = normSearch(nama);
    let r = rekap.find(x=>normSearch(x.nama)===kunci);
    if(r){ diperbarui++; }
    else {
      pesertaKey = 'diklat';
      r = newDiklatPesertaRow();
      r.nama = nama;
      rekap.push(r); ditambah++;
    }
    if(ang && !r.anggota_id) r.anggota_id = ang.id;
    if(!String(r.jabatan||'').trim()) r.jabatan = (ang && ang.jabatan) || d.jabatan || '';
    if(!String(r.nipgol||'').trim() && ang){
      const gol = golonganAnggota(ang);
      r.nipgol = (ang.nip||'') + (gol ? ' / '+gol.toUpperCase() : '');
    }
    // kualifikasi: yang dicentang di daftar peserta ditambahkan ke rekap (tidak mencabut yang sudah ada dari diklat lain)
    DIKLAT_PESERTA_COLS.forEach(c=>{
      if(d[c.key]){
        r[c.key] = true;
        if(d[c.key+'_pdf']) r[c.key+'_pdf'] = d[c.key+'_pdf'];
      }
    });
    // Diklat Lain: gabungkan isian peserta + nama diklat ini, tanpa duplikat
    const lain = (Array.isArray(r.diklat_lain_list) ? r.diklat_lain_list : []).filter(x=>(x.teks && String(x.teks).trim()) || x.pdf);
    const ada = t => lain.some(x=>String(x.teks||'').trim().toLowerCase()===String(t||'').trim().toLowerCase());
    const dari = Array.isArray(d.diklat_lain_list) ? d.diklat_lain_list.filter(x=>(x.teks && String(x.teks).trim()) || x.pdf) : [];
    dari.forEach(x=>{
      if(x.teks && String(x.teks).trim()){
        const ex = lain.find(y=>String(y.teks||'').trim().toLowerCase()===String(x.teks).trim().toLowerCase());
        if(ex){ if(!ex.pdf && x.pdf) ex.pdf = x.pdf; } else lain.push({teks:x.teks, pdf:x.pdf||null});
      } else if(x.pdf && !lain.some(y=>y.pdf && y.pdf.name===x.pdf.name)) lain.push({teks:'', pdf:x.pdf});
    });
    r.diklat_lain_list = lain.length ? lain : [{teks:'', pdf:null}];
  });
  DB.diklat_rekap = {rows: serializeRekapRows(rekap)};
  return {diperbarui, ditambah};
}
function simpanDaftarDiklat(){ if(guardRO('diklat')) return;
  const row = (DB.diklat||[]).find(r=>r.id===_daftarRowId);
  if(!row){ toast('Pilih data Diklat terlebih dahulu'); return; }
  const list = daftarRows.filter(r=>r.anggota_id || (r.nama && String(r.nama).trim()));
  if(!list.length){ toast('Belum ada peserta yang dipilih'); return; }
  _daftarDirty = true; syncDaftarToDb();               // simpan daftar peserta ke data Diklat
  const hasil = gabungDaftarKeRekap(row, list);         // gabungkan ke Rekap Diklat Anggota
  persist();
  // segarkan tabel Rekap di halaman tanpa memuat ulang
  const wrap = document.getElementById('diklat_peserta_wrap_main');
  if(wrap && _rekapRowsRef){
    useRekapDiklat();
    wrap.innerHTML = diklatRekapEditorHtml();
    const cnt = document.getElementById('rekap_count'); if(cnt) cnt.textContent = _rekapRowsRef.length + ' peserta';
    useDaftarDiklat();
  }
  toast('Data disimpan. Rekap Diklat Anggota: ' + hasil.diperbarui + ' diperbarui, ' + hasil.ditambah + ' ditambahkan');
}
/* Aktifkan konteks editor yang benar SEBELUM handler inline berjalan (fase capture),
   lalu simpan otomatis perubahan Daftar Peserta Diklat setelah interaksi (fase bubble). */
(function(){
  const activate = ev => {
    const t = ev.target; if(!t || !t.closest) return;
    if(t.closest('#daftar_panel_diklat')){
      useDaftarDiklat();
      // tandai perubahan di fase capture: elemen bisa dibuang saat editor dirender ulang oleh handler-nya
      if(['input','change','click'].includes(ev.type) && t.id!=='daftar_diklat_select' && !(ev.type==='click' && !t.closest('button'))){
        _daftarDirty = true;
        clearTimeout(_daftarTimer);
        _daftarTimer = setTimeout(syncDaftarToDb, (t.type==='file') ? 2500 : 600);
      }
    }
    else if(t.closest('#rekap_panel_diklat')) useRekapDiklat();
  };
  ['pointerdown','focusin','keydown','input','change','click'].forEach(n=>document.addEventListener(n, activate, true));
})();

/* ---- Import CSV ---- */
function decodeCsvBuffer(buf){
  try{ return new TextDecoder('utf-8', {fatal:true}).decode(buf); }
  catch(e){ return new TextDecoder('windows-1252').decode(buf); }
}
function parseCsvText(text){
  text = text.replace(/^﻿/, '');
  const sample = text.split(/\r?\n/).slice(0, 15).join('\n');
  const delim = [',',';','\t'].map(d=>({d, n:sample.split(d).length})).sort((a,b)=>b.n-a.n)[0].d;
  const rows = []; let row = [], cur = '', q = false;
  for(let i=0;i<text.length;i++){
    const ch = text[i];
    if(q){
      if(ch==='"'){ if(text[i+1]==='"'){ cur += '"'; i++; } else q = false; }
      else cur += ch;
    } else if(ch==='"'){ q = true; }
    else if(ch===delim){ row.push(cur); cur = ''; }
    else if(ch==='\n' || ch==='\r'){
      if(ch==='\r' && text[i+1]==='\n') i++;
      row.push(cur); cur = ''; rows.push(row); row = [];
    } else cur += ch;
  }
  if(cur!=='' || row.length){ row.push(cur); rows.push(row); }
  return rows;
}
function csvTruthy(v){
  const t = String(v==null?'':v).trim().toLowerCase();
  return !(t==='' || ['-','0','false','tidak','no','n'].includes(t));
}
function normLabel(s){ return String(s||'').toUpperCase().replace(/[^A-Z0-9]/g,''); }
function handleDiklatImportFile(input){
  const file = input.files && input.files[0];
  input.value = '';
  if(!file) return;
  if(!isSuper()){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const isCsv = /\.csv$/i.test(file.name);
  if(!isCsv && typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const reader = new FileReader();
  reader.onload = () => {
    try{
      let rows;
      if(isCsv) rows = parseCsvText(decodeCsvBuffer(reader.result));
      else {
        const wb = XLSX.read(reader.result, {type:'array'});
        // pakai sheet pertama yang memiliki kolom NAMA
        let ws = null;
        for(const n of wb.SheetNames){
          const r = XLSX.utils.sheet_to_json(wb.Sheets[n], {header:1, defval:'', raw:false});
          if(r.some(x=>x.some(c=>normLabel(c)==='NAMA'))){ ws = r; break; }
        }
        rows = ws || XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], {header:1, defval:'', raw:false});
      }
      prepareDiklatImport(rows);
    }catch(e){ console.error(e); toast('Gagal membaca file Excel'); }
  };
  reader.onerror = () => toast('Gagal membaca file');
  reader.readAsArrayBuffer(file);
}
let _diklatImportPending = null;
function prepareDiklatImport(rows){
  const hi = rows.findIndex(r=>r.some(c=>normLabel(c)==='NAMA') && r.some(c=>/^(NIP|JABATAN)/.test(normLabel(c))));
  if(hi<0){ toast('Header kolom NAMA tidak ditemukan di file Excel'); return; }
  const keyByLabel = {}; DIKLAT_PESERTA_COLS.forEach(c=>keyByLabel[normLabel(c.label)] = c.key);
  const known = r => (r||[]).some(c=>keyByLabel[normLabel(c)]);
  const sub = (rows[hi+1] && known(rows[hi+1])) ? rows[hi+1] : null;
  const width = Math.max(rows[hi].length, sub ? sub.length : 0);
  const colKey = {}; let iNama=-1, iJab=-1, iNip=-1, iLain=-1;
  for(let j=0;j<width;j++){
    [normLabel(rows[hi][j]), normLabel(sub ? sub[j] : '')].forEach(l=>{
      if(!l) return;
      if(l==='NAMA' && iNama<0) iNama = j;
      else if(l==='JABATAN' && iJab<0) iJab = j;
      else if(l.startsWith('NIP') && iNip<0) iNip = j;
      else if(l.startsWith('DIKLATLAIN')) iLain = j;
      else if(keyByLabel[l]) colKey[j] = keyByLabel[l];
    });
  }
  if(iNama<0){ toast('Kolom NAMA tidak ditemukan'); return; }
  if(!Object.keys(colKey).length){ toast('Kolom kualifikasi (DIKDAS, MFR, ...) tidak ditemukan'); return; }
  const out = [];
  for(let i=hi+1+(sub?1:0); i<rows.length; i++){
    const r = rows[i];
    const nama = String(r[iNama]||'').trim();
    if(!nama || /^\d*$/.test(nama)) continue;
    const o = {nama, jabatan: iJab>=0 ? String(r[iJab]||'').trim() : '', nipgol: iNip>=0 ? String(r[iNip]||'').replace(/\s*\/\s*/,' / ').trim() : ''};
    Object.keys(colKey).forEach(j=>{ o[colKey[j]] = csvTruthy(r[j]); });
    o.lain = iLain>=0 ? String(r[iLain]||'').split(/[,;\n]+/).map(x=>x.trim()).filter(Boolean) : [];
    out.push(o);
  }
  if(!out.length){ toast('Tidak ada data peserta di file Excel'); return; }
  _diklatImportPending = out;
  const sudahAda = diklatRekapSaved().length;
  openModal(`
    <div class="p-5">
      <h3 class="font-display font-semibold text-base mb-2">Import Excel Rekap Diklat Anggota</h3>
      <p class="text-sm mb-2">Ditemukan <b>${out.length}</b> peserta di file Excel.</p>
      <p class="text-xs mb-4" style="color:var(--muted);">${sudahAda ? `Daftar peserta saat ini (${sudahAda} orang) akan diganti dengan isi Excel. PDF yang sudah diupload tetap dipertahankan untuk nama yang sama.` : 'Daftar peserta akan dibuat dari isi Excel.'}</p>
      <div class="flex justify-end gap-2">
        <button class="btn btn-ghost" onclick="_diklatImportPending=null; closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="applyDiklatImport()">Impor ${out.length} Peserta</button>
      </div>
    </div>`);
}
function applyDiklatImport(){ if(guardRO('diklat')) return;
  if(!isSuper()){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const list = _diklatImportPending; if(!list) return;
  const oldMap = {};
  diklatRekapSaved().forEach(x=>{ oldMap[normSearch(x.nama)] = x; });
  pesertaKey = 'diklat'; diklatPesertaContext = 'main';
  diklatPesertaRows = _rekapRowsRef = list.map(o=>{
    const r = Object.assign(newDiklatPesertaRow(), {nama:o.nama, jabatan:o.jabatan, nipgol:o.nipgol});
    const old = oldMap[normSearch(o.nama)] || {};
    DIKLAT_PESERTA_COLS.forEach(c=>{
      r[c.key] = !!o[c.key];
      if(r[c.key] && old[c.key+'_pdf']) r[c.key+'_pdf'] = old[c.key+'_pdf'];
    });
    const oldLain = Array.isArray(old.diklat_lain_list) ? old.diklat_lain_list : [];
    r.diklat_lain_list = o.lain.length
      ? o.lain.map(t=>{ const m = oldLain.find(x=>x.pdf && String(x.teks||'').trim().toLowerCase()===t.toLowerCase()); return {teks:t, pdf:m?m.pdf:null}; })
      : [{teks:'', pdf:null}];
    return r;
  });
  saveDiklatRekap();
  _diklatImportPending = null;
  closeModal();
  diklatRekapQuery = '';
  render(currentView);
  toast('Berhasil mengimpor '+list.length+' peserta diklat');
}

/* ---- Unduh data peserta (PDF / Excel) ---- */
function toggleUnduhMenu(ev, btn){
  ev.stopPropagation();
  const dd = btn.closest('.dd');
  const wasOpen = dd.classList.contains('open');
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  if(!wasOpen) dd.classList.add('open');
}
document.addEventListener('click', ()=>document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open')));

function exportPeserta(fmt){
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  const M = PESERTA_MODULES[pesertaKey];
  const sel = (DB[pesertaKey]||[]).find(r=>r.id===diklatMainSelectedIds[pesertaKey]);
  const rekap = (pesertaKey==='diklat');
  const namaKegiatan = rekap ? 'Kantor Pencarian dan Pertolongan Jakarta' : (sel ? (sel[M.nameKey]||'') : '');
  if(rekap) ensureDiklatRekapRows();
  if(!diklatPesertaRows.length){ toast('Belum ada data peserta untuk diunduh'); return; }
  const head = ['No','Nama','Jabatan', ...(rekap?['NIP/Golongan']:[]), ...getPesertaCols(pesertaKey).map(c=>c.label), M.lainLabel];
  const isX = fmt==='excel';
  const body = diklatPesertaRows.map((r,i)=>[
    i+1, r.nama||'', r.jabatan||'', ...(rekap?[diklatNipGol(r)]:[]),
    ...getPesertaCols(pesertaKey).map(c=> r[c.key] ? (isX?'✓':'V') : '-'),
    diklatLainTeksJoined(r)
  ]);
  const judul = rekap ? 'Rekap Diklat Anggota' : 'Data Peserta '+M.label;
  const fname = (rekap ? judul : 'Data Peserta '+M.label+' - '+namaKegiatan).trim().replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_');
  try{
    if(isX){
      if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
      const aoa = [[judul], [namaKegiatan], [], head, ...body];
      const ws = XLSX.utils.aoa_to_sheet(aoa);
      ws['!cols'] = head.map((h,i)=>({wch: i===0?5 : i===1?26 : i===2?24 : (rekap&&i===3)?34 : i===head.length-1?30 : 10}));
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Peserta');
      XLSX.writeFile(wb, fname+'.xlsx');
    } else {
      if(!(window.jspdf && window.jspdf.jsPDF)){ toast('Library PDF belum termuat, periksa koneksi internet'); return; }
      const doc = new window.jspdf.jsPDF({orientation:'landscape', unit:'pt', format:'a4'});
      doc.setFontSize(13); doc.text(judul, 40, 40);
      doc.setFontSize(10); doc.text(namaKegiatan, 40, 56);
      doc.autoTable({
        head:[head], body, startY:68,
        styles:{fontSize:7, cellPadding:3, valign:'middle'},
        headStyles:{fillColor:[19,49,92], halign:'center'},
        columnStyles:Object.assign({0:{halign:'center', cellWidth:24}},
          ...getPesertaCols(pesertaKey).map((c,i)=>({[i+(rekap?4:3)]:{halign:'center'}})))
      });
      doc.save(fname+'.pdf');
    }
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}
/* ---- Card kategori Uji Periodik (di antara tabel data & Data Peserta) ---- */
const UJI_KATEGORI = [
  {label:'Teknis SAR',              desc:'Kemampuan teknis pencarian & pertolongan',
   icon:'<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6 2.6-2.2z"/>'},
  {label:'Pengetahuan Umum',        desc:'Wawasan dan pengetahuan dasar SAR',
   icon:'<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5z"/><path d="M8 7h7M8 11h7"/>'},
  {label:'Kebugaran Jasmani',       desc:'Kondisi fisik dan daya tahan tubuh',
   icon:'<path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 0 0-7.1 7.1L12 21l8.8-8.3a5 5 0 0 0 0-7.1z"/>'},
  {label:'Renang & Water Treading', desc:'Kemampuan berenang dan bertahan di air',
   icon:'<path d="M2 16c2 0 2-1.5 5-1.5S9 16 12 16s3-1.5 5-1.5 3 1.5 5 1.5"/><path d="M2 20c2 0 2-1.5 5-1.5S9 20 12 20s3-1.5 5-1.5 3 1.5 5 1.5"/><circle cx="14" cy="6" r="2"/><path d="M6 12l4-3 3 2 3-1"/>'},
];
function ujiKategoriCardsHtml(){
  return `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
    ${UJI_KATEGORI.map(k=>`
      <div class="panel rounded-xl p-4 flex items-start gap-3" style="cursor:pointer;" role="button" tabindex="0" onclick="openUjiKategori(${UJI_KATEGORI.indexOf(k)})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openUjiKategori(${UJI_KATEGORI.indexOf(k)})}">
        <div style="flex:none; width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:var(--orange-100); color:var(--orange-500);">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${k.icon}</svg>
        </div>
        <div class="min-w-0">
          <div class="font-display font-semibold text-sm" style="color:var(--navy-800);">${esc(k.label)}</div>
          <div class="text-xs mt-0.5" style="color:var(--muted);">${esc(k.desc)}</div>
        </div>
      </div>`).join('')}
  </div>`;
}
/* ---- Form input per kategori Uji Periodik (popup, gaya detail kegiatan) ---- */
const UJK_NILAI = [{key:'mfr',label:'MFR'},{key:'hart',label:'HART'},{key:'jr',label:'JR'}]; // kolom bawaan
// Kategori dengan kolom tetap sendiri (tanpa MFR/HART/JR dan tanpa tambah/ubah/hapus kolom)
const UJK_FIXED = {'Pengetahuan Umum':[{key:'nilai',label:'Nilai'}]};
// Kolom bawaan khusus per kategori (masih bisa diubah nama / dihapus / ditambah)
const UJK_DEFAULTS = {'Renang & Water Treading':[{key:'gb100',label:'Gaya Bebas 100M'},{key:'gd100',label:'Gaya Dada 100 M'},{key:'wt',label:'Water Treading'}]};
function ujkBaseDefaults(kat){ return UJK_FIXED[kat] || UJK_DEFAULTS[kat] || UJK_NILAI; }
// Kolom tambahan (opsional) disimpan per kategori: DB.uji_kategori_cols[kategori] = [{key,label}]
function ujkExtraCols(kat){ if(UJK_FIXED[kat]) return []; return ((DB.uji_kategori_cols||{})[kat]) || []; }
// Kolom bawaan (MFR/HART/JR) juga bisa diubah nama / dihapus: DB.uji_kategori_base[kat] = {hidden:[key], labels:{key:label}}
function ujkBase(kat){ return ((DB.uji_kategori_base||{})[kat]) || {hidden:[], labels:{}}; }
function ujkBaseCols(kat){
  const b = ujkBase(kat);
  if(UJK_FIXED[kat]) return UJK_FIXED[kat];
  return ujkBaseDefaults(kat).filter(c=>!(b.hidden||[]).includes(c.key)).map(c=>({key:c.key, label:(b.labels||{})[c.key]||c.label}));
}
function ujkBaseSet(kat, fn){
  if(!DB.uji_kategori_base) DB.uji_kategori_base = {};
  const b = DB.uji_kategori_base[kat] || {hidden:[], labels:{}};
  if(!b.hidden) b.hidden = [];
  if(!b.labels) b.labels = {};
  fn(b); DB.uji_kategori_base[kat] = b; persist();
}
function ujkCols(kat){ return ujkBaseCols(kat).concat(ujkExtraCols(kat)); }
// Kurang < 60 (termasuk < 50), Cukup >= 60, Baik >= 70, Sangat Baik >= 85
function ujkPredikat(avg){
  if(avg===null || isNaN(avg)) return '';
  if(avg>=85) return 'Sangat Baik';
  if(avg>=70) return 'Baik';
  if(avg>=60) return 'Cukup';
  return 'Kurang';
}
function ujkPredikatBadge(p){
  if(!p) return '<span style="color:var(--muted);">-</span>';
  const cls = p==='Kurang' ? 'b-bad' : (p==='Cukup' ? 'b-warn' : 'b-ok');
  return `<span class="badge ${cls}">${esc(p)}</span>`;
}
// Baris hasil Import Excel menyimpan satu nilai per kategori di 'nilai_import' (dipakai langsung sebagai rata-rata)
function ujkAvgRow(r, cols){
  const n = parseFloat(r.nilai_import);
  if(r.nilai_import!==undefined && r.nilai_import!=='' && !isNaN(n)) return n;
  return ujkRataRata(cols.map(c=>r[c.key]));
}
function ujkRataRata(vals){
  const nums = vals.filter(v=>v!=='' && v!==null && v!==undefined && !isNaN(parseFloat(v))).map(v=>parseFloat(v));
  return nums.length ? nums.reduce((x,y)=>x+y,0)/nums.length : null;
}
function fmtNilai(n){ return (n===null || n===undefined || n==='' || isNaN(n)) ? '-' : String(Math.round(parseFloat(n)*100)/100).replace('.',','); }
function ujkList(kat){ const u = ujiPesertaSelId(); return (DB.uji_kategori||[]).filter(x=>x.kategori===kat && (!u || x.uji_id===u)); }
function ujkRowsHtml(kat){
  const rows = ujkList(kat).slice().reverse();
  const cols = ujkCols(kat);
  if(!rows.length) return `<p class="text-sm" style="color:var(--muted);">Belum ada data ${esc(kat)}.</p>`;
  return `<div class="tablewrap"><table>
    <thead><tr><th>Nama Anggota</th><th>Jabatan</th><th class="text-center">JK</th><th class="text-center">Usia</th>${cols.map(c=>`<th>${esc(c.label)}</th>`).join('')}<th>Rata Rata</th><th>Predikat</th><th>Aksi</th></tr></thead>
    <tbody>${rows.map(r=>{
      const avg = ujkAvgRow(r, cols);
      return `<tr>
      <td class="font-medium">${esc(r.nama)||'-'}</td><td>${esc(r.jabatan)||'-'}</td><td class="text-center">${esc(r.jk)||'-'}</td><td class="text-center">${(r.usia===undefined||r.usia===null||r.usia==='')?'-':esc(r.usia)}</td>
      ${cols.map(c=>`<td>${fmtNilai(r[c.key])}</td>`).join('')}
      <td class="font-semibold">${fmtNilai(avg)}</td><td>${ujkPredikatBadge(ujkPredikat(avg))}</td>
      <td class="whitespace-nowrap"><button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="editUjiKategori('${esc(r.id)}')">Ubah</button> <button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="deleteUjiKategori('${esc(r.id)}')">Hapus</button></td>
    </tr>`;}).join('')}</tbody></table></div>`;
}
function ujkLbl(t){ return `<label class="block text-xs font-medium mb-1" style="color:var(--muted);">${t}</label>`; }
function ujkNilaiInputsHtml(kat){
  return ujkCols(kat).map(c=>`<div>
      <div class="flex items-center justify-between">${ujkLbl(esc(c.label))}
        ${!UJK_FIXED[kat] ? `<span class="whitespace-nowrap" style="margin-top:-4px;">
          <button type="button" class="btn btn-ghost !py-0 !px-1.5 text-xs" title="Ubah nama kolom" onclick="renameUjkKolom('${esc(c.key)}')">✎</button>
          <button type="button" class="btn btn-ghost !py-0 !px-1.5 text-xs" style="color:var(--bad);" title="Hapus kolom" onclick="deleteUjkKolom('${esc(c.key)}')">✕</button>
        </span>` : ''}
      </div>
      <input type="number" id="ujk_${esc(c.key)}" step="any" min="0" inputmode="decimal" placeholder="0,00" oninput="ujkHitung()">
    </div>`).join('');
}
// Render ulang blok kolom nilai tanpa menghilangkan angka yang sudah diketik
function ujkRefreshCols(){
  const kat = document.getElementById('ujk_kat').value;
  const old = {};
  document.querySelectorAll('#ujk_cols input[type=number]').forEach(i=>{ old[i.id]=i.value; });
  document.getElementById('ujk_cols').innerHTML = ujkNilaiInputsHtml(kat);
  Object.keys(old).forEach(id=>{ const el=document.getElementById(id); if(el) el.value = old[id]; });
  document.getElementById('ujk_list').innerHTML = ujkRowsHtml(kat);
  ujkRefreshPesertaPanel();
  ujkHitung();
}
let ujkEditId = null;
function openUjiKategori(idx){ if(guardRO('uji_periodik')) return;
  const k = UJI_KATEGORI[idx]; if(!k) return;
  if(!(DB.uji_periodik||[]).length){ toast('Tambahkan data Uji Periodik terlebih dahulu'); return; }
  ujiMigrasiNilaiLama();
  ujkEditId = null;
  const angOpts = '<option value="">- Pilih Anggota -</option>' + (DB.anggota||[]).map(a=>`<option value="${esc(a.id)}">${esc(a.nama)}</option>`).join('');
  openModal(`
    <div class="p-5 max-h-[88vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-1">
        <div>
          <h3 class="font-display font-semibold text-base">${esc(k.label)}</h3>
          <p class="text-xs" style="color:var(--muted);">Uji Periodik · ${esc(k.desc)}</p>
        </div>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <input type="hidden" id="ujk_kat" value="${esc(k.label)}">
      <div class="mt-4">${ujkLbl('Nama Uji Periodik')}<select id="ujk_uji_select" onchange="onUjiPesertaSelectChange(this.value)">${ujiOptionsHtml()}</select></div>
      <div class="grid sm:grid-cols-2 gap-3 mt-3">
        <div>${ujkLbl('Nama Anggota')}<select id="ujk_anggota" onchange="ujkPilihAnggota(this.value)">${angOpts}</select></div>
        <div>${ujkLbl('Jabatan')}<input type="text" id="ujk_jabatan" readonly placeholder="Terisi otomatis" style="opacity:.8;"></div>
        <div>${ujkLbl('Jenis Kelamin')}<select id="ujk_jk"><option value="">- Pilih -</option><option value="L">L</option><option value="P">P</option></select></div>
        <div>${ujkLbl('Usia')}<input type="number" id="ujk_usia" min="0" max="120" step="1" inputmode="numeric" placeholder="Tahun"></div>
      </div>
      <div id="ujk_cols" class="grid sm:grid-cols-2 gap-3 mt-3">${ujkNilaiInputsHtml(k.label)}</div>
      <div class="grid sm:grid-cols-2 gap-3 mt-3">
        <div>${ujkLbl('Rata Rata')}<input type="text" id="ujk_rata" readonly placeholder="Terisi otomatis" style="opacity:.8;"></div>
        <div>${ujkLbl('Predikat')}<div id="ujk_pred" class="pt-2">${ujkPredikatBadge('')}</div></div>
      </div>
      ${UJK_FIXED[k.label] ? '' : `<div class="flex items-end gap-2 mt-3 p-3 rounded-lg" style="background:var(--bg, #f3f4f8);">
        <div class="flex-1">${ujkLbl('Kolom / Inputan Baru')}<input type="text" id="ujk_kolom_baru" placeholder="Nama kolom, mis. Simulasi" onkeydown="if(event.key==='Enter'){event.preventDefault();addUjkKolom();}"></div>
        <button type="button" class="btn btn-ghost" onclick="addUjkKolom()">+ Tambah Kolom</button>
      </div>`}
      <div class="flex justify-end gap-2 mt-4">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" id="ujk_save_btn" onclick="saveUjiKategori()">Simpan</button>
      </div>
      <div class="mt-5 pt-4 border-t" style="border-color:var(--line);">
        <div class="flex items-center justify-between mb-2">
          <div class="font-display font-semibold text-sm">Data ${esc(k.label)}</div>
          <div class="dd">
            <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="toggleUnduhMenu(event, this)">Unduh <span aria-hidden="true">&#9662;</span></button>
            <div class="dd-menu">
              <button type="button" class="dd-item" onclick="exportUjiKategori('pdf')">PDF</button>
              <button type="button" class="dd-item" onclick="exportUjiKategori('excel')">Excel</button>
            </div>
          </div>
        </div>
        <div id="ujk_list">${ujkRowsHtml(k.label)}</div>
      </div>
    </div>`, true);
}
function exportUjiKategori(fmt){
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  const kat = document.getElementById('ujk_kat').value;
  const rows = ujkList(kat);
  if(!rows.length){ toast('Belum ada data '+kat+' untuk diunduh'); return; }
  const cols = ujkCols(kat);
  const uji = (DB.uji_periodik||[]).find(r=>r.id===ujiPesertaSelId());
  const namaUji = uji ? (uji.nama_uji||uji.nama||'') : '';
  const head = ['No','Nama Anggota','Jabatan','JK','Usia', ...cols.map(c=>c.label), 'Rata Rata','Predikat'];
  const isX = fmt==='excel';
  const body = rows.map((r,i)=>{
    const avg = ujkAvgRow(r, cols);
    const num = v => (v===undefined||v===null||v==='') ? '' : (isX ? parseFloat(v) : fmtNilai(v));
    return [i+1, r.nama||'', r.jabatan||'', r.jk||'-', (r.usia===undefined||r.usia===null||r.usia==='')?'':r.usia, ...cols.map(c=>num(r[c.key])),
      avg===null ? '' : (isX ? Math.round(avg*100)/100 : fmtNilai(avg)), ujkPredikat(avg)||'-'];
  });
  const title = 'Data '+kat;
  const fname = (title+(namaUji?' - '+namaUji:'')).trim().replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_');
  try{
    if(isX){
      if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
      const ws = XLSX.utils.aoa_to_sheet([[title],[namaUji],[],head,...body]);
      ws['!cols'] = head.map((h,i)=>({wch: i===0?5 : i===1?30 : i===2?34 : i<=4?6 : 12}));
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, kat.replace(/[\\/?*\[\]:]/g,'').slice(0,31));
      XLSX.writeFile(wb, fname+'.xlsx');
    } else {
      if(!(window.jspdf && window.jspdf.jsPDF)){ toast('Library PDF belum termuat, periksa koneksi internet'); return; }
      const doc = new window.jspdf.jsPDF({orientation:'landscape', unit:'pt', format:'a4'});
      doc.setFontSize(13); doc.text(title, 40, 40);
      doc.setFontSize(10); doc.text(namaUji, 40, 56);
      doc.autoTable({
        head:[head], body, startY:68,
        styles:{fontSize:8, cellPadding:3, valign:'middle'},
        headStyles:{fillColor:[19,49,92], halign:'center'},
        columnStyles:{0:{halign:'center', cellWidth:24}, 3:{halign:'center', cellWidth:28}, 4:{halign:'center', cellWidth:28}}
      });
      doc.save(fname+'.pdf');
    }
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}
function addUjkKolom(){
  const kat = document.getElementById('ujk_kat').value;
  const inp = document.getElementById('ujk_kolom_baru');
  const label = inp.value.trim();
  if(!label){ toast('Nama kolom wajib diisi'); return; }
  if(ujkCols(kat).some(c=>c.label.toLowerCase()===label.toLowerCase())){ toast('Nama kolom sudah ada'); return; }
  if(!DB.uji_kategori_cols) DB.uji_kategori_cols = {};
  if(!DB.uji_kategori_cols[kat]) DB.uji_kategori_cols[kat] = [];
  let n = Date.now().toString(36);
  DB.uji_kategori_cols[kat].push({key:'x_'+n, label});
  persist();
  inp.value = '';
  ujkRefreshCols();
  toast('Kolom "'+label+'" ditambahkan');
}
function renameUjkKolom(key){
  const kat = document.getElementById('ujk_kat').value;
  const c = ujkCols(kat).find(x=>x.key===key); if(!c) return;
  const nv = (prompt('Ubah nama kolom:', c.label)||'').trim();
  if(!nv || nv===c.label) return;
  if(ujkCols(kat).some(x=>x.key!==key && x.label.toLowerCase()===nv.toLowerCase())){ toast('Nama kolom sudah ada'); return; }
  if(ujkBaseDefaults(kat).some(x=>x.key===key)) ujkBaseSet(kat, b=>{ b.labels[key] = nv; });
  else { c.label = nv; persist(); }
  ujkRefreshCols();
}
function deleteUjkKolom(key){
  const kat = document.getElementById('ujk_kat').value;
  const c = ujkCols(kat).find(x=>x.key===key); if(!c) return;
  if(ujkCols(kat).length<=1){ toast('Minimal harus ada satu kolom nilai'); return; }
  if(!confirm('Hapus kolom "'+c.label+'"? Nilai kolom ini tidak akan ikut dihitung lagi.')) return;
  if(ujkBaseDefaults(kat).some(x=>x.key===key)) ujkBaseSet(kat, b=>{ if(!b.hidden.includes(key)) b.hidden.push(key); });
  else { DB.uji_kategori_cols[kat] = DB.uji_kategori_cols[kat].filter(x=>x.key!==key); persist(); }
  ujkRefreshCols();
}
function editUjiKategori(id){
  const r = (DB.uji_kategori||[]).find(x=>x.id===id); if(!r) return;
  ujkEditId = id;
  const sel = document.getElementById('ujk_anggota');
  sel.value = r.anggota_id; ujkPilihAnggota(r.anggota_id);
  document.getElementById('ujk_jk').value = r.jk || '';
  document.getElementById('ujk_usia').value = (r.usia===undefined||r.usia===null) ? '' : r.usia;
  ujkCols(r.kategori).forEach(c=>{ const el = document.getElementById('ujk_'+c.key); if(el) el.value = (r[c.key]===undefined || r[c.key]==='') ? '' : r[c.key]; });
  ujkHitung();
  document.getElementById('ujk_save_btn').textContent = 'Simpan Perubahan';
  sel.scrollIntoView({behavior:'smooth', block:'center'});
  toast('Mengubah data '+(r.nama||id));
}
function ujkPilihAnggota(id){
  const a = (DB.anggota||[]).find(x=>x.id===id);
  document.getElementById('ujk_jabatan').value = a ? (a.jabatan||'') : '';
  const jkEl = document.getElementById('ujk_jk');
  if(jkEl && !ujkEditId){
    const prev = (DB.uji_kategori||[]).slice().reverse().find(x=>x.anggota_id===id && x.jk);
    jkEl.value = prev ? prev.jk : '';
  }
  const usEl = document.getElementById('ujk_usia');
  if(usEl && !ujkEditId){
    const pu = (DB.uji_kategori||[]).slice().reverse().find(x=>x.anggota_id===id && x.usia!==undefined && x.usia!=='');
    usEl.value = pu ? pu.usia : '';
  }
}
function ujkHitung(){
  const kat = document.getElementById('ujk_kat').value;
  const avg = ujkRataRata(ujkCols(kat).map(c=>{ const el=document.getElementById('ujk_'+c.key); return el ? el.value : ''; }));
  document.getElementById('ujk_rata').value = avg===null ? '' : fmtNilai(avg);
  document.getElementById('ujk_pred').innerHTML = ujkPredikatBadge(ujkPredikat(avg));
}
function saveUjiKategori(){ if(guardRO('uji_periodik')) return;
  const kat = document.getElementById('ujk_kat').value;
  const angId = document.getElementById('ujk_anggota').value;
  const ang = (DB.anggota||[]).find(x=>x.id===angId);
  if(!ang){ toast('Nama anggota wajib dipilih'); return; }
  const jk = document.getElementById('ujk_jk').value;
  const usiaRaw = document.getElementById('ujk_usia').value;
  const usia = usiaRaw==='' ? '' : parseInt(usiaRaw,10);
  if(usia!=='' && (isNaN(usia) || usia<0 || usia>120)){ toast('Usia tidak valid'); return; }
  const cols = ujkCols(kat);
  const vals = {};
  cols.forEach(c=>{ const v = document.getElementById('ujk_'+c.key).value; vals[c.key] = v==='' ? '' : parseFloat(v); });
  const avg = ujkRataRata(Object.values(vals));
  if(avg===null){ toast('Isi minimal satu nilai'); return; }
  if(!DB.uji_kategori) DB.uji_kategori = [];
  if(ujkEditId){
    const ex = DB.uji_kategori.find(x=>x.id===ujkEditId);
    if(ex){
      delete ex.nilai_import; delete ex.sumber;
      Object.assign(ex, vals, {uji_id: ex.uji_id || ujiPesertaSelId(), anggota_id:ang.id, nama:ang.nama, jabatan:ang.jabatan||'', jk, usia, rata_rata:Math.round(avg*100)/100, predikat:ujkPredikat(avg), diperbarui:nowStr()});
      persist(); ujkRefreshPesertaPanel(); toast('Data '+kat+' diperbarui');
      openUjiKategori(UJI_KATEGORI.findIndex(x=>x.label===kat));
      return;
    }
  }
  DB.uji_kategori.push(Object.assign({
    id: uid('UKT', DB.uji_kategori), kategori: kat, uji_id: ujiPesertaSelId(),
    anggota_id: ang.id, nama: ang.nama, jabatan: ang.jabatan||'', jk, usia,
    rata_rata: Math.round(avg*100)/100, predikat: ujkPredikat(avg), dibuat: nowStr(),
  }, vals));
  persist(); ujkRefreshPesertaPanel();
  toast('Data '+kat+' disimpan');
  openUjiKategori(UJI_KATEGORI.findIndex(x=>x.label===kat));
}
function deleteUjiKategori(id){ if(guardRO('uji_periodik')) return;
  const r = (DB.uji_kategori||[]).find(x=>x.id===id); if(!r) return;
  if(!confirm('Hapus data '+r.kategori+' '+(r.nama||id)+'?')) return;
  DB.uji_kategori = DB.uji_kategori.filter(x=>x.id!==id);
  persist(); ujkRefreshPesertaPanel();
  document.getElementById('ujk_list').innerHTML = ujkRowsHtml(r.kategori);
  toast('Data dihapus');
}
/* ---- Panel "Data Peserta Uji Periodik": otomatis dari 4 card kategori ---- */

// Nilai Akhir = Teknis SAR 25% + PU 10% + Kebugaran Jasmani 40% + Renang 25%

const UJI_BOBOT = [

  {kat:'Teknis SAR',              head:'Teknis SAR',        w:0.25},

  {kat:'Pengetahuan Umum',        head:'PU',                w:0.10},

  {kat:'Kebugaran Jasmani',       head:'Kebugaran Jasmani', w:0.40},

  {kat:'Renang & Water Treading', head:'Renang',            w:0.25},

];

// Uji Periodik yang sedang dipilih (dropdown Data Peserta & form kategori)
let ujiPesertaSelectedId = '';
function ujiPesertaSelId(){
  const rows = DB.uji_periodik || [];
  if(!rows.length) return '';
  if(!ujiPesertaSelectedId || !rows.some(r=>r.id===ujiPesertaSelectedId)) ujiPesertaSelectedId = rows[rows.length-1].id;
  return ujiPesertaSelectedId;
}
// Data nilai lama (sebelum ada dropdown) belum punya uji_id -> masukkan ke uji yang sedang terpilih
function ujiMigrasiNilaiLama(){
  const id = ujiPesertaSelId(); if(!id) return;
  let changed = false;
  (DB.uji_kategori||[]).forEach(r=>{ if(!r.uji_id){ r.uji_id = id; changed = true; } });
  if(changed) persist();
}
function ujiOptionsHtml(){
  const rows = DB.uji_periodik || [];
  const sel = ujiPesertaSelId();
  return rows.map(r=>`<option value="${esc(r.id)}" ${r.id===sel?'selected':''}>${esc(r.nama_uji||r.id)}</option>`).join('');
}
function onUjiPesertaSelectChange(id){
  ujiPesertaSelectedId = id;
  const a = document.getElementById('ujk_peserta_select'); if(a && a.value!==id) a.value = id;
  const b = document.getElementById('ujk_uji_select');     if(b && b.value!==id) b.value = id;
  ujkRefreshPesertaPanel();
  const kat = document.getElementById('ujk_kat');
  const list = document.getElementById('ujk_list');
  if(kat && list) list.innerHTML = ujkRowsHtml(kat.value);
}

function ujiPesertaRekap(ujiId){
  const selId = ujiId || ujiPesertaSelId();
  const all = (DB.uji_kategori || []).filter(r=>r.uji_id===selId);
  const map = {};
  all.forEach(r=>{
    const id = r.anggota_id || r.nama; if(!id) return;
    if(!map[id]) map[id] = {id, nama:r.nama, jabatan:r.jabatan, jk:'', kat:{}};
    if(r.jk) map[id].jk = r.jk;
    const cols = ujkCols(r.kategori);
    const avg = ujkAvgRow(r, cols);
    if(avg===null) return;
    (map[id].kat[r.kategori] = map[id].kat[r.kategori] || []).push(avg);
  });
  return Object.values(map).map(o=>{
    const ang = (DB.anggota||[]).find(a=>a.id===o.id);
    if(ang){ o.nama = ang.nama; o.jabatan = ang.jabatan||o.jabatan; }
    o.nilai = {};
    UJI_BOBOT.forEach(b=>{ const a = o.kat[b.kat]; o.nilai[b.kat] = a && a.length ? a.reduce((x,y)=>x+y,0)/a.length : null; });
    const lengkap = UJI_BOBOT.every(b=>o.nilai[b.kat]!==null);
    o.akhir = lengkap ? UJI_BOBOT.reduce((t,b)=>t+o.nilai[b.kat]*b.w,0) : null;
    o.predikat = ujkPredikat(o.akhir);
    return o;
  }).sort((a,b)=>String(a.nama||'').localeCompare(String(b.nama||'')));
}
/* ---- IMT: kuesioner kesiapan fisik per peserta (Ya / Tidak), disimpan di DB.uji_imt ---- */
const IMT_PERTANYAAN = [
  'Pernahkah dokter mengatakan bahwa anda menderita suatu kelainan jantung ?',
  'Apakah anda sering mengalami nyeri dada atau nyeri di jantung anda ?',
  'Seringkah anda merasa akan pingsan atau mengeluh rasa pusing kepala yang agak parah ?',
  'Pernahkan dokter memberitahukan kepada anda bahwa tekanan darah anda terlalu tinggi ?',
  'Pernahkah dokter memberitahu kepada anda bahwa anda mengidap suatu masalah persendian atau tulang?',
  'Apakah anda membawa serta obat-obat berdasarkan resep, seperti obat untuk kelainan jantung, tekanan darah tinggi, diabetes ?',
  'Apakah terdapat suatu alasan fisik yang belum disebutkan diatas bahwa anda seharusnya tidak boleh mengikuti suatu program latihan fisik?'
];
function imtKey(pesertaId){ return ujiPesertaSelId() + '|' + pesertaId; }
function imtGet(pesertaId){
  const r = (DB.uji_imt || {})[imtKey(pesertaId)];
  return (r && Array.isArray(r.jawaban)) ? r.jawaban : [];
}
function imtRec(pesertaId){ return (DB.uji_imt || {})[imtKey(pesertaId)] || {}; }
function imtHitungNilai(tb, bb){
  tb = parseFloat(String(tb).replace(',','.')); bb = parseFloat(String(bb).replace(',','.'));
  if(!(tb>0) || !(bb>0)) return null;
  const m = tb/100;
  return bb/(m*m);
}
function imtKategori(v){
  if(v===null || v===undefined || isNaN(v)) return '';
  if(v < 18.5) return 'Underweight';
  if(v < 25)   return 'Normal';
  if(v < 30)   return 'Obese Class I';
  return 'Obese Class II';
}
function imtHitung(){
  const g = id => document.getElementById(id);
  const v = imtHitungNilai(g('imt_tb').value, g('imt_bb').value);
  g('imt_imt').value = v===null ? '' : String(Math.round(v*10)/10).replace('.',',');
  g('imt_kat').value = imtKategori(v);
}
function imtLengkap(pesertaId){
  const j = imtGet(pesertaId);
  return IMT_PERTANYAAN.every((_,i)=> j[i]==='ya' || j[i]==='tidak');
}
let imtDraft = [];
let imtPesertaId = '';
function openImtDetail(pesertaId){ if(guardRO('uji_periodik')) return;
  const o = ujiPesertaRekap().find(x=>String(x.id)===String(pesertaId));
  const uji = (DB.uji_periodik||[]).find(r=>r.id===ujiPesertaSelId());
  imtPesertaId = pesertaId;
  imtDraft = IMT_PERTANYAAN.map((_,i)=> imtGet(pesertaId)[i] || '');
  const rec = imtRec(pesertaId);
  openModal(`
    <div class="p-5 max-h-[88vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-1">
        <div>
          <h3 class="font-display font-semibold text-base">IMT — ${esc(o ? o.nama : '-')}</h3>
          <p class="text-xs" style="color:var(--muted);">${esc(uji ? (uji.nama_uji||uji.id) : '')}${o && o.jabatan ? ' · '+esc(o.jabatan) : ''}</p>
        </div>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="grid sm:grid-cols-2 gap-3 my-3">
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Usia</label>
          <input type="number" step="any" min="0" id="imt_usia" value="${esc(rec.usia)}" placeholder="Tahun"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Tinggi Badan</label>
          <input type="number" step="any" min="0" id="imt_tb" value="${esc(rec.tb)}" placeholder="cm" oninput="imtHitung()"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Berat Badan</label>
          <input type="number" step="any" min="0" id="imt_bb" value="${esc(rec.bb)}" placeholder="kg" oninput="imtHitung()"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">IMT</label>
          <input type="text" id="imt_imt" readonly value="" placeholder="Otomatis" style="background:var(--bg);"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Tekanan Darah</label>
          <input type="number" step="any" min="0" id="imt_td" value="${esc(rec.td)}" placeholder="mmHg"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Denyut Nadi</label>
          <input type="number" step="any" min="0" id="imt_nadi" value="${esc(rec.nadi)}" placeholder="x/menit"></div>
        <div class="sm:col-span-2"><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Kategori</label>
          <input type="text" id="imt_kat" readonly value="" placeholder="Otomatis setelah IMT terisi" style="background:var(--bg);"></div>
      </div>
      <div class="mb-3 text-sm" style="color:var(--muted);">Pilih salah satu jawaban (Ya / Tidak) untuk setiap pertanyaan.</div>
      <div id="imt_list">${imtListHtml()}</div>
      <div class="flex justify-end gap-2 mt-4">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-navy" onclick="saveImt()">Simpan</button>
      </div>
    </div>`, true);
  imtHitung();
}
function imtListHtml(){
  return IMT_PERTANYAAN.map((q,i)=>`
    <div class="rounded-lg p-3 mb-2" style="background:var(--bg);">
      <p class="text-sm mb-2"><span class="font-semibold">${i+1}.</span> ${esc(q)}</p>
      <div class="flex items-center gap-5 text-sm">
        <label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" style="width:1rem;height:1rem;" ${imtDraft[i]==='ya'?'checked':''} onchange="imtPilih(${i},'ya',this.checked)"> Ya</label>
        <label class="inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" style="width:1rem;height:1rem;" ${imtDraft[i]==='tidak'?'checked':''} onchange="imtPilih(${i},'tidak',this.checked)"> Tidak</label>
      </div>
    </div>`).join('');
}
function imtPilih(i, val, checked){
  imtDraft[i] = checked ? val : '';
  document.getElementById('imt_list').innerHTML = imtListHtml();
}
function saveImt(){ if(guardRO('uji_periodik')) return;
  if(!imtDraft.every(v=>v==='ya' || v==='tidak')){
    toast('Lengkapi semua pertanyaan (pilih Ya atau Tidak)'); return;
  }
  if(!DB.uji_imt) DB.uji_imt = {};
  const gv = id => (document.getElementById(id).value || '').trim();
  const imtVal = imtHitungNilai(gv('imt_tb'), gv('imt_bb'));
  DB.uji_imt[imtKey(imtPesertaId)] = {
    jawaban: imtDraft.slice(),
    usia: gv('imt_usia'), tb: gv('imt_tb'), bb: gv('imt_bb'),
    imt: imtVal===null ? '' : Math.round(imtVal*10)/10,
    kategori: imtKategori(imtVal),
    td: gv('imt_td'), nadi: gv('imt_nadi'),
    tanggal: (()=>{ const d=new Date(); const z=n=>String(n).padStart(2,'0'); return d.getFullYear()+'-'+z(d.getMonth()+1)+'-'+z(d.getDate()); })(),
    diperbarui: nowStr()
  };
  persist();
  closeModal();
  ujkRefreshPesertaPanel();
  toast('Data IMT disimpan');
}
function ujiPesertaPanelInner(){
  if(!(DB.uji_periodik||[]).length){
    return `<p class="text-sm" style="color:var(--muted);">Belum ada data Uji Periodik. Tambahkan data Uji Periodik terlebih dahulu.</p>`;
  }
  const rows = ujiPesertaRekap();
  const headNum = UJI_BOBOT.map(b=>b.head);
  const nCol = 4 + headNum.length + 3;
  return `<div class="tablewrap"><table class="rekap-uji no-clamp">
    <thead><tr>
      <th class="text-center">No</th><th>Nama</th><th>Jabatan</th><th class="c-num">JK</th>
      ${headNum.map(h=>`<th class="c-num">${esc(h)}</th>`).join('')}
      <th class="c-num">Nilai Akhir</th><th class="c-num">Predikat</th><th class="c-num">IMT</th>
    </tr></thead>
    <tbody>${rows.length ? rows.map((o,i)=>`<tr>
      <td class="c-no">${i+1}</td>
      <td class="c-nama">${esc(o.nama)||'-'}</td>
      <td class="c-jab">${esc(o.jabatan)||'-'}</td>
      <td class="c-num">${esc(o.jk)||'-'}</td>
      ${UJI_BOBOT.map(b=>`<td class="c-num">${fmtNilai(o.nilai[b.kat])}</td>`).join('')}
      <td class="c-num c-akhir">${o.akhir===null ? '<span class="text-xs" style="color:var(--muted);font-weight:400;">Belum lengkap</span>' : fmtNilai(o.akhir)}</td>
      <td class="c-num">${ujkPredikatBadge(o.predikat)}</td>
      <td class="c-num"><input type="checkbox" style="width:1rem;height:1rem;cursor:pointer;" title="Klik untuk mengisi / melihat kuesioner IMT" ${imtLengkap(o.id)?'checked':''} onclick="event.preventDefault(); openImtDetail('${esc(o.id)}')"></td>
    </tr>`).join('') : `<tr><td colspan="${nCol}" class="text-center py-6" style="color:var(--muted);">Belum ada data peserta untuk uji periodik ini. Isi nilai lewat card Teknis SAR, Pengetahuan Umum, Kebugaran Jasmani, atau Renang &amp; Water Treading.</td></tr>`}
    </tbody></table></div>
    <p class="text-[11px] mt-2" style="color:var(--muted);">${rows.length} peserta · Nilai Akhir = Teknis SAR (25%) + PU (10%) + Kebugaran Jasmani (40%) + Renang (25%). Dihitung otomatis dan tampil setelah keempat nilai terisi.</p>`;
}
function exportUjiPeserta(fmt){
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  const rows = ujiPesertaRekap();
  if(!rows.length){ toast('Belum ada data peserta untuk diunduh'); return; }
  const uji = (DB.uji_periodik||[]).find(r=>r.id===ujiPesertaSelId());
  const namaUji = uji ? (uji.nama_uji||uji.id||'') : '';
  // Semester & tahun: dari nama UDIK (mis. "UDIK S1 2026"), cadangan dari tanggal mulai
  let semester = '';
  const ms = /\bS\s*([12])\b/i.exec(namaUji);
  if(ms) semester = ms[1]==='1' ? 'I' : 'II';
  else if(uji && /^\d{4}-(\d{2})/.test(uji.tanggal_mulai||'')) semester = parseInt(uji.tanggal_mulai.slice(5,7),10)<=6 ? 'I' : 'II';
  let tahun = '';
  const my = /\b(20\d{2})\b/.exec(namaUji);
  if(my) tahun = my[1];
  else if(uji && /^\d{4}/.test(uji.tanggal_mulai||'')) tahun = uji.tanggal_mulai.slice(0,4);
  else tahun = String(new Date().getFullYear());
  const isX = fmt==='excel';
  // Urutan kolom sesuai format rekap: Teknis SAR, Pengetahuan Umum, Kemampuan Renang, Kebugaran Jasmani
  const kolom = [
    {kat:'Teknis SAR',              head:'Teknis SAR'},
    {kat:'Pengetahuan Umum',        head:'Pengetahuan Umum'},
    {kat:'Renang & Water Treading', head:'Kemampuan Renang'},
    {kat:'Kebugaran Jasmani',       head:'Kebugaran Jasmani'}
  ].map(k=>({...k, w:(UJI_BOBOT.find(b=>b.kat===k.kat)||{w:0}).w}));
  const f1 = v => (v===null||v===undefined) ? '' : (isX ? Math.round(v*10)/10 : (Math.round(v*10)/10).toFixed(1).replace('.',','));
  const body = rows.map((o,i)=>[
    i+1, o.nama||'', o.jk||'', o.jabatan||'',
    ...kolom.map(k=>f1(o.nilai[k.kat])),
    f1(o.akhir),
    o.predikat||''
  ]);
  const t1 = 'REKAPITULASI DATA';
  const t2 = 'UJI PERIODIK TENAGA SAR'+(semester?' SEMESTER '+semester:'')+' TAHUN '+tahun;
  const t3 = 'KANTOR PENCARIAN DAN PERTOLONGAN JAKARTA';
  const head1 = ['NO','Nama','Jenis Kelamin','Jabatan', ...kolom.map(k=>k.head), 'Nilai Akhir','predikat'];
  const head2 = ['','','','', ...kolom.map(k=>Math.round(k.w*100)+'%'), '',''];
  const fname = ('Rekapitulasi Data Uji Periodik'+(namaUji?' - '+namaUji:'')).trim().replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_');
  try{
    if(isX){
      if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
      const ws = XLSX.utils.aoa_to_sheet([[t1],[t2],[t3],[],head1,head2,...body]);
      const last = head1.length-1;
      ws['!merges'] = [0,1,2].map(r=>({s:{r,c:0}, e:{r,c:last}}))
        .concat([0,1,2,3,8,9].map(c=>({s:{r:4,c}, e:{r:5,c}})));
      ws['!cols'] = [5,34,9,40,11,14,14,14,11,12].map(w=>({wch:w}));
      for(let r=6; r<6+body.length; r++){
        [4,5,6,7,8].forEach(c=>{ const cell = ws[XLSX.utils.encode_cell({r,c})]; if(cell && typeof cell.v==='number') cell.z = '0.0'; });
      }
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Rekapitulasi');
      XLSX.writeFile(wb, fname+'.xlsx');
    } else {
      if(!(window.jspdf && window.jspdf.jsPDF)){ toast('Library PDF belum termuat, periksa koneksi internet'); return; }
      const doc = new window.jspdf.jsPDF({orientation:'landscape', unit:'pt', format:'a4'});
      const pw = doc.internal.pageSize.getWidth();
      doc.setFont(undefined,'bold'); doc.setFontSize(10);
      doc.text(t1, pw/2, 40, {align:'center'});
      doc.text(t2, pw/2, 54, {align:'center'});
      doc.text(t3, pw/2, 68, {align:'center'});
      doc.setFont(undefined,'normal');
      doc.autoTable({
        head:[head1, head2], body, startY:84, theme:'grid',
        styles:{fontSize:8, cellPadding:2.5, valign:'middle', halign:'center', lineColor:[0,0,0], lineWidth:0.5, textColor:[0,0,0]},
        headStyles:{fillColor:[255,255,255], textColor:[0,0,0], fontStyle:'bold', halign:'center'},
        columnStyles:{0:{cellWidth:26}, 1:{halign:'left'}, 3:{halign:'left'}}
      });
      doc.save(fname+'.pdf');
    }
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}
function exportImt(fmt){
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  const uji = (DB.uji_periodik||[]).find(r=>r.id===ujiPesertaSelId());
  const namaUji = uji ? (uji.nama_uji||uji.id||'') : '';
  const tglFmt = iso => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso||''); return m ? m[3]+'/'+m[2]+'/'+m[1] : ''; };
  let periode = '';
  const ms = /\bS\s*([12])\b/i.exec(namaUji);
  if(ms) periode = ms[1]==='1' ? 'Semester I' : 'Semester II';
  else if(uji && /^\d{4}-(\d{2})/.test(uji.tanggal_mulai||'')) periode = parseInt(uji.tanggal_mulai.slice(5,7),10)<=6 ? 'Semester I' : 'Semester II';
  const isX = fmt==='excel';
  const list = [];
  ujiPesertaRekap().forEach(o=>{
    const rec = imtRec(o.id);
    const v = imtHitungNilai(rec.tb, rec.bb);
    if(v===null) return;
    list.push([
      list.length+1,
      tglFmt(rec.tanggal) || tglFmt(uji && uji.tanggal_mulai),
      'Kantor SAR Jakarta',
      periode,
      o.nama||'',
      o.jabatan||'',
      o.jk==='L' ? 'Laki-laki' : (o.jk==='P' ? 'Perempuan' : (o.jk||'')),
      rec.usia!=='' && rec.usia!==undefined ? parseFloat(rec.usia) : '',
      isX ? Math.round(v*100)/100 : v.toFixed(2),
      imtKategori(v)
    ]);
  });
  if(!list.length){ toast('Belum ada data IMT (isi Tinggi & Berat Badan) untuk diunduh'); return; }
  const title = 'DATABASE PESERTA PENGUKURAN IMT BASARNAS';
  const head = ['No','Tanggal','Kantor SAR','Periode','Nama Peserta','Jabatan','Jenis Kelamin','Usia','Hasil IMT','Kategori'];
  const fname = ('Database_Peserta_Pengukuran_IMT'+(namaUji?' - '+namaUji:'')).trim().replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_');
  try{
    if(isX){
      if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
      const ws = XLSX.utils.aoa_to_sheet([[title],[],head,...list]);
      ws['!merges'] = [{s:{r:0,c:0}, e:{r:0,c:head.length-1}}];
      ws['!cols'] = [5,12,20,12,28,40,14,7,10,14].map(w=>({wch:w}));
      for(let r=3; r<3+list.length; r++){ const c = ws[XLSX.utils.encode_cell({r, c:8})]; if(c) c.z = '0.00'; }
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'IMT');
      XLSX.writeFile(wb, fname+'.xlsx');
    } else {
      if(!(window.jspdf && window.jspdf.jsPDF)){ toast('Library PDF belum termuat, periksa koneksi internet'); return; }
      const doc = new window.jspdf.jsPDF({orientation:'landscape', unit:'pt', format:'a4'});
      const pw = doc.internal.pageSize.getWidth();
      doc.setFont(undefined,'bold'); doc.setFontSize(15);
      doc.text(title, pw/2, 44, {align:'center'});
      doc.setFont(undefined,'normal');
      doc.autoTable({
        head:[head], body:list, startY:66, theme:'grid',
        styles:{fontSize:8, cellPadding:4, valign:'middle', halign:'center', lineColor:[210,214,222], lineWidth:0.5, textColor:[40,40,40]},
        headStyles:{fillColor:[26,115,232], textColor:255, halign:'center', fontStyle:'bold'},
        columnStyles:{4:{halign:'left'}, 5:{halign:'left'}}
      });
      doc.save(fname+'.pdf');
    }
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}
/* ---- Unduh Berita Acara hasil pelaksanaan Uji Periodik (Word) ---- */
// Keseluruhan = jumlah anggota (Daftar Anggota) per kelompok Tenaga SAR berdasarkan jabatan.
// Hadir = anggota yang namanya sudah masuk ke tabel Data Peserta Uji Periodik; selain itu Tidak Hadir.
const BA_KATEGORI = [
  {label:'Pranata Pencarian dan Pertolongan',        re:/^pranata pencarian dan pertolongan/},
  {label:'Penata Kelola Pencarian dan Pertolongan',  re:/^penata kelola pencarian dan pertolongan/},
  {label:'Pengelola Pencarian dan Pertolongan',      re:/^pengelola pencarian dan pertolongan/},
  {label:'Perawat',                                  re:/^perawat/},
  {label:'Pengelola Layanan Kesehatan',              re:/^pengelola layanan kesehatan/},
  {label:'Awak Sarana laut',                         re:/^(nakhoda|nakhkoda|mualim|kepala kamar mesin|kkm|juru minyak|juru mudi|kelasi|markonis|mandor mesin|abk|bosun|koki)/},
  {label:'Awak Sarana Darat',                        re:/^(pengemudi|sopir|mekanik|teknisi)/},
  {label:'Operator Layanan Operasional',             re:/^operator layanan operasional/}
];
function exportBA(){
  const uji = (DB.uji_periodik||[]).find(r=>r.id===ujiPesertaSelId());
  if(!uji){ toast('Belum ada data Uji Periodik'); return; }
  const rekap = ujiPesertaRekap();
  if(!rekap.length){ toast('Belum ada peserta di tabel Data Peserta Uji Periodik'); return; }
  const namaUji = uji.nama_uji||uji.id||'';

  const BLN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  const ROM = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
  const tglIndo = iso => { const m=/^(\d{4})-(\d{2})-(\d{2})/.exec(iso||''); return m ? parseInt(m[3],10)+' '+BLN[parseInt(m[2],10)-1]+' '+m[1] : ''; };

  let semester = '';
  const ms = /\bS\s*([12])\b/i.exec(namaUji);
  if(ms) semester = ms[1]==='1' ? 'I' : 'II';
  else if(/^\d{4}-(\d{2})/.test(uji.tanggal_mulai||'')) semester = parseInt(uji.tanggal_mulai.slice(5,7),10)<=6 ? 'I' : 'II';
  let tahun = '';
  const my = /\b(20\d{2})\b/.exec(namaUji);
  if(my) tahun = my[1];
  else if(/^\d{4}/.test(uji.tanggal_mulai||'')) tahun = uji.tanggal_mulai.slice(0,4);
  else tahun = String(new Date().getFullYear());

  // Hitung jumlah peserta per kelompok Tenaga SAR
  const hadirIds = new Set(rekap.map(o=>String(o.id)));
  const katOf = j => { const t=String(j||'').trim().toLowerCase(); return BA_KATEGORI.findIndex(k=>k.re.test(t)); };
  const rows = BA_KATEGORI.map(k=>({label:k.label, total:0, hadir:0, absen:[]}));
  const lain = {label:'Lainnya', total:0, hadir:0, absen:[]};
  const anggotaIds = new Set();
  (DB.anggota||[]).forEach(a=>{
    anggotaIds.add(String(a.id));
    const i = katOf(a.jabatan);
    const hadir = hadirIds.has(String(a.id));
    if(i<0 && !hadir) return; // jabatan di luar kelompok Tenaga SAR & tidak ikut uji: bukan peserta
    const r = i>=0 ? rows[i] : lain;
    r.total++;
    if(hadir) r.hadir++; else r.absen.push(a.nama);
  });
  rekap.forEach(o=>{
    if(anggotaIds.has(String(o.id))) return;
    const i = katOf(o.jabatan); const r = i>=0 ? rows[i] : lain;
    r.total++; r.hadir++;
  });
  const list = rows.concat(lain.total ? [lain] : []);
  const totalPeserta = list.reduce((t,r)=>t+r.total,0);

  const now = new Date();
  const tglBA = now.getDate()+' '+BLN[now.getMonth()]+' '+now.getFullYear();
  const hariBA = HARI[now.getDay()];
  const tglUji = tglIndo(uji.tanggal_mulai);
  const tglUjiSelesai = tglIndo(uji.tanggal_selesai);
  const pelaksanaan = tglUji ? (tglUjiSelesai && tglUjiSelesai!==tglUji ? tglUji+' s.d. '+tglUjiSelesai : tglUji) : '..........';
  const nomor = 'B.A/....../UJI PERIODIK/'+ROM[now.getMonth()]+'/BNG.02.03/BSN-'+tahun;
  const judulSem = 'SEMESTER '+(semester||'..')+' TAHUN '+tahun;
  const teksSem = 'Semester '+(semester||'..')+' Tahun '+tahun;

  const ket = r => {
    const n = r.total - r.hadir;
    if(n<=0) return '';
    return n+' orang'+(n<=3 ? ' An.'+r.absen.map(esc).join(', ') : '');
  };
  const trs = list.map((r,i)=>{
    const tdk = r.total - r.hadir;
    return `<tr>
      <td align="center">${i+1}</td>
      <td>${esc(r.label)}</td>
      <td align="center">${r.total}</td>
      <td align="center">${r.hadir}</td>
      <td align="center">${tdk>0 ? tdk : ''}</td>
      <td>${ket(r)}</td>
    </tr>`;
  }).join('');

  const html = `<\x68tml xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<\x68ead><meta charset="utf-8"><title>Berita Acara Uji Periodik<\/title>
<style>
  @page Section1 { size:21.0cm 29.7cm; margin:2.0cm 2.2cm 2.0cm 2.2cm; }
  div.Section1 { page:Section1; }
  body { font-family:"Times New Roman",serif; font-size:12pt; }
  p { margin:0 0 8pt 0; text-align:justify; line-height:150%; }
  td, th { font-family:"Times New Roman",serif; font-size:11pt; vertical-align:middle; }
  .c { text-align:center; }
<\/style><\/head>
<\x62ody><div class="Section1">
  <p class="c" style="margin:0;"><b style="font-size:14pt;">BERITA ACARA<\/b><\/p>
  <p class="c" style="margin:0;"><b>HASIL PELAKSANAAN UJI PERIODIK TENAGA SAR<\/b><\/p>
  <p class="c" style="margin:0;"><b>${esc(judulSem)}<\/b><\/p>
  <p class="c" style="margin:0 0 4pt 0;"><b>NOMOR:${esc(nomor)}<\/b><\/p>
  <hr style="border:0; border-top:2.25pt solid #000; margin:0 0 14pt 0;">
  <p style="text-indent:1.2cm;">Pada hari ini, ${hariBA} tanggal ${tglBA} bertempat di Kantor Pencarian dan Pertolongan Jakarta telah dilaksanakan Uji Periodik Tenaga SAR ${esc(teksSem)}. Pelaksanaan dilakukan pada tanggal ${esc(pelaksanaan)} dan diikuti sebanyak ${totalPeserta} peserta, dengan detail penjelasan sebagai berikut:<\/p>
  <table border="1" cellspacing="0" cellpadding="5" style="border-collapse:collapse; width:100%; margin-bottom:10pt;">
    <tr>
      <td rowspan="2" align="center" width="6%">No<\/td>
      <td rowspan="2" align="center" width="33%">Tenaga SAR<\/td>
      <td colspan="3" align="center">Jumlah Peserta<\/td>
      <td rowspan="2" align="center" width="27%">Keterangan<\/td>
    <\/tr>
    <tr>
      <td align="center" width="12%">Keseluruhan<\/td>
      <td align="center" width="9%">Hadir<\/td>
      <td align="center" width="12%">Tidak Hadir<\/td>
    <\/tr>
    ${trs}
  <\/table>
  <p style="text-indent:1.2cm;">Hasil rekapitulasi uji periodik tenaga pencarian dan pertolongan semester ${esc(semester||'..')} tahun ${esc(tahun)} dengan penjelasan terlampir.<\/p>
  <p style="text-indent:1.2cm;">Demikian Berita Acara ini dibuat dengan sebenar-benarnya, untuk digunakan sebagaimana mestinya.<\/p>
  <p style="text-align:right; margin-top:14pt;">Jakarta, ${tglBA}<\/p>
  <p class="c" style="margin:6pt 0 4pt 0;"><b>Mengetahui,<\/b><\/p>
  <table cellspacing="0" cellpadding="3" style="width:100%; border-collapse:collapse;">
    <tr>
      <td align="center" width="33%" valign="top"><b>Kasi Sumber Daya<\/b><\/td>
      <td align="center" width="34%" valign="top"><b>Kepala Kantor SAR<br>Jakarta<\/b><\/td>
      <td align="center" width="33%" valign="top"><b>Ketua Supervisi<\/b><\/td>
    <\/tr>
    <tr><td colspan="3" style="height:60pt;">&nbsp;<\/td><\/tr>
    <tr>
      <td align="center" valign="top"><b><u>Didit Permana, S.Kom<\/u><\/b><br><b>Penata Tk.I (III/d)<\/b><\/td>
      <td align="center" valign="top"><b><u>Desiana Kartika Bahari, S.E., M.H.<\/u><\/b><br><b>Pembina Tk.I(IV/b)<\/b><\/td>
      <td align="center" valign="top"><b><u>Fery Yulianto, S.S., M.Si.<\/u><\/b><br><b>Pembina Tk.I(IV/b)<\/b><\/td>
    <\/tr>
  <\/table>
<\/div><\/body><\/html>`;

  try{
    const blob = new Blob(['\ufeff'+html], {type:'application/msword'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = ('Berita_Acara_Uji_Periodik'+(namaUji?' - '+namaUji:'')).trim().replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_')+'.doc';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 2000);
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}
function ujiPesertaPanelHtml(){
  ujiMigrasiNilaiLama();
  const ada = (DB.uji_periodik||[]).length > 0;
  return `<div class="panel rounded-xl p-4 mt-4">
    <div class="flex flex-wrap items-center gap-2 mb-3">
      <span class="font-display font-semibold text-sm">Data Peserta Uji Periodik</span>
      ${ada ? `<div class="dd">
        <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="toggleUnduhMenu(event, this)">Unduh <span aria-hidden="true">&#9662;</span></button>
        <div class="dd-menu">
          <button type="button" class="dd-item" onclick="exportUjiPeserta('pdf')">PDF</button>
          <button type="button" class="dd-item" onclick="exportUjiPeserta('excel')">Excel</button>
        </div>
      </div>
      <div class="dd">
        <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="toggleUnduhMenu(event, this)">Unduh IMT <span aria-hidden="true">&#9662;</span></button>
        <div class="dd-menu">
          <button type="button" class="dd-item" onclick="exportImt('pdf')">PDF</button>
          <button type="button" class="dd-item" onclick="exportImt('excel')">Excel</button>
        </div>
      </div>
      <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" title="Unduh Berita Acara (Word)" onclick="exportBA()">Unduh BA</button>` : ''}
      ${ada ? `<select id="ujk_peserta_select" style="width:auto; min-width:14rem; max-width:100%; font-size:.85rem;" onchange="onUjiPesertaSelectChange(this.value)">${ujiOptionsHtml()}</select>` : ''}
    </div>
    <div id="ujk_peserta_panel">${ujiPesertaPanelInner()}</div>
  </div>`;
}
function ujkRefreshPesertaPanel(){
  const el = document.getElementById('ujk_peserta_panel');
  if(el){ el.innerHTML = ujiPesertaPanelInner(); if(typeof enhanceTableScroll==='function') enhanceTableScroll(); }
}

/* ============ Import Excel Uji Periodik (khusus Admin Utama) ============
   Format = sama dengan hasil Unduh Excel "Rekapitulasi Data": NO, Nama, Jenis Kelamin, Jabatan, Teknis SAR,
   Pengetahuan Umum, Kemampuan Renang, Kebugaran Jasmani (Nilai Akhir & Predikat dihitung ulang otomatis).
   Hasil dipratinjau dulu, lalu disimpan sebagai data Uji Periodik baru + nilai per kategori. */
const UJI_IMPORT_KAT = [
  {kat:'Teknis SAR',              alias:['TEKNISSAR','TEKNIS']},
  {kat:'Pengetahuan Umum',        alias:['PENGETAHUANUMUM','PU']},
  {kat:'Kebugaran Jasmani',       alias:['KEBUGARANJASMANI','KEBUGARAN']},
  {kat:'Renang & Water Treading', alias:['KEMAMPUANRENANG','RENANG','RENANGWATERTREADING']},
];
let _ujiImportPending = null;
function ujiImportBtnHtml(){
  return `<input type="file" id="uji_import_file" accept=".xlsx,.xls,.csv" style="display:none;" onchange="handleUjiImportFile(this)">
    <button type="button" class="btn btn-ghost ml-auto" title="Import hasil Uji Periodik dari Excel (khusus Admin Utama)" onclick="document.getElementById('uji_import_file').click()">Import Excel</button>`;
}
function handleUjiImportFile(input){
  const file = input.files && input.files[0];
  input.value = '';
  if(!file) return;
  if(!isSuper()){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const isCsv = /\.csv$/i.test(file.name);
  if(!isCsv && typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const reader = new FileReader();
  reader.onload = () => {
    try{
      let rows;
      if(isCsv) rows = parseCsvText(decodeCsvBuffer(reader.result));
      else {
        const wb = XLSX.read(reader.result, {type:'array'});
        let ws = null;
        for(const n of wb.SheetNames){
          const r = XLSX.utils.sheet_to_json(wb.Sheets[n], {header:1, defval:'', raw:false});
          if(r.some(x=>x.some(c=>normLabel(c)==='NAMA'))){ ws = r; break; }
        }
        rows = ws || XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], {header:1, defval:'', raw:false});
      }
      prepareUjiImport(rows, file.name);
    }catch(e){ console.error(e); toast('Gagal membaca file Excel'); }
  };
  reader.onerror = () => toast('Gagal membaca file');
  reader.readAsArrayBuffer(file);
}
function prepareUjiImport(rows, fileName){
  const hi = rows.findIndex(r=>r.some(c=>normLabel(c)==='NAMA'));
  if(hi<0){ toast('Header kolom Nama tidak ditemukan di file Excel'); return; }
  const sub = rows[hi+1] || [];
  const width = Math.max(rows[hi].length, sub.length);
  let iNama=-1, iJk=-1, iJab=-1; const katCol = {};
  for(let j=0;j<width;j++){
    [normLabel(rows[hi][j]), normLabel(sub[j])].forEach(l=>{
      if(!l) return;
      if(l==='NAMA' && iNama<0) iNama = j;
      else if(['JENISKELAMIN','JK','KELAMIN','LP'].includes(l) && iJk<0) iJk = j;
      else if(l==='JABATAN' && iJab<0) iJab = j;
      else { const k = UJI_IMPORT_KAT.find(x=>x.alias.includes(l)); if(k && !(j in katCol) && !Object.values(katCol).includes(k.kat)) katCol[j] = k.kat; }
    });
  }
  if(iNama<0){ toast('Kolom Nama tidak ditemukan'); return; }
  if(!Object.keys(katCol).length){ toast('Kolom nilai (Teknis SAR, Pengetahuan Umum, Kemampuan Renang, Kebugaran Jasmani) tidak ditemukan'); return; }
  const num = v => {
    const t = String(v==null?'':v).trim().replace('%','').replace(',','.');
    if(t==='' || t==='-') return null;
    const n = parseFloat(t);
    return (isNaN(n) || n<0 || n>100) ? NaN : n;
  };
  const out = []; let dilewati = 0, tidakValid = 0;
  for(let i=hi+1; i<rows.length; i++){
    const r = rows[i];
    const nama = String(r[iNama]||'').trim();
    if(!nama || /^[\d.,%\s]*$/.test(nama)) continue;           // baris kosong / nomor / baris persen bobot
    const o = {nama, jabatan: iJab>=0 ? String(r[iJab]||'').trim() : '', jk:'', nilai:{}};
    if(iJk>=0){ const c = String(r[iJk]||'').trim().charAt(0).toUpperCase(); o.jk = (c==='L'||c==='P') ? c : ''; }
    let ada = false;
    Object.keys(katCol).forEach(j=>{
      const v = num(r[j]);
      if(Number.isNaN(v)){ tidakValid++; o.nilai[katCol[j]] = null; }
      else { o.nilai[katCol[j]] = v; if(v!==null) ada = true; }
    });
    if(!ada){ dilewati++; continue; }
    const ang = (DB.anggota||[]).find(a=>normNama(a.nama)===normNama(nama));
    o.anggota_id = ang ? ang.id : '';
    if(ang && !o.jabatan) o.jabatan = ang.jabatan||'';
    const lengkap = UJI_BOBOT.every(b=>o.nilai[b.kat]!==null && o.nilai[b.kat]!==undefined);
    o.akhir = lengkap ? UJI_BOBOT.reduce((t,b)=>t+o.nilai[b.kat]*b.w,0) : null;
    out.push(o);
  }
  if(!out.length){ toast('Tidak ada data peserta bernilai di file Excel'); return; }
  // Nama uji bawaan dari judul file: "... SEMESTER I TAHUN 2026" -> "UDIK S1 2026"
  const judul = rows.slice(0,hi).map(r=>r.join(' ')).join(' ');
  const ms = /SEMESTER\s+(II|I|1|2)\b/i.exec(judul), my = /\b(20\d{2})\b/.exec(judul);
  const namaDef = (ms && my) ? 'UDIK S'+(/^(II|2)$/i.test(ms[1]) ? 2 : 1)+' '+my[1] : String(fileName||'').replace(/\.[^.]+$/,'');
  _ujiImportPending = out;
  const katCols = UJI_BOBOT.map(b=>b);
  openModal(`
    <div class="p-5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-2">
        <h3 class="font-display font-semibold text-base">Hasil Import Excel — Uji Periodik</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="_ujiImportPending=null; closeModal()">✕</button>
      </div>
      <p class="text-sm mb-1">Ditemukan <b>${out.length}</b> peserta bernilai${dilewati ? ` · ${dilewati} baris tanpa nilai dilewati` : ''}${tidakValid ? ` · ${tidakValid} nilai tidak valid (di luar 0–100) dikosongkan` : ''}.</p>
      <p class="text-xs mb-3" style="color:var(--muted);">Periksa tabel di bawah. Nilai Akhir &amp; Predikat dihitung ulang otomatis dari bobot (Teknis SAR 25% + PU 10% + Kebugaran 40% + Renang 25%).</p>
      <div class="grid sm:grid-cols-2 gap-3 mb-3">
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Nama Uji Periodik</label><input type="text" id="ujiImp_nama" value="${esc(namaDef)}"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Tanggal Mulai</label><input type="date" id="ujiImp_tgl" value="${esc(todayISO())}"></div>
      </div>
      <div class="tablewrap" style="max-height:320px; overflow-y:auto;"><table>
        <thead><tr><th>No</th><th>Nama</th><th class="text-center">JK</th><th>Jabatan</th>${katCols.map(b=>`<th>${esc(b.head)}</th>`).join('')}<th>Nilai Akhir</th><th>Predikat</th><th>Anggota</th></tr></thead>
        <tbody>${out.map((o,i)=>`<tr>
          <td>${i+1}</td><td class="font-medium">${esc(o.nama)}</td><td class="text-center">${esc(o.jk)||'-'}</td><td>${esc(o.jabatan)||'-'}</td>
          ${katCols.map(b=>`<td>${fmtNilai(o.nilai[b.kat])}</td>`).join('')}
          <td class="font-semibold">${o.akhir===null ? '<span class="text-xs" style="color:var(--muted);font-weight:400;">Belum lengkap</span>' : fmtNilai(o.akhir)}</td>
          <td>${ujkPredikatBadge(ujkPredikat(o.akhir))}</td>
          <td>${o.anggota_id ? '<span class="badge b-ok">Terdaftar</span>' : '<span class="badge b-warn">Belum terdaftar</span>'}</td>
        </tr>`).join('')}</tbody></table></div>
      <div class="flex justify-end gap-2 mt-4">
        <button class="btn btn-ghost" onclick="_ujiImportPending=null; closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="applyUjiImport()">Simpan ${out.length} Peserta</button>
      </div>
    </div>`, true);
}
function applyUjiImport(){
  if(!isSuper()){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const list = _ujiImportPending; if(!list) return;
  const nama = (document.getElementById('ujiImp_nama').value||'').trim();
  const tgl = document.getElementById('ujiImp_tgl').value || todayISO();
  if(!nama){ toast('Nama Uji Periodik wajib diisi'); return; }
  if((DB.uji_periodik||[]).some(r=>normNama(r.nama_uji)===normNama(nama))){ toast('Nama Uji Periodik sudah ada, gunakan nama lain'); return; }
  if(!DB.uji_periodik) DB.uji_periodik = [];
  if(!DB.uji_kategori) DB.uji_kategori = [];
  const ujiId = uid('UPR', DB.uji_periodik);
  DB.uji_periodik.push({id:ujiId, nama_uji:nama, jenis_uji:'Uji Periodik', tanggal_mulai:tgl, tanggal_selesai:tgl,
    jumlah_peserta:list.length, status:'Selesai', sumber:'Import Excel', dibuat:nowStr()});
  list.forEach(o=>{
    UJI_BOBOT.forEach(b=>{
      const v = o.nilai[b.kat]; if(v===null || v===undefined) return;
      DB.uji_kategori.push({id:uid('UKT', DB.uji_kategori), kategori:b.kat, uji_id:ujiId,
        anggota_id:o.anggota_id||'', nama:o.nama, jabatan:o.jabatan||'', jk:o.jk||'',
        nilai_import:v, rata_rata:Math.round(v*100)/100, predikat:ujkPredikat(v), sumber:'Import Excel', dibuat:nowStr()});
    });
  });
  persist();
  _ujiImportPending = null;
  ujiPesertaSelectedId = ujiId;
  closeModal();
  render(currentView);
  toast('Berhasil mengimpor '+list.length+' peserta ke '+nama);
  setTimeout(()=>{ const el = document.getElementById('ujk_peserta_panel'); if(el) el.scrollIntoView({behavior:'smooth', block:'start'}); }, 60);
}

/* ============ Import Excel umum (khusus Admin / Admin Utama) ============
   Modul: Kegiatan/Tugas, Pembinaan Tenaga SAR (tab aktif), Potensi, Narasumber, Peralatan, Kendaraan,
   Peminjaman, Pengajuan (tab aktif). Header kolom dicocokkan dengan label/nama kolom modul, file
   dipratinjau dulu (semua baris ditampilkan), baru disimpan sebagai data baru. */
let _impExcel = null;
function canImportExcel(key){ return isSuper(); } // semua modul: khusus Admin Utama
const IMP_KEGIATAN_FIELDS = [
  {key:'nama', label:'Nama Kegiatan', type:'text', required:true, alias:['NAMA','NAMAKEGIATAN','NAMATUGAS','KEGIATAN','NAMAKEGIATANTUGAS']},
  {key:'jenis', label:'Jenis', type:'text', alias:['JENIS','JENISKEGIATAN']},
  {key:'tanggal', label:'Tanggal', type:'date', alias:['TANGGAL','TANGGALMULAI','TGLMULAI','TANGGALKEGIATAN']},
  {key:'tanggal_selesai', label:'Tanggal Selesai', type:'date', alias:['TANGGALSELESAI','TGLSELESAI','SAMPAI','SAMPAITANGGAL']},
  {key:'waktu', label:'Waktu', type:'time', alias:['WAKTU','JAM']},
  {key:'lokasi', label:'Lokasi', type:'text', alias:['LOKASI','TEMPAT']},
  {key:'penanggung_jawab', label:'Penanggung Jawab', type:'text', alias:['PENANGGUNGJAWAB','PJ']},
  {key:'no_hp', label:'No. HP', type:'text', alias:['NOHP','NOMORHP','HP','TELEPON','NOTELEPON']},
  {key:'deskripsi', label:'Deskripsi', type:'text', alias:['DESKRIPSI','KETERANGAN','KET','URAIAN']},
  {key:'jumlah_dibutuhkan', label:'Anggota Dibutuhkan', type:'number', alias:['JUMLAHDIBUTUHKAN','ANGGOTADIBUTUHKAN','JUMLAHANGGOTA','JUMLAH']},
];
const IMP_SURAT_FIELDS = [
  {key:'nomor_surat', label:'Nomor Surat', type:'text', required:true, alias:['NOMORSURAT','NOSURAT','NOMOR','NO']},
  {key:'asal_surat', label:'Asal Surat', type:'text', alias:['ASALSURAT','PENGIRIM','DARI','ASAL']},
  {key:'tanggal_surat', label:'Tanggal Surat', type:'date', alias:['TANGGALSURAT','TGLSURAT','TANGGALNASKAH','TGLNASKAH']},
  {key:'tanggal_diterima', label:'Tanggal Diterima', type:'date', alias:['TANGGALDITERIMA','TGLDITERIMA','TANGGALMASUK','TGLMASUK']},
  {key:'perihal', label:'Perihal', type:'text', alias:['PERIHAL','HAL']},
  {key:'jenis', label:'Jenis', type:'text', alias:['JENIS','JENISKEGIATAN','SUMBERCLASS']},
  {key:'tanggal_kegiatan', label:'Tanggal Kegiatan', type:'date', alias:['TANGGALKEGIATAN','TGLKEGIATAN','TANGGALPELAKSANAAN']},
  {key:'waktu', label:'Waktu', type:'time', alias:['WAKTU','JAM']},
  {key:'lokasi', label:'Lokasi', type:'text', alias:['LOKASI','TEMPAT']},
  {key:'penanggung_jawab', label:'Penanggung Jawab', type:'text', alias:['PENANGGUNGJAWAB','PJ']},
  {key:'no_hp', label:'No. HP', type:'text', alias:['NOHP','NOMORHP','HP','TELEPON','NOTELEPON']},
  {key:'jumlah_dibutuhkan', label:'Anggota Dibutuhkan', type:'number', alias:['JUMLAHDIBUTUHKAN','ANGGOTADIBUTUHKAN','JUMLAHANGGOTA','JUMLAH']},
  {key:'deskripsi', label:'Isi Ringkas', type:'text', alias:['ISIRINGKAS','DESKRIPSI','KETERANGAN','KET','URAIAN']},
];
function impBtnHtml(key, cls){
  if(!canImportExcel(key)) return '';
  return `<input type="file" id="imp_excel_file" accept=".xlsx,.xls,.csv" style="display:none;" onchange="handleImpExcelFile(this,'${key}')">
    <button type="button" class="btn btn-ghost ${cls||''}" title="Import data dari Excel (khusus Admin Utama)" onclick="document.getElementById('imp_excel_file').click()">Import Excel</button>`;
}
function impKonteks(key){
  if(key==='pembinaan') return {label:'Pembinaan Tenaga SAR — '+pembinaanTab, tab:pembinaanTab};
  if(key==='pemeliharaan') return {label:'Pengajuan '+pengajuanTab, tab:pengajuanTab};
  if(key==='kegiatan') return {label:'Kegiatan/Tugas', tab:''};
  if(key==='surat_masuk') return {label:'Surat Masuk', tab:''};
  if(key==='anggota') return {label:'Daftar Anggota', tab:''};
  const m = TABLE_MODULES.find(x=>x.key===key);
  return {label:m ? m.label : key, tab:''};
}
function impFieldDefs(key){
  if(key==='kegiatan') return IMP_KEGIATAN_FIELDS;
  if(key==='surat_masuk') return IMP_SURAT_FIELDS;
  if(key==='anggota'){
    const extra = {nip:['NIP','NIPID','NRP','NOMORINDUK'], whatsapp:['WHATSAPP','WA','NOWA','NOHP','NOMORHP','HP','NOMORWHATSAPP','NOTELEPON'],
      nama:['NAMA','NAMALENGKAP'], status:['STATUS','STATUSANGGOTA'], keterangan:['KET','KETERANGAN']};
    return ANGGOTA_FIELDS.map(f=>{
      const al = [f.label, f.key].concat(extra[f.key]||[]).map(normLabel).filter(Boolean);
      // WhatsApp tidak wajib saat import (banyak data anggota belum punya nomor); hanya Nama yang wajib
      return Object.assign({}, f, {required: f.key==='nama', alias:Array.from(new Set(al))});
    });
  }
  const m = TABLE_MODULES.find(x=>x.key===key); if(!m) return [];
  const tab = impKonteks(key).tab;
  const defs = m.fields.filter(f=>{
    if(['computed','hidden'].includes(f.type) || f.noForm) return false;
    if(key==='pembinaan') return pbnKatOk(f, tab);
    if(key==='pemeliharaan') return !f.kat || [].concat(f.kat).includes(tab);
    return true;
  }).map(f=>{
    const al = [f.label, f.key, f.labelK].concat(Object.values(f.labels||{})).map(normLabel).filter(Boolean);
    if(f.key==='keterangan') al.push('KET','KETERANGAN');
    return Object.assign({}, f, {alias:Array.from(new Set(al))});
  });
  if(key==='kendaraan') defs.unshift({key:'sapras', label:'Sapras', type:'text', alias:['SAPRAS','KATEGORISAPRAS']});
  return defs;
}
function impTgl(v){
  const s = String(v==null?'':v).trim(); if(!s) return '';
  let m = /^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/.exec(s);
  let y,mo,d;
  if(m){ y=+m[1]; mo=+m[2]; d=+m[3]; }
  else if((m = /^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})/.exec(s))){ d=+m[1]; mo=+m[2]; y=+m[3]; if(y<100) y+=2000; }
  else if((m = /^(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{4})/.exec(s))){
    const i = BULAN_NAMA.findIndex(b=>b.toLowerCase().slice(0,3)===m[2].toLowerCase().slice(0,3));
    if(i<0) return null; d=+m[1]; mo=i+1; y=+m[3];
  } else return null;
  if(mo<1||mo>12||d<1||d>31) return null;
  return y+'-'+String(mo).padStart(2,'0')+'-'+String(d).padStart(2,'0');
}
function impNum(v){
  let t = String(v==null?'':v).trim().replace(/^rp\.?\s*/i,'').replace(/\s/g,''); if(!t) return '';
  if(/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(t)) t = t.replace(/\./g,'').replace(',','.');
  else t = t.replace(',','.');
  return /^-?\d+(\.\d+)?$/.test(t) ? t : null;
}
function impVal(f, raw){
  const s = String(raw==null?'':raw).trim();
  if(!s) return {v:''};
  if(f.type==='date'){ const t = impTgl(s); return t===null ? {v:'', bad:1} : {v:t}; }
  if(f.type==='number'){ const t = impNum(s); return t===null ? {v:'', bad:1} : {v:t}; }
  if(f.type==='time'){ const m = /^(\d{1,2})[:.](\d{2})/.exec(s); return {v: m ? String(m[1]).padStart(2,'0')+':'+m[2] : s}; }
  if(f.type==='select' && f.options){
    const o = f.options.find(x=>normLabel(x)===normLabel(s));
    if(o) return {v:o};
    return f.isStatus ? {v:f.options[0], bad:1} : {v:s};
  }
  return {v:s};
}
function handleImpExcelFile(input, key){
  const file = input.files && input.files[0];
  input.value = '';
  if(!file) return;
  if(!canImportExcel(key)){ toast('Import Excel hanya untuk Admin Utama'); return; }
  if(guardRO(key)) return;
  if(key==='surat_masuk'){ impSuratBaca(file); return; }
  const isCsv = /\.csv$/i.test(file.name);
  if(!isCsv && typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const reader = new FileReader();
  reader.onload = () => {
    try{
      let sheets = [];
      if(isCsv) sheets = [parseCsvText(decodeCsvBuffer(reader.result))];
      else {
        const wb = XLSX.read(reader.result, {type:'array'});
        sheets = wb.SheetNames.map(n=>XLSX.utils.sheet_to_json(wb.Sheets[n], {header:1, defval:'', raw:false, dateNF:'yyyy-mm-dd'}));
      }
      prepareImpExcel(sheets, key, file.name);
    }catch(e){ console.error(e); toast('Gagal membaca file Excel'); }
  };
  reader.onerror = () => toast('Gagal membaca file');
  reader.readAsArrayBuffer(file);
}
function prepareImpExcel(sheets, key, fileName){
  const defs = impFieldDefs(key), kon = impKonteks(key);
  const req = defs.filter(f=>f.required);
  // pilih sheet & baris header dengan kecocokan kolom terbanyak
  let best = null;
  sheets.forEach(rows=>{
    rows.slice(0,20).forEach((r,hi)=>{
      const map = {}, used = new Set();
      r.forEach((c,j)=>{
        const l = normLabel(c); if(!l) return;
        const f = defs.find(d=>!used.has(d.key) && d.alias.includes(l));
        if(f){ map[j] = f; used.add(f.key); }
      });
      const n = Object.keys(map).length;
      if(n && req.every(f=>used.has(f.key)) && (!best || n>best.n)) best = {rows, hi, map, n, header:r};
    });
  });
  if(!best){ toast('Header kolom tidak cocok. Pastikan ada kolom: ' + req.map(f=>f.label).join(', ') + ' (gunakan "Unduh Template")'); return; }
  const {rows, hi, map, header} = best;
  const cols = Object.keys(map).map(Number).sort((a,b)=>a-b);
  const diabaikan = header.map((c,j)=>[c,j]).filter(([c,j])=>String(c||'').trim() && !(j in map)).map(([c])=>String(c).trim());
  const existing = key==='kegiatan' ? (DB.kegiatan||[]) : (DB[key]||[]);
  const nameF = req[0], tglF = defs.find(f=>f.key==='tanggal');
  const out = []; let kosong = 0, tanpaNama = 0, nilaiBad = 0;
  for(let i=hi+1; i<rows.length; i++){
    const r = rows[i];
    if(!r.some(c=>String(c==null?'':c).trim())) { kosong++; continue; }
    const o = {}; let bad = 0;
    cols.forEach(j=>{ const f = map[j]; const x = impVal(f, r[j]); o[f.key] = x.v; if(x.bad) bad++; });
    if(!String(o[nameF.key]||'').trim()){ tanpaNama++; continue; }
    nilaiBad += bad;
    const dup = existing.some(e => {
      if(key==='anggota'){
        const nipB = String(o.nip||'').replace(/\s/g,'');
        return nipB ? String(e.nip||'').replace(/\s/g,'')===nipB : normNama(e.nama)===normNama(o.nama);
      }
      const en = key==='kegiatan' ? e.nama : e[nameF.key];
      return normNama(en)===normNama(o[nameF.key]) && (!tglF || String(e.tanggal||'')===String(o.tanggal||''))
        && (key!=='pembinaan' || pbnKat(e)===kon.tab) && (key!=='pemeliharaan' || pgjKat(e)===kon.tab)
        && (key!=='peralatan' || normNama(e.lokasi||'KPP Jakarta')===normNama(o.lokasi||'KPP Jakarta'))
        && (key!=='kendaraan' || normNama(e.nomor_polisi)===normNama(o.nomor_polisi))
        && (key!=='peminjaman' || normNama(e.nama_barang)===normNama(o.nama_barang));
    });
    out.push({o, dup, bad});
  }
  if(!out.length){ toast('Tidak ada data yang dapat diimpor dari file Excel'); return; }
  _impExcel = {key, rows:out, cols:cols.map(j=>map[j]), fileName};
  const show = _impExcel.cols.slice(0, 8);
  const baru = out.filter(x=>!x.dup).length;
  openModal(`
    <div class="p-5 max-h-[90vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-2">
        <h3 class="font-display font-semibold text-base">Hasil Import Excel — ${esc(kon.label)}</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="_impExcel=null; closeModal()">✕</button>
      </div>
      <p class="text-sm mb-1">File <b>${esc(fileName)}</b>: ditemukan <b>${out.length}</b> baris data${baru!==out.length ? ` · <b>${out.length-baru}</b> sudah ada di modul` : ''}${tanpaNama ? ` · ${tanpaNama} baris tanpa ${esc(nameF.label)} dilewati` : ''}${nilaiBad ? ` · ${nilaiBad} nilai tidak valid dikosongkan` : ''}.</p>
      ${diabaikan.length ? `<p class="text-xs mb-1" style="color:var(--muted);">Kolom tidak dikenali (diabaikan): ${esc(diabaikan.join(', '))}</p>` : ''}
      <p class="text-xs mb-3" style="color:var(--muted);">Periksa tabel di bawah${_impExcel.cols.length>show.length ? ` (menampilkan ${show.length} dari ${_impExcel.cols.length} kolom, semuanya tetap disimpan)` : ''}, lalu klik Simpan. Data masuk sebagai data baru.</p>
      <div class="tablewrap" style="max-height:340px; overflow-y:auto;"><table>
        <thead><tr><th>No</th>${show.map(f=>`<th>${esc(f.label)}</th>`).join('')}<th>Keterangan</th></tr></thead>
        <tbody>${out.map((x,i)=>`<tr>
          <td>${i+1}</td>${show.map(f=>`<td>${esc(f.type==='date' ? tglDMY(x.o[f.key]) : x.o[f.key])||'-'}</td>`).join('')}
          <td>${x.dup ? '<span class="badge b-warn">Sudah ada</span>' : '<span class="badge b-ok">Baru</span>'}${x.bad ? ' <span class="badge b-warn">Nilai tidak valid</span>' : ''}</td>
        </tr>`).join('')}</tbody></table></div>
      <label class="flex items-center gap-2 text-sm mt-3"><input type="checkbox" id="imp_skip_dup" ${out.some(x=>x.dup)?'checked':''} style="width:auto;"> Lewati data yang sudah ada</label>
      <div class="flex flex-wrap justify-between gap-2 mt-4">
        <button class="btn btn-ghost" onclick="unduhTemplateImp('${key}')">Unduh Template</button>
        <div class="flex gap-2">
          <button class="btn btn-ghost" onclick="_impExcel=null; closeModal()">Batal</button>
          <button class="btn btn-primary" onclick="applyImpExcel()">Simpan Data</button>
        </div>
      </div>
    </div>`, true);
}
function unduhTemplateImp(key){
  if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const defs = impFieldDefs(key).filter(f=>f.key!=='sapras' || key==='kendaraan');
  const ws = XLSX.utils.aoa_to_sheet([defs.map(f=>f.label)]);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Template');
  XLSX.writeFile(wb, 'Template_Import_'+impKonteks(key).label.replace(/[^A-Za-z0-9]+/g,'_')+'.xlsx');
}
function applyImpExcel(){
  const P = _impExcel; if(!P) return;
  if(!canImportExcel(P.key)){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const key = P.key, kon = impKonteks(key);
  const skipDup = !!(document.getElementById('imp_skip_dup') && document.getElementById('imp_skip_dup').checked);
  const list = P.rows.filter(x=>!(skipDup && x.dup));
  if(!list.length){ toast('Tidak ada data baru untuk disimpan'); return; }
  const now = new Date();
  let ok = 0, stok = 0;
  list.forEach(x=>{
    const d = Object.assign({}, x.o);
    if(key==='surat_masuk'){
      if(!DB.surat_masuk) DB.surat_masuk = [];
      const jk = normLabel(d.jenis);
      const gm = GENERIC_MODULES.find(m=>normLabel(m.key)===jk || normLabel(m.label)===jk) || GENERIC_MODULES.find(m=>m.key==='lainnya') || GENERIC_MODULES[GENERIC_MODULES.length-1];
      DB.surat_masuk.push({
        id: uid('SM', DB.surat_masuk),
        nomor_surat: d.nomor_surat, asal_surat: d.asal_surat||'',
        tanggal_surat: d.tanggal_surat||'', tanggal_diterima: d.tanggal_diterima || d.tanggal_surat || todayISO(),
        perihal: d.perihal || '-', jenis: gm.key, jenis_label: gm.label,
        tanggal_kegiatan: d.tanggal_kegiatan||'', waktu: d.waktu||'', lokasi: d.lokasi||'',
        penanggung_jawab: d.penanggung_jawab||'', no_hp: d.no_hp||'',
        jumlah_dibutuhkan: parseInt(d.jumlah_dibutuhkan)||1, deskripsi: d.deskripsi||'',
        file_pdf: null, status: 'Menunggu Persetujuan', acc: [], keterangan_persetujuan: '', kegiatan_id: '',
        diajukan_oleh: pelaku(), sumber: 'Import Excel', dibuat: nowStr(), diperbarui: nowStr(),
      });
      ok++; return;
    }
    if(key==='kegiatan'){
      DB.kegiatan = DB.kegiatan || [];
      const jk = normLabel(d.jenis);
      const gm = GENERIC_MODULES.find(m=>normLabel(m.key)===jk || normLabel(m.label)===jk) || GENERIC_MODULES.find(m=>m.key==='lainnya');
      const tgl = d.tanggal || '';
      const row = {
        id: uid('KGT', DB.kegiatan),
        nomor: (DB.kegiatan.length+1).toString().padStart(3,'0') + '/KGT/' + now.getFullYear(),
        jenis: gm.key, jenis_label: gm.label,
        nama: d.nama, tanggal: tgl, tanggal_selesai: (d.tanggal_selesai && d.tanggal_selesai>=tgl) ? d.tanggal_selesai : tgl, waktu: d.waktu||'',
        lokasi: d.lokasi||'', penanggung_jawab: d.penanggung_jawab||'', no_hp: d.no_hp||'',
        deskripsi: d.deskripsi||'', jumlah_dibutuhkan: parseInt(d.jumlah_dibutuhkan)||1,
        status_persetujuan:'Disetujui', keterangan_persetujuan:'Data hasil Import Excel — tidak memerlukan persetujuan atasan.', tanpa_acc:true, anggota_ids:[],
        diajukan_oleh: pelaku(),
        status_pelaksanaan:'Draft', status_wa:'Belum Terkirim', wa_log:[], dokumentasi:'',
        file_pdf: null, sumber:'Import Excel',
        dibuat: nowStr(), diperbarui: nowStr(),
      };
      DB.kegiatan.push(row);
      const linkedId = autoFillModuleFromKegiatan(gm.key, row);
      if(linkedId) row.linked_record_id = linkedId;
      ok++; return;
    }
    const m = key==='anggota' ? {prefix:'AG', fields:ANGGOTA_FIELDS} : TABLE_MODULES.find(z=>z.key===key);
    const rows = DB[key] || (DB[key]=[]);
    if(key==='pembinaan') d.kategori_pembinaan = kon.tab;
    if(key==='kendaraan'){
      const s = normLabel(d.sapras);
      d.sapras = s.includes('LAUT') ? 'Sapras Laut' : (s.includes('DARAT') ? 'Sapras Darat'
        : (saprasFilter || Object.keys(SAPRAS_JENIS).find(k=>SAPRAS_JENIS[k].includes(d.jenis_kendaraan)) || 'Sapras Darat'));
    }
    if(key==='peminjaman'){
      if(!d.jumlah) d.jumlah = 1;
      if(d.tanggal_kembali) d.status = 'Dikembalikan';
      else if(!d.status || d.status==='Dikembalikan') d.status = 'Dipinjam';
      if(!d.jenis_barang) d.jenis_barang = 'Peralatan';
      if(d.jenis_barang==='Peralatan' && d.status!=='Dikembalikan'){
        const alat = (DB.peralatan||[]).find(a=>normNama(a.nama_barang)===normNama(d.nama_barang));
        if(alat){
          const sisa = peralatanBaik(alat) - peralatanDipinjam(alat.nama_barang) - peralatanDipelihara(alat.nama_barang);
          if((parseFloat(d.jumlah)||1) > sisa){ stok++; return; }
        }
      }
    }
    if(key==='pemeliharaan'){
      d.kategori_pengajuan = kon.tab;
      if(!d.jenis_barang) d.jenis_barang = 'Peralatan';
      if(kon.tab==='Kebutuhan'){ if(!d.jumlah_kebutuhan) d.jumlah_kebutuhan = 1; }
      else {
        if(d.tanggal_selesai) d.status = 'Selesai';
        else if(!d.status) d.status = 'Dijadwalkan';
        if(d.jenis_barang!=='Kendaraan') d.km_saat_ini = '';
        if(d.jenis_barang==='Kendaraan') d.jumlah = '';
        else {
          if(!d.jumlah) d.jumlah = 1;
          if(d.status==='Sedang Dikerjakan'){
            const alat = (DB.peralatan||[]).find(a=>normNama(a.nama_barang)===normNama(d.nama_barang));
            if(alat){
              const sisa = peralatanBaik(alat) - peralatanDipinjam(alat.nama_barang) - peralatanDipelihara(alat.nama_barang);
              if((parseFloat(d.jumlah)||1) > sisa){ stok++; return; }
            }
          }
        }
      }
      d.status_persetujuan = 'Menunggu Persetujuan'; d.diajukan_oleh = pelaku(); d.diajukan_waktu = nowStr();
    }
    m.fields.filter(f=>f.isStatus && !d[f.key] && f.options && !(key==='pemeliharaan' && kon.tab==='Kebutuhan')).forEach(f=>{ d[f.key] = f.options[0]; });
    d.id = uid(m.prefix, rows);
    d.tahun_input = now.getFullYear(); d.bulan_input = now.getMonth()+1;
    d.tanggal_input = now.toLocaleDateString('sv-SE');
    d.diperbarui_oleh = pelaku(); d.diperbarui_waktu = nowStr(); d.sumber = 'Import Excel';
    rows.push(d);
    if(key==='pemeliharaan') syncKendaraanDariPemeliharaan(d);
    if(key==='pembinaan' && kon.tab==='Samapta') pbnAbsSync(DB, d);
    ok++;
  });
  if(!ok){ toast(stok ? 'Tidak ada data tersimpan: stok peralatan tidak mencukupi' : 'Tidak ada data tersimpan'); return; }
  persist();
  _impExcel = null;
  closeModal();
  if(key==='kegiatan') kegTab = 'kegiatan';
  if(key==='surat_masuk') kegTab = 'surat';
  render(currentView);
  toast('Berhasil mengimpor '+ok+' data ke '+kon.label + (stok ? ' ('+stok+' dilewati: stok tidak cukup)' : ''));
}

/* ============ Import Excel Persuratan: Surat Masuk + Surat Keluar (khusus Admin Utama) ============
   Dipakai dari Kegiatan/Tugas (tab Surat Masuk) dan Persuratan (tab Surat Masuk / Surat Keluar).
   - Membaca SEMUA sheet & SEMUA baris/kolom file (sheet "Surat Masuk" -> Surat Masuk, sheet "Surat Keluar" -> Surat Keluar).
   - Kolom yang tidak dikenali / tanpa judul tetap disimpan (tidak ada data yang dibuang).
   - Hanya menambah data baru: data yang sudah terinput tidak diubah/dihapus; baris yang sudah ada dilewati.
   - Data hasil import tidak memerlukan persetujuan atasan (tanpa ACC, tidak membuat Kegiatan otomatis). */
let _impSurat = null;
const IMP_KET_TANPA_ACC = 'Data hasil Import Excel — tidak memerlukan persetujuan atasan.';
const BLN_IMPOR = {januari:1,jan:1,februari:2,feb:2,pebruari:2,maret:3,mar:3,april:4,apr:4,mei:5,juni:6,jun:6,juli:7,jul:7,
  agustus:8,agu:8,agt:8,ags:8,september:9,sep:9,sept:9,oktober:10,okt:10,november:11,nov:11,nopember:11,desember:12,des:12};
function impBersih(v){ return String(v==null?'':v).replace(/[\u00a0\u200b]/g,' ').replace(/\s+/g,' ').trim(); }
function impBuangLabel(v, labels){
  const re = new RegExp('^(?:' + labels.join('|') + ')\\s*:\\s*', 'i');
  return impBersih(v).replace(re, '').trim();
}
function impHurufKolom(j){ let n = j+1, t = ''; while(n>0){ const r = (n-1)%26; t = String.fromCharCode(65+r) + t; n = Math.floor((n-1)/26); } return t; }
/* "Rabu, 7 Januari 2026 pukul 14.42 WIB" -> {iso:'2026-01-07', jam:'14:42'} */
function impTglIndo(v){
  const s = impBersih(v); if(!s) return {iso:'', jam:'', bad:false};
  let iso = '';
  const m = /(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{4})/.exec(s);
  if(m){ const mo = BLN_IMPOR[m[2].toLowerCase()]; if(mo) iso = m[3] + '-' + String(mo).padStart(2,'0') + '-' + String(+m[1]).padStart(2,'0'); }
  if(!iso){ const t = impTgl(s); if(t) iso = t; }
  const j = /pukul\s*(\d{1,2})[.:](\d{2})/i.exec(s) || /\b(\d{1,2}):(\d{2})(?::\d{2})?\b/.exec(s);
  return {iso, jam: j ? String(j[1]).padStart(2,'0') + ':' + j[2] : '', bad: !iso};
}
function impSuratBtnHtml(cls){
  if(!canImportExcel('surat_masuk')) return '';
  return `<input type="file" id="imp_surat_file" accept=".xlsx,.xls,.csv" style="display:none;" onchange="handleImpSuratFile(this)">
    <button type="button" class="btn btn-ghost ${cls||''}" title="Import Surat Masuk & Surat Keluar dari Excel (khusus Admin Utama)" onclick="document.getElementById('imp_surat_file').click()">Import Excel</button>`;
}
function handleImpSuratFile(input){
  const file = input.files && input.files[0];
  input.value = '';
  if(file) impSuratBaca(file);
}
function impSuratBaca(file){
  if(!canImportExcel('surat_masuk')){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const isCsv = /\.csv$/i.test(file.name);
  if(!isCsv && typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const reader = new FileReader();
  reader.onload = () => {
    try{
      let sheets = [];
      if(isCsv) sheets = [{name: file.name, rows: parseCsvText(decodeCsvBuffer(reader.result))}];
      else {
        const wb = XLSX.read(reader.result, {type:'array'});
        sheets = wb.SheetNames.map(n=>({name:n, rows: XLSX.utils.sheet_to_json(wb.Sheets[n], {header:1, defval:'', raw:false, dateNF:'yyyy-mm-dd'})}));
      }
      impSuratSiapkan(sheets, file.name);
    }catch(e){ console.error(e); toast('Gagal membaca file Excel'); }
  };
  reader.onerror = () => toast('Gagal membaca file');
  reader.readAsArrayBuffer(file);
}
const IMP_SM_ALIAS = {
  no:['NO','NOURUT','NOMORURUT'],
  tgl_masuk:['TANGGALMASUK','TGLMASUK','TANGGALDITERIMA','TGLDITERIMA','TANGGALDISPOSISI','TGLDISPOSISI'],
  tgl_naskah:['TANGGALNASKAH','TGLNASKAH','TANGGALSURAT','TGLSURAT'],
  dari:['DARI','ASALSURAT','ASAL','PENGIRIM'],
  batas:['BATASWAKTU','BATAS'],
  nomor:['NOMORSURAT','NOMORNASKAH','NOSURAT','NONASKAH'],
  hal:['HAL','PERIHAL'],
  status:['STATUS','STATUSSURAT'],
  instruksi:['TAMBAHANINSTRUKSIKKS','INSTRUKSIKKS','INSTRUKSI','TAMBAHANINSTRUKSI'],
};
const IMP_SK_ALIAS = {
  no:['NO','NOURUT','NOMORURUT'],
  tgl:['TANGGAL','TGL','TANGGALSURAT','TGLSURAT'],
  nomor:['NOMORSURAT','NOSURAT','NOMOR'],
  uraian:['URAIANKEGIATAN','URAIAN','PERIHAL','HAL'],
  tujuan:['TUJUAN','KEPADA'],
  status:['STATUS','STATUSKIRIM','KETERANGAN'],
};
function impPetaKolom(rows, alias, wajib){
  let best = null;
  rows.slice(0, 25).forEach((r, hi)=>{
    const map = {}, used = new Set();
    r.forEach((c, j)=>{
      const l = normLabel(c); if(!l) return;
      const k = Object.keys(alias).find(x=>!used.has(x) && alias[x].includes(l));
      if(k){ map[k] = j; used.add(k); }
    });
    const n = Object.keys(map).length;
    if(wajib.every(k=>k in map) && (!best || n>best.n)) best = {hi, map, n, header:r};
  });
  return best;
}
/* Kolom di luar peta (judul tidak dikenal atau tanpa judul) yang berisi data tetap diambil. */
function impKolomLain(rows, best){
  const dipakai = new Set(Object.values(best.map));
  const out = [];
  const lebar = Math.max(best.header.length, ...rows.slice(best.hi+1).map(r=>r.length), 0);
  for(let j=0; j<lebar; j++){
    if(dipakai.has(j)) continue;
    const ada = rows.slice(best.hi+1).some(r=>impBersih(r[j]));
    if(!ada) continue;
    const judul = impBersih(best.header[j]);
    out.push({j, key:'kolom_' + impHurufKolom(j).toLowerCase(), label: judul || ('Kolom ' + impHurufKolom(j) + ' (tanpa judul)')});
  }
  return out;
}
function impSuratSiapkan(sheets, fileName){
  const masuk = [], keluar = [], info = [];
  let kosong = 0, tanpaIsi = 0, nilaiBad = 0;
  const kolomLabel = {};
  const sudahMasuk = new Set((DB.surat_masuk||[]).map(e=>impKunciMasuk(e.nomor_surat, e.tanggal_diterima, e.jam_diterima, e.perihal)));
  const sudahKeluar = new Set((DB.surat_keluar||[]).map(e=>impKunciKeluar(e.nomor_surat, e.perihal)));
  sheets.forEach(sh=>{
    const rows = sh.rows || [];
    const nm = String(sh.name||'').toLowerCase();
    let tipe = /keluar/.test(nm) ? 'keluar' : (/masuk/.test(nm) ? 'masuk' : '');
    let best = null;
    if(tipe!=='keluar'){ const b = impPetaKolom(rows, IMP_SM_ALIAS, ['nomor','hal']); if(b && (tipe==='masuk' || ('dari' in b.map) || ('tgl_naskah' in b.map))){ best = b; tipe = 'masuk'; } }
    if(!best && tipe!=='masuk'){ const b = impPetaKolom(rows, IMP_SK_ALIAS, ['nomor','uraian']); if(b){ best = b; tipe = 'keluar'; } }
    if(!best){ info.push('Sheet "' + sh.name + '" dilewati (kolom Nomor Surat / Hal tidak ditemukan)'); return; }
    const c = best.map, lain = impKolomLain(rows, best);
    const g = (r, k) => (k in c) ? r[c[k]] : '';
    const dataRows = rows.slice(best.hi + 1);
    let jml = 0;
    dataRows.forEach(r=>{
      if(!r.some(x=>impBersih(x))){ kosong++; return; }
      if(tipe==='masuk'){
        const dis = impTglIndo(impBuangLabel(g(r,'tgl_masuk'), ['Tanggal disposisi','Tanggal masuk','Tanggal diterima']));
        const nas = impTglIndo(impBuangLabel(g(r,'tgl_naskah'), ['Tanggal naskah','Tanggal surat']));
        const bat = impTglIndo(impBuangLabel(g(r,'batas'), ['Batas waktu','Batas']));
        const o = {
          no: impBersih(g(r,'no')),
          tanggal_diterima: dis.iso, jam_diterima: dis.jam, disposisi_teks: impBuangLabel(g(r,'tgl_masuk'), ['Tanggal disposisi','Tanggal masuk','Tanggal diterima']),
          tanggal_surat: nas.iso, naskah_teks: impBuangLabel(g(r,'tgl_naskah'), ['Tanggal naskah','Tanggal surat']),
          batas_waktu: bat.iso, batas_jam: (bat.jam && bat.jam!=='00:00') ? bat.jam : '', batas_teks: impBuangLabel(g(r,'batas'), ['Batas waktu','Batas']),
          asal_surat: impBuangLabel(g(r,'dari'), ['Dari']),
          nomor_surat: impBuangLabel(g(r,'nomor'), ['Nomor naskah','Nomor surat']),
          perihal: impBuangLabel(g(r,'hal'), ['Hal','Perihal']),
          status_surat: impBersih(g(r,'status')), instruksi_kks: impBersih(g(r,'instruksi')),
          lain: {}
        };
        if(!o.nomor_surat && !o.perihal){ tanpaIsi++; return; }
        lain.forEach(k=>{ const v = impBersih(r[k.j]); if(v){ o.lain[k.key] = v; kolomLabel[k.key] = k.label; } });
        const bad = (dis.bad?1:0) + (nas.bad?1:0) + (bat.bad?1:0);
        nilaiBad += bad;
        const kunci = impKunciMasuk(o.nomor_surat, o.tanggal_diterima, o.jam_diterima, o.perihal);
        const dup = sudahMasuk.has(kunci); // hanya dibandingkan dengan data yang sudah ada di aplikasi (baris berulang di dalam file tetap diimpor semua)
        masuk.push({o, dup, bad}); jml++;
      } else {
        const tg = impTglIndo(g(r,'tgl'));
        const o = {
          no: impBersih(g(r,'no')), tanggal_surat: tg.iso, tanggal_teks: impBersih(g(r,'tgl')),
          nomor_surat: impBersih(g(r,'nomor')), perihal: impBersih(g(r,'uraian')), tujuan: impBersih(g(r,'tujuan')),
          kirim: '', lain: {}
        };
        if(!o.nomor_surat && !o.perihal){ tanpaIsi++; return; }
        if('status' in c) o.kirim = impBersih(g(r,'status'));
        lain.forEach(k=>{ const v = impBersih(r[k.j]); if(v){ o.lain[k.key] = v; kolomLabel[k.key] = k.label; } });
        nilaiBad += tg.bad ? 1 : 0;
        const kunci = impKunciKeluar(o.nomor_surat, o.perihal);
        const dup = sudahKeluar.has(kunci);
        keluar.push({o, dup, bad: tg.bad ? 1 : 0}); jml++;
      }
    });
    // Surat Keluar: kolom tanpa judul yang isinya status pengiriman (TERKIRIM/BELUM) dibaca sebagai Status Kirim
    if(tipe==='keluar' && !('status' in c)){
      const baru = keluar.slice(keluar.length - jml);
      const kunci = lain.find(k=>{ const vs = baru.map(x=>x.o.lain[k.key]).filter(Boolean); return vs.length && vs.every(v=>/^(TERKIRIM|DIKIRIM|BELUM|BELUM TERKIRIM|SUDAH|SELESAI)$/i.test(v)); });
      if(kunci){ baru.forEach(x=>{ x.o.kirim = x.o.lain[kunci.key] || ''; delete x.o.lain[kunci.key]; }); delete kolomLabel[kunci.key]; }
    }
    info.push('Sheet "' + sh.name + '" dibaca sebagai Surat ' + (tipe==='masuk' ? 'Masuk' : 'Keluar') + ': ' + jml + ' baris');
  });
  if(!masuk.length && !keluar.length){ toast('Tidak ada data surat yang dapat diimpor. Pastikan ada kolom Nomor Surat dan Hal/Uraian (gunakan "Unduh Template")'); return; }
  _impSurat = {masuk, keluar, fileName, kolomLabel};
  impSuratPratinjau(info, {kosong, tanpaIsi, nilaiBad});
}
function impKunciMasuk(nomor, tgl, jam, perihal){ return normLabel(nomor) + '|' + String(tgl||'') + '|' + String(jam||'') + '|' + normLabel(perihal); }
function impKunciKeluar(nomor, perihal){ return normLabel(nomor) + '|' + normLabel(perihal); }
function impSuratPratinjau(info, st){
  const P = _impSurat;
  const bm = P.masuk.filter(x=>!x.dup).length, bk = P.keluar.filter(x=>!x.dup).length;
  const ada = P.masuk.length - bm + P.keluar.length - bk;
  const kolLain = Object.keys(P.kolomLabel);
  const badge = x => (x.dup ? '<span class="badge b-warn">Sudah ada</span>' : '<span class="badge b-ok">Baru</span>') + (x.bad ? ' <span class="badge b-warn">Tanggal tidak terbaca</span>' : '');
  const tglJam = (t, j) => (tglDMY(t) || '-') + (j ? ' ' + j : '');
  const tblMasuk = P.masuk.length ? `
    <p class="font-medium text-sm mt-3 mb-1">Surat Masuk — ${P.masuk.length} baris (${bm} baru)</p>
    <div class="tablewrap" style="max-height:300px; overflow-y:auto;"><table>
      <thead><tr><th>No</th><th>Tgl Diterima</th><th>Tgl Surat</th><th>Asal Surat</th><th>Batas</th><th>Nomor Surat</th><th>Perihal</th><th>Status</th><th>Instruksi KKS</th>${kolLain.filter(k=>P.masuk.some(x=>k in x.o.lain)).map(k=>`<th>${esc(P.kolomLabel[k])}</th>`).join('')}<th>Keterangan</th></tr></thead>
      <tbody>${P.masuk.map((x,i)=>`<tr>
        <td>${esc(x.o.no)||i+1}</td><td class="whitespace-nowrap">${esc(tglJam(x.o.tanggal_diterima, x.o.jam_diterima))}</td><td class="whitespace-nowrap">${esc(tglDMY(x.o.tanggal_surat))||'-'}</td>
        <td style="min-width:180px;">${esc(x.o.asal_surat)||'-'}</td><td class="whitespace-nowrap">${esc(tglJam(x.o.batas_waktu, x.o.batas_jam))}</td>
        <td style="min-width:150px;">${esc(x.o.nomor_surat)||'-'}</td><td style="min-width:220px;">${esc(x.o.perihal)||'-'}</td>
        <td>${esc(x.o.status_surat)||'-'}</td><td>${esc(x.o.instruksi_kks)||'-'}</td>
        ${kolLain.filter(k=>P.masuk.some(y=>k in y.o.lain)).map(k=>`<td>${esc(x.o.lain[k])||'-'}</td>`).join('')}
        <td>${badge(x)}</td></tr>`).join('')}</tbody></table></div>` : '';
  const tblKeluar = P.keluar.length ? `
    <p class="font-medium text-sm mt-4 mb-1">Surat Keluar — ${P.keluar.length} baris (${bk} baru)</p>
    <div class="tablewrap" style="max-height:300px; overflow-y:auto;"><table>
      <thead><tr><th>No</th><th>Tanggal</th><th>Nomor Surat</th><th>Uraian Kegiatan</th><th>Status Kirim</th>${kolLain.filter(k=>P.keluar.some(x=>k in x.o.lain)).map(k=>`<th>${esc(P.kolomLabel[k])}</th>`).join('')}<th>Keterangan</th></tr></thead>
      <tbody>${P.keluar.map((x,i)=>`<tr>
        <td>${esc(x.o.no)||i+1}</td><td class="whitespace-nowrap">${esc(tglDMY(x.o.tanggal_surat))||'-'}</td><td style="min-width:170px;">${esc(x.o.nomor_surat)||'-'}</td>
        <td style="min-width:260px;">${esc(x.o.perihal)||'-'}</td><td>${esc(x.o.kirim)||'-'}</td>
        ${kolLain.filter(k=>P.keluar.some(y=>k in y.o.lain)).map(k=>`<td>${esc(x.o.lain[k])||'-'}</td>`).join('')}
        <td>${badge(x)}</td></tr>`).join('')}</tbody></table></div>` : '';
  openModal(`
    <div class="p-5 max-h-[92vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-2">
        <h3 class="font-display font-semibold text-base">Hasil Import Excel — Persuratan</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="_impSurat=null; closeModal()">✕</button>
      </div>
      <p class="text-sm mb-1">File <b>${esc(P.fileName)}</b>: <b>${P.masuk.length}</b> surat masuk dan <b>${P.keluar.length}</b> surat keluar terbaca${ada ? ` · <b>${ada}</b> sudah ada di aplikasi` : ''}${st.tanpaIsi ? ` · ${st.tanpaIsi} baris tanpa nomor & perihal dilewati` : ''}${st.nilaiBad ? ` · ${st.nilaiBad} tanggal tidak terbaca dikosongkan` : ''}.</p>
      ${info.length ? `<p class="text-xs mb-1" style="color:var(--muted);">${info.map(esc).join(' · ')}</p>` : ''}
      <p class="text-xs mb-1" style="color:var(--muted);">Data yang sudah terinput tidak diubah atau dihapus — import hanya menambah data baru. Data hasil import <b>tidak memerlukan persetujuan atasan</b>.</p>
      ${tblMasuk}${tblKeluar}
      <label class="flex items-center gap-2 text-sm mt-3"><input type="checkbox" id="imp_surat_skip" checked style="width:auto;"> Lewati data yang sudah ada (cegah data ganda)</label>
      <div class="flex flex-wrap justify-between gap-2 mt-4">
        <button class="btn btn-ghost" onclick="unduhTemplateSurat()">Unduh Template</button>
        <div class="flex gap-2">
          <button class="btn btn-ghost" onclick="_impSurat=null; closeModal()">Batal</button>
          <button class="btn btn-primary" onclick="applyImpSurat()">Simpan Data</button>
        </div>
      </div>
    </div>`, true);
}
function unduhTemplateSurat(){
  if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([['No.','Tanggal Masuk','Tanggal Naskah','Dari','Batas Waktu','Nomor Surat','Hal','Status','Tambahan Instruksi KKS']]), 'Surat Masuk');
  XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([['No.','Tanggal','Nomor Surat','Uraian Kegiatan','Status']]), 'Surat Keluar');
  XLSX.writeFile(wb, 'Template_Import_Persuratan.xlsx');
}
function applyImpSurat(){
  const P = _impSurat; if(!P) return;
  if(!canImportExcel('surat_masuk')){ toast('Import Excel hanya untuk Admin Utama'); return; }
  const skip = !!(document.getElementById('imp_surat_skip') && document.getElementById('imp_surat_skip').checked);
  const lm = P.masuk.filter(x=>!(skip && x.dup)), lk = P.keluar.filter(x=>!(skip && x.dup));
  if(!lm.length && !lk.length){ toast('Tidak ada data baru untuk disimpan'); return; }
  if(!Array.isArray(DB.surat_masuk)) DB.surat_masuk = [];
  if(!Array.isArray(DB.surat_keluar)) DB.surat_keluar = [];
  const gm = GENERIC_MODULES.find(m=>m.key==='lainnya') || GENERIC_MODULES[GENERIC_MODULES.length-1];
  const label = {}; // label kolom tambahan (judul kolom di Excel) disimpan bersama data
  lm.forEach(x=>{
    const o = x.o;
    const row = {
      id: uid('SM', DB.surat_masuk),
      nomor_surat: o.nomor_surat || '-', asal_surat: o.asal_surat || '',
      tanggal_surat: o.tanggal_surat || '', tanggal_diterima: o.tanggal_diterima || o.tanggal_surat || todayISO(),
      jam_diterima: o.jam_diterima || '', batas_waktu: o.batas_waktu || '', batas_jam: o.batas_jam || '',
      perihal: o.perihal || '-', jenis: gm.key, jenis_label: gm.label,
      tanggal_kegiatan: '', waktu: '', lokasi: '', penanggung_jawab: '', no_hp: '', jumlah_dibutuhkan: 1, deskripsi: '',
      status_surat: o.status_surat || '', instruksi_kks: o.instruksi_kks || '', no_urut_excel: o.no || '',
      disposisi_teks: o.disposisi_teks || '', naskah_teks: o.naskah_teks || '', batas_teks: o.batas_teks || '',
      file_pdf: null,
      status: 'Disetujui', acc: [], tanpa_acc: true, keterangan_persetujuan: IMP_KET_TANPA_ACC, kegiatan_id: '',
      diajukan_oleh: pelaku(), sumber: 'Import Excel', dibuat: nowStr(), diperbarui: nowStr(),
    };
    const lbl = {};
    Object.keys(o.lain||{}).forEach(k=>{ row[k] = o.lain[k]; lbl[k] = P.kolomLabel[k] || k; });
    if(Object.keys(lbl).length) row.kolom_label = lbl;
    DB.surat_masuk.push(row);
  });
  lk.forEach(x=>{
    const o = x.o;
    const kirim = /^(TERKIRIM|DIKIRIM|SUDAH|SELESAI)$/i.test(o.kirim) ? 'Terkirim' : (o.kirim ? 'Belum Terkirim' : '');
    const row = {
      id: uid('SK', DB.surat_keluar),
      nomor_surat: o.nomor_surat || '-', tujuan: o.tujuan || '', perihal: o.perihal || '-', sifat: '',
      tanggal_surat: o.tanggal_surat || '', tgl_upload: o.tanggal_surat || '',
      status_kirim: kirim, status_proses: 'Belum Di Proses', no_urut_excel: o.no || '', tanggal_teks: o.tanggal_teks || '',
      file_pdf: null, keterangan: '', sumber: 'Import Excel',
      dibuat_oleh: pelaku(), dibuat: nowStr(), diperbarui: nowStr(),
    };
    const lbl = {};
    Object.keys(o.lain||{}).forEach(k=>{ row[k] = o.lain[k]; lbl[k] = P.kolomLabel[k] || k; });
    if(Object.keys(lbl).length) row.kolom_label = lbl;
    DB.surat_keluar.push(row);
  });
  persist();
  _impSurat = null;
  closeModal();
  if(currentView==='persuratan') psTab = (lm.length || !lk.length) ? 'masuk' : 'keluar'; else kegTab = 'surat';
  render(currentView);
  toast('Berhasil mengimpor ' + lm.length + ' surat masuk dan ' + lk.length + ' surat keluar (tanpa persetujuan)');
}
/* Rincian data import (batas waktu, status, instruksi KKS, kolom tambahan) pada detail Surat Masuk */
function suratImportDetailHtml(sm){
  if(sm.sumber!=='Import Excel') return '';
  const lbl = sm.kolom_label || {};
  const lain = Object.keys(sm).filter(k=>/^kolom_[a-z]+$/.test(k) && sm[k]!=='' && sm[k]!=null);
  const row = (t, v) => `<div><span style="color:var(--muted);">${esc(t)}:</span> ${esc(v)||'-'}</div>`;
  return row('Waktu Diterima', sm.jam_diterima ? sm.jam_diterima + ' WIB' : '')
    + row('Batas Waktu', (tglDMY(sm.batas_waktu) || '') + (sm.batas_jam ? ' ' + sm.batas_jam : ''))
    + row('Status Surat', sm.status_surat) + row('Tambahan Instruksi KKS', sm.instruksi_kks)
    + lain.map(k=>row(lbl[k] || k, sm[k])).join('')
    + (sm.no_urut_excel ? row('No. pada Excel', sm.no_urut_excel) : '');
}

function diklatReadOnlyPanelHtml(){
  const rows = diklatRekapSaved();
  const cols = DIKLAT_PESERTA_COLS;
  return `<div class="panel rounded-xl p-4 mt-4">
    <div class="flex items-center gap-2 mb-3"><span class="font-display font-semibold text-sm">Rekap Diklat Anggota</span><span class="text-xs" style="color:var(--muted);">${rows.length} peserta</span></div>
    <div class="tablewrap" style="max-height:520px; overflow-y:auto;"><table>
      <thead><tr><th class="text-center">No</th><th>Nama</th><th>Jabatan</th><th>NIP/Golongan</th>${cols.map(c=>`<th class="text-center">${esc(c.label)}</th>`).join('')}<th>Diklat Lain</th></tr></thead>
      <tbody>${rows.length ? rows.map((r,i)=>`<tr>
        <td class="text-center">${i+1}</td><td class="font-medium">${esc(r.nama)}</td><td>${esc(r.jabatan)||'-'}</td><td>${esc(r.nipgol)||'-'}</td>
        ${cols.map(c=>`<td class="text-center">${r[c.key]?'✓':'-'}</td>`).join('')}
        <td>${esc((Array.isArray(r.diklat_lain_list)?r.diklat_lain_list:[]).map(x=>x.teks).filter(Boolean).join('; '))||'-'}</td></tr>`).join('') : `<tr><td colspan="${cols.length+5}" class="text-center py-6" style="color:var(--muted);">Belum ada data peserta.</td></tr>`}</tbody>
    </table></div></div>`;
}
function diklatMainPanelHtml(key){
  if(key==='diklat' && modulReadOnly('diklat')) return diklatReadOnlyPanelHtml();
  if(key==='uji_periodik') return ujiPesertaPanelHtml();
  if(key==='diklat') return daftarDiklatPanelHtml() + diklatRekapPanelHtml();
  pesertaKey = key;
  const M = PESERTA_MODULES[key];
  const rows = DB[key] || [];
  if(!rows.length){
    return `<div class="panel rounded-xl p-4 mt-4"><p class="text-sm font-display font-semibold mb-1">Data Peserta ${esc(M.label)}</p><p class="text-sm" style="color:var(--muted);">Belum ada data ${esc(M.label)}. Tambahkan data ${esc(M.label)} terlebih dahulu.</p></div>`;
  }
  if(!diklatMainSelectedIds[key] || !rows.find(r=>r.id===diklatMainSelectedIds[key])){
    diklatMainSelectedIds[key] = rows[rows.length-1].id;
  }
  const selected = rows.find(r=>r.id===diklatMainSelectedIds[key]);
  diklatPesertaContext = 'main';
  diklatPesertaRows = Array.isArray(selected.peserta_matrix) ? JSON.parse(JSON.stringify(selected.peserta_matrix)) : [];
  if(key==='uji_periodik') loadUjiCols(selected);
  return `
    <div class="panel rounded-xl p-4 mt-4">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
          <span class="font-display font-semibold text-sm">Data Peserta ${esc(M.label)}</span>
          <select onchange="pesertaKey='${key}'; onDiklatMainSelectChange(this.value)">
            ${rows.map(r=>`<option value="${esc(r.id)}" ${r.id===diklatMainSelectedIds[key]?'selected':''}>${esc(r[M.nameKey])}</option>`).join('')}
          </select>
        </div>
        <div class="flex items-center gap-2">
          ${key==='diklat' ? `<div class="dd">
            <button type="button" class="btn btn-ghost !py-1.5 !px-3 text-xs" onclick="toggleUnduhMenu(event, this)">Unduh <span aria-hidden="true">&#9662;</span></button>
            <div class="dd-menu">
              <button type="button" class="dd-item" onclick="pesertaKey='${key}'; exportPeserta('pdf')">PDF</button>
              <button type="button" class="dd-item" onclick="pesertaKey='${key}'; exportPeserta('excel')">Excel</button>
            </div>
          </div>` : ''}
          <button class="btn btn-primary !py-1.5 !px-3 text-xs" onclick="pesertaKey='${key}'; saveDiklatMainPeserta()">Simpan Data Peserta</button>
        </div>
      </div>
      <div id="diklat_peserta_wrap_main">${diklatPesertaEditorHtml()}</div>
    </div>`;
}

function statusDariKegiatan(key, rec){
  const map = {'Draft':'Direncanakan', 'Dalam Pelaksanaan':'Berlangsung', 'Selesai':'Selesai'};
  const k = kegiatanTerkaitRecord(key, rec).find(x=>map[x.status_pelaksanaan]);
  if(k) return map[k.status_pelaksanaan];
  return ['Direncanakan','Berlangsung','Selesai'].includes(rec && rec.status) ? rec.status : 'Direncanakan';
}
// Status data modul mengikuti Kegiatan/Tugas terkaitnya (terkunci, tidak bisa diubah manual).
function statusDariKegiatanAktif(key, f, rec){
  if(f.autoFromKegiatan) return true;
  return !!(f.isStatus && f.options && f.options.includes('Direncanakan') && rec && kegiatanTerkaitRecord(key, rec).length);
}
function statusFieldValue(key, f, rec){
  return statusDariKegiatanAktif(key, f, rec) ? statusDariKegiatan(key, rec) : (rec ? rec[f.key] : undefined);
}
function statusBadge(val){
  if(val===undefined || val==='') return '';
  const s = String(val);
  let cls='b-info';
  if(/selesai|aktif$|disetujui|layak$|terkirim|baik$|tersedia|siap operasi|dikembalikan/i.test(s) && !/tidak layak|nonaktif/i.test(s)) cls='b-ok';
  else if(/menunggu|draft|direncanakan|dipinjam|perawatan|dikerjakan/i.test(s)) cls='b-warn';
  else if(/ditolak|nonaktif|tidak layak|gagal|rusak|terlambat/i.test(s)) cls='b-bad';
  return `<span class="badge ${cls}">${esc(s)}</span>`;
}

function enhanceTableScroll(){
  document.querySelectorAll('.tablewrap').forEach(wrap=>{
    const table = wrap.querySelector('table');
    if(!table) return;
    const top = document.createElement('div');
    top.className = 'scrolltop';
    const inner = document.createElement('div');
    top.appendChild(inner);
    wrap.parentNode.insertBefore(top, wrap);
    function sync(){
      inner.style.width = table.scrollWidth + 'px';
      top.classList.toggle('hide', table.scrollWidth <= wrap.clientWidth + 1);
    }
    let guard = false;
    top.addEventListener('scroll', ()=>{ if(guard) return; guard=true; wrap.scrollLeft = top.scrollLeft; guard=false; });
    wrap.addEventListener('scroll', ()=>{ if(guard) return; guard=true; top.scrollLeft = wrap.scrollLeft; guard=false; });
    sync();
    window.addEventListener('resize', sync);
  });
}

/* ---- Pencarian: frasa utuh, lintas kolom, fokus input tetap terjaga ---- */
function normSearch(t){ return String(t==null?'':t).toLowerCase().replace(/\s+/g,' ').trim(); }
function rowSearchText(r, fields){
  const parts = [];
  Object.values(r).forEach(v=>{
    if(v==null || typeof v==='object') return;
    parts.push(v);
  });
  if(Array.isArray(r.peserta_matrix)) r.peserta_matrix.forEach(p=>{ if(p){ parts.push(p.nama, p.jabatan); } });
  if(fields) fields.forEach(f=>{ if(f.derivedFrom){ try{ parts.push(fieldValue(f,r)); }catch(e){} } });
  return normSearch(parts.filter(x=>x!=null && x!=='').join(' | '));
}
function matchSearch(r, q, fields){
  const nq = normSearch(q);
  return !nq || rowSearchText(r, fields).includes(nq);
}
function runSearchRender(inputEl, fn){
  const sid = inputEl.getAttribute('data-sid');
  const pos = inputEl.selectionStart;
  fn();
  const n = document.querySelector('[data-sid="'+sid+'"]');
  if(n){ n.focus(); try{ n.setSelectionRange(pos,pos); }catch(e){} }
}
/* ---- Filter Sapras (Kendaraan): Darat / Laut berdasarkan Jenis Kendaraan; klik lagi untuk menampilkan semua ---- */
const SAPRAS_JENIS = {
  'Sapras Darat':['Mobil','Motor','Truk','Ambulans'],
  'Sapras Laut':['Kapal','RIB / Perahu Karet']
};
let saprasFilter = '';
function labelKendaraan(f, laut){ return (f.key==='nomor_polisi' && laut) ? 'No Lambung' : f.label; }
function saprasKategori(r){ return r.sapras || Object.keys(SAPRAS_JENIS).find(k=>SAPRAS_JENIS[k].includes(r.jenis_kendaraan)) || ''; }
function saprasCocok(r){ return !saprasFilter || saprasKategori(r)===saprasFilter; }
function setSaprasFilter(v){ saprasFilter = (saprasFilter===v) ? '' : v; render(currentView); }
function saprasButtonsHtml(){
  const rows = DB.kendaraan || [];
  return `<div class="flex flex-wrap items-center gap-2 mb-3">
    ${Object.keys(SAPRAS_JENIS).map(k=>{
      const n = rows.filter(r=>saprasKategori(r)===k).length;
      return `<button type="button" class="btn ${saprasFilter===k?'btn-primary':'btn-ghost'}" onclick="setSaprasFilter('${k}')">${k} <span class="badge ${saprasFilter===k?'':'b-info'}" style="${saprasFilter===k?'background:rgba(255,255,255,.25);color:#fff;':''}">${n}</span></button>`;
    }).join('')}
    ${saprasFilter ? `<span class="text-xs" style="color:var(--muted);">Klik tombol aktif lagi untuk menampilkan semua kendaraan</span>` : ''}
  </div>`;
}
/* ---- Form Kendaraan: tombol Sapras Darat / Sapras Laut menentukan pilihan Jenis Kendaraan ---- */
const SAPRAS_FORM_JENIS = {
  'Sapras Darat':['Mobil','Motor','Truk','Ambulans'],
  'Sapras Laut':['Kapal','RIB / Perahu Karet']
};
// Jenis tambahan buatan pengguna (lewat kolom "Nama jenis" + tombol +), disimpan per kategori Sapras
function jenisKendaraanOpsi(sapras){
  const custom = ((DB.jenis_kendaraan_custom||{})[sapras]) || [];
  const hidden = ((DB.jenis_kendaraan_hidden||{})[sapras]) || [];
  return (SAPRAS_FORM_JENIS[sapras]||[]).filter(j=>!hidden.includes(j)).concat(custom);
}
function hapusJenisKendaraan(){
  const hid = document.querySelector('[data-field="sapras"]');
  const sel = document.querySelector('[data-field="jenis_kendaraan"]');
  if(!hid || !sel) return;
  const sapras = hid.value, nama = sel.value;
  if(!nama){ toast('Pilih jenis yang ingin dihapus'); return; }
  if(sel.options.length<=1){ toast('Minimal harus ada satu jenis kendaraan'); return; }
  const dipakai = (DB.kendaraan||[]).filter(r=>r.jenis_kendaraan===nama).length;
  const pesan = 'Hapus jenis "'+nama+'" dari pilihan?' + (dipakai ? '\n\n'+dipakai+' data kendaraan yang sudah memakai jenis ini tidak ikut terhapus, jenisnya tetap tercatat.' : '');
  if(!confirm(pesan)) return;
  const custom = (DB.jenis_kendaraan_custom||{})[sapras] || [];
  if(custom.includes(nama)){
    DB.jenis_kendaraan_custom[sapras] = custom.filter(j=>j!==nama);
  } else {
    if(!DB.jenis_kendaraan_hidden) DB.jenis_kendaraan_hidden = {};
    if(!DB.jenis_kendaraan_hidden[sapras]) DB.jenis_kendaraan_hidden[sapras] = [];
    DB.jenis_kendaraan_hidden[sapras].push(nama);
  }
  persist();
  sel.value = ''; // supaya jenis yang dihapus tidak dimunculkan lagi
  pilihSaprasForm(sapras);
  toast('Jenis "'+nama+'" dihapus');
}
function tambahJenisKendaraan(){
  const hid = document.querySelector('[data-field="sapras"]');
  const sel = document.querySelector('[data-field="jenis_kendaraan"]');
  const inp = document.getElementById('jenis_baru');
  if(!hid || !sel || !inp) return;
  const nama = inp.value.trim().replace(/\s+/g,' ');
  if(!nama){ toast('Isi Nama jenis terlebih dahulu'); return; }
  const sapras = hid.value;
  const ada = [...sel.options].find(o=>o.value.toLowerCase()===nama.toLowerCase());
  if(ada){ sel.value = ada.value; inp.value=''; toast('Jenis sudah ada, dipilih'); return; }
  if(!DB.jenis_kendaraan_custom) DB.jenis_kendaraan_custom = {};
  if(!DB.jenis_kendaraan_custom[sapras]) DB.jenis_kendaraan_custom[sapras] = [];
  const hd = (DB.jenis_kendaraan_hidden||{})[sapras] || [];
  const asli = (SAPRAS_FORM_JENIS[sapras]||[]).find(j=>j.toLowerCase()===nama.toLowerCase());
  if(asli && hd.includes(asli)) DB.jenis_kendaraan_hidden[sapras] = hd.filter(j=>j!==asli); // jenis bawaan yang pernah dihapus: dimunculkan lagi
  else DB.jenis_kendaraan_custom[sapras].push(nama);
  persist();
  pilihSaprasForm(sapras);
  if(![...sel.options].some(o=>o.value===nama)) sel.add(new Option(nama, nama));
  sel.value = nama;
  inp.value = '';
  toast('Jenis "'+nama+'" ditambahkan');
}
// Pilihan Lokasi / Pangkalan: Sapras Laut punya tambahan dermaga/pelabuhan
const LOKASI_KENDARAAN = ['KPP Jakarta','USS Bekasi','USS Bogor','Pos SAR Sukabumi'];
const LOKASI_LAUT_EXTRA = ['Dermaga Pantai Mutiara','Pel. T.Priok Ekinggom'];
function saprasDariRow(row){
  if(row && row.sapras) return row.sapras;
  if(row){ const k = Object.keys(SAPRAS_JENIS).find(k=>SAPRAS_JENIS[k].includes(row.jenis_kendaraan)); if(k) return k; }
  return 'Sapras Darat';
}
function kendaraanSaprasPickerHtml(row){
  const cur = saprasDariRow(row);
  return `<div class="sm:col-span-2 flex flex-wrap gap-2" id="sapras_picker">
    ${Object.keys(SAPRAS_FORM_JENIS).map(k=>`<button type="button" class="btn ${cur===k?'btn-primary':'btn-ghost'}" data-sapras="${k}" onclick="pilihSaprasForm('${k}')">${k}</button>`).join('')}
  </div>`;
}
function pilihSaprasForm(val){
  const hid = document.querySelector('[data-field="sapras"]');
  const sel = document.querySelector('[data-field="jenis_kendaraan"]');
  if(!hid || !sel) return;
  hid.value = val;
  const prev = sel.value;
  const opts = jenisKendaraanOpsi(val);
  if(prev && !opts.includes(prev) && !Object.keys(SAPRAS_FORM_JENIS).some(k=>k!==val && jenisKendaraanOpsi(k).includes(prev))) opts.push(prev); // nilai lama tetap tampil
  sel.innerHTML = opts.map(o=>`<option value="${esc(o)}" ${o===prev?'selected':''}>${esc(o)}</option>`).join('');
  const lok = document.querySelector('[data-field="lokasi"]');
  if(lok){
    const pl = lok.value;
    const lopts = LOKASI_KENDARAAN.concat(val==='Sapras Laut' ? LOKASI_LAUT_EXTRA : []);
    if(pl && !lopts.includes(pl) && !LOKASI_LAUT_EXTRA.includes(pl)) lopts.push(pl); // nilai lama tetap tampil
    lok.innerHTML = lopts.map(o=>`<option value="${esc(o)}" ${o===pl?'selected':''}>${esc(o)}</option>`).join('');
  }
  const lblNp = document.getElementById('lbl_nomor_polisi');
  if(lblNp) lblNp.textContent = (val==='Sapras Laut') ? 'No Lambung' : 'Nomor Polisi / Registrasi';
  document.querySelectorAll('.sapras-laut-only').forEach(el=>{ el.style.display = (val==='Sapras Laut') ? '' : 'none'; });
  document.querySelectorAll('#sapras_picker [data-sapras]').forEach(b=>{
    const on = b.getAttribute('data-sapras')===val;
    b.classList.toggle('btn-primary', on); b.classList.toggle('btn-ghost', !on);
  });
}
function wireKendaraanForm(row){
  if(!document.getElementById('sapras_picker')) return;
  const tg = document.querySelector('[data-field="tanggal"]');
  if(tg && !tg.value) tg.value = todayISO();
  const hid = document.querySelector('[data-field="sapras"]');
  const sel = document.querySelector('[data-field="jenis_kendaraan"]');
  const cur = saprasDariRow(row);
  const prev = row ? row.jenis_kendaraan : '';
  if(prev && !sel.querySelector('option[value="'+prev.replace(/"/g,'&quot;')+'"]')) sel.add(new Option(prev, prev));
  sel.value = prev || sel.value;
  pilihSaprasForm(cur);
  if(prev && [...sel.options].some(o=>o.value===prev)) sel.value = prev;
  if(!document.getElementById('jenis_baru')){
    const box = document.createElement('div');
    box.className = 'flex gap-2 mt-2';
    box.innerHTML = '<input type="text" id="jenis_baru" placeholder="Nama jenis" maxlength="40" onkeydown="if(event.key===\'Enter\'){event.preventDefault();tambahJenisKendaraan();}">'
      + '<button type="button" class="btn btn-ghost" style="font-size:1rem;line-height:1;padding:.4rem .8rem;" title="Tambah jenis baru" onclick="tambahJenisKendaraan()">+</button>'
      + '<button type="button" class="btn btn-ghost" style="font-size:1rem;line-height:1;padding:.4rem .8rem;color:var(--bad);" title="Hapus jenis yang sedang dipilih" onclick="hapusJenisKendaraan()">&minus;</button>';
    sel.insertAdjacentElement('afterend', box);
  }
}
function renderGenericTable(key, fields, label, restricted){
  if(key==='pemeliharaan') return renderPengajuan();
  if(key==='pembinaan' && pembinaanTab==='Absensi') return renderPbnAbsensi(label, restricted);
  const rows = key==='pembinaan' ? (DB[key] || []).filter(r=>pbnKat(r)===pembinaanTab) : (DB[key] || []);
  const q = (search[key]||'').toLowerCase();
  const periodeOn = key!=='anggota' && key!=='rescuer';
  const bySearch = q ? rows.filter(r => matchSearch(r, q, fields)) : rows;
  const isSmp = key==='pembinaan' && pembinaanTab==='Samapta';
  const byPeriode = isSmp ? bySearch.filter(pbnSamaptaInRange) : (periodeOn ? bySearch.filter(r=>inPeriode(r, key)) : bySearch);
  const filtered = key==='peralatan' ? byPeriode.filter(peralatanCocok)
    : (key==='kendaraan' ? byPeriode.filter(saprasCocok) : byPeriode);
  // Kendaraan + filter Sapras Laut: tampilkan juga kolom data khusus laut (Endurance, Speed, dst.)
  const cols = key==='pembinaan'
    ? pbnTabCols(pembinaanTab)
    : fields.filter(f=>f.col || (key==='kendaraan' && saprasFilter==='Sapras Laut' && f.laut) || (key==='kendaraan' && saprasFilter==='Sapras Darat' && f.darat));
  const canEdit = key==='rescuer' ? false : (modulReadOnly(key) ? false : (!restricted || isApprover()));
  // Tombol "+ Tambah" hanya tersedia di modul berikut (Kegiatan punya halaman sendiri)
  const ADD_ALLOWED = ['anggota','potensi','uji_periodik','uji_pemeliharaan','lainnya','peralatan','kendaraan','peminjaman','pemeliharaan'];
  // Pembinaan Tenaga SAR: tab "Pembinaan" terisi dari Kegiatan/Tugas; tab Pembelajaran & Samapta diinput langsung.
  const canAdd = canEdit && (ADD_ALLOWED.includes(key) || key==='pembinaan');
  const addLabel = key==='pembinaan' ? pembinaanTab : label;

  if(key==='rescuer'){
    // Rescuer sekarang menampilkan SEMUA anggota dari Daftar Anggota (bukan
    // dataset terpisah), lengkap dengan rekap jumlah kegiatan per jenis.
    const anggotaRows = DB.anggota || [];
    const filteredAnggota = q ? anggotaRows.filter(a => matchSearch(a, q)) : anggotaRows;
    document.getElementById('app').innerHTML = `
      <div class="panel rounded-xl p-4">
        <div class="flex flex-wrap items-center gap-2 mb-4">
          <input type="text" placeholder="Cari data ${esc(label)}..." value="${esc(search[key]||'')}" class="!w-56" data-sid="search-${key}" oninput="runSearchRender(this, ()=>{ search['${key}']=this.value; renderGenericTable('${key}', window.__lastFields, '${esc(label)}', ${!!restricted}); })">
          <span class="text-xs ml-1" style="color:var(--muted);">${filteredAnggota.length} data</span>
          <span class="ml-auto text-xs badge b-info">Lihat saja</span>
        </div>
        <div class="tablewrap">
          <table>
            <thead><tr>
              <th>Nama</th>
              <th>NIP/ID</th>
              ${RESCUER_JENIS_COLS.map(c=>`<th>${esc(c.label)}</th>`).join('')}
            </tr></thead>
            <tbody>
              ${filteredAnggota.length ? filteredAnggota.map(a=>`
                <tr style="cursor:pointer;" onclick="openGenericDetail('anggota','${a.id}')">
                  <td class="font-medium">${esc(a.nama)}</td>
                  <td>${esc(a.nip)}</td>
                  ${RESCUER_JENIS_COLS.map(c=>`<td class="text-center">${getAnggotaKegiatanCountByJenis(a.id, c.key)}</td>`).join('')}
                </tr>`).join('') : `<tr><td colspan="10" class="text-center py-6" style="color:var(--muted);">Belum ada data.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>`;
    window.__lastFields = fields;
    enhanceTableScroll();
    return;
  }

  document.getElementById('app').innerHTML = `
    <div class="panel rounded-xl p-4">
      ${key==='kendaraan' ? saprasButtonsHtml() : ''}
      ${key==='pembinaan' ? pembinaanTabHtml() : ''}
      <div class="flex flex-wrap items-center gap-2 mb-4">
        <input type="text" placeholder="Cari data ${esc(label)}..." value="${esc(search[key]||'')}" class="!w-56" data-sid="search-${key}" oninput="runSearchRender(this, ()=>{ search['${key}']=this.value; renderGenericTable('${key}', window.__lastFields, '${esc(label)}', ${!!restricted}); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${filtered.length} data</span>
        ${isSmp ? pbnSamaptaRangeHtml() : (periodeOn ? periodeSelectHtml(key) : '')}
        ${key==='peralatan' ? peralatanFilterHtml() : ''}
        ${key==='pembinaan' && pembinaanTab==='Samapta' ? `<div class="dd ml-auto">
          <button type="button" class="btn btn-ghost" onclick="toggleUnduhMenu(event, this)">Unduh Laporan <span aria-hidden="true">&#9662;</span></button>
          <div class="dd-menu">
            <button type="button" class="dd-item" onclick="exportSamapta('pdf')">PDF</button>
            <button type="button" class="dd-item" onclick="exportSamapta('excel')">Excel</button>
          </div>
        </div>` : ''}
        ${key==='uji_periodik' && isSuper() ? ujiImportBtnHtml() : ''}
        ${(['anggota','pembinaan','potensi','narasumber','peralatan','kendaraan','peminjaman'].includes(key) && !(key==='pembinaan' && pembinaanTab==='Absensi')) ? impBtnHtml(key, (isSmp || !canAdd) ? '' : 'ml-auto') : ''}
        ${canAdd ? `<button class="btn btn-primary ${(isSmp || (key==='uji_periodik' && isSuper()) || (canImportExcel(key) && ['anggota','pembinaan','potensi','narasumber','peralatan','kendaraan','peminjaman'].includes(key))) ? '' : 'ml-auto'}" onclick="openGenericForm('${key}')">+ Tambah ${esc(addLabel)}</button>` : (canEdit ? '' :`<span class="ml-auto text-xs badge b-info">Lihat saja</span>`)}
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr>
            ${key!=='anggota' && key!=='rescuer' ? '<th>ID</th>' : ''}
            ${cols.filter(c=>!c.isStatus).map(c=>`<th>${esc(key==='kendaraan' ? labelKendaraan(c, saprasFilter==='Sapras Laut') : (key==='pembinaan' ? pbnLabel(c, pembinaanTab) : c.label))}</th>`).join('')}
            ${fields.some(f=>f.isStatus) && !(key==='pembinaan' && pembinaanTab==='Samapta') ? '<th>Status</th>' : ''}
            ${key==='anggota' ? '<th>Kegiatan Diikuti</th>' : ''}
            ${canEdit ? '<th>Aksi</th>' : ''}
          </tr></thead>
          <tbody>
            ${filtered.length ? filtered.slice().reverse().map(r=>`
              <tr style="cursor:pointer;" onclick="openGenericDetail('${key}','${r.id}')">
                ${key!=='anggota' && key!=='rescuer' ? `<td class="font-medium">${esc(r.id)}</td>` : ''}
                ${cols.filter(c=>!c.isStatus).map(c=>`<td>${cellHtml(key,c,r)}</td>`).join('')}
                ${fields.some(f=>f.isStatus) && !(key==='pembinaan' && pembinaanTab==='Samapta') ? `<td>${statusBadge(statusFieldValue(key, fields.find(f=>f.isStatus), r))}</td>` : ''}
                ${key==='anggota' ? `<td class="text-center font-semibold">${getAnggotaKegiatanCount(r.id)}</td>` : ''}
                ${canEdit ? `<td class="whitespace-nowrap" onclick="event.stopPropagation();">
                  <div style="display:flex; flex-direction:column; align-items:flex-start; gap:.35rem;">
                    <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openGenericForm('${key}','${r.id}')">Ubah</button>
                    <button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="deleteGeneric('${key}','${r.id}','${esc(label)}')">Hapus</button>
                  </div>
                </td>` : ''}
              </tr>`).join('') : `<tr><td colspan="10" class="text-center py-6" style="color:var(--muted);">Belum ada data.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>
    ${key==='uji_periodik' && !modulReadOnly(key) ? ujiKategoriCardsHtml() : ''}
    ${PESERTA_MODULES[key] ? diklatMainPanelHtml(key) : ''}`;
  window.__lastFields = fields;
  enhanceTableScroll();
}

/* ============================= PENGAJUAN (Kebutuhan & Pemeliharaan) =============================
   Pengajuan dibuat siapa saja, lalu di-ACC / ditolak oleh KKS, KSO, KSD, Admin, atau Admin Utama.
   Pemeliharaan baru berpengaruh ke stok Peralatan / kondisi Kendaraan setelah Disetujui. */
const PENGAJUAN_TABS = ['Kebutuhan','Pemeliharaan'];
const PENGAJUAN_ACC_ROLES = ['KKS','KSO','KSD','Admin'];
let pengajuanTab = 'Kebutuhan', pengajuanKatForm = 'Kebutuhan';
function pgjKat(r){ return (r && r.kategori_pengajuan) || 'Pemeliharaan'; }
function pgjSP(r){ return (r && r.status_persetujuan) || 'Disetujui'; }
function pgjLabel(f, kat){ return (kat==='Kebutuhan' && f.labelK) ? f.labelK : f.label; }
function pgjAktif(r){ return pgjKat(r)==='Pemeliharaan' && pgjSP(r)==='Disetujui'; }
function canAccPengajuan(){ return isSuper() || PENGAJUAN_ACC_ROLES.includes(currentRole); }
function canManagePengajuan(r){ return !r || pgjSP(r)!=='Disetujui' || canAccPengajuan(); }
/* ---- Pembinaan Tenaga SAR: tab Pembinaan / Pembelajaran / Samapta ---- */
let pembinaanTab = 'Pembelajaran', pembinaanKatForm = 'Pembelajaran';
function pbnMeta(){ return TABLE_MODULES.find(m=>m.key==='pembinaan'); }
function pbnKat(r){ const k = r && r.kategori_pembinaan; return pbnMeta().tabs.includes(k) ? k : 'Pembelajaran'; }
function pbnLabel(f, kat){ return (f.labels && f.labels[kat]) || f.label; }
function pbnTabCols(tab){ const m = pbnMeta(); return (m.tabCols[tab]||[]).map(k=>m.fields.find(f=>f.key===k)).filter(Boolean); }
function pbnKatOk(f, kat){ return !f.kat || [].concat(f.kat).includes(kat); }
function pembinaanTabHtml(){
  const rows = DB.pembinaan || [];
  return `<div class="flex flex-wrap gap-2 mb-4">${pbnMeta().tabs.concat('Absensi').map(t=>{
    const n = t==='Absensi' ? (DB.pembinaan_absensi||[]).length : rows.filter(r=>pbnKat(r)===t).length;
    return `<button class="btn ${pembinaanTab===t?'btn-primary':'btn-ghost'}" onclick="pembinaanTab='${t}'; render('pembinaan')">${t} <span class="badge b-info">${n} data</span></button>`;
  }).join('')}</div>`;
}
/* ---- Unduh Laporan tab Samapta (PDF / Excel), mengikuti pencarian + filter Bulan/Tahun yang aktif ---- */
/* Filter rentang tanggal tab Samapta (Dari - Sampai); menggantikan filter Bulan/Tahun di tab ini */
let pbnSmpRange = {from:'', to:''};
function pbnSamaptaInRange(r){
  const g = pbnSmpRange; if(!g.from && !g.to) return true;
  const t = /^\d{4}-\d{2}-\d{2}/.test(String(r.tanggal||'')) ? String(r.tanggal).slice(0,10) : '';
  if(!t) return false;
  return (!g.from || t >= g.from) && (!g.to || t <= g.to);
}
function pbnSamaptaRangeHtml(){
  const g = pbnSmpRange;
  return `<span class="inline-flex items-center gap-1.5 flex-wrap">
    <span class="text-xs" style="color:var(--muted);">Dari</span>
    <input type="date" style="width:auto;" aria-label="Dari tanggal" value="${esc(g.from)}" ${g.to?`max="${esc(g.to)}"`:''} onchange="setPbnSmpRange('from',this.value)">
    <span class="text-xs" style="color:var(--muted);">sampai</span>
    <input type="date" style="width:auto;" aria-label="Sampai tanggal" value="${esc(g.to)}" ${g.from?`min="${esc(g.from)}"`:''} onchange="setPbnSmpRange('to',this.value)">
    ${(g.from||g.to) ? `<button type="button" class="btn btn-ghost !py-1 !px-2 text-xs" onclick="resetPbnSmpRange()">Reset</button>` : ''}
  </span>`;
}
function setPbnSmpRange(field, val){
  const g = pbnSmpRange; g[field] = val || '';
  if(g.from && g.to && g.from > g.to){ const t = g.from; g.from = g.to; g.to = t; }
  render('pembinaan');
}
function resetPbnSmpRange(){ pbnSmpRange = {from:'', to:''}; render('pembinaan'); }
function pbnSamaptaRows(){
  const key = 'pembinaan';
  const q = (search[key]||'').toLowerCase();
  let rows = (DB.pembinaan||[]).filter(r=>pbnKat(r)==='Samapta');
  if(q) rows = rows.filter(r=>matchSearch(r, q, pbnMeta().fields));
  rows = rows.filter(pbnSamaptaInRange);
  return rows.slice().sort((a,b)=>String(a.tanggal||'').localeCompare(String(b.tanggal||'')));
}
function pbnSamaptaJumlah(r){ return parseInt(r.jumlah_peserta,10) || pbnPesertaList(r).length; }
function pbnSamaptaDokTeks(r){
  const fl = Array.isArray(r.dokumentasi_files) ? r.dokumentasi_files.map(f=>f.name).filter(Boolean) : [];
  return fl.concat(r.dokumentasi ? [r.dokumentasi] : []).join(', ');
}
function exportSamapta(fmt){
  document.querySelectorAll('.dd.open').forEach(d=>d.classList.remove('open'));
  const rows = pbnSamaptaRows();
  if(!rows.length){ toast('Belum ada data Samapta untuk diunduh'); return; }
  const g = pbnSmpRange;
  const periode = (g.from||g.to) ? (g.from ? tglDMY(g.from) : 'awal')+' s.d. '+(g.to ? tglDMY(g.to) : 'sekarang') : 'Semua Tanggal';
  const head = ['No','ID','Nama Kegiatan','Materi / Program Latihan','Tanggal','Lokasi','Jumlah Peserta','Nama Anggota','Dokumentasi'];
  const body = rows.map((r,i)=>[
    i+1, r.id||'', r.nama_kegiatan||'', r.materi||'', tglDMY(r.tanggal||''), r.lokasi||'',
    pbnSamaptaJumlah(r), pbnPesertaList(r).join(', '), pbnSamaptaDokTeks(r)
  ]);
  const totalPeserta = rows.reduce((n,r)=>n+pbnSamaptaJumlah(r), 0);
  const ringkas = 'Total kegiatan: '+rows.length+'   |   Total peserta: '+totalPeserta;
  const judul = 'Laporan Pembinaan Tenaga SAR - Samapta';
  const fname = ('Laporan Samapta '+periode).replace(/[\\/:*?"<>|]+/g,'').replace(/\s+/g,'_');
  try{
    if(fmt==='excel'){
      if(typeof XLSX==='undefined'){ toast('Library Excel belum termuat, periksa koneksi internet'); return; }
      const ws = XLSX.utils.aoa_to_sheet([[judul],['Periode: '+periode],['Dicetak: '+nowStr()],[],head,...body,[],[ringkas]]);
      ws['!cols'] = [5,10,32,30,12,24,10,40,30].map(w=>({wch:w}));
      const wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Samapta');
      XLSX.writeFile(wb, fname+'.xlsx');
    } else {
      if(!(window.jspdf && window.jspdf.jsPDF)){ toast('Library PDF belum termuat, periksa koneksi internet'); return; }
      const doc = new window.jspdf.jsPDF({orientation:'landscape', unit:'pt', format:'a4'});
      doc.setFontSize(13); doc.text(judul, 40, 40);
      doc.setFontSize(10); doc.text('Periode: '+periode, 40, 56);
      doc.setFontSize(9); doc.text('Dicetak '+nowStr(), 40, 70);
      doc.autoTable({
        head:[head], body, startY:80,
        styles:{fontSize:8, cellPadding:3, valign:'middle'},
        headStyles:{fillColor:[19,49,92], halign:'center'},
        columnStyles:{0:{halign:'center', cellWidth:24}, 6:{halign:'center', cellWidth:48}}
      });
      const y = (doc.lastAutoTable ? doc.lastAutoTable.finalY : 80) + 18;
      doc.setFontSize(9); doc.text(ringkas, 40, y);
      doc.save(fname+'.pdf');
    }
  }catch(e){ console.error(e); toast('Gagal mengunduh file'); }
}
/* ---- Absensi Pembinaan Tenaga SAR ----
   Nama anggota yang dipilih di tab Samapta otomatis masuk ke tab Absensi (sumber 'Samapta', default Hadir).
   Absensi juga bisa ditambah manual (sumber 'Manual'). Disimpan di DB.pembinaan_absensi. */
const ABS_STATUS = ['Hadir','Izin','Sakit','Alpa'];
function pbnPesertaList(r){ return String((r && r.peserta) || '').split(';').map(s=>s.trim()).filter(Boolean); }
// Samakan absensi otomatis dengan data Samapta (satu data Samapta bila diberikan, jika tidak semuanya). Mengembalikan true bila ada perubahan.
function pbnAbsSync(db, samapta){
  if(!Array.isArray(db.pembinaan_absensi)) db.pembinaan_absensi = [];
  const abs = db.pembinaan_absensi;
  const list = samapta ? [samapta] : (db.pembinaan||[]).filter(r=>pbnKat(r)==='Samapta');
  let changed = false;
  list.forEach(sm=>{
    const names = pbnPesertaList(sm);
    for(let i=abs.length-1; i>=0; i--){
      if(abs[i].sumber==='Samapta' && abs[i].sumber_id===sm.id && !names.includes(abs[i].nama_anggota)){ abs.splice(i,1); changed = true; }
    }
    names.forEach(n=>{
      const a = abs.find(x=>x.sumber==='Samapta' && x.sumber_id===sm.id && x.nama_anggota===n);
      if(!a){
        abs.push({id:uid('ABS', abs), nama_anggota:n, tanggal:sm.tanggal||'', nama_kegiatan:sm.nama_kegiatan||'', kehadiran:'Hadir', keterangan:'', sumber:'Samapta', sumber_id:sm.id});
        changed = true;
      } else if(a.tanggal!==(sm.tanggal||'') || a.nama_kegiatan!==(sm.nama_kegiatan||'')){
        a.tanggal = sm.tanggal||''; a.nama_kegiatan = sm.nama_kegiatan||''; changed = true;
      }
    });
  });
  return changed;
}
function pbnAbsHapusSumber(samaptaId){
  const n = (DB.pembinaan_absensi||[]).length;
  DB.pembinaan_absensi = (DB.pembinaan_absensi||[]).filter(a=>!(a.sumber==='Samapta' && a.sumber_id===samaptaId));
  return n !== DB.pembinaan_absensi.length;
}
function absBadge(v){
  const cls = {Hadir:'b-ok', Izin:'b-info', Sakit:'b-warn', Alpa:'b-bad'}[v] || 'b-info';
  return `<span class="badge ${cls}">${esc(v||'-')}</span>`;
}
function renderPbnAbsensi(label, restricted){
  const canEdit = !restricted || isApprover();
  const q = normSearch(search['pembinaan_absensi']||'');
  const all = DB.pembinaan_absensi || [];
  const rows = all.filter(a=>!q || normSearch([a.nama_anggota, a.nama_kegiatan, a.tanggal, a.kehadiran, a.keterangan, a.sumber].join(' ')).includes(q))
    .slice().sort((a,b)=>String(b.tanggal||'').localeCompare(String(a.tanggal||'')) || String(a.nama_anggota).localeCompare(String(b.nama_anggota)));
  const rekap = ABS_STATUS.map(s=>`<span class="badge ${{Hadir:'b-ok',Izin:'b-info',Sakit:'b-warn',Alpa:'b-bad'}[s]}">${s}: ${all.filter(a=>a.kehadiran===s).length}</span>`).join(' ');
  document.getElementById('app').innerHTML = `
    <div class="panel rounded-xl p-4">
      ${pembinaanTabHtml()}
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <input type="text" placeholder="Cari absensi..." value="${esc(search['pembinaan_absensi']||'')}" class="!w-56" data-sid="search-pembinaan_absensi" oninput="runSearchRender(this, ()=>{ search['pembinaan_absensi']=this.value; renderPbnAbsensi('${esc(label)}', ${!!restricted}); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} data</span>
        ${canEdit ? `<button class="btn btn-primary ml-auto" onclick="openAbsensiForm()">+ Tambah Absensi</button>` : `<span class="ml-auto text-xs badge b-info">Lihat saja</span>`}
      </div>
      <div class="flex flex-wrap items-center gap-2 mb-3">${rekap}</div>
      <div class="text-[11px] mb-3" style="color:var(--muted);">Nama anggota yang dipilih pada tab Samapta masuk otomatis ke sini (status awal Hadir). Anda tetap bisa mengubah kehadirannya atau menambah absensi manual.</div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>No</th><th>Nama Anggota</th><th>Tanggal</th><th>Kegiatan</th><th>Kehadiran</th><th>Keterangan</th><th>Sumber</th>${canEdit ? '<th>Aksi</th>' : ''}</tr></thead>
          <tbody>
            ${rows.length ? rows.map((a,i)=>`
              <tr>
                <td>${i+1}</td>
                <td class="font-medium">${esc(a.nama_anggota)}</td>
                <td>${esc(tglDMY(a.tanggal)||'-')}</td>
                <td>${esc(a.nama_kegiatan||'-')}</td>
                <td>${absBadge(a.kehadiran)}</td>
                <td>${esc(a.keterangan||'')}</td>
                <td><span class="badge ${a.sumber==='Samapta'?'b-info':'b-warn'}">${a.sumber==='Samapta'?'Otomatis (Samapta)':'Manual'}</span></td>
                ${canEdit ? `<td class="whitespace-nowrap"><div style="display:flex; flex-direction:column; align-items:flex-start; gap:.35rem;">
                  <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openAbsensiForm('${a.id}')">Ubah</button>
                  ${a.sumber==='Samapta' ? '' : `<button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="deleteAbsensi('${a.id}')">Hapus</button>`}
                </div></td>` : ''}
              </tr>`).join('') : `<tr><td colspan="8" class="text-center py-6" style="color:var(--muted);">Belum ada data absensi.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>`;
  enhanceTableScroll();
}
function openAbsensiForm(id){
  const row = id ? (DB.pembinaan_absensi||[]).find(a=>a.id===id) : null;
  const auto = !!(row && row.sumber==='Samapta');
  const anggota = (DB.anggota||[]).filter(a=>a.status==='Aktif' || (row && a.nama===row.nama_anggota)).map(a=>a.nama);
  const kegiatan = Array.from(new Set((DB.pembinaan||[]).filter(r=>pbnKat(r)==='Samapta').map(r=>r.nama_kegiatan).filter(Boolean)));
  const ro = auto ? 'readonly style="background:var(--line); cursor:not-allowed;"' : '';
  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display font-semibold text-base">${row?'Ubah':'Tambah'} Absensi — Samapta</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      ${auto ? '<div class="text-[11px] mb-3" style="color:var(--muted);">Data ini otomatis dari tab Samapta: nama, tanggal, dan kegiatan mengikuti data Samapta. Anda hanya dapat mengubah kehadiran dan keterangan.</div>' : ''}
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Nama Anggota *</label>
          <input type="text" id="abs_nama" list="abs_nama_list" autocomplete="off" value="${esc(row?row.nama_anggota:'')}" placeholder="Pilih atau ketik nama" ${ro}>
          <datalist id="abs_nama_list">${anggota.map(n=>`<option value="${esc(n)}">`).join('')}</datalist></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Tanggal</label>
          <input type="date" id="abs_tanggal" value="${esc(row?row.tanggal:todayISO())}" ${ro}></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Kegiatan</label>
          <input type="text" id="abs_kegiatan" list="abs_kegiatan_list" autocomplete="off" value="${esc(row?row.nama_kegiatan:'')}" placeholder="Pilih kegiatan Samapta atau ketik" ${ro}>
          <datalist id="abs_kegiatan_list">${kegiatan.map(n=>`<option value="${esc(n)}">`).join('')}</datalist></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Kehadiran</label>
          <select id="abs_hadir">${ABS_STATUS.map(o=>`<option value="${o}" ${(row?row.kehadiran:'Hadir')===o?'selected':''}>${o}</option>`).join('')}</select></div>
        <div class="sm:col-span-2"><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Keterangan</label>
          <textarea id="abs_ket" rows="2">${esc(row?row.keterangan:'')}</textarea></div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="saveAbsensi(${row?`'${id}'`:'null'})">Simpan</button>
      </div>
    </div>`, true);
}
function saveAbsensi(id){
  const v = k => (document.getElementById(k)||{}).value || '';
  const nama = v('abs_nama').trim();
  if(!nama){ toast('Nama anggota wajib diisi'); return; }
  if(!Array.isArray(DB.pembinaan_absensi)) DB.pembinaan_absensi = [];
  const rows = DB.pembinaan_absensi;
  const meta = {diperbarui_oleh: pelaku(), diperbarui_waktu: nowStr()};
  if(id){
    const r = rows.find(a=>a.id===id); if(!r) return;
    if(r.sumber!=='Samapta'){ r.nama_anggota = nama; r.tanggal = v('abs_tanggal'); r.nama_kegiatan = v('abs_kegiatan').trim(); }
    r.kehadiran = v('abs_hadir'); r.keterangan = v('abs_ket').trim();
    Object.assign(r, meta);
    toast('Absensi diperbarui');
  } else {
    rows.push(Object.assign({id:uid('ABS', rows), nama_anggota:nama, tanggal:v('abs_tanggal'), nama_kegiatan:v('abs_kegiatan').trim(), kehadiran:v('abs_hadir'), keterangan:v('abs_ket').trim(), sumber:'Manual', sumber_id:''}, meta));
    toast('Absensi ditambahkan');
  }
  persist(); closeModal(); render('pembinaan');
}
function deleteAbsensi(id){
  const r = (DB.pembinaan_absensi||[]).find(a=>a.id===id); if(!r) return;
  if(r.sumber==='Samapta'){ toast('Absensi otomatis dihapus dengan melepas nama dari data Samapta'); return; }
  if(!confirm(`Hapus absensi "${r.nama_anggota}"?`)) return;
  DB.pembinaan_absensi = DB.pembinaan_absensi.filter(a=>a.id!==id);
  persist(); toast('Absensi dihapus'); render('pembinaan');
}
function fieldTampilForm(key, f){
  if(key==='pembinaan') return !f.noForm && pbnKatOk(f, pembinaanKatForm);
  if(key!=='pemeliharaan') return true;
  return !f.noForm && (!f.kat || f.kat===pengajuanKatForm);
}
function fieldTampilDetail(key, f, row){
  if(key==='pembinaan') return pbnKatOk(f, pbnKat(row));
  if(key!=='pemeliharaan') return true;
  return !f.noDetail && (!f.kat || f.kat===pgjKat(row));
}
function pengajuanTabHtml(){
  const rows = DB.pemeliharaan || [];
  return `<div class="flex flex-wrap gap-2 mb-4">${PENGAJUAN_TABS.map(t=>{
    const all = rows.filter(r=>pgjKat(r)===t);
    const mn = all.filter(r=>pgjSP(r)==='Menunggu Persetujuan').length;
    return `<button class="btn ${pengajuanTab===t?'btn-primary':'btn-ghost'}" onclick="pengajuanTab='${t}'; render('pemeliharaan')">${t} <span class="badge ${mn?'b-warn':'b-info'}">${mn ? mn+' menunggu' : all.length+' data'}</span></button>`;
  }).join('')}</div>`;
}
function renderPengajuan(){
  const key = 'pemeliharaan';
  const m = TABLE_MODULES.find(x=>x.key===key);
  const kat = pengajuanTab;
  const q = (search[key]||'').toLowerCase();
  let rows = (DB[key]||[]).filter(r=>pgjKat(r)===kat);
  if(q) rows = rows.filter(r=>matchSearch(r, q, m.fields));
  rows = rows.filter(r=>inPeriode(r, key));
  const fld = k => m.fields.find(f=>f.key===k);
  const cols = (kat==='Kebutuhan'
    ? ['jenis_barang','nama_barang','jumlah_kebutuhan','tingkat_urgensi','estimasi_biaya','tanggal']
    : ['jenis_barang','nama_barang','jenis_pemeliharaan','tanggal','jadwal_berikutnya']).map(fld);
  const showStatus = kat==='Pemeliharaan';
  const acc = canAccPengajuan();
  document.getElementById('app').innerHTML = `
    <div class="panel rounded-xl p-4">
      ${pengajuanTabHtml()}
      <div class="flex flex-wrap items-center gap-2 mb-4">
        <input type="text" placeholder="Cari data Pengajuan ${esc(kat)}..." value="${esc(search[key]||'')}" class="!w-56" data-sid="search-${key}" oninput="runSearchRender(this, ()=>{ search['${key}']=this.value; renderPengajuan(); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} data</span>
        ${periodeSelectHtml(key)}
        ${impBtnHtml(key, 'ml-auto')}
        <button class="btn btn-primary ${canImportExcel() ? '' : 'ml-auto'}" onclick="openGenericForm('${key}')">+ Tambah Pengajuan ${esc(kat)}</button>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr>
            <th>ID</th>
            ${cols.map(c=>`<th>${esc(pgjLabel(c,kat))}</th>`).join('')}
            ${showStatus ? '<th>Status Pekerjaan</th>' : ''}
            <th>Persetujuan</th>
            <th>Aksi</th>
          </tr></thead>
          <tbody>
            ${rows.length ? rows.slice().reverse().map(r=>{
              const sp = pgjSP(r), manage = canManagePengajuan(r);
              return `<tr style="cursor:pointer;" onclick="openGenericDetail('${key}','${r.id}')">
                <td class="font-medium">${esc(r.id)}</td>
                ${cols.map(c=>`<td>${cellHtml(key,c,r)}</td>`).join('')}
                ${showStatus ? `<td>${statusBadge(r.status)}</td>` : ''}
                <td>${statusBadge(sp)}${sp==='Ditolak' && r.alasan_penolakan ? `<div class="text-[11px]" style="color:var(--muted);">${esc(r.alasan_penolakan)}</div>` : ''}${sp==='Disetujui' && r.acc_oleh ? `<div class="text-[11px]" style="color:var(--muted);">oleh ${esc(r.acc_oleh)}${r.acc_nama ? ' (' + esc(r.acc_nama) + ')' : ''}</div>` : ''}</td>
                <td class="whitespace-nowrap" onclick="event.stopPropagation();">
                  <div style="display:flex; flex-direction:column; align-items:flex-start; gap:.35rem;">
                    ${sp==='Menunggu Persetujuan' && acc ? `<div class="flex gap-1"><button class="btn btn-ok !py-1 !px-2 text-xs" onclick="accPengajuan('${r.id}')">ACC</button><button class="btn btn-bad !py-1 !px-2 text-xs" onclick="tolakPengajuan('${r.id}')">Tolak</button></div>` : ''}
                    ${manage ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openGenericForm('${key}','${r.id}')">Ubah</button>
                    <button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="deleteGeneric('${key}','${r.id}','Pengajuan')">Hapus</button>` : ''}
                  </div>
                </td>
              </tr>`; }).join('') : `<tr><td colspan="12" class="text-center py-6" style="color:var(--muted);">Belum ada pengajuan ${esc(kat)}.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>`;
  enhanceTableScroll();
}
function pengajuanApprovalHtml(row){
  const sp = pgjSP(row);
  return `<div class="sm:col-span-2 rounded-lg p-3" style="background:var(--bg);">
    <div class="text-xs font-medium mb-1" style="color:var(--muted);">Persetujuan Pengajuan</div>
    <div class="flex flex-wrap items-center gap-2">${statusBadge(sp)}<span class="text-xs" style="color:var(--muted);">Diajukan oleh ${row.diajukan_oleh ? esc(pelakuTeks(row.diajukan_oleh)) : '-'}${row.diajukan_waktu ? ' · ' + esc(row.diajukan_waktu) : ''}</span></div>
    ${row.keterangan_persetujuan ? `<p class="text-xs mt-2">${esc(row.keterangan_persetujuan)}</p>` : ''}
    ${sp==='Menunggu Persetujuan' ? (canAccPengajuan()
      ? `<div class="flex gap-2 mt-3"><button class="btn btn-ok" onclick="accPengajuan('${row.id}')">ACC</button><button class="btn btn-bad" onclick="tolakPengajuan('${row.id}')">Tolak</button></div><p class="text-[11px] mt-2" style="color:var(--muted);">Pimpinan juga dapat memutuskan lewat WhatsApp. Keputusan pertama (dashboard atau WhatsApp) bersifat final.</p>`
      : `<p class="text-[11px] mt-2" style="color:var(--muted);">Menunggu ACC ${PENGAJUAN_ACC_ROLES.join(' / ')} atau Admin Utama.</p>`) : ''}
  </div>`;
}
function accPengajuan(id){
  const r = (DB.pemeliharaan||[]).find(x=>x.id===id);
  if(!r || pgjSP(r)!=='Menunggu Persetujuan') return;
  if(!canAccPengajuan()){ toast('Hanya ' + PENGAJUAN_ACC_ROLES.join('/') + ' atau Admin Utama yang dapat ACC'); return; }
  if(pgjKat(r)==='Pemeliharaan' && r.jenis_barang==='Peralatan' && r.status==='Sedang Dikerjakan'){
    const alat = (DB.peralatan||[]).find(a=>normNama(a.nama_barang)===normNama(r.nama_barang));
    if(alat){
      const sisa = peralatanBaik(alat) - peralatanDipinjam(alat.nama_barang) - peralatanDipelihara(alat.nama_barang);
      if((parseFloat(r.jumlah)||1) > sisa){ toast('Stok tersedia hanya ' + Math.max(0,sisa) + ' - jumlah unit melebihi stok, ubah pengajuan dulu'); return; }
    }
  }
  r.status_persetujuan = 'Disetujui';
  r.acc_oleh = currentRole; r.acc_nama = currentUserName; r.acc_waktu = nowStr();
  r.keterangan_persetujuan = `Disetujui oleh ${currentRole} (${currentUserName}) pada ${nowStr()}.`;
  r.alasan_penolakan = '';
  syncKendaraanDariPemeliharaan(r);
  persist();
  toast('Pengajuan ' + id + ' disetujui');
  closeModal(); render('pemeliharaan'); openGenericDetail('pemeliharaan', id);
}
function tolakPengajuan(id){
  const r = (DB.pemeliharaan||[]).find(x=>x.id===id);
  if(!r || pgjSP(r)!=='Menunggu Persetujuan') return;
  if(!canAccPengajuan()){ toast('Hanya ' + PENGAJUAN_ACC_ROLES.join('/') + ' atau Admin Utama yang dapat menolak'); return; }
  const alasan = prompt('Alasan penolakan:');
  if(alasan===null) return;
  r.status_persetujuan = 'Ditolak';
  r.alasan_penolakan = alasan || '(tanpa keterangan)';
  r.acc_oleh = currentRole; r.acc_nama = currentUserName; r.acc_waktu = nowStr();
  r.keterangan_persetujuan = `Ditolak oleh ${currentRole} (${currentUserName}) pada ${nowStr()}: ${r.alasan_penolakan}`;
  persist();
  toast('Pengajuan ' + id + ' ditolak');
  closeModal(); render('pemeliharaan'); openGenericDetail('pemeliharaan', id);
}

function openGenericDetail(key, id){
  const meta = key==='anggota' ? {label:'Anggota', fields:ANGGOTA_FIELDS} : (()=>{const m=TABLE_MODULES.find(m=>m.key===key); return {label:m.label, fields:m.fields};})();
  const rows = DB[key] || [];
  const row = rows.find(r=>r.id===id);
  if(!row) return;

  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-display font-semibold text-base">Detail ${esc(meta.label)}</h3>
          <div class="text-xs" style="color:var(--muted);">${esc(row.id)}</div>
        </div>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        ${meta.fields.filter(f=>!(f.laut && saprasKategori(row)!=='Sapras Laut') && fieldTampilDetail(key,f,row)).map(f=>`
          <div class="${f.type==='textarea'?'sm:col-span-2':''}">
            <div class="text-xs font-medium mb-1" style="color:var(--muted);">${esc(key==='kendaraan' ? labelKendaraan(f, saprasKategori(row)==='Sapras Laut') : (key==='pemeliharaan' ? pgjLabel(f, pgjKat(row)) : (key==='pembinaan' ? pbnLabel(f, pbnKat(row)) : f.label)))}</div>
            <div class="text-sm font-medium">${f.isStatus ? (statusBadge(statusFieldValue(key, f, row))||'-') : (cellHtml(key,f,row) || '-')}</div>
          </div>`).join('')}
        ${key==='anggota' ? `
          <div>
            <div class="text-xs font-medium mb-1" style="color:var(--muted);">Kegiatan Diikuti</div>
            <div class="text-sm font-medium">${getAnggotaKegiatanCount(row.id)} penugasan</div>
          </div>` : ''}
        ${key==='pemeliharaan' ? pengajuanApprovalHtml(row) : ''}
        ${row.diperbarui_oleh ? `
          <div class="sm:col-span-2">
            <div class="text-xs font-medium mb-1" style="color:var(--muted);">Diupdate Oleh</div>
            <div class="text-sm font-medium">${esc(pelakuTeks(row.diperbarui_oleh))}${row.diperbarui_waktu ? ` <span class="text-xs font-normal" style="color:var(--muted);">· ${esc(row.diperbarui_waktu)}</span>` : ''}</div>
          </div>` : ''}
      </div>
      ${PESERTA_MODULES[key] ? diklatPesertaDetailHtml(row, key) : ''}
      ${lampiranWaHtml(key, row)}
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Tutup</button>
        ${canGenericEdit(key, row) ? `<button class="btn btn-primary" onclick="closeModal(); openGenericForm('${key}','${id}')">Ubah Data</button>` : ''}
      </div>
    </div>`, true);
}
function ujiTop3Html(row){
  const M = PESERTA_MODULES.uji_periodik;
  const rows = Array.isArray(row.peserta_matrix) ? row.peserta_matrix : [];
  const groups = [{jk:'L', label:'Laki-laki (L)'}, {jk:'P', label:'Perempuan (P)'}];
  const block = g => {
    const top = rows
      .filter(r=>r.jk===g.jk)
      .map(r=>({r, avg:ujiNilaiRataRata(r, (Array.isArray(row.kolom_peserta)&&row.kolom_peserta.length) ? row.kolom_peserta : ((DB.kolom_peserta && DB.kolom_peserta.uji_periodik) || defaultUjiCols()))}))
      .filter(x=>x.avg!==null)
      .sort((a,b)=>b.avg-a.avg)
      .slice(0,3);
    return `
      <div class="mb-3">
        <div class="text-xs font-semibold mb-1">3 Nilai Keseluruhan Tertinggi — ${esc(g.label)}</div>
        <div class="tablewrap">
          <table>
            <thead><tr>
              <th class="text-center" style="width:3rem;">No</th><th>Nama</th><th>Jabatan</th><th class="text-center">${esc(M.lainLabel)}</th>
            </tr></thead>
            <tbody>
              ${top.length ? top.map((x,n)=>`
                <tr>
                  <td class="text-center">${n+1}</td>
                  <td class="font-medium">${esc(x.r.nama)||'-'}</td>
                  <td>${esc(x.r.jabatan)||'-'}</td>
                  <td class="text-center font-semibold">${x.avg.toLocaleString('id-ID',{maximumFractionDigits:2})}</td>
                </tr>`).join('') : `<tr><td colspan="4" class="text-center py-3" style="color:var(--muted);">Belum ada data nilai.</td></tr>`}
            </tbody>
          </table>
        </div>
      </div>`;
  };
  return `
    <div class="mt-4 pt-4 border-t" style="border-color:var(--line);">
      <div class="text-xs font-medium mb-2" style="color:var(--muted);">Data Peserta ${esc(M.label)}</div>
      ${groups.map(block).join('')}
    </div>`;
}
function ujiTop3AkhirHtml(ujiId){
  ujiMigrasiNilaiLama();
  const rekap = ujiPesertaRekap(ujiId).filter(o=>o.akhir!==null);
  const groups = [{jk:'L', label:'Laki-laki (L)'}, {jk:'P', label:'Perempuan (P)'}];
  const medal = ['🥇','🥈','🥉'];
  const block = g => {
    const top = rekap.filter(o=>o.jk===g.jk).sort((a,b)=>b.akhir-a.akhir).slice(0,3);
    return `
      <div class="mb-3">
        <div class="text-xs font-semibold mb-1">Peringkat 3 Besar Nilai Akhir — ${esc(g.label)}</div>
        <div class="tablewrap">
          <table>
            <thead><tr><th class="text-center" style="width:3.5rem;">Rank</th><th>Nama</th><th>Jabatan</th><th class="text-center">Nilai Akhir</th><th>Predikat</th></tr></thead>
            <tbody>${top.length ? top.map((o,i)=>`<tr>
              <td class="text-center">${medal[i]} ${i+1}</td>
              <td class="font-medium">${esc(o.nama)||'-'}</td><td>${esc(o.jabatan)||'-'}</td>
              <td class="text-center font-semibold">${fmtNilai(o.akhir)}</td><td>${ujkPredikatBadge(o.predikat)}</td>
            </tr>`).join('') : `<tr><td colspan="5" class="text-center py-3" style="color:var(--muted);">Belum ada peserta ${esc(g.label)} dengan nilai akhir lengkap.</td></tr>`}</tbody>
          </table>
        </div>
      </div>`;
  };
  const tanpaJk = rekap.filter(o=>o.jk!=='L' && o.jk!=='P').length;
  return `
    <div class="mt-4 pt-4 border-t" style="border-color:var(--line);">
      <div class="text-xs font-medium mb-2" style="color:var(--muted);">Perangkingan Nilai Akhir Uji Periodik</div>
      ${groups.map(block).join('')}
      ${tanpaJk ? `<p class="text-[11px]" style="color:var(--muted);">${tanpaJk} peserta belum diisi JK sehingga tidak masuk perangkingan.</p>` : ''}
      <p class="text-[11px] mt-1" style="color:var(--muted);">Hanya peserta dengan keempat nilai lengkap (Teknis SAR, PU, Kebugaran Jasmani, Renang) yang diperingkat.</p>
    </div>`;
}
function diklatPesertaDetailHtml(row, key){
  if((key||'diklat')==='uji_periodik') return ujiTop3AkhirHtml(row.id);
  const M = PESERTA_MODULES[key||'diklat'];
  const rows = Array.isArray(row.peserta_matrix) ? row.peserta_matrix : [];
  return `
    <div class="mt-4 pt-4 border-t" style="border-color:var(--line);">
      <div class="text-xs font-medium mb-2" style="color:var(--muted);">Data Peserta ${esc(M.label)}</div>
      <div class="tablewrap">
        <table>
          <thead><tr>
            <th>Nama</th><th>Jabatan</th>${(key||'diklat')==='uji_periodik' ? '<th class="text-center">JK</th>' : ''}
            ${getPesertaCols(key||'diklat').map(c=>`<th class="text-center">${esc(c.label)}</th>`).join('')}
            <th>${esc(M.lainLabel)}</th>
          </tr></thead>
          <tbody>
            ${rows.length ? rows.map((r,ri)=>`
              <tr>
                <td class="font-medium">${esc(r.nama)||'-'}</td>
                <td>${esc(r.jabatan)||'-'}</td>
                ${(key||'diklat')==='uji_periodik' ? `<td class="text-center">${esc(r.jk)||'-'}</td>` : ''}
                ${getPesertaCols(key||'diklat').map(c=>`<td class="text-center">${(key||'diklat')==='uji_periodik' ? ((typeof r[c.key]==='boolean' || r[c.key]==null || String(r[c.key]).trim()==='') ? '-' : esc(r[c.key])) : (r[c.key]?'✓':'-')}${(key||'diklat')==='diklat' && r[c.key] && r[c.key+'_pdf'] ? `<br><a href="#" class="text-[11px]" style="color:var(--orange-500); text-decoration:underline;" onclick="viewPesertaColPdfSaved(event,'${esc(row.id)}','${key||'diklat'}',${ri},'${c.key}')">Lihat PDF</a>` : ''}</td>`).join('')}
                <td>
                  ${(key||'diklat')==='diklat' ? (() => {
                    const items = (Array.isArray(r.diklat_lain_list) ? r.diklat_lain_list : ((r.diklat_lain_teks||r.diklat_lain_pdf) ? [{teks:r.diklat_lain_teks, pdf:r.diklat_lain_pdf}] : [])).filter(x=>(x.teks&&String(x.teks).trim())||x.pdf);
                    return items.length ? items.map((it,j)=>`<div>${esc(it.teks)||'-'}${it.pdf ? ` <a href="#" class="text-xs" style="color:var(--orange-500); text-decoration:underline;" onclick="viewDiklatLainPdfSaved(event,'${esc(row.id)}','diklat',${ri},${j})">Lihat PDF</a>` : ''}</div>`).join('') : '-';
                  })() : (key==='uji_periodik' ? `<span class="font-semibold">${ujiNilaiRataRataText(r)}</span>` : `${esc(r.diklat_lain_teks)||'-'}${r.diklat_lain_pdf ? `<br><a href="${r.diklat_lain_pdf.data}" target="_blank" rel="noopener" class="text-xs" style="color:var(--accent, #dd4a24);">Lihat PDF</a>` : ''}`)}
                </td>
              </tr>`).join('') : `<tr><td colspan="${getPesertaCols(key||'diklat').length+((key||'diklat')==='uji_periodik'?4:3)}" class="text-center py-4" style="color:var(--muted);">Belum ada data peserta.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>`;
}

function canGenericEdit(key, row){
  if(modulReadOnly(key)) return false;
  if(key==='pemeliharaan') return canManagePengajuan(row);
  if(key==='anggota') return isApprover();
  if(key==='rescuer') return false;
  return true;
}

function fieldInput(f, val, key, row){
  const v = esc(val===undefined?'':val);
  if(statusDariKegiatanAktif(key, f, row)){
    const cur = statusDariKegiatan(key, row || {});
    return `<input type="text" data-field="${f.key}" value="${esc(cur)}" readonly disabled style="background:var(--line); font-weight:600; cursor:not-allowed;">
      <div class="text-[11px] mt-1" style="color:var(--muted);">Otomatis mengikuti status Kegiatan/Tugas terkait.</div>`;
  }
  // Pembinaan Tenaga SAR tab Pembelajaran: Materi berupa dropdown (tab Samapta tetap isian teks)
  if(key==='pembinaan' && f.key==='materi' && pembinaanKatForm==='Pembelajaran'){
    const base = ['Hard','Jungle','Water','Medis'];
    const opts = (val && !base.includes(val)) ? [val].concat(base) : base; // nilai lama tetap tampil
    return `<select data-field="materi">${opts.map(o=>`<option value="${esc(o)}" ${val===o?'selected':''}>${esc(o)}</option>`).join('')}</select>`;
  }
  // Pembinaan Tenaga SAR tab Pembelajaran: Peserta dipilih dari Daftar Anggota (tab Samapta tetap isian teks)
  if(key==='pembinaan' && f.key==='peserta'){
    const dipilih = String(val||'').split(';').map(s=>s.trim()).filter(Boolean);
    const aktif = (DB.anggota||[]).filter(a=>a.status==='Aktif' || dipilih.includes(a.nama));
    const namaAng = new Set((DB.anggota||[]).map(a=>a.nama));
    const lain = dipilih.filter(n=>!namaAng.has(n)); // isian lama yang bukan nama anggota tetap dipertahankan
    const items = lain.concat(aktif.map(a=>a.nama));
    return `<input type="hidden" data-field="peserta" id="pbn_peserta_val" value="${esc(dipilih.join('; '))}">
      <button type="button" id="pbn_peserta_btn" class="btn btn-ghost" style="width:100%; justify-content:space-between;" onclick="pbnPesertaToggle()"><span id="pbn_peserta_label">Pilih Peserta</span><span id="pbn_peserta_arrow" aria-hidden="true">&#9662;</span></button>
      <div id="pbn_peserta_panel" style="display:none; margin-top:.4rem;">
      <input type="text" id="pbn_peserta_cari" placeholder="Cari nama anggota..." autocomplete="off" oninput="pbnPesertaFilter(this.value)" style="margin-bottom:.4rem;">
      <div id="pbn_peserta_list" style="max-height:190px; overflow-y:auto; border:1px solid var(--line); border-radius:.5rem; background:var(--bg); padding:.3rem;">
        ${items.map(n=>`<label class="pbn-peserta-item flex items-center gap-2 px-2 py-1 rounded" data-cari="${esc(n.toLowerCase())}" style="cursor:pointer; font-size:.82rem;">
          <input type="checkbox" class="pbn-peserta-chk" value="${esc(n)}" style="width:1rem;height:1rem;" ${dipilih.includes(n)?'checked':''} onchange="pbnPesertaSync()"><span>${esc(n)}</span></label>`).join('') || '<p class="text-xs p-2" style="color:var(--muted);">Daftar Anggota masih kosong.</p>'}
      </div>
      </div>
      <div id="pbn_peserta_info" class="text-[11px] mt-1" style="color:var(--muted);"></div>`;
  }
  // Pembinaan Tenaga SAR tab Pembelajaran: Dokumentasi berupa unggah file (format apa saja, bisa banyak file)
  if(key==='pembinaan' && f.key==='dokumentasi'){
    pbnDokExisting = Array.isArray(row && row.dokumentasi_files) ? row.dokumentasi_files.map(x=>Object.assign({},x)) : [];
    pbnDokRemoved = [];
    const lama = (row && row.dokumentasi && !pbnDokExisting.length) ? String(row.dokumentasi) : '';
    return `<input type="hidden" data-field="dokumentasi" id="pbn_dok_val" value="${esc(val===undefined?'':val)}">
      <input type="file" id="pbn_dok_input" multiple onchange="pbnDokRender()" style="width:100%;">
      <div class="text-[11px] mt-1" style="color:var(--muted);">Boleh semua jenis file (PDF, foto, video, dokumen, arsip, dll), maks. 25 MB per file.</div>
      ${lama ? `<div class="text-[11px] mt-1" style="color:var(--muted);">Tautan sebelumnya: ${esc(lama)}</div>` : ''}
      <div id="pbn_dok_list" class="mt-1"></div>`;
  }
  if(f.type==='select'){
    // Nilai lama yang tidak ada di daftar pilihan tetap ditampilkan agar tidak hilang saat Ubah
    const opts = (val && !f.options.includes(val)) ? [val].concat(f.options) : f.options;
    return `<select data-field="${f.key}">${opts.map(o=>`<option value="${esc(o)}" ${val===o?'selected':''}>${esc(o)}</option>`).join('')}</select>`;
  }
  if(f.type==='textarea'){
    return `<textarea data-field="${f.key}" rows="2">${v}</textarea>`;
  }
  if(f.type==='computed'){
    return `<input type="number" data-field="${f.key}" value="${v||0}" readonly disabled style="background:var(--line); font-weight:600; cursor:not-allowed;">`
      + (f.minusBorrowed ? `<div class="text-[11px] mt-1" style="color:var(--muted);">Berkurang saat dipinjam atau sedang dipelihara, kembali otomatis saat dikembalikan / pemeliharaan selesai.</div>` : '');
  }
  if(key==='peminjaman' && f.key==='nama_barang'){
    return `<select data-field="nama_barang" id="pmj_barang"></select>
      <div id="pmj_hint" class="text-[11px] mt-1" style="color:var(--muted);"></div>`;
  }
  if(key==='pemeliharaan' && f.key==='nama_barang' && pengajuanKatForm==='Pemeliharaan'){
    return `<select data-field="nama_barang" id="phr_barang"></select>
      <div id="phr_hint" class="text-[11px] mt-1" style="color:var(--muted);"></div>`;
  }
  return `<input type="${f.type}" data-field="${f.key}" value="${v}"${(f.type==='number' && (f.laut || f.decimal)) ? ' step="any" min="0"' : ''}${f.placeholder ? ` placeholder="${esc(f.placeholder)}"` : ''}>`;
}

/* Dokumentasi Pembinaan (tab Pembelajaran): file apa saja, disimpan di server lewat fdbPut */
let pbnDokExisting = [], pbnDokRemoved = [];
function pbnDokSize(n){ n = n||0; return n>=1048576 ? (n/1048576).toFixed(1)+' MB' : Math.max(1,Math.round(n/1024))+' KB'; }
function pbnDokRender(){
  const box = document.getElementById('pbn_dok_list'); if(!box) return;
  const inp = document.getElementById('pbn_dok_input');
  const baru = inp && inp.files ? Array.from(inp.files) : [];
  box.innerHTML = pbnDokExisting.map((f,i)=>`<div class="flex items-center gap-2 text-xs py-0.5"><span style="flex:1; word-break:break-all;">📎 ${esc(f.name)} <span style="color:var(--muted);">(${pbnDokSize(f.size)})</span></span>
      <button type="button" class="btn btn-ghost !py-0.5 !px-2 text-xs" onclick="pbnDokUnduh('${esc(f.fid)}')">Unduh</button>
      <button type="button" class="btn btn-ghost !py-0.5 !px-2 text-xs" style="color:var(--bad);" onclick="pbnDokHapus(${i})">Hapus</button></div>`).join('')
    + baru.map(f=>`<div class="text-xs py-0.5" style="word-break:break-all;">🆕 ${esc(f.name)} <span style="color:var(--muted);">(${pbnDokSize(f.size)}) — diunggah saat Simpan</span></div>`).join('');
}
function pbnDokHapus(i){ const f = pbnDokExisting[i]; if(!f) return; pbnDokRemoved.push(f.fid); pbnDokExisting.splice(i,1); pbnDokRender(); }
async function pbnDokUnduh(fid){
  try{
    const rec = await fdbGet(fid);
    if(!rec){ toast('File tidak ditemukan di server'); return; }
    const url = URL.createObjectURL(rec.blob);
    const a = document.createElement('a'); a.href = url; a.download = rec.name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 5000);
  }catch(e){ toast('Gagal mengunduh file'); }
}
function pbnDokDetailHtml(row){
  const fl = Array.isArray(row.dokumentasi_files) ? row.dokumentasi_files : [];
  if(!fl.length) return esc(row.dokumentasi||'');
  // Tampilan singkat: tombol "Lihat File" (PDF dibuka di tab baru, jenis lain diunduh)
  return fl.map((f,i)=>`<div><button type="button" class="btn btn-ghost !py-0.5 !px-2 text-xs" title="${esc(f.name)}" onclick="event.stopPropagation(); pbnDokLihat('${esc(f.fid)}')">Lihat File${fl.length>1 ? ' '+(i+1) : ''}</button></div>`).join('');
}
async function pbnDokLihat(fid){
  try{
    const rec = await fdbGet(fid);
    if(!rec){ toast('File tidak ditemukan di server'); return; }
    if(!(rec.blob.type==='application/pdf' || /\.pdf$/i.test(rec.name))) return pbnDokUnduh(fid); // bukan PDF: unduh
    const blob = rec.blob.type==='application/pdf' ? rec.blob : new Blob([rec.blob], {type:'application/pdf'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.target = '_blank'; a.rel = 'noopener';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 60000);
  }catch(e){ toast('Gagal membuka file'); }
}
/* Peserta Pembinaan (tab Pembelajaran): cari, centang, dan Jumlah Peserta terisi otomatis */
function pbnPesertaFilter(q){
  const nq = normSearch(q);
  document.querySelectorAll('#pbn_peserta_list .pbn-peserta-item').forEach(el=>{
    el.style.display = (!nq || normSearch(el.getAttribute('data-cari')).includes(nq)) ? '' : 'none';
  });
}
function pbnPesertaSync(){
  const dipilih = Array.from(document.querySelectorAll('#pbn_peserta_list .pbn-peserta-chk:checked')).map(el=>el.value);
  const hid = document.getElementById('pbn_peserta_val'); if(hid) hid.value = dipilih.join('; ');
  const jml = document.querySelector('[data-field="jumlah_peserta"]'); if(jml) jml.value = dipilih.length;
  const info = document.getElementById('pbn_peserta_info'); if(info) info.textContent = dipilih.length ? dipilih.length + ' peserta dipilih dari Daftar Anggota' : '';
  const lbl = document.getElementById('pbn_peserta_label'); if(lbl) lbl.textContent = dipilih.length ? 'Pilih Peserta (' + dipilih.length + ' dipilih)' : 'Pilih Peserta';
}
function pbnPesertaToggle(){
  const p = document.getElementById('pbn_peserta_panel'); if(!p) return;
  const buka = p.style.display === 'none';
  p.style.display = buka ? '' : 'none';
  const a = document.getElementById('pbn_peserta_arrow'); if(a) a.innerHTML = buka ? '&#9652;' : '&#9662;';
  if(buka){ const c = document.getElementById('pbn_peserta_cari'); if(c) c.focus(); }
}
function wirePesertaPembinaan(key){
  if(key==='pembinaan') pbnDokRender();
  if(key!=='pembinaan' || !document.getElementById('pbn_peserta_list')) return;
  const jml = document.querySelector('[data-field="jumlah_peserta"]');
  if(jml){ jml.readOnly = true; jml.style.background = 'var(--line)'; jml.style.cursor = 'not-allowed'; }
  pbnPesertaSync();
}
function wireComputedFields(fields){
  const q = k => document.querySelector(`[data-field="${k}"]`);
  fields.filter(f=>f.type==='computed').forEach(cf=>{
    const target = q(cf.key);
    if(!target) return;
    const recompute = ()=>{
      let sum = 0;
      (cf.computeFrom||[]).forEach(k=>{ const src = q(k); sum += src ? (parseFloat(src.value)||0) : 0; });
      if(cf.minusBorrowed){ const n = q('nama_barang'); sum = Math.max(0, sum - peralatanDipinjam(n ? n.value : '') - peralatanDipelihara(n ? n.value : '')); }
      target.value = sum;
    };
    (cf.computeFrom||[]).forEach(k=>{ const src = q(k); if(src) src.addEventListener('input', recompute); });
    if(cf.minusBorrowed){ const n = q('nama_barang'); if(n) n.addEventListener('input', recompute); }
    recompute();
  });
}

/* Peminjaman <-> Peralatan: dropdown barang mengikuti Jenis, menampilkan stok Tersedia, dan membatasi jumlah pinjam. */
function pmjOpsi(jenis, exceptId){
  if(jenis==='Kendaraan'){
    return (DB.kendaraan||[]).map(k=>({value:k.nama_kendaraan, label:k.nama_kendaraan + (k.nomor_polisi?' ['+k.nomor_polisi+']':'') + (kendaraanDipelihara(k.nama_kendaraan)?' — sedang dipelihara':''), stok:null}));
  }
  return (DB.peralatan||[]).map(a=>{
    const stok = Math.max(0, peralatanBaik(a) - peralatanDipinjam(a.nama_barang, exceptId) - peralatanDipelihara(a.nama_barang));
    return {value:a.nama_barang, label:a.nama_barang + (a.kode_rak?' ['+a.kode_rak+']':'') + ' — tersedia ' + stok, stok};
  });
}
function wirePeminjamanForm(key, id, row){
  if(key!=='peminjaman') return;
  const q = k => document.querySelector(`[data-field="${k}"]`);
  const jenisEl = q('jenis_barang'), namaEl = q('nama_barang'), jmlEl = q('jumlah'), hint = document.getElementById('pmj_hint');
  const statusEl = q('status'), kembaliEl = q('tanggal_kembali');
  if(!jenisEl || !namaEl) return;
  let pilih = row ? row.nama_barang : '';
  const isiOpsi = ()=>{
    const ops = pmjOpsi(jenisEl.value, id);
    if(pilih && !ops.some(o=>o.value===pilih)) ops.unshift({value:pilih, label:pilih, stok:null});
    namaEl.innerHTML = ops.length
      ? `<option value="">— Pilih ${jenisEl.value==='Kendaraan'?'kendaraan':'peralatan'} —</option>` + ops.map(o=>`<option value="${esc(o.value)}" ${o.value===pilih?'selected':''}>${esc(o.label)}</option>`).join('')
      : `<option value="">(Belum ada data ${jenisEl.value==='Kendaraan'?'Kendaraan':'Peralatan'})</option>`;
    updateHint();
  };
  const updateHint = ()=>{
    pilih = namaEl.value;
    if(jenisEl.value!=='Peralatan' || !pilih){ hint.textContent = ''; if(jmlEl) jmlEl.removeAttribute('max'); return; }
    const alat = (DB.peralatan||[]).find(a=>normNama(a.nama_barang)===normNama(pilih));
    if(!alat){ hint.textContent = ''; return; }
    const stok = Math.max(0, peralatanBaik(alat) - peralatanDipinjam(alat.nama_barang, id) - peralatanDipelihara(alat.nama_barang));
    hint.textContent = 'Stok tersedia: ' + stok + ' dari ' + peralatanBaik(alat) + ' unit kondisi Baik (unit Rusak ' + (parseFloat(alat.jumlah_rusak)||0) + ' tidak dihitung). Tersedia di modul Peralatan akan berkurang sesuai jumlah pinjam.';
    if(jmlEl) jmlEl.setAttribute('max', stok);
  };
  jenisEl.addEventListener('change', ()=>{ pilih = ''; isiOpsi(); });
  namaEl.addEventListener('change', updateHint);
  if(jmlEl && !jmlEl.value) jmlEl.value = 1;
  // Status dan Tanggal Dikembalikan saling sinkron: dikembalikan => stok Peralatan pulih.
  if(statusEl && kembaliEl){
    statusEl.addEventListener('change', ()=>{ if(statusEl.value==='Dikembalikan' && !kembaliEl.value) kembaliEl.value = todayISO(); });
    kembaliEl.addEventListener('change', ()=>{ if(kembaliEl.value) statusEl.value = 'Dikembalikan'; });
  }
  isiOpsi();
}
/* Pemeliharaan: dropdown barang mengikuti Jenis (Peralatan / Kendaraan) dan menampilkan info status peminjaman. */
function phrOpsi(jenis){
  if(jenis==='Kendaraan'){
    return (DB.kendaraan||[]).map(k=>({value:k.nama_kendaraan, label:k.nama_kendaraan + (k.nomor_polisi?' ['+k.nomor_polisi+']':'')}));
  }
  return (DB.peralatan||[]).map(a=>({value:a.nama_barang, label:a.nama_barang + (a.kode_rak?' ['+a.kode_rak+']':'')}));
}
function kendaraanSedangDipinjam(nama){
  const n = normNama(nama); if(!n) return null;
  return (DB.peminjaman||[]).find(p=>p.jenis_barang==='Kendaraan' && normNama(p.nama_barang)===n && p.status!=='Dikembalikan' && !p.tanggal_kembali) || null;
}
function wirePemeliharaanForm(key, id, row){
  if(key!=='pemeliharaan' || pengajuanKatForm!=='Pemeliharaan') return;
  const q = k => document.querySelector(`[data-field="${k}"]`);
  const jenisEl = q('jenis_barang'), namaEl = q('nama_barang'), hint = document.getElementById('phr_hint');
  const statusEl = q('status'), selesaiEl = q('tanggal_selesai'), tglEl = q('tanggal'), kmEl = q('km_saat_ini'), jmlEl = q('jumlah');
  if(!jenisEl || !namaEl) return;
  let pilih = row ? row.nama_barang : '';
  const updateHint = ()=>{
    pilih = namaEl.value;
    const kend = jenisEl.value==='Kendaraan';
    if(kmEl){ const w = kmEl.closest('div'); if(w) w.style.display = kend ? '' : 'none'; }
    if(jmlEl){ const w = jmlEl.closest('div'); if(w) w.style.display = kend ? 'none' : ''; if(!kend && !jmlEl.value) jmlEl.value = 1; }
    if(!pilih){ hint.textContent = ''; return; }
    if(kend){
      const pj = kendaraanSedangDipinjam(pilih);
      const k = (DB.kendaraan||[]).find(x=>normNama(x.nama_kendaraan)===normNama(pilih));
      hint.textContent = (k && k.kondisi ? 'Kondisi saat ini: ' + k.kondisi + '. Setelah pengajuan disetujui, kondisi kendaraan otomatis menjadi Unserviceable saat Sedang Dikerjakan, dan Serviceable bila selesai dengan kondisi Baik. ' : '') + (pj ? 'Perhatian: kendaraan ini masih tercatat dipinjam oleh ' + pj.nama_peminjam + '.' : '');
    } else {
      const a = (DB.peralatan||[]).find(x=>normNama(x.nama_barang)===normNama(pilih));
      if(!a){ hint.textContent = ''; return; }
      const dip = peralatanDipinjam(a.nama_barang);
      const dpl = peralatanDipelihara(a.nama_barang, id);
      hint.textContent = 'Jumlah Baik ' + peralatanBaik(a) + ', Rusak ' + (parseFloat(a.jumlah_rusak)||0) + (dip ? ', sedang dipinjam ' + dip : '') + (dpl ? ', sedang dipelihara ' + dpl : '') + '. Selama status Sedang Dikerjakan dan pengajuan sudah disetujui, stok Tersedia di Peralatan berkurang sesuai Jumlah Unit.';
      if(jmlEl) jmlEl.setAttribute('max', Math.max(0, peralatanBaik(a) - dip - dpl));
    }
  };
  const isiOpsi = ()=>{
    const ops = phrOpsi(jenisEl.value);
    if(pilih && !ops.some(o=>o.value===pilih)) ops.unshift({value:pilih, label:pilih});
    const nm = jenisEl.value==='Kendaraan' ? 'kendaraan' : 'peralatan';
    namaEl.innerHTML = ops.length
      ? `<option value="">— Pilih ${nm} —</option>` + ops.map(o=>`<option value="${esc(o.value)}" ${o.value===pilih?'selected':''}>${esc(o.label)}</option>`).join('')
      : `<option value="">(Belum ada data ${jenisEl.value})</option>`;
    updateHint();
  };
  jenisEl.addEventListener('change', ()=>{ pilih = ''; isiOpsi(); });
  namaEl.addEventListener('change', updateHint);
  if(tglEl && !tglEl.value && !row) tglEl.value = todayISO();
  // Status dan Tanggal Selesai saling sinkron.
  if(statusEl && selesaiEl){
    statusEl.addEventListener('change', ()=>{ if(statusEl.value==='Selesai' && !selesaiEl.value) selesaiEl.value = todayISO(); });
    selesaiEl.addEventListener('change', ()=>{ if(selesaiEl.value) statusEl.value = 'Selesai'; });
  }
  isiOpsi();
}
function openGenericForm(key, id){ if(guardRO(key)) return;
  const meta = key==='anggota' ? {label:'Anggota', fields:ANGGOTA_FIELDS, prefix:'AG'} : (()=>{const m=TABLE_MODULES.find(m=>m.key===key); return {label:m.label, fields:m.fields, prefix:m.prefix};})();
  const rows = DB[key] || [];
  const row = id ? rows.find(r=>r.id===id) : null;
  if(key==='pemeliharaan') pengajuanKatForm = row ? pgjKat(row) : pengajuanTab;
  if(key==='pembinaan') pembinaanKatForm = row ? pbnKat(row) : pembinaanTab;

  if(PESERTA_MODULES[key] && key!=='uji_periodik'){
    pesertaKey = key;
    diklatPesertaContext = 'modal';
    diklatPesertaRows = (row && Array.isArray(row.peserta_matrix)) ? JSON.parse(JSON.stringify(row.peserta_matrix)) : [];
  }

  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display font-semibold text-base">${row?'Ubah':'Tambah'} Data ${esc(meta.label)}${key==='pemeliharaan' ? ' ' + esc(pengajuanKatForm) : ''}${key==='pembinaan' ? ' — ' + esc(pembinaanKatForm) : ''}</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        ${key==='kendaraan' ? kendaraanSaprasPickerHtml(row) : ''}
        ${meta.fields.filter(f=>fieldTampilForm(key,f)).map(f=> f.type==='hidden' ? fieldInput(f, row?row[f.key]:'', key, row) : `
          <div class="${f.type==='textarea'?'sm:col-span-2':''}${f.laut?' sapras-laut-only':''}">
            <label ${f.key==='nomor_polisi' ? 'id="lbl_nomor_polisi"' : ''} class="block text-xs font-medium mb-1" style="color:var(--muted);">${esc(key==='pemeliharaan' ? pgjLabel(f, pengajuanKatForm) : (key==='pembinaan' ? pbnLabel(f, pembinaanKatForm) : f.label))}${f.required?' *':''}${f.type==='computed'?' (otomatis)':''}</label>
            ${fieldInput(f, row?row[f.key]:'', key, row)}
          </div>`).join('')}
      </div>
      ${PESERTA_MODULES[key] && key!=='uji_periodik' ? `<div class="mt-4 pt-4 border-t" style="border-color:var(--line);" id="diklat_peserta_wrap">${diklatPesertaEditorHtml()}</div>` : ''}
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="saveGeneric('${key}', ${row?`'${id}'`:'null'})">Simpan</button>
      </div>
    </div>`, true);
  wireComputedFields(meta.fields);
  wirePeminjamanForm(key, id, row);
  wirePemeliharaanForm(key, id, row);
  wireKendaraanForm(row);
  wirePesertaPembinaan(key);
}

async function saveGeneric(key, id){ if(guardRO(key)) return;
  const meta = key==='anggota' ? {fields:ANGGOTA_FIELDS, prefix:'AG', label:'Anggota'} : (()=>{const m=TABLE_MODULES.find(m=>m.key===key); return {fields:m.fields, prefix:m.prefix, label:m.label};})();
  const rows = DB[key] || (DB[key]=[]);
  const data = {};
  let missing = false;
  document.querySelectorAll('[data-field]').forEach(el=>{
    const f = meta.fields.find(f=>f.key===el.getAttribute('data-field'));
    data[f.key] = el.value;
    if(f.required && !el.value.trim()) missing = true;
  });
  if(missing){ toast('Mohon lengkapi kolom bertanda *'); return; }

  // Pembinaan > Pembelajaran: unggah file dokumentasi baru, lalu simpan metadatanya
  let dokBaru = null;
  if(key==='pembinaan' && document.getElementById('pbn_dok_input')){
    const files = Array.from(document.getElementById('pbn_dok_input').files || []);
    if(files.some(f=>f.size > HASIL_MAX_BYTES)){ toast('Ada file yang melebihi 25 MB'); return; }
    const up = [];
    if(files.length) toast('Mengunggah dokumentasi...');
    try{
      for(const f of files){
        const fid = 'D'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
        await fdbPut({fid, kid:id||'', name:f.name, blob:f});
        up.push({fid, name:f.name, size:f.size, type:f.type||'', uploaded:nowStr()});
      }
    }catch(e){ up.forEach(x=>fdbDel(x.fid)); toast('Gagal mengunggah file: ' + (e.message||'')); return; }
    dokBaru = pbnDokExisting.concat(up);
    data.dokumentasi_files = dokBaru;
    if(dokBaru.length) data.dokumentasi = dokBaru.map(x=>x.name).join('; ');
    pbnDokRemoved.forEach(fid=>fdbDel(fid));
  }

  if(key==='kendaraan' && data.sapras!=='Sapras Laut'){
    meta.fields.filter(f=>f.laut).forEach(f=>{ data[f.key] = ''; });
  }
  if(key==='peminjaman'){
    if(data.status==='Dikembalikan' && !data.tanggal_kembali) data.tanggal_kembali = todayISO();
    if(data.tanggal_kembali) data.status = 'Dikembalikan';
  }
  if(key==='peminjaman' && data.jenis_barang==='Peralatan' && data.status!=='Dikembalikan' && !data.tanggal_kembali){
    const alat = (DB.peralatan||[]).find(a=>normNama(a.nama_barang)===normNama(data.nama_barang));
    if(alat){
      const sisa = peralatanBaik(alat) - peralatanDipinjam(alat.nama_barang, id) - peralatanDipelihara(alat.nama_barang);
      if((parseFloat(data.jumlah)||1) > sisa){ toast('Stok tersedia hanya '+Math.max(0,sisa)+' - jumlah pinjam melebihi stok'); return; }
    }
  }

  if(key==='pemeliharaan') data.kategori_pengajuan = pengajuanKatForm;
  if(key==='pembinaan') data.kategori_pembinaan = pembinaanKatForm;
  if(key==='pemeliharaan' && pengajuanKatForm==='Kebutuhan'){ if(!data.jumlah_kebutuhan) data.jumlah_kebutuhan = 1; }
  if(key==='pemeliharaan' && pengajuanKatForm==='Pemeliharaan'){
    if(data.status==='Selesai' && !data.tanggal_selesai) data.tanggal_selesai = todayISO();
    if(data.tanggal_selesai) data.status = 'Selesai';
    if(data.tanggal && data.tanggal_selesai && data.tanggal_selesai < data.tanggal){ toast('Tanggal Selesai tidak boleh sebelum Tanggal Pemeliharaan'); return; }
    if(data.jenis_barang!=='Kendaraan') data.km_saat_ini = '';
    if(data.jenis_barang==='Kendaraan') data.jumlah = '';
    else {
      if(!data.jumlah) data.jumlah = 1;
      const alat = (DB.peralatan||[]).find(a=>normNama(a.nama_barang)===normNama(data.nama_barang));
      if(alat && data.status==='Sedang Dikerjakan'){
        const sisa = peralatanBaik(alat) - peralatanDipinjam(alat.nama_barang) - peralatanDipelihara(alat.nama_barang, id);
        if((parseFloat(data.jumlah)||1) > sisa){ toast('Stok tersedia hanya '+Math.max(0,sisa)+' - jumlah unit melebihi stok'); return; }
      }
    }
  }

  if(PESERTA_MODULES[key] && key!=='uji_periodik'){
    data.peserta_matrix = diklatPesertaRows.map(r=>Object.assign({}, r));
  }
  data.diperbarui_oleh = pelaku(); // jejak: Nama sebagai (Peran)
  data.diperbarui_waktu = nowStr();

  if(id){
    const idx = rows.findIndex(r=>r.id===id);
    if(key==='pemeliharaan' && pgjSP(rows[idx])==='Ditolak'){ // diperbaiki setelah ditolak = diajukan ulang
      data.status_persetujuan = 'Menunggu Persetujuan'; data.alasan_penolakan = ''; data.keterangan_persetujuan = '';
      data.diajukan_oleh = pelaku(); data.diajukan_waktu = nowStr();
    }
    rows[idx] = Object.assign({}, rows[idx], data);
    const nSync = syncKegiatanFromModule(key, rows[idx]);
    toast(nSync ? 'Data diperbarui, Kegiatan/Tugas terkait ikut menyesuaikan' : 'Data diperbarui');
  }else{
    data.id = uid(meta.prefix, rows);
    if(key==='pemeliharaan'){ data.status_persetujuan = 'Menunggu Persetujuan'; data.diajukan_oleh = pelaku(); data.diajukan_waktu = nowStr(); }
    data.tahun_input = new Date().getFullYear(); data.bulan_input = new Date().getMonth()+1;
    data.tanggal_input = new Date().toLocaleDateString('sv-SE'); // yyyy-mm-dd lokal, acuan filter tanggal Dashboard
    rows.push(data);
    toast('Data ditambahkan');
  }
  if(key==='pemeliharaan') syncKendaraanDariPemeliharaan(id ? rows.find(r=>r.id===id) : data);
  if(key==='pembinaan' && pembinaanKatForm==='Samapta') pbnAbsSync(DB, id ? rows.find(r=>r.id===id) : data);
  persist();
  closeModal();
  render(currentView);
}

// Kegiatan/Tugas yang terhubung dengan satu data modul: kegiatan yang membuat data
// itu (dari_kegiatan_id) atau kegiatan yang menunjuk data itu (linked_record_id).
function kegiatanTerkaitRecord(key, rec){
  if(!rec) return [];
  return (DB.kegiatan||[]).filter(k =>
    (rec.dari_kegiatan_id && k.id===rec.dari_kegiatan_id) ||
    (k.jenis===key && k.linked_record_id===rec.id)
  );
}
function deleteGeneric(key, id, label){ if(guardRO(key)) return;
  const rec = (DB[key]||[]).find(r=>r.id===id);
  if(key==='pemeliharaan' && !canManagePengajuan(rec)){ toast('Pengajuan yang sudah disetujui hanya dapat dihapus oleh ' + PENGAJUAN_ACC_ROLES.join('/') + ' atau Admin Utama'); return; }
  const terkait = kegiatanTerkaitRecord(key, rec);
  const pesan = terkait.length
    ? `Hapus data ${label} "${id}"?\n\nKegiatan/Tugas terkait (${terkait.map(k=>k.id).join(', ')}) juga akan ikut terhapus.`
    : `Hapus data ${label} "${id}"?`;
  if(!confirm(pesan)) return;
  if(key==='pembinaan' && rec) (rec.dokumentasi_files||[]).forEach(f=>fdbDel(f.fid));
  if(key==='pembinaan' && rec && pbnKat(rec)==='Samapta') pbnAbsHapusSumber(rec.id);
  DB[key] = (DB[key]||[]).filter(r=>r.id!==id);
  if(terkait.length){
    const hapusIds = new Set(terkait.map(k=>k.id));
    (DB.kegiatan||[]).filter(k=>hapusIds.has(k.id)).forEach(k=>{ (k.hasil_files||[]).forEach(f=>fdbDel(f.fid)); (k.bukti_files||[]).forEach(f=>fdbDel(f.fid)); lampiranWaList(k).forEach(f=>fdbDel(f.fid)); if(k.sprin) fdbDel(k.sprin.fid); });
    DB.kegiatan = (DB.kegiatan||[]).filter(k=>!hapusIds.has(k.id));
  }
  persist();
  toast(terkait.length ? 'Data dan Kegiatan/Tugas terkait dihapus' : 'Data dihapus');
  render(currentView);
}

/* ============================= AUTO-ISI DATA MODUL DARI KEGIATAN ============================= */
// Saat Kegiatan/Tugas diajukan, data inputannya otomatis dibuatkan juga
// sebagai data baru di modul sesuai Jenis-nya (mis. Jenis=Narasumber -> juga
// tercatat di Narasumber), supaya tidak perlu input dobel.
// Kebalikan dari autoFillModuleFromKegiatan: kalau data di modul diubah, Kegiatan/Tugas
// yang terhubung (dari_kegiatan_id / linked_record_id) ikut disesuaikan.
// Mengembalikan jumlah kegiatan yang diperbarui.
function syncKegiatanFromModule(key, rec){
  const mod = GENERIC_MODULES.find(m=>m.key===key);
  if(!mod || !rec) return 0;
  const list = kegiatanTerkaitRecord(key, rec).filter(k=>k.jenis===key);
  if(!list.length) return 0;
  const has = fk => mod.fields.some(f=>f.key===fk);
  const nameField = mod.fields.find(f=>f.required) || mod.fields.find(f=>f.col && f.type==='text');
  const dateField = mod.fields.find(f=>f.type==='date');
  const pjKey = ['penanggung_jawab','pembina','petugas','instruktur'].find(fk => has(fk) && fk!==(nameField&&nameField.key));
  const notesField = has('materi') ? 'materi' : (['keterangan','hasil'].find(has));
  list.forEach(k=>{
    if(nameField && String(rec[nameField.key]||'').trim()) k.nama = rec[nameField.key];
    if(dateField && rec[dateField.key]) k.tanggal = rec[dateField.key];
    if(has('tanggal_selesai') && rec.tanggal_selesai) k.tanggal_selesai = rec.tanggal_selesai;
    else if(dateField && rec[dateField.key]) k.tanggal_selesai = rec[dateField.key];
    if(has('waktu') && rec.waktu) k.waktu = rec.waktu;
    if(has('lokasi')) k.lokasi = rec.lokasi || '';
    if(pjKey) k.penanggung_jawab = rec[pjKey] || '';
    if(has('jumlah_peserta') && parseInt(rec.jumlah_peserta)>0) k.jumlah_dibutuhkan = parseInt(rec.jumlah_peserta);
    if(notesField) k.deskripsi = rec[notesField] || '';
    k.diperbarui = nowStr();
  });
  return list.length;
}

function autoFillModuleFromKegiatan(jenisKey, k){
  if(jenisKey === 'rescuer') return null; // Rescuer mengambil data langsung dari Daftar Anggota
  const mod = GENERIC_MODULES.find(m=>m.key===jenisKey);
  if(!mod) return null;
  const rows = DB[jenisKey] || (DB[jenisKey]=[]);
  const data = {};

  const nameField = mod.fields.find(f=>f.required) || mod.fields.find(f=>f.col && f.type==='text');
  if(nameField) data[nameField.key] = k.nama;

  const dateField = mod.fields.find(f=>f.type==='date');
  if(dateField) data[dateField.key] = k.tanggal;
  if(mod.fields.some(f=>f.key==='tanggal_selesai')) data.tanggal_selesai = k.tanggal_selesai || k.tanggal;

  if(mod.fields.some(f=>f.key==='waktu')) data.waktu = k.waktu;
  if(mod.fields.some(f=>f.key==='lokasi')) data.lokasi = k.lokasi;

  const pjFieldKey = ['penanggung_jawab','pembina','petugas','instruktur'].find(fk => mod.fields.some(f=>f.key===fk) && fk!==(nameField&&nameField.key));
  if(pjFieldKey) data[pjFieldKey] = k.penanggung_jawab;

  if(mod.fields.some(f=>f.key==='jumlah_peserta')) data.jumlah_peserta = k.jumlah_dibutuhkan;

  if(mod.fields.some(f=>f.key==='materi')) data.materi = k.deskripsi;
  else {
    const notesField = mod.fields.find(f=>['keterangan','hasil'].includes(f.key));
    if(notesField) data[notesField.key] = k.deskripsi;
  }

  const statusField = mod.fields.find(f=>f.isStatus);
  if(statusField && statusField.options && statusField.options.length) data[statusField.key] = statusField.options[0];

  if(mod.fields.some(f=>f.key==='dokumentasi') && k.file_pdf) data.dokumentasi = k.file_pdf.name;

  // ID data modul dibuat SAMA PERSIS dengan ID Kegiatan/Tugas-nya (mis.
  // KGT-003 tetap KGT-003 di modul Diklat), bukan diganti prefix modul.
  // Kalau ID itu kebetulan sudah terpakai di modul tsb, baru pakai
  // penomoran otomatis biasa.
  let newId = k.id;
  if(!newId || rows.some(r=>r.id===newId)) newId = uid(mod.prefix, rows);
  data.id = newId;
  data.dari_kegiatan_id = k.id; // jejak asal data, bukan kolom yang ditampilkan tabel
  rows.push(data);
  return data.id;
}

// Kalau status Kegiatan/Tugas berubah (persetujuan/pelaksanaan), sesuaikan
// juga status data di modul terkait — dicari berdasarkan Jenis (k.jenis) dan
// ID data yang otomatis terbentuk (k.linked_record_id).
function syncModuleStatusFromKegiatan(k){
  if(!k || !k.linked_record_id) return;
  const mod = GENERIC_MODULES.find(m=>m.key===k.jenis);
  if(!mod) return;
  const rows = DB[k.jenis] || [];
  const rec = rows.find(r=>r.id===k.linked_record_id);
  if(!rec) return;
  const statusField = mod.fields.find(f=>f.isStatus);
  if(!statusField || !statusField.options) return;
  let target = null;
  if(k.status_pelaksanaan === 'Selesai') target = 'Selesai';
  else if(k.status_pelaksanaan === 'Dalam Pelaksanaan') target = 'Berlangsung';
  else if(k.status_pelaksanaan === 'Draft') target = 'Direncanakan';
  if(target && statusField.options.includes(target)){
    rec[statusField.key] = target;
  }
}

/* ============================= KEGIATAN / TUGAS ============================= */
/* ============================= PERSURATAN: SURAT MASUK & SURAT KELUAR ============================= */
// Surat Masuk = tampilan data DB.surat_masuk yang dicatat di modul Kegiatan/Tugas (satu sumber data, tidak digandakan).
// Surat Keluar = data sendiri (DB.surat_keluar), dikelola langsung di modul ini.
const SURAT_SIFAT = ['Biasa','Segera','Sangat Segera','Rahasia'];
let psTab = 'masuk';
let skPdfFile = null, skEditId = null;
function getSuratKeluar(id){ return (DB.surat_keluar||[]).find(x=>x.id===id); }
/* Surat Tugas (Sprin) yang sudah diupload Admin di Kegiatan/Tugas otomatis tampil sebagai Surat Keluar (tanpa data ganda). */
/* Keterangan proses: Belum Di Proses (default) -> Sedang Di Proses (ditandai Admin) -> Selesai (otomatis setelah file diupload). */
const PROSES_LIST = ['Belum Di Proses','Sedang Di Proses'];
function prosesBadgeHtml(st){ return `<span class="badge ${st==='Selesai'?'b-ok':(st==='Sedang Di Proses'?'b-warn':'b-bad')}">${esc(st)}</span>`; }
function suratKeluarStatus(sk){
  if(sk.otomatis){ const k = getKegiatan(sk.kegiatan_id); return sprinStatusLabel(k||{}); }
  if(sk.status_kirim==='Terkirim' || sk.file_pdf || lampiranWaList(sk).length) return 'Selesai';
  return sk.status_proses==='Sedang Di Proses' ? 'Sedang Di Proses' : 'Belum Di Proses';
}
function setSuratKeluarProses(id, val){
  if(!isAdmin()){ toast('Hanya Admin yang dapat mengubah keterangan'); return; }
  if(!PROSES_LIST.includes(val)) return;
  const sk = getSuratKeluar(id); if(!sk) return;
  sk.status_proses = val; sk.diperbarui = nowStr();
  persist(); renderPersuratan(); toast('Keterangan: ' + val);
}
function setSprinProses(id, val, dariDetail){
  if(!isAdmin()){ toast('Hanya Admin yang dapat mengubah keterangan'); return; }
  if(!PROSES_LIST.includes(val)) return;
  const k = getKegiatan(id); if(!k || k.sprin) return;
  k.sprin_proses = val; k.diperbarui = nowStr();
  persist(); toast('Keterangan Sprin: ' + val);
  if(dariDetail){ render('kegiatan'); openKegiatanDetail(id); } else renderPersuratan();
}
function suratKeluarOtomatis(){
  return (DB.kegiatan||[]).filter(k=>k.sprin || sprinPerluTampil(k)).map(k=>{
    const nm = (k.anggota_ids||[]).map(id=>{ const a=(DB.anggota||[]).find(x=>x.id===id); return a ? a.nama : ''; }).filter(Boolean).join(', ');
    return {id:'ST-'+k.id, otomatis:true, kegiatan_id:k.id, nomor_surat:k.sprin?String(k.sprin.name||'').replace(/\.pdf$/i,''):'(Sprin belum diupload)',
      tanggal_surat:k.sprin?(k.sprin.tanggal||''):'', tujuan:nm||'-', perihal:'Surat Tugas: '+(k.nama||k.id), sifat:'Biasa', lampiran:'-'};
  });
}
/* Tgl Surat (Surat Keluar) terisi otomatis = tanggal file surat diupload. Belum ada file -> '-'. */
function suratKeluarTglUpload(sk){
  if(sk.otomatis) return sk.tanggal_surat||'';
  if(sk.tgl_upload) return sk.tgl_upload;
  const wa = lampiranWaList(sk).map(f=>String(f.tanggal||f.uploaded_iso||f.at||'').slice(0,10)).find(d=>/^\d{4}-\d{2}-\d{2}$/.test(d));
  if(wa) return wa;
  return sk.file_pdf ? (sk.tanggal_surat||'') : '';
}
function suratKeluarSemua(){ return (DB.surat_keluar||[]).concat(suratKeluarOtomatis()); }
function setPsTab(t){ psTab = t; renderPersuratan(); }
function renderPersuratan(){
  const nMasuk = (DB.surat_masuk||[]).length, nKeluar = suratKeluarSemua().length;
  const tabBtn = (key,label,n) => `<button class="btn ${psTab===key?'btn-primary':'btn-ghost'}" onclick="setPsTab('${key}')">${label} <span class="badge b-info" style="margin-left:.35rem;">${n}</span></button>`;
  document.getElementById('app').innerHTML = `
    <div class="flex flex-wrap items-center gap-2 mb-3">
      ${tabBtn('masuk','Surat Masuk',nMasuk)}
      ${tabBtn('keluar','Surat Keluar',nKeluar)}
    </div>
    ${psTab==='masuk' ? psMasukHtml() : psKeluarHtml()}`;
  enhanceTableScroll();
}
function psMasukHtml(){
  const q = (search.psmasuk||'').toLowerCase();
  const rows = (DB.surat_masuk||[]).filter(s => matchSearch(s, q));
  return `
    <div class="panel rounded-xl p-4">
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <input type="text" placeholder="Cari surat masuk..." value="${esc(search.psmasuk||'')}" class="!w-56" data-sid="search-psmasuk" oninput="runSearchRender(this, ()=>{ search.psmasuk=this.value; renderPersuratan(); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} surat</span>
        ${impSuratBtnHtml('ml-auto')}
        <button class="btn btn-ghost ${canImportExcel('surat_masuk') ? '' : 'ml-auto'}" onclick="kegTab='surat'; render('kegiatan')">Kelola di Kegiatan/Tugas</button>
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">Daftar ini diambil otomatis dari Surat Masuk pada modul Kegiatan/Tugas. Surat baru dicatat dan di-ACC di modul tersebut.</p>
      <div class="tablewrap"><table>
        <thead><tr><th>ID</th><th>Nomor Surat</th><th>Asal Surat</th><th>Perihal</th><th>Tgl Surat</th><th>Tgl Diterima</th><th>Batas Waktu</th><th>Status Surat</th><th>Instruksi KKS</th><th>Persetujuan</th><th>File</th><th>Aksi</th></tr></thead>
        <tbody>${rows.length ? rows.slice().reverse().map(sm=>`
          <tr style="cursor:pointer;" onclick="openSuratDetail('${esc(sm.id)}')">
            <td class="font-medium whitespace-nowrap">${esc(sm.id)}</td><td style="min-width:150px;">${esc(sm.nomor_surat)}</td><td style="min-width:150px;">${esc(sm.asal_surat)||'-'}</td>
            <td style="min-width:200px;"><div class="clamp3" title="${esc(sm.perihal)}">${esc(sm.perihal)}</div></td>
            <td class="whitespace-nowrap">${esc(tglDMY(sm.tanggal_surat))||'-'}</td><td class="whitespace-nowrap">${esc(tglDMY(sm.tanggal_diterima))||'-'}${sm.jam_diterima ? '<div class="text-[11px]" style="color:var(--muted);">'+esc(sm.jam_diterima)+' WIB</div>' : ''}</td>
            <td class="whitespace-nowrap">${esc(tglDMY(sm.batas_waktu))||'-'}</td><td class="whitespace-nowrap">${esc(sm.status_surat)||'-'}</td><td style="min-width:120px;">${esc(sm.instruksi_kks)||'-'}</td>
            <td>${suratPersetujuanHtml(sm)}</td>
            <td onclick="event.stopPropagation();">${sm.file_pdf ? `<button class="btn btn-navy !py-1 !px-2 text-xs" onclick="openPdfDataUrl(getSurat('${esc(sm.id)}').file_pdf.data)">Lihat</button>` : '-'}</td>
            <td onclick="event.stopPropagation();"><button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openSuratDetail('${esc(sm.id)}')">Detail</button></td>
          </tr>`).join('') : `<tr><td colspan="12" class="text-center py-6" style="color:var(--muted);">Belum ada surat masuk.</td></tr>`}</tbody>
      </table></div>
    </div>`;
}
function psKeluarHtml(){
  const q = (search.pskeluar||'').toLowerCase();
  const rows = suratKeluarSemua().filter(s => matchSearch(s, q));
  const ro = !isAdmin();
  return `
    <div class="panel rounded-xl p-4">
      <div class="flex flex-wrap items-center gap-2 mb-3">
        <input type="text" placeholder="Cari surat keluar..." value="${esc(search.pskeluar||'')}" class="!w-56" data-sid="search-pskeluar" oninput="runSearchRender(this, ()=>{ search.pskeluar=this.value; renderPersuratan(); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} surat</span>
        ${impSuratBtnHtml('ml-auto')}
        ${ro ? '' : `<button class="btn btn-primary ${canImportExcel('surat_masuk') ? '' : 'ml-auto'}" onclick="openSuratKeluarForm()">+ Surat Keluar</button>`}
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">Sprin (ID berawalan ST-) dari Kegiatan/Tugas otomatis masuk ke daftar ini. Keterangan: <strong>Belum Di Proses</strong> → <strong>Sedang Di Proses</strong> → <strong>Selesai</strong> (otomatis setelah file diupload). Hanya Admin yang dapat menginput dan mengubah keterangan.</p>
      <div class="tablewrap"><table>
        <thead><tr><th>ID</th><th>Nomor Surat</th><th>Tujuan</th><th>Perihal</th><th>Sifat</th><th>Tgl Surat</th><th>Status Kirim</th><th>Keterangan</th><th>File</th><th>Aksi</th></tr></thead>
        <tbody>${rows.length ? rows.slice().reverse().map(sk=>`
          <tr>
            <td class="font-medium whitespace-nowrap">${esc(sk.id)}</td><td style="min-width:150px;">${esc(sk.nomor_surat)}</td><td style="min-width:150px;">${esc(sk.tujuan)||'-'}</td>
            <td style="min-width:200px;"><div class="clamp3" title="${esc(sk.perihal)}">${esc(sk.perihal)}</div></td>
            <td class="whitespace-nowrap">${esc(sk.sifat)||'-'}</td><td class="whitespace-nowrap">${esc(tglDMY(suratKeluarTglUpload(sk)))||'-'}</td>
            <td class="whitespace-nowrap">${sk.status_kirim ? `<span class="badge ${sk.status_kirim==='Terkirim' ? 'b-ok' : 'b-warn'}">${esc(sk.status_kirim)}</span>` : '-'}</td>
            <td class="whitespace-nowrap">${suratKeluarKetHtml(sk, ro)}</td>
            <td>${sk.otomatis ? (getKegiatan(sk.kegiatan_id)&&getKegiatan(sk.kegiatan_id).sprin ? `<button class="btn btn-navy !py-1 !px-2 text-xs" onclick="lihatSprin('${esc(sk.kegiatan_id)}')">Lihat</button>` : '-') : (sk.file_pdf ? `<button class="btn btn-navy !py-1 !px-2 text-xs" onclick="openPdfDataUrl(getSuratKeluar('${esc(sk.id)}').file_pdf.data)">Lihat</button>` : (lampiranWaList(sk).length ? lampiranWaList(sk).map(f=>`<button class="btn btn-navy !py-1 !px-2 text-xs" title="${esc(f.name)} (WhatsApp)" onclick="lihatLampiran('${esc(f.fid)}')">Lihat</button>`).join(' ') : '-'))}</td>
            <td class="whitespace-nowrap">${sk.otomatis ? `<span class="badge b-info">Otomatis</span> <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="kegTab='kegiatan'; render('kegiatan'); openKegiatanDetail('${esc(sk.kegiatan_id)}')">Kegiatan</button>` : ro ? '-' : `<button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openSuratKeluarForm('${esc(sk.id)}')">Ubah</button>
              <button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="deleteSuratKeluar('${esc(sk.id)}')">Hapus</button>`}</td>
          </tr>`).join('') : `<tr><td colspan="10" class="text-center py-6" style="color:var(--muted);">Belum ada surat keluar.</td></tr>`}</tbody>
      </table></div>
    </div>`;
}
function suratKeluarKetHtml(sk, ro){
  const st = suratKeluarStatus(sk);
  if(st==='Selesai' || ro) return prosesBadgeHtml(st);
  const fn = sk.otomatis ? `setSprinProses('${esc(sk.kegiatan_id)}',this.value)` : `setSuratKeluarProses('${esc(sk.id)}',this.value)`;
  return `<select class="!w-40 !py-1 text-xs" aria-label="Keterangan proses" onchange="${fn}">${PROSES_LIST.map(o=>`<option ${st===o?'selected':''}>${o}</option>`).join('')}</select>`;
}
function guardAdminSurat(){ if(!isAdmin()){ toast('Hanya Admin yang dapat menginput Surat Keluar'); return true; } return false; }
function openSuratKeluarForm(editId){ if(guardAdminSurat()) return;
  const e = editId ? getSuratKeluar(editId) : null;
  if(editId && !e) return;
  skEditId = e ? e.id : null;
  skPdfFile = e && e.file_pdf ? e.file_pdf : null;
  const lbl = t => `<label class="block text-xs font-medium mb-1" style="color:var(--muted);">${t}</label>`;
  const val = (k,def='') => esc(e ? (e[k]==null ? '' : e[k]) : def);
  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-display font-semibold text-base">${e ? 'Ubah Surat Keluar ' + esc(e.id) : 'Surat Keluar Baru'}</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div>${lbl('Nomor Surat *')}<input type="text" id="sk_nomor" value="${val('nomor_surat')}"></div>
        <div>${lbl('Tujuan *')}<input type="text" id="sk_tujuan" value="${val('tujuan')}"></div>
        <div>${lbl('Sifat')}<select id="sk_sifat">${SURAT_SIFAT.map(o=>`<option ${(e?e.sifat:'Biasa')===o?'selected':''}>${o}</option>`).join('')}</select></div>
        <div class="sm:col-span-2">${lbl('Perihal *')}<input type="text" id="sk_perihal" value="${val('perihal')}"></div>
        <div>${lbl('Ditandatangani Oleh')}<input type="text" id="sk_ttd" value="${val('penandatangan')}"></div>
        <div>${lbl('Status Kirim')}<select id="sk_kirim">${['Belum Terkirim','Terkirim'].map(o=>`<option ${(e&&e.status_kirim?e.status_kirim:'Belum Terkirim')===o?'selected':''}>${o}</option>`).join('')}</select></div>
        <div class="sm:col-span-2">${lbl('File Surat (PDF)')}
          <input type="file" id="sk_pdf" accept="application/pdf,.pdf" onchange="handleSkPdfChange(this)">
          <p id="sk_pdf_info" class="text-[11px] mt-1" style="color:var(--muted);">${e && e.file_pdf ? 'File saat ini: ' + esc(e.file_pdf.name) + '. Pilih file baru hanya jika ingin menggantinya.' : 'Maks. 10MB, hanya format PDF.'} Tanggal surat terisi otomatis saat file diupload.</p>
        </div>
        <div class="sm:col-span-2">${lbl('Keterangan Proses')}<select id="sk_proses">${PROSES_LIST.map(o=>`<option ${(e&&e.status_proses?e.status_proses:'Belum Di Proses')===o?'selected':''}>${o}</option>`).join('')}</select>
          <p class="text-[11px] mt-1" style="color:var(--muted);">Otomatis menjadi <strong>Selesai</strong> setelah file surat diupload.</p></div>
        <div class="sm:col-span-2">${lbl('Catatan')}<textarea id="sk_ket" rows="2">${val('keterangan')}</textarea></div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="saveSuratKeluar()">${e ? 'Simpan Perubahan' : 'Simpan Surat Keluar'}</button>
      </div>
    </div>`, true);
}
function handleSkPdfChange(input){
  const info = document.getElementById('sk_pdf_info');
  const cur = skEditId ? getSuratKeluar(skEditId) : null;
  skPdfFile = cur && cur.file_pdf ? cur.file_pdf : null;
  const file = input.files && input.files[0];
  if(!file){ info.textContent = cur && cur.file_pdf ? 'File saat ini: ' + cur.file_pdf.name : 'Maks. 10MB, hanya format PDF.'; return; }
  if(file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')){ toast('File harus berformat PDF'); input.value=''; return; }
  if(file.size > 10*1024*1024){ toast('Ukuran file maksimal 10MB'); input.value=''; return; }
  const reader = new FileReader();
  reader.onload = () => { skPdfFile = {name:file.name, size:file.size, data:reader.result}; info.textContent = `Terpilih: ${file.name} (${(file.size/1024).toFixed(0)} KB)`; };
  reader.onerror = () => toast('Gagal membaca file PDF');
  reader.readAsDataURL(file);
}
function saveSuratKeluar(){ if(guardAdminSurat()) return;
  const v = id => document.getElementById(id).value.trim();
  const nomor = v('sk_nomor'), tujuan = v('sk_tujuan'), perihal = v('sk_perihal');
  if(!nomor){ toast('Nomor surat wajib diisi'); return; }
  if(!tujuan && !(skEditId && (getSuratKeluar(skEditId)||{}).sumber==='Import Excel')){ toast('Tujuan wajib diisi'); return; }
  if(!perihal){ toast('Perihal wajib diisi'); return; }
  if(!DB.surat_keluar) DB.surat_keluar = [];
  const data = {nomor_surat:nomor, tujuan, perihal, sifat:v('sk_sifat'), status_kirim:v('sk_kirim'),
    penandatangan:v('sk_ttd'), keterangan:document.getElementById('sk_ket').value, file_pdf:skPdfFile, status_proses:v('sk_proses')};
  if(skEditId){
    const ex = getSuratKeluar(skEditId);
    if(!ex){ toast('Surat tidak ditemukan'); return; }
    const oldPdf = ex.file_pdf;
    Object.assign(ex, data, {diperbarui:nowStr()});
    if(skPdfFile && skPdfFile !== oldPdf) ex.tgl_upload = todayISO(); // file baru/diganti -> tanggal upload hari ini
    ex.tanggal_surat = suratKeluarTglUpload(ex);
    toast('Surat keluar diperbarui');
  } else {
    const tgl = skPdfFile ? todayISO() : '';
    DB.surat_keluar.push(Object.assign({id:uid('SK', DB.surat_keluar), dibuat_oleh:pelaku(), dibuat:nowStr(), diperbarui:nowStr(), tgl_upload:tgl, tanggal_surat:tgl}, data));
    toast('Surat keluar disimpan');
  }
  persist();
  skPdfFile = null; skEditId = null;
  closeModal(); psTab = 'keluar'; renderPersuratan();
}
function deleteSuratKeluar(id){ if(guardAdminSurat()) return;
  const sk = getSuratKeluar(id); if(!sk) return;
  if(!confirm('Hapus surat keluar ' + sk.nomor_surat + '?')) return;
  DB.surat_keluar = DB.surat_keluar.filter(x=>x.id!==id);
  persist(); renderPersuratan(); toast('Surat keluar dihapus');
}

/* ============================= SURAT MASUK -> ACC -> KEGIATAN ============================= */
// Alur awal modul Kegiatan/Tugas: Surat Masuk dicatat -> menunggu ACC pimpinan
// (KKS/KSB/KSD/KSO) -> setelah di-ACC, Kegiatan/Tugas terbentuk otomatis
// (beserta data di modul sesuai Jenis-nya lewat autoFillModuleFromKegiatan).
const SURAT_ACC_ROLES = ['KKS','KSB','KSD','KSO'];
// 'salah_satu' = cukup 1 dari 4 pejabat yang ACC | 'semua' = keempatnya harus ACC
const SURAT_ACC_MODE = 'salah_satu';
let kegTab = 'surat';
let smPdfFile = null; // {name, size, data}
let smEditId = null;  // id surat yang sedang diedit (null = mode tambah)

function canAccSurat(){ return isSuper() || SURAT_ACC_ROLES.includes(currentRole); }
// Edit/Hapus: siapa pun boleh selama surat masih menunggu; setelah diputuskan hanya pejabat ACC.
function canManageSurat(sm){ return sm.status==='Menunggu Persetujuan' || canAccSurat() || (!!sm.tanpa_acc && isAdmin()); }
function suratPersetujuanHtml(sm){ return sm.tanpa_acc ? '<span class="badge b-info">Tanpa ACC (Import)</span>' : statusBadge(sm.status); }
function getSurat(id){ return (DB.surat_masuk||[]).find(x=>x.id===id); }
function suratAccRoles(sm){ return (sm.acc||[]).map(a=>a.role); }
function suratAccInfo(sm){
  if(sm.tanpa_acc) return 'Import Excel — tidak perlu persetujuan';
  if(sm.status==='Ditolak') return '';
  if(SURAT_ACC_MODE!=='semua') return sm.status==='Disetujui' ? 'ACC: '+suratAccRoles(sm).join(', ') : 'Menunggu salah satu ACC '+SURAT_ACC_ROLES.join('/');
  const sudah = suratAccRoles(sm);
  return 'ACC ' + sudah.length + '/' + SURAT_ACC_ROLES.length + (sudah.length ? ' ('+sudah.join(', ')+')' : '');
}
function setKegTab(t){ kegTab = t; renderKegiatan(); }

function renderKegiatan(){
  const menunggu = (DB.surat_masuk||[]).filter(s=>s.status==='Menunggu Persetujuan').length;
  const tabBtn = (key,label,badge) => `<button class="btn ${kegTab===key?'btn-primary':'btn-ghost'}" onclick="setKegTab('${key}')">${label}${badge?` <span class="badge b-warn" style="margin-left:.35rem;">${badge}</span>`:''}</button>`;
  document.getElementById('app').innerHTML = `
    <div class="flex flex-wrap items-center gap-2 mb-3">
      ${tabBtn('surat','1. Surat Masuk',menunggu)}
      <span style="color:var(--muted);">→</span>
      <span class="text-xs" style="color:var(--muted);">ACC ${SURAT_ACC_ROLES.join('/')}</span>
      <span style="color:var(--muted);">→</span>
      ${tabBtn('kegiatan','2. Kegiatan/Tugas',0)}
      <span style="color:var(--muted);">→</span>
      ${tabBtn('selesai','3. Tugas Selesai',0)}
    </div>
    ${kegTab==='surat' ? suratListHtml() : (kegTab==='selesai' ? tugasSelesaiListHtml() : kegiatanListHtml())}`;
  enhanceTableScroll();
}

function tugasSelesaiListHtml(){
  const q = (search.selesai||'').toLowerCase();
  const rows = (DB.kegiatan||[]).filter(k => k.status_pelaksanaan==='Selesai' && matchSearch(k, q) && inPeriode(k, 'kegiatan'));
  const namaAnggota = k => (k.anggota_ids||[]).map(id=>{ const a=(DB.anggota||[]).find(x=>x.id===id); return a ? namaDepan(a.nama) : ''; }).filter(Boolean).join(', ');
  return `
    <div class="panel rounded-xl p-4">
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <input type="text" placeholder="Cari tugas selesai..." value="${esc(search.selesai||'')}" class="!w-56" data-sid="search-selesai" oninput="runSearchRender(this, ()=>{ search.selesai=this.value; renderKegiatan(); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} tugas selesai</span>
        ${periodeSelectHtml('kegiatan')}
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">Daftar otomatis dari Kegiatan/Tugas yang status pelaksanaannya Selesai.</p>
      <div class="tablewrap">
        <table>
          <thead><tr>
            <th>ID</th><th>Nama Kegiatan</th><th>Jenis</th><th>Tanggal</th><th>Lokasi</th><th>Anggota Bertugas</th>
            <th>Hasil</th><th>Pelaksanaan</th><th>Aksi</th>
          </tr></thead>
          <tbody>
            ${rows.length ? rows.slice().reverse().map(k=>`
              <tr style="cursor:pointer;" onclick="openKegiatanDetail('${k.id}')">
                <td class="font-medium">${esc(k.id)}</td>
                <td><div class="clamp3" title="${esc(k.nama)}">${esc(k.nama)}</div></td>
                <td>${esc(k.jenis_label)}${k.jenis_ket ? '<div class="text-[11px]" style="color:var(--muted);">'+esc(k.jenis_ket)+'</div>' : ''}</td>
                <td>${esc(tglRentang(k))}</td>
                <td>${esc(k.lokasi)||'-'}</td>
                <td>${esc(namaAnggota(k))||'-'}</td>
                <td>${(k.hasil_files||[]).length ? (k.hasil_files.length+' file') : '<span style="color:var(--muted);">Belum ada</span>'}</td>
                <td>${statusBadge(k.status_pelaksanaan)}</td>
                <td onclick="event.stopPropagation();"><button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openKegiatanDetail('${k.id}')">Detail</button></td>
              </tr>`).join('') : `<tr><td colspan="9" class="text-center py-6" style="color:var(--muted);">Belum ada tugas yang selesai.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>`;
}

function suratListHtml(){
  const q = (search.surat||'').toLowerCase();
  const rows = (DB.surat_masuk||[]).filter(s => matchSearch(s, q));
  return `
    <div class="panel rounded-xl p-4">
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <input type="text" placeholder="Cari surat masuk..." value="${esc(search.surat||'')}" class="!w-56" data-sid="search-surat" oninput="runSearchRender(this, ()=>{ search.surat=this.value; renderKegiatan(); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} surat</span>
        ${impSuratBtnHtml('ml-auto')}
        <button class="btn btn-primary ${canImportExcel('surat_masuk') ? '' : 'ml-auto'}" onclick="openSuratForm()">+ Surat Masuk</button>
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">Catat surat masuk → menunggu ACC ${SURAT_ACC_ROLES.join('/')} → setelah di-ACC, Kegiatan/Tugas terbentuk otomatis.</p>
      <div class="tablewrap">
        <table>
          <thead><tr>
            <th>ID</th><th>Nomor Surat</th><th>Asal Surat</th><th>Perihal</th><th>Jenis</th><th>Tgl Surat</th>
            <th>Persetujuan</th><th>Kegiatan</th><th>Aksi</th>
          </tr></thead>
          <tbody>
            ${rows.length ? rows.slice().reverse().map(sm=>{
              const kg = sm.kegiatan_id ? getKegiatan(sm.kegiatan_id) : null;
              return `
              <tr style="cursor:pointer;" onclick="openSuratDetail('${sm.id}')">
                <td class="font-medium">${esc(sm.id)}</td>
                <td>${esc(sm.nomor_surat)}</td>
                <td>${esc(sm.asal_surat)||'-'}</td>
                <td>${esc(sm.perihal)}</td>
                <td>${esc(sm.jenis_label)}${sm.jenis_ket ? '<div class="text-[11px]" style="color:var(--muted);">'+esc(sm.jenis_ket)+'</div>' : ''}</td>
                <td>${esc(tglDMY(sm.tanggal_surat))||'-'}</td>
                <td>${suratPersetujuanHtml(sm)}<div class="text-[11px]" style="color:var(--muted);">${esc(suratAccInfo(sm))}</div></td>
                <td onclick="event.stopPropagation();">${kg ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="kegTab='kegiatan'; render('kegiatan'); openKegiatanDetail('${kg.id}')">${esc(kg.id)}</button>` : '-'}</td>
                <td onclick="event.stopPropagation();"><div class="flex flex-wrap gap-1">
                  <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openSuratDetail('${sm.id}')">Detail</button>
                  ${canManageSurat(sm) ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openSuratForm('${sm.id}')">Edit</button>
                  <button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="deleteSurat('${sm.id}')">Hapus</button>` : ''}
                </div></td>
              </tr>`;}).join('') : `<tr><td colspan="9" class="text-center py-6" style="color:var(--muted);">Belum ada surat masuk.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>`;
}

function kegiatanListHtml(){
  const q = (search.kegiatan||'').toLowerCase();
  const rows = DB.kegiatan.filter(k => k.status_pelaksanaan!=='Selesai' && matchSearch(k, q) && inPeriode(k, 'kegiatan'));
  return `
    <div class="panel rounded-xl p-4">
      <div class="flex flex-wrap items-center gap-2 mb-2">
        <input type="text" placeholder="Cari kegiatan..." value="${esc(search.kegiatan||'')}" class="!w-56" data-sid="search-kegiatan" oninput="runSearchRender(this, ()=>{ search.kegiatan=this.value; renderKegiatan(); })">
        <span class="text-xs ml-1" style="color:var(--muted);">${rows.length} kegiatan</span>
        ${periodeSelectHtml('kegiatan')}
        ${impBtnHtml('kegiatan', 'ml-auto')}
        <button class="btn btn-ghost ${canImportExcel() ? '' : 'ml-auto'}" onclick="setKegTab('surat')">+ Dari Surat Masuk</button>
        <button class="btn btn-primary" onclick="openKegiatanForm()">+ Ajukan Kegiatan/Tugas</button>
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">Kegiatan/Tugas terbentuk otomatis setelah Surat Masuk di-ACC, atau bisa diajukan langsung tanpa surat masuk (menunggu persetujuan di Detail).</p>
      <div class="tablewrap">
        <table>
          <thead><tr>
            <th>ID</th><th>Nama Kegiatan</th><th>Jenis</th><th>Tanggal</th><th>Lokasi</th><th>Asal Surat</th>
            <th>Persetujuan</th><th>Pelaksanaan</th><th>WhatsApp</th><th>Aksi</th>
          </tr></thead>
          <tbody>
            ${rows.length ? rows.slice().reverse().map(k=>{
              const sm = k.surat_id ? getSurat(k.surat_id) : null;
              return `
              <tr style="cursor:pointer;" onclick="openKegiatanDetail('${k.id}')">
                <td class="font-medium">${esc(k.id)}</td>
                <td><div class="clamp3" title="${esc(k.nama)}">${esc(k.nama)}</div></td>
                <td>${esc(k.jenis_label)}${k.jenis_ket ? '<div class="text-[11px]" style="color:var(--muted);">'+esc(k.jenis_ket)+'</div>' : ''}</td>
                <td>${esc(tglRentang(k))}</td>
                <td>${esc(k.lokasi)}</td>
                <td>${sm ? esc(sm.id) : '-'}</td>
                <td>${statusBadge(k.status_persetujuan)}</td>
                <td>${statusBadge(k.status_pelaksanaan)}</td>
                <td>${statusBadge(k.status_wa)}</td>
                <td onclick="event.stopPropagation();"><button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="openKegiatanDetail('${k.id}')">Detail</button></td>
              </tr>`;}).join('') : `<tr><td colspan="10" class="text-center py-6" style="color:var(--muted);">Belum ada kegiatan.</td></tr>`}
          </tbody>
        </table>
      </div>
    </div>`;
}

function openSuratForm(editId){
  const e = editId ? getSurat(editId) : null;
  if(editId && !e) return;
  smEditId = e ? e.id : null;
  smPdfFile = e && e.file_pdf ? e.file_pdf : null;
  const jenisOptions = GENERIC_MODULES.map(m=>`<option value="${m.key}" ${e && e.jenis===m.key ? 'selected' : ''}>${esc(m.label)}</option>`).join('');
  const lbl = t => `<label class="block text-xs font-medium mb-1" style="color:var(--muted);">${t}</label>`;
  const val = (k,def='') => esc(e ? (e[k]==null ? '' : e[k]) : def);
  const pdfInfo = e
    ? (e.file_pdf ? `File saat ini: ${esc(e.file_pdf.name)} (${(e.file_pdf.size/1024).toFixed(0)} KB). Pilih file baru hanya jika ingin menggantinya.` : 'Belum ada file. Maks. 10MB, hanya format PDF.')
    : 'Maks. 10MB, hanya format PDF. Upload dulu agar form terisi otomatis, lalu periksa kembali.';
  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-1">
        <h3 class="font-display font-semibold text-base">${e ? 'Edit Surat Masuk ' + esc(e.id) : 'Surat Masuk Baru'}</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <p class="text-xs mb-3" style="color:var(--muted);">${e
        ? (e.kegiatan_id ? 'Surat ini sudah menjadi Kegiatan ' + esc(e.kegiatan_id) + '. Perubahan di sini hanya mengubah data surat, tidak mengubah kegiatan yang sudah terbentuk.' : 'Perubahan akan disimpan pada surat ini.')
        : `Setelah disimpan, surat menunggu ACC ${SURAT_ACC_ROLES.join('/')}. Kegiatan/Tugas dibuat otomatis begitu di-ACC.`}</p>
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="sm:col-span-2">
          ${lbl(e ? 'File Surat (PDF)' : 'File Surat (PDF) — isian di bawah terisi otomatis dari surat')}
          <input type="file" id="sm_pdf" accept="application/pdf,.pdf" onchange="handleSmPdfChange(this)">
          <p id="sm_pdf_info" class="text-[11px] mt-1" style="color:var(--muted);">${pdfInfo}</p>
        </div>
        <div>${lbl('Nomor Surat *')}<input type="text" id="sm_nomor" value="${val('nomor_surat')}"></div>
        <div>${lbl('Asal Surat (Pengirim)')}<input type="text" id="sm_asal" value="${val('asal_surat')}"></div>
        <div>${lbl('Tanggal Surat')}<input type="date" id="sm_tgl_surat" value="${val('tanggal_surat', todayISO())}"></div>
        <div>${lbl('Tanggal Diterima')}<input type="date" id="sm_tgl_terima" value="${val('tanggal_diterima', todayISO())}"></div>
        <div class="sm:col-span-2">${lbl('Perihal * (menjadi nama kegiatan)')}<input type="text" id="sm_perihal" value="${val('perihal')}"></div>
        <div>${lbl('Sumber Class (Jenis Kegiatan)')}<select id="sm_jenis" onchange="smToggleJenisKet()">${jenisOptions}</select>
          <div id="sm_jenis_ket_wrap" class="mt-2" style="display:${val('jenis')==='lainnya' ? 'block' : 'none'};">${lbl('Keterangan (Jenis Lainnya) *')}<input type="text" id="sm_jenis_ket" placeholder="Jelaskan jenis kegiatan..." value="${val('jenis_ket')}"></div></div>
        <div>${lbl('Tanggal Kegiatan')}<input type="date" id="sm_tgl_keg" value="${val('tanggal_kegiatan', todayISO())}"></div>
        <div>${lbl('Waktu')}<input type="time" id="sm_waktu" value="${val('waktu', '08:00')}"></div>
        <div>${lbl('Lokasi')}<input type="text" id="sm_lokasi" value="${val('lokasi')}"></div>
        <div>${lbl('Penanggung Jawab')}<input type="text" id="sm_pj" value="${val('penanggung_jawab')}"></div>
        <div>${lbl('No. HP')}<input type="tel" id="sm_hp" inputmode="tel" placeholder="mis. 0812xxxxxxxx" value="${val('no_hp')}"></div>
        <div>${lbl('Jumlah Anggota Dibutuhkan')}<input type="number" id="sm_jml" min="1" value="${val('jumlah_dibutuhkan', 2)}"></div>
        <div class="sm:col-span-2">${lbl('Isi Ringkas / Deskripsi')}<textarea id="sm_desk" rows="2">${val('deskripsi')}</textarea></div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="saveSuratForm()">${e ? 'Simpan Perubahan' : 'Simpan Surat Masuk'}</button>
      </div>
    </div>`, true);
}

function handleSmPdfChange(input){
  const info = document.getElementById('sm_pdf_info');
  const def = 'Maks. 10MB, hanya format PDF.';
  const file = input.files && input.files[0];
  const cur = smEditId ? getSurat(smEditId) : null;
  smPdfFile = cur && cur.file_pdf ? cur.file_pdf : null; // mode edit: tetap pakai file lama bila tidak ganti
  if(!file){ info.textContent = cur && cur.file_pdf ? 'File saat ini: ' + cur.file_pdf.name : def; return; }
  if(file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')){
    toast('File harus berformat PDF'); input.value = ''; info.textContent = def; return;
  }
  if(file.size > 10*1024*1024){
    toast('Ukuran file maksimal 10MB'); input.value = ''; info.textContent = def; return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    smPdfFile = { name: file.name, size: file.size, data: reader.result };
    if(smEditId){ info.textContent = `File baru: ${file.name} (${(file.size/1024).toFixed(0)} KB) — akan menggantikan file lama saat disimpan.`; return; }
    info.textContent = `Terpilih: ${file.name} (${(file.size/1024).toFixed(0)} KB) — membaca isi surat…`; smAutofillFromPdf(file, info);
  };
  reader.onerror = () => { toast('Gagal membaca file PDF'); info.textContent = def; };
  reader.readAsDataURL(file);
}

/* ---- Isi otomatis form Surat Masuk dari PDF ---- */
let _pdfjsPromise = null;
function loadPdfJs(){
  if(_pdfjsPromise) return _pdfjsPromise;
  const base = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
  _pdfjsPromise = new Promise((resolve, reject) => {
    if(window.pdfjsLib) return resolve(window.pdfjsLib);
    const sc = document.createElement('script');
    sc.src = base + 'pdf.min.js';
    sc.onload = async () => {
      try{
        // worker diambil sebagai blob agar lolos batasan cross-origin
        const code = await (await fetch(base + 'pdf.worker.min.js')).text();
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = URL.createObjectURL(new Blob([code], {type:'text/javascript'}));
      }catch(e){ window.pdfjsLib.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js'; }
      resolve(window.pdfjsLib);
    };
    sc.onerror = () => { _pdfjsPromise = null; reject(new Error('Library pembaca PDF gagal dimuat')); };
    document.head.appendChild(sc);
  });
  return _pdfjsPromise;
}

async function pdfToLines(file){
  const lib = await loadPdfJs();
  const pdf = await lib.getDocument({data: await file.arrayBuffer()}).promise;
  const lines = [];
  const maxPage = Math.min(pdf.numPages, 3);
  for(let p=1; p<=maxPage; p++){
    const tc = await (await pdf.getPage(p)).getTextContent();
    const rows = [];
    tc.items.forEach(it => {
      if(!it.str || !it.str.trim()) return;
      const y = it.transform[5], x = it.transform[4];
      let r = rows.find(r => Math.abs(r.y - y) < 3);
      if(!r){ r = {y, parts:[]}; rows.push(r); }
      r.parts.push({x, t: it.str});
    });
    rows.sort((a,b) => b.y - a.y).forEach(r => {
      lines.push(r.parts.sort((a,b) => a.x - b.x).map(o => o.t).join(' ').replace(/\s+/g,' ').trim());
    });
  }
  return lines;
}

const BLN_ID = {januari:1,februari:2,maret:3,april:4,mei:5,juni:6,juli:7,agustus:8,september:9,oktober:10,november:11,desember:12,
  jan:1,feb:2,mar:3,apr:4,jun:6,jul:7,agu:8,agt:8,ags:8,sep:9,okt:10,nov:11,des:12};
function parseTglId(str){
  if(!str) return '';
  let m = str.match(/(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{4})/);
  if(m && BLN_ID[m[2].toLowerCase()]){
    return `${m[3]}-${String(BLN_ID[m[2].toLowerCase()]).padStart(2,'0')}-${m[1].padStart(2,'0')}`;
  }
  m = str.match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/);
  if(m) return `${m[3]}-${m[2].padStart(2,'0')}-${m[1].padStart(2,'0')}`;
  return '';
}

function parseSuratLines(lines){
  const out = {};
  const text = lines.join('\n');
  const val = (re, grp=1) => { const m = text.match(re); return m ? m[grp].trim() : ''; };

  // Nomor
  let nomor = val(/^\s*(?:Nomor|No\.?)\s*[:：]\s*(.+)$/im);
  if(nomor) out.nomor = nomor.replace(/\s*\/\s*/g,'/').replace(/\s{2,}/g,' ');

  // Perihal (gabung baris lanjutan bila ada)
  const iP = lines.findIndex(l => /^(Perihal|Hal)\s*[:：]/i.test(l));
  if(iP >= 0){
    let per = lines[iP].replace(/^(Perihal|Hal)\s*[:：]\s*/i,'');
    const nx = lines[iP+1];
    if(nx && !/[:：]/.test(nx) && !/^(Yth|Kepada|Lampiran|Sifat|Dengan hormat)/i.test(nx) && !/\d{4}/.test(nx) && !/,\s*\d{1,2}\s/.test(nx) && nx.length < 80) per += ' ' + nx;
    out.perihal = per.trim();
  }

  // Asal surat: kop surat (baris sebelum Nomor), ambil baris huruf besar bermakna
  const iN = lines.findIndex(l => /^(Nomor|No\.?)\s*[:：]/i.test(l));
  const kop = lines.slice(0, iN > 0 ? iN : 6).filter(l => l.length > 3 && !/(jl\.|jalan|telp|telepon|fax|email|website|www\.|@|\d{5})/i.test(l));
  const dari = val(/^\s*(?:Dari|Pengirim)\s*[:：]\s*(.+)$/im);
  if(dari) out.asal = dari;
  else if(kop.length) out.asal = kop.slice(0, 2).join(' ').replace(/\s{2,}/g,' ');

  // Tanggal surat: "Jakarta, 2 Oktober 2026"
  const mTs = text.match(/[A-Za-z .]+,\s*(\d{1,2}\s+[A-Za-z]+\.?\s+\d{4})/);
  if(mTs) out.tgl_surat = parseTglId(mTs[1]);

  // Tanggal kegiatan
  let tk = val(/(?:Hari\s*\/\s*Tanggal|Hari,?\s*Tanggal|Tanggal|Hari)\s*[:：]\s*(.+)/i);
  if(tk) out.tgl_keg = parseTglId(tk);
  if(!out.tgl_keg){
    const all = [...text.matchAll(/(\d{1,2}\s+(?:Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+\d{4})/gi)].map(m => parseTglId(m[1]));
    const cand = all.find(d => d && d !== out.tgl_surat);
    if(cand) out.tgl_keg = cand;
  }

  // Waktu
  const mW = text.match(/(?:Waktu|Pukul|Jam)\s*[:：]?\s*(\d{1,2})[.:](\d{2})/i);
  if(mW) out.waktu = String(mW[1]).padStart(2,'0') + ':' + mW[2];

  // Lokasi
  const lok = val(/(?:Tempat|Lokasi)\s*[:：]\s*(.+)/i);
  if(lok) out.lokasi = lok;

  // Penanggung jawab & HP
  const pj = val(/(?:Penanggung\s*Jawab|Narahubung|Contact\s*Person|CP|PIC)\s*[:：]\s*([^\d(+\n]+)/i);
  if(pj) out.pj = pj.replace(/[-–,]+\s*$/,'').trim();
  const hp = val(/((?:\+62|62|0)8[\d\s\-]{8,14}\d)/);
  if(hp) out.hp = hp.replace(/[\s\-]/g,'');

  // Jumlah anggota
  const mJ = text.match(/(\d{1,3})\s*\(?[a-z]*\)?\s*(?:orang|personel|personil)\b/i);
  if(mJ) out.jml = parseInt(mJ[1]);

  // Jenis kegiatan dari kata kunci (perihal diutamakan)
  const hay = ((out.perihal||'') + ' ' + text).toLowerCase();
  const map = [
    ['sgts', /sar go to school|sgts/], ['bimtek', /bimtek|bimbingan teknis/], ['diklat', /diklat|pendidikan dan pelatihan/],
    ['narasumber', /narasumber/], ['uji_periodik', /uji periodik/], ['uji_pemeliharaan', /pemeliharaan/],
    ['pemberdayaan', /pemberdayaan/], ['pembinaan', /pembinaan/], ['potensi', /potensi sar|potensi/],
    ['rescuer', /rescuer/], ['peminjaman', /peminjaman|pinjam/], ['kendaraan', /kendaraan/], ['peralatan', /peralatan/],
    ['lainnya', /kegiatan lainnya|lainnya/],
  ];
  const hit = map.find(([k,re]) => re.test(hay));
  if(hit) out.jenis = hit[0];

  // Deskripsi singkat: kalimat pembuka setelah salam
  const iS = lines.findIndex(l => /dengan hormat|sehubungan|menindaklanjuti|bersama ini/i.test(l));
  if(iS >= 0) out.desk = lines.slice(iS, iS+4).join(' ').slice(0, 400);
  return out;
}

async function smAutofillFromPdf(file, info){
  const def = `Terpilih: ${file.name} (${(file.size/1024).toFixed(0)} KB)`;
  try{
    const lines = await pdfToLines(file);
    if(lines.join('').length < 30){
      info.textContent = def + ' — PDF berupa gambar/scan, isi tidak bisa dibaca otomatis. Silakan isi manual.';
      return;
    }
    const d = parseSuratLines(lines);
    const set = (id, v) => {
      if(v === undefined || v === null || v === '') return false;
      const el = document.getElementById(id);
      if(!el) return false;
      el.value = v;
      el.style.outline = '2px solid #f59e0b'; el.style.outlineOffset = '-2px';
      el.addEventListener('input', () => { el.style.outline = ''; }, {once:true});
      return true;
    };
    let n = 0;
    [['sm_nomor',d.nomor],['sm_asal',d.asal],['sm_tgl_surat',d.tgl_surat],['sm_tgl_terima',todayISO()],
     ['sm_perihal',d.perihal],['sm_jenis',d.jenis],['sm_tgl_keg',d.tgl_keg],['sm_waktu',d.waktu],
     ['sm_lokasi',d.lokasi],['sm_pj',d.pj],['sm_hp',d.hp],['sm_jml',d.jml],['sm_desk',d.desk]
    ].forEach(([id,v]) => { if(set(id,v) && id!=='sm_tgl_terima') n++; });
    smToggleJenisKet();
    info.textContent = def + (n ? ` — ${n} kolom terisi otomatis (bertanda oranye). Mohon periksa kembali.` : ' — tidak ada data yang dikenali, isi manual.');
    if(n) toast(n + ' kolom terisi otomatis dari surat');
  }catch(e){
    console.error(e);
    info.textContent = def + ' — gagal membaca isi PDF otomatis, silakan isi manual.';
  }
}

function smToggleJenisKet(){
  const sel = document.getElementById('sm_jenis'), w = document.getElementById('sm_jenis_ket_wrap');
  if(!sel || !w) return;
  const on = sel.value==='lainnya';
  w.style.display = on ? 'block' : 'none';
  if(on){ const i = document.getElementById('sm_jenis_ket'); if(i) i.focus(); }
}
function saveSuratForm(){
  const v = id => document.getElementById(id).value.trim();
  const nomor = v('sm_nomor'), perihal = v('sm_perihal');
  if(!nomor){ toast('Nomor surat wajib diisi'); return; }
  if(!perihal){ toast('Perihal wajib diisi'); return; }
  if(!DB.surat_masuk) DB.surat_masuk = [];
  const jenisKey = document.getElementById('sm_jenis').value;
  const jenisKet = jenisKey==='lainnya' ? v('sm_jenis_ket') : '';
  if(jenisKey==='lainnya' && !jenisKet){ toast('Keterangan jenis kegiatan (Lainnya) wajib diisi'); return; }
  const data = {
    jenis_ket: jenisKet,
    nomor_surat: nomor, asal_surat: v('sm_asal'),
    tanggal_surat: v('sm_tgl_surat'), tanggal_diterima: v('sm_tgl_terima'),
    perihal, jenis: jenisKey, jenis_label: GENERIC_MODULES.find(m=>m.key===jenisKey).label,
    tanggal_kegiatan: v('sm_tgl_keg'), waktu: v('sm_waktu'), lokasi: v('sm_lokasi'),
    penanggung_jawab: v('sm_pj'), no_hp: v('sm_hp'),
    jumlah_dibutuhkan: parseInt(v('sm_jml'))||1, deskripsi: document.getElementById('sm_desk').value,
    file_pdf: smPdfFile,
  };
  // ---- UPDATE ----
  if(smEditId){
    const sm = getSurat(smEditId);
    if(!sm){ toast('Surat tidak ditemukan'); return; }
    if(!canManageSurat(sm)){ toast('Anda tidak berwenang mengubah surat ini'); return; }
    Object.assign(sm, data, { diperbarui: nowStr() });
    persist();
    smPdfFile = null; smEditId = null;
    closeModal();
    toast('Surat masuk diperbarui');
    kegTab = 'surat';
    render('kegiatan');
    return;
  }
  // ---- CREATE ----
  const row = Object.assign({
    id: uid('SM', DB.surat_masuk),
    status: 'Menunggu Persetujuan', acc: [], keterangan_persetujuan: '', kegiatan_id: '', diajukan_oleh: pelaku(),
    dibuat: nowStr(), diperbarui: nowStr(),
  }, data);
  DB.surat_masuk.push(row);
  persist();
  smPdfFile = null;
  closeModal();
  toast('Surat masuk dicatat, menunggu ACC ' + SURAT_ACC_ROLES.join('/'));
  kegTab = 'surat';
  render('kegiatan');
}

function openSuratDetail(id){
  const sm = getSurat(id);
  if(!sm) return;
  const kg = sm.kegiatan_id ? getKegiatan(sm.kegiatan_id) : null;
  const menunggu = sm.status==='Menunggu Persetujuan';
  const sudahAcc = suratAccRoles(sm).includes(currentRole);
  const bisaAcc = menunggu && canAccSurat() && !sudahAcc;
  openModal(`
    <div class="p-5 max-h-[88vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-1">
        <div>
          <h3 class="font-display font-semibold text-base">${esc(sm.perihal)}</h3>
          <p class="text-xs" style="color:var(--muted);">${esc(sm.id)} · ${esc(sm.nomor_surat)} · ${esc(sm.jenis_label)}${sm.jenis_ket ? ' ('+esc(sm.jenis_ket)+')' : ''}</p>
        </div>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="flex flex-wrap gap-1.5 my-3">${suratPersetujuanHtml(sm)} <span class="text-xs" style="color:var(--muted);align-self:center;">${esc(suratAccInfo(sm))}</span></div>

      <div class="grid sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm mb-4">
        <div><span style="color:var(--muted);">Asal Surat:</span> ${esc(sm.asal_surat)||'-'}</div>
        <div><span style="color:var(--muted);">Tanggal Surat:</span> ${esc(sm.tanggal_surat)||'-'}</div>
        <div><span style="color:var(--muted);">Tanggal Diterima:</span> ${esc(sm.tanggal_diterima)||'-'}</div>
        <div><span style="color:var(--muted);">Tanggal Kegiatan:</span> ${esc(sm.tanggal_kegiatan)||'-'} ${esc(sm.waktu)}</div>
        <div><span style="color:var(--muted);">Lokasi:</span> ${esc(sm.lokasi)||'-'}</div>
        <div><span style="color:var(--muted);">Penanggung Jawab:</span> ${esc(sm.penanggung_jawab)||'-'}</div>
        <div><span style="color:var(--muted);">No. HP:</span> ${esc(sm.no_hp)||'-'}</div>
        <div><span style="color:var(--muted);">Anggota Dibutuhkan:</span> ${sm.jumlah_dibutuhkan}</div>
        <div class="sm:col-span-2"><span style="color:var(--muted);">Isi Ringkas:</span> ${esc(sm.deskripsi)||'-'}</div>
        ${suratImportDetailHtml(sm)}
      </div>

      ${sm.file_pdf ? `
        <div class="flex items-center gap-2.5 rounded-lg p-3 mb-4" style="background:var(--bg);">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium truncate">${esc(sm.file_pdf.name)}</p>
            <p class="text-[11px]" style="color:var(--muted);">${(sm.file_pdf.size/1024).toFixed(0)} KB</p>
          </div>
          <a href="${sm.file_pdf.data}" download="${esc(sm.file_pdf.name)}" class="btn btn-ghost !py-1 !px-2 text-xs">Unduh</a>
          <button class="btn btn-navy !py-1 !px-2 text-xs" onclick="openPdfDataUrl(getSurat('${sm.id}').file_pdf.data)">Lihat</button>
        </div>` : ''}

      ${(sm.acc||[]).length ? `
        <div class="mb-4">
          <p class="text-sm font-medium mb-2">Riwayat ACC</p>
          ${sm.acc.map(a=>`<div class="text-xs py-1" style="color:var(--muted);"><span class="badge b-ok">${esc(a.role)}</span> ${esc(a.waktu)}</div>`).join('')}
        </div>` : ''}

      ${sm.keterangan_persetujuan ? `<div class="text-sm rounded-lg p-3 mb-4" style="background:var(--bg);"><strong>Keterangan:</strong> ${esc(sm.keterangan_persetujuan)}</div>` : ''}

      ${bisaAcc ? `
        <div class="rounded-lg p-3 mb-4" style="background:var(--bg);">
          <p class="text-sm font-medium mb-1">Persetujuan (${esc(currentRole)})</p>
          <p class="text-[11px] mb-2" style="color:var(--muted);">Setelah di-ACC, Kegiatan/Tugas langsung terbentuk otomatis.</p>
          <div class="flex gap-2">
            <button class="btn btn-ok" onclick="accSurat('${sm.id}')">ACC</button>
            <button class="btn btn-bad" onclick="rejectSurat('${sm.id}')">Tolak</button>
          </div>
          <p class="text-[11px] mt-2" style="color:var(--muted);">Pimpinan juga dapat memutuskan lewat WhatsApp. Keputusan pertama (dashboard atau WhatsApp) bersifat final.</p>
        </div>` : (menunggu ? `<p class="text-xs mb-4" style="color:var(--muted);">${sudahAcc ? 'Anda sudah ACC surat ini, menunggu pejabat lain.' : 'Hanya ' + SURAT_ACC_ROLES.join('/') + ' yang dapat ACC surat ini.'}</p>` : '')}

      ${lampiranWaHtml('surat_masuk', sm)}

      ${kg ? `<button class="btn btn-primary mb-2" onclick="closeModal(); kegTab='kegiatan'; render('kegiatan'); openKegiatanDetail('${kg.id}')">Lihat Kegiatan ${esc(kg.id)}</button>` : ''}

      <div class="flex justify-between items-center mt-4 pt-3 border-t" style="border-color:var(--line);">
        <span class="text-[11px]" style="color:var(--muted);">Dibuat ${esc(sm.dibuat)} · Diperbarui ${esc(sm.diperbarui)}</span>
        ${canManageSurat(sm) ? `<span class="flex gap-2">
          <button class="btn btn-ghost text-xs" onclick="openSuratForm('${sm.id}')">Edit</button>
          <button class="btn btn-ghost text-xs" style="color:var(--bad);" onclick="deleteSurat('${sm.id}')">Hapus</button>
        </span>` : ''}
      </div>
    </div>`, true);
}

function buatKegiatanDariSurat(sm){
  const row = {
    id: uid('KGT', DB.kegiatan),
    nomor: (DB.kegiatan.length+1).toString().padStart(3,'0') + '/KGT/2026',
    jenis: sm.jenis, jenis_label: sm.jenis_label, jenis_ket: sm.jenis_ket || '',
    nama: sm.perihal, tanggal: sm.tanggal_kegiatan, tanggal_selesai: sm.tanggal_kegiatan, waktu: sm.waktu,
    lokasi: sm.lokasi, penanggung_jawab: sm.penanggung_jawab, no_hp: sm.no_hp,
    deskripsi: sm.deskripsi, jumlah_dibutuhkan: sm.jumlah_dibutuhkan,
    status_persetujuan: 'Disetujui',
    keterangan_persetujuan: `Disetujui (ACC ${suratAccRoles(sm).join(', ')}) berdasarkan Surat Masuk ${sm.id} nomor ${sm.nomor_surat}.`,
    anggota_ids: [], status_pelaksanaan: 'Draft', status_wa: 'Belum Terkirim', wa_log: [], dokumentasi: '',
    file_pdf: sm.file_pdf || null, surat_id: sm.id,
    diajukan_oleh: sm.diajukan_oleh || null,
    disetujui_oleh: (sm.acc||[]).map(a=>({nama:a.nama||'-', peran:a.role})),
    dibuat: nowStr(), diperbarui: nowStr(),
  };
  DB.kegiatan.push(row);
  const linkedId = autoFillModuleFromKegiatan(sm.jenis, row);
  if(linkedId) row.linked_record_id = linkedId;
  sm.kegiatan_id = row.id;
  return row;
}

function accSurat(id){
  const sm = getSurat(id);
  if(!sm || sm.status!=='Menunggu Persetujuan') return;
  if(!canAccSurat()){ toast('Hanya ' + SURAT_ACC_ROLES.join('/') + ' atau Admin Utama yang dapat ACC'); return; }
  if(suratAccRoles(sm).includes(currentRole)){ toast('Anda sudah ACC surat ini'); return; }
  sm.acc = sm.acc || [];
  sm.acc.push({role: currentRole, nama: currentUserName, waktu: nowStr()});
  sm.diperbarui = nowStr();
  const cukup = SURAT_ACC_MODE==='semua' ? (isSuper() || SURAT_ACC_ROLES.every(r=>suratAccRoles(sm).includes(r))) : true; // Admin Utama menggantikan semua pejabat
  if(!cukup){
    persist();
    toast('ACC ' + currentRole + ' tercatat, menunggu pejabat lain');
    render('kegiatan'); openSuratDetail(id);
    return;
  }
  sm.status = 'Disetujui';
  sm.keterangan_persetujuan = `Di-ACC oleh ${suratAccRoles(sm).join(', ')} (${currentUserName}) pada ${nowStr()}.`;
  const k = buatKegiatanDariSurat(sm);
  persist();
  toast(`Surat di-ACC, Kegiatan ${k.id} terbentuk otomatis`);
  kegTab = 'kegiatan';
  render('kegiatan'); openKegiatanDetail(k.id);
}

function rejectSurat(id){
  const alasan = prompt('Alasan penolakan:');
  if(alasan===null) return;
  const sm = getSurat(id);
  if(!sm) return;
  sm.status = 'Ditolak';
  sm.keterangan_persetujuan = `Ditolak oleh ${currentRole} (${currentUserName}): ${alasan || '(tanpa keterangan)'}`;
  sm.diperbarui = nowStr();
  persist();
  toast('Surat ditolak');
  render('kegiatan'); openSuratDetail(id);
}

function deleteSurat(id){
  const sm = getSurat(id);
  if(!sm) return;
  if(!canManageSurat(sm)){ toast('Anda tidak berwenang menghapus surat ini'); return; }
  const kg = sm.kegiatan_id ? getKegiatan(sm.kegiatan_id) : null;
  const pesan = kg
    ? `Hapus surat masuk ${id}?\n\nSurat ini sudah menjadi Kegiatan ${kg.id}. Kegiatan TIDAK ikut terhapus, hanya hubungannya ke surat ini yang dilepas.`
    : `Hapus surat masuk ${id}?`;
  if(!confirm(pesan)) return;
  if(kg){ kg.surat_id = ''; kg.diperbarui = nowStr(); }
  DB.surat_masuk = DB.surat_masuk.filter(x=>x.id!==id);
  persist();
  closeModal();
  toast('Surat masuk dihapus');
  render('kegiatan');
}

function openKegiatanForm(){
  const jenisOptions = GENERIC_MODULES.map(m=>`<option value="${m.key}">${esc(m.label)}</option>`).join('');
  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display font-semibold text-base">Ajukan Kegiatan/Tugas</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Nama Kegiatan *</label><input type="text" id="kf_nama"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Sumber Class</label><select id="kf_jenis" onchange="kfToggleJenisKet()">${jenisOptions}</select>
          <div id="kf_jenis_ket_wrap" class="mt-2" style="display:none;"><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Keterangan (Jenis Lainnya) *</label><input type="text" id="kf_jenis_ket" placeholder="Jelaskan jenis kegiatan..."></div></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Tanggal Kegiatan</label><input type="date" id="kf_tanggal" value="${todayISO()}" onchange="kfSyncTanggal()"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Tanggal Selesai</label><input type="date" id="kf_tanggal_selesai" value="${todayISO()}" min="${todayISO()}"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Waktu</label><input type="time" id="kf_waktu" value="08:00"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Lokasi</label><input type="text" id="kf_lokasi"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Penanggung Jawab</label><input type="text" id="kf_pj"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">No. HP</label><input type="tel" id="kf_hp" inputmode="tel" placeholder="mis. 0812xxxxxxxx"></div>
        <div><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Jumlah Anggota Dibutuhkan</label><input type="number" id="kf_jml" min="1" value="2"></div>
        <div class="sm:col-span-2"><label class="block text-xs font-medium mb-1" style="color:var(--muted);">Deskripsi Kegiatan</label><textarea id="kf_desk" rows="2"></textarea></div>
        <div class="sm:col-span-2">
          <label class="block text-xs font-medium mb-1" style="color:var(--muted);">Lampiran File PDF</label>
          <input type="file" id="kf_pdf" accept="application/pdf,.pdf" onchange="handleKfPdfChange(this)">
          <p id="kf_pdf_info" class="text-[11px] mt-1" style="color:var(--muted);">Maks. 10MB, hanya format PDF.</p>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="saveKegiatanForm()">Ajukan</button>
      </div>
    </div>`, true);
}

// Tanggal selesai tidak boleh sebelum tanggal mulai
function kfSyncTanggal(){
  const a = document.getElementById('kf_tanggal'), b = document.getElementById('kf_tanggal_selesai');
  if(!a || !b) return;
  b.min = a.value || '';
  if(a.value && (!b.value || b.value < a.value)) b.value = a.value;
}

let kfPdfFile = null; // {name, size, data} data = base64 data URL

function handleKfPdfChange(input){
  const info = document.getElementById('kf_pdf_info');
  const file = input.files && input.files[0];
  kfPdfFile = null;
  if(!file) { info.textContent = 'Maks. 10MB, hanya format PDF.'; return; }
  if(file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')){
    toast('File harus berformat PDF');
    input.value = '';
    info.textContent = 'Maks. 10MB, hanya format PDF.';
    return;
  }
  if(file.size > 10*1024*1024){
    toast('Ukuran file maksimal 10MB');
    input.value = '';
    info.textContent = 'Maks. 10MB, hanya format PDF.';
    return;
  }
  const reader = new FileReader();
  reader.onload = () => {
    kfPdfFile = { name: file.name, size: file.size, data: reader.result };
    info.textContent = `Terpilih: ${file.name} (${(file.size/1024).toFixed(0)} KB)`;
  };
  reader.onerror = () => {
    toast('Gagal membaca file PDF');
    info.textContent = 'Maks. 10MB, hanya format PDF.';
  };
  reader.readAsDataURL(file);
}

function kfToggleJenisKet(){
  const sel = document.getElementById('kf_jenis'), w = document.getElementById('kf_jenis_ket_wrap');
  if(!sel || !w) return;
  const on = sel.value==='lainnya';
  w.style.display = on ? 'block' : 'none';
  if(on){ const i = document.getElementById('kf_jenis_ket'); if(i) i.focus(); }
}
function saveKegiatanForm(){
  const nama = document.getElementById('kf_nama').value.trim();
  if(!nama){ toast('Nama kegiatan wajib diisi'); return; }
  const jenisKey = document.getElementById('kf_jenis').value;
  const jenisLabel = GENERIC_MODULES.find(m=>m.key===jenisKey).label;
  const jenisKet = jenisKey==='lainnya' ? document.getElementById('kf_jenis_ket').value.trim() : '';
  if(jenisKey==='lainnya' && !jenisKet){ toast('Keterangan jenis kegiatan (Lainnya) wajib diisi'); return; }
  const tglMulai = document.getElementById('kf_tanggal').value;
  const tglSelesai = document.getElementById('kf_tanggal_selesai').value || tglMulai;
  if(tglMulai && tglSelesai < tglMulai){ toast('Tanggal selesai tidak boleh sebelum tanggal kegiatan'); return; }
  const row = {
    id: uid('KGT', DB.kegiatan),
    nomor: (DB.kegiatan.length+1).toString().padStart(3,'0') + '/KGT/2026',
    jenis: jenisKey, jenis_label: jenisLabel, jenis_ket: jenisKet,
    nama, tanggal: tglMulai, tanggal_selesai: tglSelesai, waktu: document.getElementById('kf_waktu').value,
    lokasi: document.getElementById('kf_lokasi').value, penanggung_jawab: document.getElementById('kf_pj').value, no_hp: document.getElementById('kf_hp').value.trim(),
    deskripsi: document.getElementById('kf_desk').value, jumlah_dibutuhkan: parseInt(document.getElementById('kf_jml').value)||1,
    status_persetujuan:'Menunggu Persetujuan', keterangan_persetujuan:'', anggota_ids:[],
    diajukan_oleh: pelaku(),
    status_pelaksanaan:'Draft', status_wa:'Belum Terkirim', wa_log:[], dokumentasi:'',
    file_pdf: kfPdfFile,
    dibuat: nowStr(), diperbarui: nowStr(),
  };
  DB.kegiatan.push(row);
  const linkedId = autoFillModuleFromKegiatan(jenisKey, row);
  if(linkedId){ row.linked_record_id = linkedId; }
  persist();
  kfPdfFile = null;
  closeModal();
  toast(linkedId ? `Kegiatan diajukan, data juga tercatat otomatis di ${jenisLabel}` : 'Kegiatan diajukan, menunggu persetujuan');
  render('kegiatan');
}

function getKegiatan(id){ return DB.kegiatan.find(k=>k.id===id); }

function openKegiatanDetail(id){
  const k = getKegiatan(id);
  if(!k) return;
  const assigned = DB.anggota.filter(a => k.anggota_ids.includes(a.id));
  const canApprove = isApprover() && (!isAdmin() || isSuper()) && k.status_persetujuan==='Menunggu Persetujuan';
  const canAssign = k.status_persetujuan==='Disetujui' && !k.sprin && isApprover();
  const canSendWa = k.status_persetujuan==='Disetujui' && k.anggota_ids.length>0 && !!k.sprin && k.status_wa!=='Terkirim' && isApprover();
  const canProgress = k.status_persetujuan==='Disetujui' && isApprover();

  openModal(`
    <div class="p-5 max-h-[88vh] overflow-y-auto">
      <div class="flex items-start justify-between mb-1">
        <div>
          <h3 class="font-display font-semibold text-base">${esc(k.nama)}</h3>
          <p class="text-xs" style="color:var(--muted);">${esc(k.id)} · ${esc(k.nomor)} · ${esc(k.jenis_label)}${k.jenis_ket ? ' ('+esc(k.jenis_ket)+')' : ''}</p>
        </div>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <div class="flex flex-wrap gap-1.5 my-3">
        ${statusBadge(k.status_persetujuan)} ${statusBadge(k.status_pelaksanaan)} ${statusBadge(k.status_wa)} ${sprinBadgeHtml(k)}
      </div>

      <div class="grid sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm mb-4">
        <div><span style="color:var(--muted);">Tanggal:</span> ${esc(tglRentang(k))} ${esc(k.waktu)}</div>
        <div><span style="color:var(--muted);">Lokasi:</span> ${esc(k.lokasi)||'-'}</div>
        <div><span style="color:var(--muted);">Penanggung Jawab:</span> ${esc(k.penanggung_jawab)||'-'}</div>
        <div><span style="color:var(--muted);">No. HP:</span> ${esc(k.no_hp)||'-'}</div>
        <div><span style="color:var(--muted);">Anggota Dibutuhkan:</span> ${k.jumlah_dibutuhkan}</div>
        <div class="sm:col-span-2"><span style="color:var(--muted);">Deskripsi:</span> ${esc(k.deskripsi)||'-'}</div>
        <div class="sm:col-span-2"><span style="color:var(--muted);">Diajukan Oleh:</span> ${esc(pelakuTeks(k.diajukan_oleh))}</div>
        <div class="sm:col-span-2"><span style="color:var(--muted);">${k.status_persetujuan==='Ditolak' ? 'Ditolak Oleh' : 'Disetujui Oleh'}:</span> ${k.status_persetujuan==='Menunggu Persetujuan' ? '-' : esc(pelakuTeks(disetujuOlehKegiatan(k))) + (k.acc_via ? ' · via ' + esc(k.acc_via) : '')}</div>
      </div>

      ${k.file_pdf ? `
        <div class="flex items-center gap-2.5 rounded-lg p-3 mb-4" style="background:var(--bg);">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--bad);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium truncate">${esc(k.file_pdf.name)}</p>
            <p class="text-[11px]" style="color:var(--muted);">${(k.file_pdf.size/1024).toFixed(0)} KB</p>
          </div>
          <a href="${k.file_pdf.data}" download="${esc(k.file_pdf.name)}" class="btn btn-ghost !py-1 !px-2 text-xs">Unduh</a>
          <a href="${k.file_pdf.data}" target="_blank" rel="noopener" class="btn btn-navy !py-1 !px-2 text-xs">Lihat</a>
        </div>` : ''}

      ${(k.surat_id && getSurat(k.surat_id)) ? `<div class="text-sm rounded-lg p-3 mb-4 flex items-center justify-between gap-2" style="background:var(--bg);"><span><span style="color:var(--muted);">Asal:</span> Surat Masuk ${esc(k.surat_id)} · ${esc(getSurat(k.surat_id).nomor_surat)}</span><button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="closeModal(); kegTab='surat'; render('kegiatan'); openSuratDetail('${k.surat_id}')">Lihat Surat</button></div>` : ''}

      ${k.linked_record_id ? `<button class="btn btn-ghost mb-4" onclick="closeModal(); openGenericDetail('${k.jenis}','${k.linked_record_id}')">Lihat Data di ${esc(k.jenis_label)}</button>` : ''}

      ${k.keterangan_persetujuan ? `<div class="text-sm rounded-lg p-3 mb-4" style="background:var(--bg);"><strong>Keterangan Persetujuan:</strong> ${esc(k.keterangan_persetujuan)}</div>` : ''}
      ${k.status_persetujuan==='Ditolak' ? `<p class="text-xs mb-4" style="color:var(--bad);">Kegiatan ditolak: status pelaksanaan terkunci <strong>Draft</strong> dan tidak dapat diubah; data kegiatan ini tidak ada di modul ${esc(k.jenis_label)}.</p>` : ''}

      ${canApprove ? `
        <div class="rounded-lg p-3 mb-4" style="background:var(--bg);">
          <p class="text-sm font-medium mb-2">Persetujuan (${esc(currentRole)})</p>
          <div class="flex gap-2">
            <button class="btn btn-ok" onclick="approveKegiatan('${k.id}')">Setujui</button>
            <button class="btn btn-bad" onclick="rejectKegiatan('${k.id}')">Tolak</button>
          </div>
          <p class="text-[11px] mt-2" style="color:var(--muted);">Pimpinan juga dapat memutuskan lewat WhatsApp. Keputusan pertama (dashboard atau WhatsApp) bersifat final.</p>
        </div>` : ''}

      ${assigned.length ? `
        <div class="mb-4">
          <p class="text-sm font-medium mb-2">Anggota Ditugaskan</p>
          <div class="flex flex-wrap gap-1.5">
            ${assigned.map(a=>`<span class="badge b-info">${esc(a.nama)}</span>`).join('')}
          </div>
        </div>` : ''}

      ${canAssign ? `<button class="btn btn-navy mb-4" onclick="openAssignAnggota('${k.id}')">${k.anggota_ids.length ? 'Ubah Anggota Bertugas' : 'Pilih Anggota Bertugas'}</button>` : ''}
      ${(k.status_persetujuan==='Disetujui' && k.sprin && isApprover()) ? `<p class="text-[11px] mb-4" style="color:var(--muted);">Sprin sudah terinput, anggota bertugas terkunci.</p>` : ''}
      ${sprinCardHtml(k)}
      ${canSendWa ? `<button class="btn btn-primary mb-4" onclick="sendWhatsapp('${k.id}')">Kirim Info Tugas via WhatsApp</button>` : ''}

      ${k.wa_log.length ? `<p class="text-xs mb-4" style="color:var(--muted);">Terakhir dikirim ke ${k.wa_log[k.wa_log.length-1].jumlah} anggota pada ${esc(k.wa_log[k.wa_log.length-1].waktu)}.</p>` : ''}

      ${canProgress ? `
        <div class="flex flex-wrap items-center gap-2 mb-2">
          ${k.status_pelaksanaan==='Draft' ? `<button class="btn btn-ghost" onclick="setPelaksanaan('${k.id}','Dalam Pelaksanaan')">Mulai Pelaksanaan</button>` : ''}
          ${k.status_pelaksanaan==='Dalam Pelaksanaan' ? `<button class="btn btn-ghost" onclick="setPelaksanaan('${k.id}','Selesai')">Tandai Selesai</button>` : ''}
          ${k.status_pelaksanaan!=='Selesai' ? `<span class="text-[11px]" style="color:var(--muted);">Tombol cadangan jika status tidak berubah otomatis dari upload file.</span>` : ''}
        </div>` : ''}

      ${buktiKegiatanHtml(k)}
      ${totalPesertaHtml(k)}
      ${laporanSelesaiHtml(k)}
      ${hasilKegiatanHtml(k)}
      ${lampiranWaHtml('kegiatan', k)}

      <div class="flex justify-between items-center mt-4 pt-3 border-t" style="border-color:var(--line);">
        <span class="text-[11px]" style="color:var(--muted);">Dibuat ${esc(k.dibuat)} · Diperbarui ${esc(k.diperbarui)}</span>
        ${isApprover() ? `<button class="btn btn-ghost text-xs" style="color:var(--bad);" onclick="deleteKegiatan('${k.id}')">Hapus</button>` : ''}
      </div>
    </div>`, true);
}

function touchKegiatan(k){ k.diperbarui = nowStr(); syncModuleStatusFromKegiatan(k); persist(); }

function approveKegiatan(id){
  const k = getKegiatan(id);
  if(!k || k.status_persetujuan!=='Menunggu Persetujuan'){ toast('Status persetujuan sudah final dan tidak dapat diubah'); return; }
  k.status_persetujuan = 'Disetujui';
  k.keterangan_persetujuan = `Disetujui oleh ${currentRole} pada ${nowStr()}.`;
  k.disetujui_oleh = pelaku();
  touchKegiatan(k);
  toast('Kegiatan disetujui');
  render('kegiatan'); openKegiatanDetail(id);
}
function rejectKegiatan(id){
  const k0 = getKegiatan(id);
  if(!k0 || k0.status_persetujuan!=='Menunggu Persetujuan'){ toast('Status persetujuan sudah final dan tidak dapat diubah'); return; }
  const alasan = prompt('Alasan penolakan:');
  if(alasan===null) return;
  const k = k0;
  k.status_persetujuan = 'Ditolak';
  k.status_pelaksanaan = 'Draft'; // ditolak: pelaksanaan kembali/tetap Draft dan terkunci
  k.keterangan_persetujuan = `Ditolak oleh ${currentRole}: ${alasan || '(tanpa keterangan)'}`;
  k.disetujui_oleh = pelaku();
  const dihapus = dataModulTerkaitKegiatan(k);
  dihapus.forEach(t=>{ DB[t.key] = (DB[t.key]||[]).filter(r=>r.id!==t.id); });
  k.linked_record_id = '';
  touchKegiatan(k);
  toast(dihapus.length ? 'Kegiatan ditolak, data di modul ' + k.jenis_label + ' dihapus' : 'Kegiatan ditolak');
  render('kegiatan'); openKegiatanDetail(id);
}

// Anggota yang sudah ditugaskan di kegiatan lain pada tanggal yang sama (kegiatan ditolak tidak dihitung)
function anggotaBentrok(k){
  const tgl = String(k.tanggal||'').slice(0,10);
  const tglAkhir = String(k.tanggal_selesai||'').slice(0,10) || tgl;
  const map = {};
  if(!tgl) return map;
  (DB.kegiatan||[]).forEach(o=>{
    const oa = String(o.tanggal||'').slice(0,10), ob = String(o.tanggal_selesai||'').slice(0,10) || oa;
    // bentrok bila rentang tanggal kedua kegiatan saling beririsan
    if(o.id===k.id || o.status_persetujuan==='Ditolak' || !oa || oa > tglAkhir || ob < tgl) return;
    (o.anggota_ids||[]).forEach(aid=>{ (map[aid] = map[aid]||[]).push(o.id); });
  });
  return map;
}
function openAssignAnggota(id){
  const k = getKegiatan(id);
  if(k.sprin){ toast('Sprin sudah terinput, anggota bertugas tidak dapat diubah'); return; }
  const aktif = DB.anggota.filter(a=>a.status==='Aktif');
  const bentrok = anggotaBentrok(k);
  const dipilih = new Set(k.anggota_ids||[]);
  openModal(`
    <div class="p-5 max-h-[85vh] overflow-y-auto">
      <div class="flex items-center justify-between mb-1">
        <h3 class="font-display font-semibold text-base">${dipilih.size ? 'Ubah' : 'Pilih'} Anggota Bertugas</h3>
        <button class="btn btn-ghost !px-2 !py-1" onclick="closeModal()">✕</button>
      </div>
      <p class="text-xs mb-1" style="color:var(--muted);">Dibutuhkan ${k.jumlah_dibutuhkan} anggota untuk "${esc(k.nama)}" pada ${esc(tglRentang(k))}.</p>
      <p class="text-[11px] mb-3" style="color:var(--muted);">Anggota yang sudah bertugas di kegiatan lain pada rentang tanggal yang sama tidak dapat dipilih.</p>
      <div class="flex items-center gap-2 mb-2">
        <input type="text" id="assign_cari" placeholder="Cari nama atau jabatan anggota..." style="flex:1; min-width:0;" autocomplete="off" oninput="filterAssignAnggota(this.value)">
        <button type="button" class="btn btn-navy" id="assign_btn_sar" style="white-space:nowrap;" onclick="toggleAssignTenagaSar(${k.jumlah_dibutuhkan})">Tenaga SAR</button>
      </div>
      <div class="space-y-1.5 max-h-[45vh] overflow-y-auto" id="assign_list">
        ${aktif.map(a=>{
          const bt = bentrok[a.id];
          return `
          <label class="assign-item flex items-center gap-2.5 p-2 rounded-lg" data-sar="${isTenagaSar(a)?'1':'0'}" data-cari="${esc((a.nama+' '+(a.jabatan||'')).toLowerCase())}" style="background:var(--bg); ${bt?'opacity:.55; cursor:not-allowed;':''}">
            <input type="checkbox" class="assign-chk" value="${a.id}" style="width:1rem;height:1rem;" ${dipilih.has(a.id)?'checked':''} ${bt?'disabled':''}>
            <span class="text-sm">${esc(a.nama)} <span style="color:var(--muted)">— ${esc(a.jabatan)}</span>${bt ? ` <span class="badge b-warn" style="margin-left:.25rem;">Bertugas di ${esc(bt.join(', '))}</span>` : ''}</span>
          </label>`;}).join('')}
        <p id="assign_kosong" class="text-sm text-center py-4" style="display:none; color:var(--muted);">Anggota tidak ditemukan.</p>
      </div>
      <div id="assign_wa_box" class="mt-3 p-3 rounded-lg flex flex-wrap items-center justify-between gap-2" style="display:none; background:var(--bg); position:sticky; bottom:0; z-index:5;">
        <span class="text-xs" style="color:var(--muted);">Tenaga SAR dipilih. Kirim notifikasi penugasan ke WA Grup.</span>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" class="btn btn-navy" onclick="saveAssignAnggota('${id}')">Simpan Data</button>
          <button type="button" class="btn btn-primary" onclick="kirimNotifWaGrup('${id}')">Kirim Notifikasi via WA Grup</button>
        </div>
      </div>
      <div class="flex justify-end gap-2 mt-5">
        <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" onclick="saveAssignAnggota('${id}')">Simpan Penugasan</button>
      </div>
    </div>`, true);
}
// Tenaga SAR = anggota yang jabatannya termasuk kelompok Tenaga SAR (BA_KATEGORI)
function isTenagaSar(a){
  const t = String((a && a.jabatan) || '').trim().toLowerCase();
  return BA_KATEGORI.some(k=>k.re.test(t));
}
// Tombol "Tenaga SAR": centang semua anggota Tenaga SAR yang masih bisa dipilih (tidak bentrok).
// Klik lagi bila semuanya sudah tercentang untuk membatalkan centang Tenaga SAR.
function toggleAssignTenagaSar(maks){
  const btn = document.getElementById('assign_btn_sar');
  const boxes = Array.from(document.querySelectorAll('#assign_list .assign-item'))
    .filter(el=>el.getAttribute('data-sar')==='1')
    .map(el=>el.querySelector('.assign-chk'))
    .filter(cb=>cb && !cb.disabled);
  if(!boxes.length){ toast('Tidak ada anggota Tenaga SAR yang dapat dipilih'); return; }
  // Klik pertama selalu mencentang semua Tenaga SAR; klik berikutnya (setelah aktif) membatalkan centang
  const aktif = btn && btn.getAttribute('data-aktif')==='1';
  const centang = !aktif;
  boxes.forEach(cb=>{ cb.checked = centang; });
  if(btn){
    btn.setAttribute('data-aktif', centang ? '1' : '0');
    btn.textContent = centang ? 'Tenaga SAR ✓' : 'Tenaga SAR';
  }
  const waBox = document.getElementById('assign_wa_box');
  if(waBox) waBox.style.display = centang ? '' : 'none';
  if(!centang){ toast('Centang Tenaga SAR dibatalkan'); return; }
  const total = document.querySelectorAll('.assign-chk:checked').length;
  if(maks && total > maks) toast(`${boxes.length} Tenaga SAR dicentang, melebihi kebutuhan (${maks} anggota). Kurangi centang sebelum menyimpan`);
  else toast(`${boxes.length} Tenaga SAR dicentang`);
}
// Kirim notifikasi penugasan ke WA Grup: buka WhatsApp dengan pesan siap kirim (pilih grup tujuan di WhatsApp)
function kirimNotifWaGrup(id){
  const k = getKegiatan(id);
  if(!k) return;
  const ids = Array.from(document.querySelectorAll('.assign-chk:checked')).map(el=>el.value);
  const nama = DB.anggota.filter(a=>ids.includes(a.id)).map(a=>a.nama);
  if(!nama.length){ toast('Belum ada anggota yang dicentang'); return; }
  // Uji Periodik: template ringkas (Kegiatan / Tanggal / Jam / Lokasi) tanpa daftar anggota
  if(k.jenis==='uji_periodik'){
    const pesanUjian = [
      'Kegiatan: ' + (k.nama||''),
      'Tanggal: ' + tglRentang(k),
      'Jam: ' + (k.waktu ? k.waktu + ' WIB' : '-'),
      'Lokasi: ' + (k.lokasi||'-')
    ].join('\n');
    window.open('https://wa.me/?text=' + encodeURIComponent(pesanUjian), '_blank');
    toast('WhatsApp dibuka, pilih WA Grup tujuan');
    return;
  }
  const baris = [
    '*NOTIFIKASI PENUGASAN*',
    '',
    'Kegiatan : ' + (k.nama||''),
    'Tanggal  : ' + tglRentang(k) + (k.waktu ? ' pukul ' + k.waktu : ''),
    'Lokasi   : ' + (k.lokasi||'-'),
    '',
    'Anggota bertugas (' + nama.length + '):'
  ].concat(nama.map((n,i)=>(i+1)+'. '+n)).concat(['', 'Mohon dipersiapkan. Terima kasih.']);
  window.open('https://wa.me/?text=' + encodeURIComponent(baris.join('\n')), '_blank');
  toast('WhatsApp dibuka, pilih WA Grup tujuan');
}
// Cari anggota: sembunyikan yang tidak cocok; centang tetap tersimpan walau sedang disembunyikan
function filterAssignAnggota(q){
  const nq = normSearch(q);
  let n = 0;
  document.querySelectorAll('#assign_list .assign-item').forEach(el=>{
    const ok = !nq || normSearch(el.getAttribute('data-cari')).includes(nq);
    el.style.display = ok ? '' : 'none';
    if(ok) n++;
  });
  const k = document.getElementById('assign_kosong');
  if(k) k.style.display = n ? 'none' : '';
}
function saveAssignAnggota(id){
  const k = getKegiatan(id);
  if(k.sprin){ toast('Sprin sudah terinput, anggota bertugas tidak dapat diubah'); return; }
  const chosen = Array.from(document.querySelectorAll('.assign-chk:checked')).map(el=>el.value);
  if(chosen.length===0){ toast('Pilih minimal satu anggota'); return; }
  if(chosen.length > k.jumlah_dibutuhkan){ toast(`Maksimal ${k.jumlah_dibutuhkan} anggota untuk kegiatan ini`); return; }
  const bentrok = anggotaBentrok(k);
  const dobel = chosen.filter(aid=>bentrok[aid]);
  if(dobel.length){ toast('Ada anggota yang sudah bertugas di kegiatan lain pada rentang tanggal yang sama'); return; }
  const berubah = JSON.stringify((k.anggota_ids||[]).slice().sort()) !== JSON.stringify(chosen.slice().sort());
  k.anggota_ids = chosen;
  if(berubah && k.status_wa==='Terkirim') k.status_wa = 'Belum Terkirim'; // anggota berubah: info tugas perlu dikirim ulang
  touchKegiatan(k);
  toast('Penugasan disimpan');
  closeModal();
  render('kegiatan'); openKegiatanDetail(id);
}

// Kirim info tugas (template Nama Kegiatan/Tanggal/Lokasi/Jam/Anggota) + PDF Sprin ke WhatsApp tiap anggota bertugas.
// Pengiriman dilakukan server (api.php?action=wa_tugas) lewat gateway WhatsApp; token tidak pernah ke browser.
let waTugasBusy = false;
async function sendWhatsapp(id){
  if(waTugasBusy) return;
  const k = getKegiatan(id); if(!k) return;
  const assigned = DB.anggota.filter(a=>k.anggota_ids.includes(a.id));
  if(!k.sprin){ toast('Upload Sprin terlebih dahulu'); return; }
  const tanpaNo = assigned.filter(a=>!String(a.whatsapp||'').trim()).map(a=>a.nama);
  let msg = `Kirim ke ${assigned.length} anggota via WhatsApp?\n\nSetiap anggota menerima 2 pesan terpisah:\n1. Info tugas + PDF Sprin\n2. Link update kegiatan\n\n(Link upload laporan dikirim otomatis setelah anggota mengisi update kegiatan.)`;
  if(tanpaNo.length) msg += `\n\nTanpa nomor WhatsApp (tidak akan menerima):\n- ${tanpaNo.join('\n- ')}`;
  if(!confirm(msg)) return;
  waTugasBusy = true;
  toast('Mengirim ke WhatsApp, mohon tunggu...');
  try{
    persist(); await flushPersist(); // pastikan data (anggota bertugas, Sprin) di server sudah terbaru
    const r = await fetch(API_URL + '?action=wa_tugas', {method:'POST', headers:{'X-Requested-With':'basarnas','Content-Type':'application/json'}, body: JSON.stringify({id})});
    const j = await r.json().catch(()=>({}));
    if(!r.ok || !j.ok){ toast(j.error || 'Gagal mengirim WhatsApp'); return; }
    const gagal = (j.hasil||[]).filter(h=>!h.ok);
    if(j.terkirim > 0){
      // Ambil ulang record terbaru (DB bisa saja sudah diganti hasil sinkronisasi saat pengiriman berjalan);
      // status & riwayat kirim dicatat server, browser hanya mengikuti.
      const kk = getKegiatan(id) || k;
      kk.status_wa = j.status_wa || (gagal.length ? 'Belum Terkirim' : 'Terkirim');
      if(Array.isArray(j.wa_log)) kk.wa_log = j.wa_log;
      else { kk.wa_log = kk.wa_log || []; kk.wa_log.push({waktu: nowStr(), jumlah: j.terkirim}); }
      if(j.status_wa){ persist(); SYNC_REV = -1; } else touchKegiatan(kk);
    }
    if(gagal.length) alert(`Terkirim ke ${j.terkirim} dari ${j.total} anggota.\n\nGagal:\n` + gagal.map(h=>`- ${h.nama}: ${h.ket}`).join('\n') + '\n\nPerbaiki nomor/koneksi lalu kirim ulang.');
    else toast(`Info tugas + Sprin dan link update terkirim ke ${j.terkirim} anggota`);
  }catch(e){
    toast('Gagal mengirim WhatsApp: ' + (e && e.message ? e.message : 'koneksi bermasalah'));
  }finally{
    waTugasBusy = false;
    render('kegiatan'); openKegiatanDetail(id);
  }
}

function setPelaksanaan(id, status){
  const k = getKegiatan(id);
  if(!k || k.status_persetujuan!=='Disetujui'){ toast('Kegiatan yang ditolak/belum disetujui tetap berstatus Draft dan tidak dapat diubah'); return; }
  k.status_pelaksanaan = status;
  touchKegiatan(k);
  toast(status==='Selesai' ? 'Kegiatan selesai, dipindahkan ke Tugas Selesai' : 'Status pelaksanaan diperbarui: ' + status);
  if(status==='Selesai'){ kegTab = 'selesai'; notifSelesaiWa(id); }
  render('kegiatan'); openKegiatanDetail(id);
}

// Data modul yang terhubung dengan satu kegiatan: data yang ditunjuk kegiatan
// (linked_record_id) atau data yang mencatat kegiatan ini sebagai asalnya (dari_kegiatan_id).
function dataModulTerkaitKegiatan(k){
  const hasil = [];
  if(!k) return hasil;
  GENERIC_MODULES.forEach(m=>{
    (DB[m.key]||[]).forEach(r=>{
      const tertaut = (m.key===k.jenis && k.linked_record_id && r.id===k.linked_record_id) || r.dari_kegiatan_id===k.id;
      if(tertaut) hasil.push({key:m.key, label:m.label, id:r.id});
    });
  });
  return hasil;
}
function deleteKegiatan(id){
  const k = (DB.kegiatan||[]).find(x=>x.id===id);
  const terkait = dataModulTerkaitKegiatan(k);
  const pesan = terkait.length
    ? 'Hapus kegiatan ini?\n\nData terkait di modul (' + terkait.map(t=>t.label+' '+t.id).join(', ') + ') juga akan ikut terhapus.'
    : 'Hapus kegiatan ini?';
  if(!confirm(pesan)) return;
  ((k&&k.hasil_files)||[]).forEach(f=>fdbDel(f.fid));
  ((k&&k.bukti_files)||[]).forEach(f=>fdbDel(f.fid));
  lampiranWaList(k).forEach(f=>fdbDel(f.fid));
  if(k && k.sprin) fdbDel(k.sprin.fid);
  DB.kegiatan = DB.kegiatan.filter(x=>x.id!==id);
  terkait.forEach(t=>{ DB[t.key] = (DB[t.key]||[]).filter(r=>r.id!==t.id); });
  persist();
  closeModal();
  toast(terkait.length ? 'Kegiatan dan data terkait di modul dihapus' : 'Kegiatan dihapus');
  render('kegiatan');
}


/* ============================= SPRIN (SURAT PERINTAH) ============================= */
// Pengganti Surat Tugas: Sprin dibuat di luar aplikasi, lalu diupload (PDF) oleh Admin.
// File disimpan di IndexedDB (sama seperti file hasil kegiatan); metadata di k.sprin.
// Keterangan: Belum Di Proses (default) / Sedang Di Proses (diatur Admin) / Selesai (otomatis setelah diupload).
const SPRIN_MAX_BYTES = 25*1024*1024;
function sprinPerluTampil(k){ return k.status_persetujuan==='Disetujui' && (k.anggota_ids||[]).length>0; }
function sprinStatusLabel(k){ return k.sprin ? 'Selesai' : (k.sprin_proses==='Sedang Di Proses' ? 'Sedang Di Proses' : 'Belum Di Proses'); }
function sprinBadgeHtml(k){
  if(!sprinPerluTampil(k)) return '';
  return 'Sprin: ' + prosesBadgeHtml(sprinStatusLabel(k));
}
function isAdmin(){ return currentRole==='Admin' || isSuper(); }
function sprinCardHtml(k){
  if(!sprinPerluTampil(k)) return '';
  const sp = k.sprin;
  const ico = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg>`;
  return `
    <div class="rounded-lg p-3 mb-4" style="background:var(--bg);">
      <div class="flex items-center gap-2.5">
        ${ico}
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium">Sprin</p>
          <p class="text-[11px]" style="color:var(--muted);">${sp ? esc(sp.name)+' · '+fmtUkuran(sp.size)+' · diupload '+esc(sp.uploaded) : 'Belum diupload oleh Admin'}</p>
        </div>
        ${sprinBadgeHtml(k)}
      </div>
      ${(!sp && isAdmin()) ? `<div class="mt-3"><p class="text-[11px] mb-1" style="color:var(--muted);">Keterangan Sprin:</p><select class="!w-52" onchange="setSprinProses('${k.id}',this.value,true)">${PROSES_LIST.map(o=>`<option ${sprinStatusLabel(k)===o?'selected':''}>${o}</option>`).join('')}</select></div>` : ''}
      ${sp ? `<div class="flex flex-wrap gap-2 mt-3">
        <button class="btn btn-navy !py-1 !px-2 text-xs" onclick="lihatSprin('${k.id}')">Lihat</button>
        <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="unduhSprin('${k.id}')">Unduh</button>
        ${isAdmin() ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="hapusSprin('${k.id}')">Hapus</button>` : ''}
      </div>` : ''}
      ${isAdmin() ? `<div class="mt-3">
        <p class="text-[11px] mb-1" style="color:var(--muted);">${sp ? 'Ganti file Sprin (PDF, maks. 25MB):' : 'Upload file Sprin (PDF, maks. 25MB):'}</p>
        <input type="file" accept="application/pdf,.pdf" onchange="uploadSprin('${k.id}', this)">
      </div>` : (sp ? '' : `<p class="text-[11px] mt-2" style="color:var(--muted);">Sprin akan diupload oleh Admin.</p>`)}
    </div>`;
}
async function uploadSprin(id, input){
  const k = getKegiatan(id); if(!k) return;
  if(!isAdmin()){ toast('Hanya Admin yang dapat mengupload Sprin'); return; }
  const f = (input.files||[])[0]; if(!f) return;
  if(!(f.type==='application/pdf' || /\.pdf$/i.test(f.name))){ toast('Sprin harus berformat PDF'); input.value=''; return; }
  if(f.size > SPRIN_MAX_BYTES){ toast('File Sprin melebihi 25MB'); input.value=''; return; }
  const fid = 'S'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
  try{
    await fdbPut({fid, kid:id, name:f.name, blob:f});
    if(k.sprin) await fdbDel(k.sprin.fid);
    k.sprin = {fid, name:f.name, size:f.size, type:'application/pdf', uploaded:nowStr(), tanggal:todayISO(), oleh:currentRole};
    k.diperbarui = nowStr(); persist();
    toast('Sprin berhasil diupload');
  }catch(e){ toast('Gagal menyimpan file Sprin'); }
  render('kegiatan'); openKegiatanDetail(id);
}
async function sprinBlobUrl(id){
  const k = getKegiatan(id); if(!k || !k.sprin) return null;
  const rec = await fdbGet(k.sprin.fid);
  if(!rec){ toast('File Sprin tidak ditemukan di server'); return null; }
  const blob = rec.blob.type==='application/pdf' ? rec.blob : new Blob([rec.blob], {type:'application/pdf'});
  return {url:URL.createObjectURL(blob), name:rec.name};
}
async function lihatSprin(id){
  try{
    const r = await sprinBlobUrl(id); if(!r) return;
    const a = document.createElement('a');
    a.href = r.url; a.target = '_blank'; a.rel = 'noopener';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(r.url), 60000);
  }catch(e){ toast('Gagal membuka Sprin'); }
}
async function unduhSprin(id){
  try{
    const r = await sprinBlobUrl(id); if(!r) return;
    const a = document.createElement('a');
    a.href = r.url; a.download = r.name;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(r.url), 5000);
  }catch(e){ toast('Gagal mengunduh Sprin'); }
}
async function hapusSprin(id){
  const k = getKegiatan(id); if(!k || !k.sprin) return;
  if(!isAdmin()){ toast('Hanya Admin yang dapat menghapus Sprin'); return; }
  if(!confirm(`Hapus file Sprin "${k.sprin.name}"?\n\nKeterangan akan kembali menjadi "Belum Di Proses" / "Sedang Di Proses".`)) return;
  await fdbDel(k.sprin.fid);
  delete k.sprin;
  k.diperbarui = nowStr(); persist();
  toast('Sprin dihapus');
  render('kegiatan'); openKegiatanDetail(id);
}

/* ============================= UPLOAD HASIL KEGIATAN ============================= */
// File hasil (format apa pun) disimpan di IndexedDB agar tidak membebani localStorage;
// metadatanya (nama, ukuran, waktu) disimpan di data kegiatan (k.hasil_files).
const FILE_DB_NAME = 'basarnas_kegiatan_files';
const HASIL_MAX_BYTES = 25*1024*1024;
/* Penyimpanan file di server (folder data/uploads) lewat api.php */
async function fdbPut(rec){
  const fd = new FormData();
  fd.append('fid', rec.fid); fd.append('kid', rec.kid || ''); fd.append('file', rec.blob, rec.name);
  const r = await fetch(API_URL + '?action=file_put', {method:'POST', headers:{'X-Requested-With':'basarnas'}, body:fd});
  const j = await r.json().catch(()=>({}));
  if(!r.ok || !j.ok) throw new Error(j.error || 'Upload gagal');
  return true;
}
async function fdbGet(fid){
  const r = await fetch(API_URL + '?action=file_get&fid=' + encodeURIComponent(fid));
  if(!r.ok) return null;
  const blob = await r.blob();
  let name = fid; const hn = r.headers.get('X-File-Name');
  if(hn){ try{ name = decodeURIComponent(hn); }catch(e){ name = hn; } }
  return {fid, name, blob};
}
function fdbDel(fid){
  return fetch(API_URL + '?action=file_del&fid=' + encodeURIComponent(fid), {method:'POST', headers:{'X-Requested-With':'basarnas'}}).catch(()=>{});
}

function fmtUkuran(b){ return b>=1048576 ? (b/1048576).toFixed(1)+' MB' : Math.max(1,Math.round(b/1024))+' KB'; }

/* ---- Bukti penugasan (JPG/PNG/PDF) saat status "Dalam Pelaksanaan": bisa diupload anggota ---- */
const BUKTI_MAX_BYTES = 10*1024*1024;
function buktiTipeOk(f){ return /^image\/(jpeg|png)$/.test(f.type) || f.type==='application/pdf' || /\.(jpe?g|png|pdf)$/i.test(f.name); }
function buktiKegiatanHtml(k){
  if(k.status_persetujuan!=='Disetujui') return '';
  const files = k.bukti_files||[];
  const aktif = k.status_pelaksanaan==='Dalam Pelaksanaan' || (k.status_pelaksanaan==='Draft' && !!k.sprin);
  if(!aktif && !files.length) return '';
  return `
    <div class="rounded-lg p-3 mb-4" style="background:var(--bg);">
      <p class="text-sm font-medium mb-1">Bukti Penugasan</p>
      <p class="text-[11px] mb-2" style="color:var(--muted);">${aktif ? (k.status_pelaksanaan==='Draft' ? 'Sprin sudah tersedia. Upload foto atau dokumen sebagai bukti bahwa anggota sedang bertugas; setelah terupload, status pelaksanaan otomatis menjadi <strong>Dalam Pelaksanaan</strong>. ' : 'Tambahkan bukti lain bila perlu. ') + 'Format JPG, PNG atau PDF, maks. 10MB per file.' : 'Bukti yang diupload selama kegiatan berlangsung.'}</p>
      ${files.length ? `<div class="space-y-1.5 mb-3">${files.map(f=>`
        <div class="flex items-center gap-2 rounded-lg p-2" style="background:var(--panel, #fff);">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium truncate">${esc(f.name)}</p>
            <p class="text-[11px]" style="color:var(--muted);">${fmtUkuran(f.size)} · ${esc(f.uploaded)}${f.oleh ? ' · oleh '+esc(f.oleh) : ''}</p>
          </div>
          <button class="btn btn-navy !py-1 !px-2 text-xs" onclick="lihatBukti('${k.id}','${f.fid}')">Lihat</button>
          <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="unduhHasil('${k.id}','${f.fid}')">Unduh</button>
          ${isApprover() ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="hapusBukti('${k.id}','${f.fid}')">Hapus</button>` : ''}
        </div>`).join('')}</div>` : `<p class="text-xs mb-3" style="color:var(--muted);">Belum ada bukti yang diupload.</p>`}
      ${aktif ? `<input type="file" multiple accept="image/jpeg,image/png,application/pdf,.jpg,.jpeg,.png,.pdf" onchange="uploadBukti('${k.id}', this)">` : ''}
    </div>`;
}
async function uploadBukti(id, input){
  const k = getKegiatan(id); if(!k) return;
  if(!(k.status_persetujuan==='Disetujui' && (k.status_pelaksanaan==='Dalam Pelaksanaan' || (k.status_pelaksanaan==='Draft' && k.sprin)))){ toast('Bukti dapat diupload setelah Sprin tersedia'); return; }
  const files = Array.from(input.files||[]); if(!files.length) return;
  let ok = 0;
  for(const f of files){
    if(!buktiTipeOk(f)){ toast(`"${f.name}" harus berformat JPG, PNG atau PDF`); continue; }
    if(f.size > BUKTI_MAX_BYTES){ toast(`"${f.name}" melebihi 10MB`); continue; }
    const fid = 'B'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
    try{
      await fdbPut({fid, kid:id, name:f.name, blob:f});
      (k.bukti_files = k.bukti_files||[]).push({fid, name:f.name, size:f.size, type:f.type||'', uploaded:nowStr(), oleh:currentRole});
      ok++;
    }catch(e){ toast(`Gagal menyimpan "${f.name}"`); }
  }
  if(ok){
    const mulai = k.status_pelaksanaan==='Draft';
    if(mulai){ k.status_pelaksanaan = 'Dalam Pelaksanaan'; touchKegiatan(k); } else { k.diperbarui = nowStr(); persist(); }
    toast(mulai ? 'Bukti terupload, status pelaksanaan menjadi Dalam Pelaksanaan' : ok+' bukti penugasan diupload');
    if(mulai) render('kegiatan');
    notifBuktiWa(id);
  }
  openKegiatanDetail(id);
}
// Beri tahu semua anggota bertugas lewat WhatsApp bahwa bukti sudah terinput (dikirim server setelah data tersimpan)
async function notifSelesaiWa(id){
  try{
    await flushPersist();
    const r = await fetch(API_URL + '?action=wa_notif_selesai', {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'basarnas'}, body:JSON.stringify({id})});
    const j = await r.json().catch(()=>({ok:false}));
    if(!r.ok || !j.ok){ toast('Notifikasi WA selesai gagal: ' + (j.error || ('HTTP '+r.status))); return; }
    if(j.dilewati) return;
    toast('Notifikasi selesai terkirim ke ' + j.terkirim + ' dari ' + j.total + ' anggota bertugas');
  }catch(e){ console.error(e); }
}
async function notifBuktiWa(id){
  try{
    await flushPersist();
    const r = await fetch(API_URL + '?action=wa_notif_bukti', {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'basarnas'}, body:JSON.stringify({id})});
    const j = await r.json().catch(()=>({ok:false}));
    if(!r.ok || !j.ok){ toast('Notifikasi WA ke anggota gagal: ' + (j.error || ('HTTP '+r.status))); return; }
    if(j.dilewati) return;
    toast('Notifikasi WA terkirim ke ' + j.terkirim + ' dari ' + j.total + ' anggota bertugas');
  }catch(e){ console.error(e); }
}
async function lihatBukti(id, fid){
  try{
    const rec = await fdbGet(fid);
    if(!rec){ toast('File tidak ditemukan di server'); return; }
    if(!/^(application\/pdf|image\/(png|jpeg))$/.test(rec.blob.type)){ return unduhHasil(id, fid); } // selain PDF/JPG/PNG: unduh saja
    const url = URL.createObjectURL(rec.blob);
    const a = document.createElement('a');
    a.href = url; a.target = '_blank'; a.rel = 'noopener';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 60000);
  }catch(e){ toast('Gagal membuka file'); }
}
async function hapusBukti(id, fid){
  const k = getKegiatan(id); if(!k) return;
  const f = (k.bukti_files||[]).find(x=>x.fid===fid);
  if(!f || !confirm(`Hapus bukti "${f.name}"?`)) return;
  await fdbDel(fid);
  k.bukti_files = k.bukti_files.filter(x=>x.fid!==fid);
  const balik = k.status_pelaksanaan==='Dalam Pelaksanaan' && !k.bukti_files.length;
  if(balik){ k.status_pelaksanaan = 'Draft'; touchKegiatan(k); } else { k.diperbarui = nowStr(); persist(); }
  toast(balik ? 'Bukti dihapus, status kembali Draft' : 'Bukti dihapus');
  if(balik) render('kegiatan');
  openKegiatanDetail(id);
}

/* ---- Lampiran dari WhatsApp (rec.lampiran_files): dikirim lewat tautan upload / chat WA, disimpan di data/uploads ---- */
function lampiranWaList(rec){ return (rec && Array.isArray(rec.lampiran_files)) ? rec.lampiran_files : []; }
function lampiranWaHtml(key, rec){
  const fl = lampiranWaList(rec);
  if(!fl.length) return '';
  return `
    <div class="mt-4 rounded-lg p-3" style="background:var(--bg);">
      <p class="text-sm font-medium mb-2">Lampiran (WhatsApp)</p>
      ${fl.map(f=>`
        <div class="flex items-center gap-2 py-1.5">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium truncate" title="${esc(f.name)}">${esc(f.name)}</p>
            <p class="text-[11px]" style="color:var(--muted);">${f.size ? Math.max(1,Math.round(f.size/1024))+' KB · ' : ''}${esc(f.uploaded||'')}${f.oleh ? ' · '+esc(f.oleh) : ''}</p>
          </div>
          <button class="btn btn-navy !py-1 !px-2 text-xs" onclick="lihatLampiran('${esc(f.fid)}')">Lihat</button>
          <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="unduhLampiran('${esc(f.fid)}')">Unduh</button>
          ${isApprover() ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="hapusLampiran('${esc(key)}','${esc(rec.id)}','${esc(f.fid)}')">Hapus</button>` : ''}
        </div>`).join('')}
    </div>`;
}
async function lihatLampiran(fid){
  try{
    const rec = await fdbGet(fid);
    if(!rec){ toast('File tidak ditemukan di server'); return; }
    if(!/^(application\/pdf|image\/(png|jpeg))$/.test(rec.blob.type)) return unduhLampiran(fid);
    const url = URL.createObjectURL(rec.blob);
    const a = document.createElement('a');
    a.href = url; a.target = '_blank'; a.rel = 'noopener';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 60000);
  }catch(e){ toast('Gagal membuka file'); }
}
async function unduhLampiran(fid){
  try{
    const rec = await fdbGet(fid);
    if(!rec){ toast('File tidak ditemukan di server'); return; }
    const url = URL.createObjectURL(rec.blob);
    const a = document.createElement('a');
    a.href = url; a.download = rec.name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 5000);
  }catch(e){ toast('Gagal mengunduh file'); }
}
async function hapusLampiran(key, id, fid){
  if(!isApprover()) return;
  const rec = (DB[key]||[]).find(r=>r.id===id); if(!rec) return;
  const f = lampiranWaList(rec).find(x=>x.fid===fid);
  if(!f || !confirm(`Hapus lampiran "${f.name}"?`)) return;
  await fdbDel(fid);
  rec.lampiran_files = rec.lampiran_files.filter(x=>x.fid!==fid);
  if(rec.diperbarui) rec.diperbarui = nowStr();
  persist();
  toast('Lampiran dihapus');
  if(key==='kegiatan') openKegiatanDetail(id);
  else if(key==='surat_masuk') openSuratDetail(id);
  else if(key==='surat_keluar') { closeModal(); renderPersuratan(); }
  else openGenericDetail(key, id);
}

/* ---- Laporan penyelesaian (PDF): saat diupload, status pelaksanaan otomatis menjadi Selesai ---- */
function laporanSelesaiHtml(k){
  if(k.status_persetujuan!=='Disetujui' || k.status_pelaksanaan!=='Dalam Pelaksanaan') return '';
  return `
    <div class="rounded-lg p-3 mb-4" style="background:var(--bg); border:1px dashed var(--orange-500);">
      <p class="text-sm font-medium mb-1">Hasil Penugasan</p>
      <p class="text-[11px] mb-2" style="color:var(--muted);">Upload file PDF hasil penugasan (maks. 25MB) <strong>atau</strong> masukkan link hasil penugasan (mis. Google Drive). Setelah file/link tersimpan, status pelaksanaan otomatis berubah menjadi <strong>Selesai</strong> dan kegiatan pindah ke Tugas Selesai.</p>
      <p class="text-xs font-medium mb-1">Upload file PDF</p>
      <input type="file" accept="application/pdf,.pdf" onchange="uploadLaporanSelesai('${k.id}', this)">
      <p class="text-xs font-medium mt-3 mb-1">Atau input link</p>
      <div class="flex flex-wrap gap-2">
        <input type="url" id="hasilLink_${k.id}" placeholder="https://..." class="flex-1 min-w-[200px]" onkeydown="if(event.key==='Enter'){event.preventDefault();simpanLinkLaporan('${k.id}');}">
        <button class="btn btn-navy !py-1 !px-3 text-xs" onclick="simpanLinkLaporan('${k.id}')">Simpan Link</button>
      </div>
    </div>`;
}
/* Link hasil: hanya http/https. Mengembalikan URL bersih atau null. */
function normalisasiLink(v){
  v = String(v||'').trim(); if(!v) return null;
  if(!/^[a-z][a-z0-9+.-]*:/i.test(v)) v = 'https://' + v;
  try{ const u = new URL(v); return /^https?:$/.test(u.protocol) ? u.href : null; }catch(e){ return null; }
}
function bukaLinkHasil(id, fid){
  const k = getKegiatan(id); const f = k && [].concat(k.hasil_files||[]).find(x=>x.fid===fid);
  const u = f && normalisasiLink(f.link);
  if(!u){ toast('Link tidak valid'); return; }
  window.open(u, '_blank', 'noopener,noreferrer');
}
function simpanLinkLaporan(id){
  const k = getKegiatan(id); if(!k) return;
  if(k.status_persetujuan!=='Disetujui' || k.status_pelaksanaan!=='Dalam Pelaksanaan'){ toast('Kegiatan tidak sedang Dalam Pelaksanaan'); return; }
  const inp = document.getElementById('hasilLink_'+id);
  const u = normalisasiLink(inp && inp.value);
  if(!u){ toast('Masukkan link yang valid (diawali http:// atau https://)'); return; }
  const fid = 'U'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
  (k.hasil_files = k.hasil_files||[]).push({fid, name:u, link:u, size:0, type:'link', uploaded:nowStr(), laporan:true, oleh:currentRole});
  k.status_pelaksanaan = 'Selesai';
  touchKegiatan(k);
  toast('Link hasil tersimpan, status pelaksanaan menjadi Selesai');
  notifSelesaiWa(id);
  kegTab = 'selesai';
  render('kegiatan'); openKegiatanDetail(id);
}
async function uploadLaporanSelesai(id, input){
  const k = getKegiatan(id); if(!k) return;
  if(k.status_persetujuan!=='Disetujui' || k.status_pelaksanaan!=='Dalam Pelaksanaan'){ toast('Kegiatan tidak sedang Dalam Pelaksanaan'); return; }
  const f = (input.files||[])[0]; if(!f) return;
  if(!(f.type==='application/pdf' || /\.pdf$/i.test(f.name))){ toast('File harus berformat PDF'); input.value=''; return; }
  if(f.size > HASIL_MAX_BYTES){ toast('File melebihi 25MB'); input.value=''; return; }
  const fid = 'L'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
  try{
    await fdbPut({fid, kid:id, name:f.name, blob:f});
  }catch(e){ toast('Gagal menyimpan file'); return; }
  (k.hasil_files = k.hasil_files||[]).push({fid, name:f.name, size:f.size, type:'application/pdf', uploaded:nowStr(), laporan:true, oleh:currentRole});
  k.status_pelaksanaan = 'Selesai';
  touchKegiatan(k);
  toast('Laporan terupload, status pelaksanaan menjadi Selesai');
  notifSelesaiWa(id);
  kegTab = 'selesai';
  render('kegiatan'); openKegiatanDetail(id);
}

/* Total Peserta (khusus SGTS): kotak tersendiri, terpisah dari Hasil Kegiatan; bisa diisi sejak Dalam Pelaksanaan. */
function totalPesertaHtml(k){
  if(k.jenis!=='sgts' || k.status_persetujuan!=='Disetujui' || !(k.anggota_ids||[]).length) return '';
  if(k.status_pelaksanaan!=='Dalam Pelaksanaan' && k.status_pelaksanaan!=='Selesai') return '';
  const ada = k.total_peserta!=null && k.total_peserta!=='';
  return `
    <div class="rounded-lg p-3 mb-4" style="background:var(--bg);">
      <label class="text-sm font-medium block mb-1" for="totalPeserta_${k.id}">Total Peserta</label>
      <div class="flex flex-wrap items-center gap-2">
        <input type="number" min="0" step="1" id="totalPeserta_${k.id}" value="${ada ? esc(String(k.total_peserta)) : ''}" placeholder="Jumlah peserta yang hadir" style="max-width:220px;" onkeydown="if(event.key==='Enter'){event.preventDefault();simpanTotalPeserta('${k.id}');}">
        <button class="btn btn-navy !py-1 !px-3 text-xs" onclick="simpanTotalPeserta('${k.id}')">Simpan</button>
        ${ada ? `<span class="text-[11px]" style="color:var(--muted);">Tersimpan: <strong>${esc(String(k.total_peserta))}</strong> peserta</span>` : ''}
      </div>
    </div>`;
}
function hasilKegiatanHtml(k){
  if(k.status_persetujuan!=='Disetujui' || !(k.anggota_ids||[]).length) return '';
  if(k.status_pelaksanaan!=='Selesai'){
    return `<p class="text-xs mb-4" style="color:var(--muted);">Upload hasil kegiatan tersedia setelah kegiatan ditandai selesai.</p>`;
  }
  const files = k.hasil_files||[];
  return `
    <div class="rounded-lg p-3 mb-4" style="background:var(--bg);">
      <p class="text-sm font-medium mb-1">Hasil Kegiatan</p>
      <p class="text-[11px] mb-2" style="color:var(--muted);">Upload dokumen hasil kegiatan (laporan, foto, video, dll; semua format, maks. 25MB per file) atau tambahkan link.</p>
      ${files.length ? `<div class="space-y-1.5 mb-3">${files.map(f=>`
        <div class="flex items-center gap-2 rounded-lg p-2" style="background:var(--panel, #fff);">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium truncate">${f.link ? '🔗 ' : ''}${esc(f.name)}</p>
            <p class="text-[11px]" style="color:var(--muted);">${f.link ? 'Link' : fmtUkuran(f.size)} · ${esc(f.uploaded)}${f.oleh ? ' · oleh '+esc(f.oleh) : ''}</p>
          </div>
          ${f.link ? `<button class="btn btn-navy !py-1 !px-2 text-xs" onclick="bukaLinkHasil('${k.id}','${f.fid}')">Buka Link</button>` : `<button class="btn btn-navy !py-1 !px-2 text-xs" onclick="lihatBukti('${k.id}','${f.fid}')">Lihat</button>
          <button class="btn btn-ghost !py-1 !px-2 text-xs" onclick="unduhHasil('${k.id}','${f.fid}')">Unduh</button>`}
          ${isApprover() ? `<button class="btn btn-ghost !py-1 !px-2 text-xs" style="color:var(--bad);" onclick="hapusHasil('${k.id}','${f.fid}')">Hapus</button>` : ''}
        </div>`).join('')}</div>` : `<p class="text-xs mb-3" style="color:var(--muted);">Belum ada file atau link yang diinput.</p>`}
      <input type="file" multiple onchange="uploadHasilKegiatan('${k.id}', this)">
      <div class="flex flex-wrap gap-2 mt-3">
        <input type="url" id="hasilLinkTambah_${k.id}" placeholder="Atau tempel link hasil (https://...)" class="flex-1 min-w-[200px]" onkeydown="if(event.key==='Enter'){event.preventDefault();tambahLinkHasil('${k.id}');}">
        <button class="btn btn-navy !py-1 !px-3 text-xs" onclick="tambahLinkHasil('${k.id}')">Simpan Link</button>
      </div>
    </div>`;
}
async function uploadHasilKegiatan(id, input){
  const k = getKegiatan(id); if(!k) return;
  const files = Array.from(input.files||[]); if(!files.length) return;
  let ok = 0;
  for(const f of files){
    if(f.size > HASIL_MAX_BYTES){ toast(`"${f.name}" melebihi 25MB`); continue; }
    const fid = 'F'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
    try{
      await fdbPut({fid, kid:id, name:f.name, blob:f});
      (k.hasil_files = k.hasil_files||[]).push({fid, name:f.name, size:f.size, type:f.type||'', uploaded:nowStr()});
      ok++;
    }catch(e){ toast(`Gagal menyimpan "${f.name}"`); }
  }
  if(ok){ k.diperbarui = nowStr(); persist(); toast(ok+' file hasil kegiatan diupload'); }
  openKegiatanDetail(id);
}
/* Sar Go To School: total peserta aktual dicatat di kegiatan dan disalin ke data modul SGTS yang terhubung (Jumlah Peserta). */
function simpanTotalPeserta(id){
  const k = getKegiatan(id); if(!k || k.jenis!=='sgts') return;
  const inp = document.getElementById('totalPeserta_'+id);
  const raw = String(inp ? inp.value : '').trim();
  if(!/^\d+$/.test(raw)){ toast('Total Peserta harus berupa angka bulat (0 atau lebih)'); return; }
  k.total_peserta = parseInt(raw, 10);
  const rec = k.linked_record_id ? (DB.sgts||[]).find(r=>r.id===k.linked_record_id) : null;
  if(rec) rec.jumlah_peserta = k.total_peserta;
  k.diperbarui = nowStr(); persist();
  toast('Total Peserta tersimpan: ' + k.total_peserta);
  openKegiatanDetail(id);
}
function tambahLinkHasil(id){
  const k = getKegiatan(id); if(!k) return;
  const inp = document.getElementById('hasilLinkTambah_'+id);
  const u = normalisasiLink(inp && inp.value);
  if(!u){ toast('Masukkan link yang valid (diawali http:// atau https://)'); return; }
  const fid = 'U'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
  (k.hasil_files = k.hasil_files||[]).push({fid, name:u, link:u, size:0, type:'link', uploaded:nowStr(), oleh:currentRole});
  k.diperbarui = nowStr(); persist(); toast('Link hasil kegiatan tersimpan');
  openKegiatanDetail(id);
}
async function unduhHasil(id, fid){
  try{
    const rec = await fdbGet(fid);
    if(!rec){ toast('File tidak ditemukan di server'); return; }
    const url = URL.createObjectURL(rec.blob);
    const a = document.createElement('a');
    a.href = url; a.download = rec.name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 5000);
  }catch(e){ toast('Gagal mengunduh file'); }
}
async function hapusHasil(id, fid){
  const k = getKegiatan(id); if(!k) return;
  const f = (k.hasil_files||[]).find(x=>x.fid===fid);
  if(!f || !confirm(`Hapus file "${f.name}"?`)) return;
  await fdbDel(fid);
  k.hasil_files = k.hasil_files.filter(x=>x.fid!==fid);
  // Laporan penyelesaian dihapus (dan tidak ada laporan lain): status kembali Dalam Pelaksanaan
  const kembali = f.laporan && k.status_pelaksanaan==='Selesai' && !k.hasil_files.some(x=>x.laporan);
  if(kembali){ k.status_pelaksanaan = 'Dalam Pelaksanaan'; touchKegiatan(k); kegTab = 'kegiatan'; }
  else { k.diperbarui = nowStr(); persist(); }
  toast(kembali ? 'File dihapus, status kembali Dalam Pelaksanaan dan kegiatan tampil lagi di Kegiatan/Tugas' : 'File dihapus');
  if(kembali) render('kegiatan');
  openKegiatanDetail(id);
}

/* ============================= BATAS 3 BARIS UNTUK SEMUA SEL TABEL =============================
   Teks sel tabel yang lebih dari 3 baris dipotong dengan (...); teks lengkap muncul sebagai tooltip.
   Sel berisi tombol/input/badge/link/tabel bersarang tidak diubah. */
let _clampBusy = false, _clampTimer = null;
function clampTableCells(root){
  if(_clampBusy) return;
  _clampBusy = true;
  try{
    (root || document).querySelectorAll('td').forEach(td=>{
      if(td.dataset.cl || td.colSpan > 1 || td.closest('.no-clamp') || td.classList.contains('whitespace-nowrap')) return;
      td.dataset.cl = '1';
      if(td.querySelector('button,input,select,textarea,a,svg,table,div,.badge,img')) return;
      const txt = td.textContent.trim();
      if(!txt) return;
      if(/^[A-Z]{2,4}-\d+$/.test(txt)) return; // kolom ID (mis. KGT-042) tidak diubah
      const wrap = document.createElement('div');
      wrap.className = 'clamp3';
      wrap.title = txt;
      while(td.firstChild) wrap.appendChild(td.firstChild);
      td.appendChild(wrap);
    });
  }finally{ _clampBusy = false; }
}
function scheduleClamp(){
  clearTimeout(_clampTimer);
  _clampTimer = setTimeout(()=>clampTableCells(), 30);
}
new MutationObserver(()=>{ if(!_clampBusy) scheduleClamp(); })
  .observe(document.body, {childList:true, subtree:true});

/* ============================= INIT ============================= */
TABLE_MODULES.forEach(m=>search[m.key]='');
search.kegiatan=''; search.anggota=''; search.selesai='';
renderSidebar();
render(awalView());
</script>
</body>
</html>