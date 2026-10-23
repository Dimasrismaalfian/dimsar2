<?php
/**
 * Halaman tautan dari bot WhatsApp:  u.php/<TOKEN>
 *  - token 'lampiran': upload 1 foto/PDF untuk data yang sudah tersimpan
 *  - token 'form'    : formulir isian modul (dari perintah FORM <modul>) + lampiran opsional
 * Tanpa login: token acak 8 karakter pada tautan menjadi tanda izin (1 foto/PDF per tautan, ada masa berlaku).
 */
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/lib.php';
require __DIR__ . '/wa_config.php';
require __DIR__ . '/wa_lampiran.php';
require __DIR__ . '/wa_inti.php';
require __DIR__ . '/wa_acc.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

if (!function_exists('ini_parse_quantity')) {
    function ini_parse_quantity(string $s): int
    {
        $s = trim($s); $n = (int)$s; $u = strtolower(substr($s, -1));
        return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
    }
}

function u_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function u_token(): string
{
    $t = '';
    if (!empty($_SERVER['PATH_INFO'])) $t = ltrim((string)$_SERVER['PATH_INFO'], '/');
    elseif (isset($_GET['t'])) $t = (string)$_GET['t'];
    elseif (preg_match('#/u\.php/([A-Za-z0-9]+)#', (string)($_SERVER['REQUEST_URI'] ?? ''), $m)) $t = $m[1];
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t) ?? '');
}

function u_json(array $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_OUT);
    exit;
}

/** Kerangka halaman. $isi sudah berupa HTML aman. */
function u_halaman(string $judul, string $isi, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . u_h($judul) . '</title>'
        . '<style>
:root{--bg:#f3f5f8;--card:#fff;--tx:#12263f;--mut:#667789;--line:#dbe2ea;--navy:#0b2a4a;--org:#f26522;--ok:#157347;--bad:#b02a37;color-scheme:light dark}
@media (prefers-color-scheme:dark){:root{--bg:#0d1520;--card:#162131;--tx:#e8eef6;--mut:#9aabbd;--line:#2a3a4f;--navy:#2f6db5;--org:#ff8a4c;--ok:#4cc38a;--bad:#ff7b86}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:16px}
.card{max-width:460px;margin:24px auto;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
h1{font-size:18px;margin:0 0 2px}.mut{color:var(--mut);font-size:13px}.info{background:var(--bg);border-radius:10px;padding:10px 12px;margin:14px 0;font-size:14px}
.info b{display:block;font-size:15px}
.btn{display:block;width:100%;border:0;border-radius:10px;padding:12px;font-size:15px;font-weight:600;cursor:pointer;background:var(--navy);color:#fff}
.btn[disabled]{opacity:.55;cursor:default}.pick{display:block;border:2px dashed var(--line);border-radius:12px;padding:22px 12px;text-align:center;cursor:pointer;margin-bottom:12px}
.pick:hover{border-color:var(--org)}.pick input{display:none}#prev{max-width:100%;max-height:260px;border-radius:8px;margin:10px auto 0;display:none}
.fl{display:block;font-size:13px;font-weight:600;margin:12px 0 4px}.fl i{color:var(--bad);font-style:normal}.in{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--tx);font:inherit}textarea.in{min-height:70px;resize:vertical}.msg{white-space:pre-line}.msg{margin-top:12px;font-size:14px}.ok{color:var(--ok)}.bad{color:var(--bad)}.big{font-size:40px;line-height:1}
</style></head><body><div class="card">' . $isi . '</div></body></html>';
    exit;
}

function u_status_halaman(string $ikon, string $judul, string $pesan, int $code = 200): never
{
    u_halaman($judul, '<div class="big">' . $ikon . '</div><h1 style="margin-top:8px">' . u_h($judul) . '</h1><p class="mut">' . u_h($pesan) . '</p>', $code);
}

/** Mode formulir: token jenis 'form'. */
function u_form(PDO $p, array $tok): never
{
    $cmd = (string)$tok['cmd'];
    $spec = in_array($cmd, WA_FORM_CMDS, true) ? wa_spec($cmd) : [];
    if (!$spec) u_status_halaman('⚠️', 'Formulir tidak tersedia', 'Jenis formulir tidak dikenal.', 404);
    $st = wl_token_status($tok);
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

    if (!$post) {
        if ($st === 'dipakai') u_status_halaman('✅', 'Formulir sudah dikirim', 'Tautan formulir ini sudah dipakai. Ketik FORM di WhatsApp untuk tautan baru.');
        if ($st === 'kedaluwarsa') u_status_halaman('⌛', 'Tautan kedaluwarsa', 'Ketik FORM di WhatsApp untuk mendapatkan tautan baru.', 410);
        $panjang = ['deskripsi', 'alasan', 'uraian', 'keterangan', 'materi', 'hasil', 'peserta', 'suku_cadang'];
        $h = '';
        foreach ($spec['fields'] as $k => $f) {
            $nm = 'f_' . $k;
            $lab = '<label class="fl" for="' . $nm . '">' . u_h($f['label']) . ($f['req'] ? ' <i>*</i>' : '') . '</label>';
            switch ($f['tipe']) {
                case 'enum':
                    $o = '<option value="">— pilih —</option>';
                    foreach ($f['opts'] as $op) $o .= '<option>' . u_h($op) . '</option>';
                    $in = '<select class="in" id="' . $nm . '" name="' . $nm . '">' . $o . '</select>';
                    break;
                case 'date': $in = '<input class="in" type="date" id="' . $nm . '" name="' . $nm . '">'; break;
                case 'time': $in = '<input class="in" type="time" id="' . $nm . '" name="' . $nm . '">'; break;
                case 'int': case 'num': case 'dec':
                    $in = '<input class="in" type="text" inputmode="decimal" id="' . $nm . '" name="' . $nm . '">'; break;
                default:
                    $in = in_array($k, $panjang, true)
                        ? '<textarea class="in" id="' . $nm . '" name="' . $nm . '"></textarea>'
                        : '<input class="in" type="text" id="' . $nm . '" name="' . $nm . '">';
            }
            $h .= $lab . $in;
        }
        $maksMb = (int)(wl_maks_byte() / 1048576);
        $isi = '<h1>Formulir ' . u_h((string)$tok['modul']) . '</h1><div class="mut">Dashboard BASARNAS · pengirim: ' . u_h((string)($tok['nama'] ?: $tok['nomor'])) . '</div>'
            . '<form id="f" method="post" enctype="multipart/form-data">' . $h
            . '<label class="fl">Lampiran (opsional)</label><label class="pick" id="pick"><input type="file" id="file" accept="image/*,application/pdf">'
            . '<div id="lbl">Ketuk untuk memilih foto atau PDF</div><div class="mut">Maksimal 1 foto/file · ' . $maksMb . ' MB · foto otomatis dikompres</div><img id="prev" alt=""></label>'
            . '<button class="btn" id="kirim" type="submit">Simpan Data</button></form><div class="msg" id="msg"></div>'
            . '<script>
(function(){
  var MAKS=' . $maksMb . '*1048576, SISI=' . WL_SISI_MAKS_PX . ', f=document.getElementById("f"), inp=document.getElementById("file"),
      lbl=document.getElementById("lbl"), prev=document.getElementById("prev"), btn=document.getElementById("kirim"), msg=document.getElementById("msg"), blob=null, nama="";
  function say(t,c){msg.className="msg "+(c||"");msg.textContent=t;}
  function ukuran(b){return b>1048576?(b/1048576).toFixed(1)+" MB":Math.max(1,Math.round(b/1024))+" KB";}
  function kompres(file){
    return new Promise(function(res){
      if(!/^image\\/(jpeg|png|webp)$/.test(file.type)) return res(file);
      var img=new Image(), u=URL.createObjectURL(file);
      img.onload=function(){
        var w=img.naturalWidth,h=img.naturalHeight,s=Math.min(1,SISI/Math.max(w,h)),c=document.createElement("canvas");
        c.width=Math.max(1,Math.round(w*s)); c.height=Math.max(1,Math.round(h*s));
        var x=c.getContext("2d"); x.fillStyle="#fff"; x.fillRect(0,0,c.width,c.height); x.drawImage(img,0,0,c.width,c.height);
        c.toBlob(function(b){URL.revokeObjectURL(u); res(b&&b.size<file.size?b:file);},"image/jpeg",0.72);
      };
      img.onerror=function(){URL.revokeObjectURL(u);res(file);};
      img.src=u;
    });
  }
  inp.addEventListener("change",function(){
    blob=null; prev.style.display="none"; say("");
    var file=inp.files&&inp.files[0]; if(!file){lbl.textContent="Ketuk untuk memilih foto atau PDF";return;}
    nama=file.name; lbl.textContent="Memproses…";
    kompres(file).then(function(b){
      if(b.size>MAKS){lbl.textContent="Ketuk untuk memilih foto atau PDF"; say("File terlalu besar ("+ukuran(b.size)+"). Maksimal ' . $maksMb . ' MB.","bad"); return;}
      blob=b; if(b!==file) nama=nama.replace(/\\.[A-Za-z0-9]+$/,"")+".jpg";
      lbl.textContent=nama+" · "+ukuran(b.size);
      if(/^image\\//.test(b.type)){prev.src=URL.createObjectURL(b);prev.style.display="block";}
    });
  });
  f.addEventListener("submit",function(e){
    e.preventDefault();
    var fd=new FormData(f); fd.delete("file"); fd.append("ajax","1"); if(blob) fd.append("file",blob,nama);
    btn.disabled=true; btn.textContent="Menyimpan…"; say("");
    fetch(location.href,{method:"POST",body:fd}).then(function(r){return r.json().catch(function(){return{ok:false,error:"Respons server tidak dikenal"};});})
    .then(function(j){
      if(j.ok){f.style.display="none"; say("✅ Data tersimpan dengan ID "+j.id+". Konfirmasi dikirim ke WhatsApp Anda."+(j.lampiran_error?"\nLampiran belum masuk: "+j.lampiran_error:""),"ok");}
      else{btn.disabled=false; btn.textContent="Simpan Data"; say(j.error||"Gagal menyimpan.","bad");}
    }).catch(function(){btn.disabled=false; btn.textContent="Simpan Data"; say("Koneksi gagal. Coba lagi.","bad");});
  });
})();
</script>';
        u_halaman('Formulir ' . $tok['modul'], $isi);
    }

    /* ---- POST: simpan data (+ lampiran opsional) ---- */
    $gagal = static function (string $m, int $c = 400): never { u_json(['ok' => false, 'error' => $m], $c); };
    if ($st === 'dipakai') $gagal('Formulir ini sudah dikirim. Ketik FORM di WhatsApp untuk tautan baru.', 409);
    if ($st === 'kedaluwarsa') $gagal('Tautan sudah kedaluwarsa. Ketik FORM di WhatsApp untuk tautan baru.', 410);
    $batas = (int)ini_parse_quantity((string)ini_get('post_max_size'));
    if (!$_POST && $batas > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $batas) $gagal('File terlalu besar.', 413);
    $kv = [];
    foreach ($spec['fields'] as $k => $f) $kv[$k] = trim((string)($_POST['f_' . $k] ?? ''));
    [$data, $err] = wa_validasi($spec, $kv);
    if ($err) $gagal(implode("\n", $err));
    $f = $_FILES['file'] ?? null;
    $adaFile = $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    if ($f && !$adaFile && ($f['error'] ?? 0) !== UPLOAD_ERR_NO_FILE) $gagal('Lampiran terlalu besar atau gagal diterima.');
    if ($adaFile && ($jn = (int)$f['size']) > wl_maks_byte()) $gagal('Lampiran melebihi ' . (int)(wl_maks_byte() / 1048576) . ' MB.');

    $pelaku = ['nama' => (string)($tok['nama'] ?: $tok['nomor']), 'nomor' => (string)$tok['nomor']];
    $lock = fopen(DATA_DIR . '/wa.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    try {
        $t = wl_token_ambil($p, (string)$tok['token']);
        if (!$t || wl_token_status($t) !== 'ok') $gagal('Formulir ini sudah dikirim atau kedaluwarsa.', 409);
        $db = load_db();
        try {
            $detail = wa_simpan($db, $cmd, $data, $pelaku, '');
        } catch (RuntimeException $e) {
            $gagal($e->getMessage());
        }
        save_db($p, $db);
        acc_sweep_nanti(); // permintaan ACC ke pimpinan setelah respons selesai
        $p->prepare('UPDATE wa_upload SET dipakai = 1 WHERE token = ?')->execute([$t['token']]);
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
    wa_log("{$pelaku['nomor']} ({$pelaku['nama']}): form $cmd OK");

    $id = preg_match('/^ID: (\S+)/mu', $detail, $m) ? $m[1] : '';
    $lampOk = false; $lampErr = '';
    if ($adaFile && $id !== '') {
        $tmp = DATA_DIR . '/up_' . bin2hex(random_bytes(6));
        if (move_uploaded_file($f['tmp_name'], $tmp)) {
            try {
                $t2 = wl_token_buat($p, WA_MODUL_DATA[$cmd][0], $id, (string)$tok['modul'], wa_judul_data($cmd, $data), $pelaku['nomor'], $pelaku['nama']);
                $h = wl_simpan($p, wl_token_ambil($p, $t2), $tmp, (string)$f['name'], 'tautan');
                if ($h['ok']) $lampOk = true; else $lampErr = (string)$h['error'];
            } catch (Throwable $e) { $lampErr = 'Gagal menyimpan lampiran.'; }
            @unlink($tmp);
        } else $lampErr = 'Gagal menerima lampiran.';
    }
    try {
        wa_kirim($pelaku['nomor'], wa_balasan_tersimpan($p, $cmd, $detail, $data, $pelaku, !$lampOk));
    } catch (Throwable $e) { wa_log('FORM balasan gagal: ' . $e->getMessage()); }
    u_json(['ok' => true, 'id' => $id, 'lampiran_error' => $lampErr]);
}

/** Tautan update kegiatan (bukti penugasan) & upload laporan, dikirim ke anggota yang ditugaskan. */
function u_kegiatan(PDO $p, array $tok): never
{
    $jenis = (string)$tok['jenis']; // 'update' | 'laporan'
    $laporan = $jenis === 'laporan';
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $st = wl_token_status($tok);

    if ($post) {
        $gagal = static function (string $m, int $c = 400): never { u_json(['ok' => false, 'error' => $m], $c); };
        if ($st === 'dipakai') $gagal('Laporan sudah dikirim lewat tautan ini.', 409);
        if ($st === 'kedaluwarsa') $gagal('Tautan sudah tidak berlaku. Minta pengiriman ulang info tugas ke admin.', 410);
        $link = $laporan ? trim((string)($_POST['link'] ?? '')) : '';
        if ($link !== '') {
            if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $link)) $link = 'https://' . $link;
            $h = wl_simpan_kegiatan($p, $tok, null, '', $link);
        } else {
            $f = $_FILES['file'] ?? null;
            $batas = (int)ini_parse_quantity((string)ini_get('post_max_size'));
            if (!$f && $batas > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $batas) $gagal('File terlalu besar.', 413);
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $kode = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
                $gagal(in_array($kode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'File terlalu besar.' : ($laporan ? 'Pilih file PDF atau isi link laporan.' : 'Pilih foto atau PDF terlebih dahulu.'));
            }
            $tmp = DATA_DIR . '/up_' . bin2hex(random_bytes(6));
            if (!move_uploaded_file($f['tmp_name'], $tmp)) $gagal('Gagal menerima file di server.', 500);
            $h = wl_simpan_kegiatan($p, $tok, $tmp, (string)$f['name'], null);
            @unlink($tmp);
        }
        if (!$h['ok']) $gagal((string)$h['error']);
        wa_log("{$tok['nomor']} ({$tok['nama']}): $jenis {$tok['rec_id']} OK → {$h['status']}");
        u_json(['ok' => true, 'name' => $h['name'], 'status' => $h['status']]);
    }

    $judul = $laporan ? 'Upload Laporan' : 'Update Kegiatan';
    if ($st === 'dipakai') u_status_halaman('✅', 'Laporan sudah terkirim', 'Laporan untuk kegiatan ' . $tok['rec_id'] . ' sudah diterima lewat tautan ini. Terima kasih.');
    if ($st === 'kedaluwarsa') u_status_halaman('⌛', 'Tautan tidak berlaku', 'Masa berlaku tautan sudah habis atau sudah diganti tautan baru. Minta admin mengirim ulang info tugas.', 410);

    $k = null;
    foreach (load_db()['kegiatan'] ?? [] as $r) if (($r['id'] ?? '') === (string)$tok['rec_id']) { $k = $r; break; }
    if (!$k) u_status_halaman('⚠️', 'Kegiatan tidak ditemukan', 'Kegiatan ' . $tok['rec_id'] . ' sudah tidak ada di dashboard.', 404);
    if ($e = wl_kegiatan_boleh($k, $jenis)) u_status_halaman('ℹ️', $judul . ' belum bisa dilakukan', $e);

    $a = (string)($k['tanggal'] ?? ''); $b = (string)($k['tanggal_selesai'] ?? '');
    $tgl = ($b !== '' && substr($b, 0, 10) > substr($a, 0, 10)) ? wa_tgl_panjang($a) . ' s.d. ' . wa_tgl_panjang($b) : wa_tgl_panjang($a);
    $maksMb = $laporan ? 25 : (int)(wl_maks_byte() / 1048576);
    $info = '<div class="info"><span class="mut">' . u_h((string)$tok['rec_id']) . ' · ' . u_h((string)($k['status_pelaksanaan'] ?? 'Draft')) . '</span>'
        . '<b>' . u_h((string)$k['nama']) . '</b><span class="mut">' . u_h($tgl) . ((string)($k['waktu'] ?? '') !== '' ? ' · ' . u_h((string)$k['waktu']) . ' WIB' : '')
        . ((string)($k['lokasi'] ?? '') !== '' ? '<br>' . u_h((string)$k['lokasi']) : '') . '</span></div>';

    if ($laporan) {
        $form = '<p class="mut">Upload laporan penugasan (PDF, maks. ' . $maksMb . ' MB) <b>atau</b> isi link laporan (mis. Google Drive). Setelah terkirim, kegiatan otomatis berstatus <b>Selesai</b>. Tautan ini hanya bisa dipakai sekali.</p>'
            . '<label class="pick" id="pick"><input type="file" id="file" accept="application/pdf,.pdf"><div class="big">📄</div><div id="lbl">Ketuk untuk memilih file PDF</div></label>'
            . '<label class="fl" for="link">Atau link laporan</label><input class="in" type="url" id="link" placeholder="https://..." style="margin-bottom:12px">'
            . '<button class="btn" id="kirim" type="submit">Kirim Laporan</button>';
    } else {
        $form = '<p class="mut">Upload foto/dokumen sebagai bukti bahwa Anda sedang bertugas (JPG, PNG atau PDF, maks. ' . $maksMb . ' MB per file). Setelah terkirim, kegiatan otomatis berstatus <b>Dalam Pelaksanaan</b>. Boleh dikirim beberapa kali.</p>'
            . '<label class="pick" id="pick"><input type="file" id="file" accept="image/*,application/pdf" multiple><div class="big">📷</div><div id="lbl">Ketuk untuk memilih foto atau PDF</div><div class="mut">Foto otomatis dikompres</div></label>'
            . '<button class="btn" id="kirim" type="submit" disabled>Kirim Update</button>';
    }
    $isi = '<h1>' . $judul . '</h1><div class="mut">Dashboard BASARNAS · ' . u_h((string)($tok['nama'] ?: $tok['nomor'])) . '</div>' . $info
        . '<form id="f" method="post" enctype="multipart/form-data">' . $form . '</form><div class="msg" id="msg"></div>'
        . '<script>
(function(){
  var LAP=' . ($laporan ? 'true' : 'false') . ', MAKS=' . $maksMb . '*1048576, SISI=' . WL_SISI_MAKS_PX . ', f=document.getElementById("f"), inp=document.getElementById("file"),
      lbl=document.getElementById("lbl"), btn=document.getElementById("kirim"), msg=document.getElementById("msg"), lnk=document.getElementById("link"), items=[], awal=lbl.textContent;
  function say(t,c){msg.className="msg "+(c||"");msg.textContent=t;}
  function ukuran(b){return b>1048576?(b/1048576).toFixed(1)+" MB":Math.max(1,Math.round(b/1024))+" KB";}
  function kompres(file){
    return new Promise(function(res){
      if(!/^image\\/(jpeg|png|webp)$/.test(file.type)) return res(file);
      var img=new Image(), u=URL.createObjectURL(file);
      img.onload=function(){
        var w=img.naturalWidth,h=img.naturalHeight,s=Math.min(1,SISI/Math.max(w,h)),c=document.createElement("canvas");
        c.width=Math.max(1,Math.round(w*s)); c.height=Math.max(1,Math.round(h*s));
        var x=c.getContext("2d"); x.fillStyle="#fff"; x.fillRect(0,0,c.width,c.height); x.drawImage(img,0,0,c.width,c.height);
        c.toBlob(function(b){URL.revokeObjectURL(u); res(b&&b.size<file.size?b:file);},"image/jpeg",0.72);
      };
      img.onerror=function(){URL.revokeObjectURL(u);res(file);};
      img.src=u;
    });
  }
  inp.addEventListener("change",function(){
    items=[]; if(!LAP) btn.disabled=true; say("");
    var fl=Array.prototype.slice.call(inp.files||[]); if(!fl.length){lbl.textContent=awal;return;}
    lbl.textContent="Memproses…";
    Promise.all(fl.map(function(file){return kompres(file).then(function(b){return {b:b,n:(b!==file?file.name.replace(/\\.[A-Za-z0-9]+$/,"")+".jpg":file.name)};});})).then(function(r){
      var besar=r.filter(function(x){return x.b.size>MAKS;});
      if(besar.length){lbl.textContent=awal; say("File terlalu besar: "+besar[0].n+" ("+ukuran(besar[0].b.size)+"). Maksimal "+(MAKS/1048576)+" MB.","bad"); return;}
      items=r; lbl.textContent=r.length+" file dipilih"+(r.length===1?" · "+r[0].n+" ("+ukuran(r[0].b.size)+")":""); btn.disabled=false;
    });
  });
  function kirim(fd){
    return fetch(location.href,{method:"POST",body:fd}).then(function(r){return r.json().catch(function(){return{ok:false,error:"Respons server tidak dikenal"};});});
  }
  f.addEventListener("submit",function(e){
    e.preventDefault();
    var link=lnk?lnk.value.trim():"";
    if(LAP&&!items.length&&!link){say("Pilih file PDF atau isi link laporan.","bad");return;}
    var label=btn.textContent; btn.disabled=true; btn.textContent="Mengirim…"; say("");
    var jobs=[];
    if(LAP){var fd=new FormData(); if(items.length&&!link) fd.append("file",items[0].b,items[0].n); else fd.append("link",link); jobs=[fd];}
    else jobs=items.map(function(it){var fd=new FormData(); fd.append("file",it.b,it.n); return fd;});
    var ok=0, galat="";
    jobs.reduce(function(pr,fd){return pr.then(function(){return kirim(fd).then(function(j){if(j.ok)ok++; else galat=j.error||"Gagal mengirim.";});});},Promise.resolve())
    .then(function(){
      if(ok&&!galat){f.style.display="none"; say(LAP?"✅ Laporan terkirim. Kegiatan berstatus Selesai. Terima kasih.":"✅ "+ok+" file terkirim. Kegiatan berstatus Dalam Pelaksanaan. Anda bisa menutup halaman ini atau mengirim bukti lain.","ok");
        if(!LAP){f.style.display="";items=[];inp.value="";lbl.textContent=awal;btn.textContent=label;btn.disabled=true;}}
      else{btn.disabled=false; btn.textContent=label; say((ok?ok+" file terkirim, sisanya gagal: ":"")+galat,"bad");}
    }).catch(function(){btn.disabled=false; btn.textContent=label; say("Koneksi gagal. Coba lagi.","bad");});
  });
})();
</script>';
    u_halaman($judul . ' ' . $tok['rec_id'], $isi);
}

try {
    $token = u_token();
    if ($token === '' || strlen($token) > 16) u_status_halaman('⚠️', 'Tautan tidak valid', 'Periksa kembali tautan upload dari pesan WhatsApp.', 404);
    $p = pdo();
    $tok = wl_token_ambil($p, $token);
    if (!$tok) u_status_halaman('⚠️', 'Tautan tidak dikenal', 'Tautan ini tidak ditemukan. Salin ulang tautan dari pesan WhatsApp.', 404);

    if (($tok['jenis'] ?? '') === 'sprin') { // unduhan Sprin untuk anggota bertugas (juga dipakai gateway WhatsApp untuk mengambil lampiran)
        if ((int)$tok['kedaluwarsa'] < time()) u_status_halaman('⏱️', 'Tautan kedaluwarsa', 'Minta admin mengirim ulang info tugas.', 410);
        $kg = null;
        foreach (load_db()['kegiatan'] ?? [] as $r) if (($r['id'] ?? '') === (string)$tok['rec_id']) { $kg = $r; break; }
        $fid = (string)($kg['sprin']['fid'] ?? '');
        $path = UPLOAD_DIR . '/' . $fid;
        if (!preg_match('/^[A-Za-z0-9]{3,48}$/', $fid) || !is_file($path) || !str_starts_with((string)@file_get_contents($path, false, null, 0, 5), '%PDF'))
            u_status_halaman('⚠️', 'Sprin tidak ditemukan', 'File Sprin belum diupload atau sudah dihapus.', 404);
        $nm = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string)($kg['sprin']['name'] ?? 'Sprin.pdf')) ?: 'Sprin.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nm . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    if (($tok['jenis'] ?? 'lampiran') === 'form') u_form($p, $tok);
    if (in_array($tok['jenis'] ?? '', WL_KEGIATAN_JENIS, true)) u_kegiatan($p, $tok);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    /* ---------------- POST: terima file ---------------- */
    if ($method === 'POST') {
        $ajax = isset($_POST['ajax']);
        $fail = static function (string $m, int $c = 400) use ($ajax): never {
            if ($ajax) u_json(['ok' => false, 'error' => $m], $c);
            u_status_halaman('⚠️', 'Lampiran belum terkirim', $m, $c);
        };
        $st = wl_token_status($tok);
        if ($st === 'dipakai') $fail('Lampiran untuk data ini sudah terkirim (maksimal 1 foto/file).', 409);
        if ($st === 'kedaluwarsa') $fail('Tautan sudah kedaluwarsa. Minta data diinput ulang atau hubungi admin.', 410);
        $f = $_FILES['file'] ?? null;
        $batas = (int)ini_parse_quantity((string)ini_get('post_max_size'));
        if (!$f && $batas > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $batas) $fail('File terlalu besar.', 413);
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $kode = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            $fail(in_array($kode, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'File terlalu besar.' : 'Pilih foto atau PDF terlebih dahulu.');
        }
        $tmp = DATA_DIR . '/up_' . bin2hex(random_bytes(6));
        if (!move_uploaded_file($f['tmp_name'], $tmp)) $fail('Gagal menerima file di server.', 500);
        $hasil = wl_simpan($p, $tok, $tmp, (string)$f['name'], 'tautan');
        @unlink($tmp); // sisa jika simpan gagal
        if (!$hasil['ok']) $fail((string)$hasil['error']);
        $ket = $hasil['meta']['name'] . ' (' . max(1, (int)round($hasil['meta']['size'] / 1024)) . ' KB)';
        if ($ajax) u_json(['ok' => true, 'name' => $hasil['meta']['name'], 'size' => $hasil['meta']['size']]);
        u_status_halaman('✅', 'Lampiran terkirim', $ket . ' sudah masuk ke ' . $tok['rec_id'] . '. Anda bisa menutup halaman ini.');
    }

    /* ---------------- GET: tampilkan formulir ---------------- */
    $st = wl_token_status($tok);
    if ($st === 'dipakai') u_status_halaman('✅', 'Lampiran sudah terkirim', 'Data ' . $tok['rec_id'] . ' sudah memiliki lampiran lewat tautan ini (maksimal 1 foto/file).');
    if ($st === 'kedaluwarsa') u_status_halaman('⌛', 'Tautan kedaluwarsa', 'Masa berlaku tautan sudah habis. Hubungi admin untuk lampiran data ' . $tok['rec_id'] . '.', 410);

    $maksMb = (int)(wl_maks_byte() / 1048576);
    $isi = '<h1>Upload Lampiran</h1><div class="mut">Dashboard BASARNAS · lewat WhatsApp</div>'
        . '<div class="info"><span class="mut">' . u_h((string)$tok['modul']) . ' · ' . u_h((string)$tok['rec_id']) . '</span>'
        . '<b>' . u_h((string)$tok['judul']) . '</b></div>'
        . '<form id="f" method="post" enctype="multipart/form-data">'
        . '<label class="pick" id="pick"><input type="file" name="file" id="file" accept="image/*,application/pdf">'
        . '<div class="big">📷</div><div id="lbl">Ketuk untuk memilih foto atau PDF</div><div class="mut">Maksimal 1 foto/file · ' . $maksMb . ' MB</div>'
        . '<img id="prev" alt=""></label>'
        . '<button class="btn" id="kirim" type="submit" disabled>Kirim Lampiran</button></form>'
        . '<div class="msg" id="msg"></div>'
        . '<p class="mut" style="margin-top:14px">Foto otomatis dikompres agar hemat penyimpanan.</p>'
        . '<script>
(function(){
  var MAKS=' . $maksMb . '*1048576, SISI=' . WL_SISI_MAKS_PX . ', f=document.getElementById("f"), inp=document.getElementById("file"),
      lbl=document.getElementById("lbl"), prev=document.getElementById("prev"), btn=document.getElementById("kirim"), msg=document.getElementById("msg"), blob=null, nama="";
  function say(t,c){msg.className="msg "+(c||"");msg.textContent=t;}
  function ukuran(b){return b>1048576?(b/1048576).toFixed(1)+" MB":Math.max(1,Math.round(b/1024))+" KB";}
  function kompres(file){
    return new Promise(function(res){
      if(!/^image\\/(jpeg|png|webp)$/.test(file.type)) return res(file);
      var img=new Image(), u=URL.createObjectURL(file);
      img.onload=function(){
        var w=img.naturalWidth,h=img.naturalHeight,s=Math.min(1,SISI/Math.max(w,h)),c=document.createElement("canvas");
        c.width=Math.max(1,Math.round(w*s)); c.height=Math.max(1,Math.round(h*s));
        var x=c.getContext("2d"); x.fillStyle="#fff"; x.fillRect(0,0,c.width,c.height); x.drawImage(img,0,0,c.width,c.height);
        c.toBlob(function(b){URL.revokeObjectURL(u); res(b&&b.size<file.size?b:file);},"image/jpeg",0.72);
      };
      img.onerror=function(){URL.revokeObjectURL(u);res(file);};
      img.src=u;
    });
  }
  inp.addEventListener("change",function(){
    blob=null; btn.disabled=true; prev.style.display="none"; say("");
    var file=inp.files&&inp.files[0]; if(!file) return;
    nama=file.name; lbl.textContent="Memproses…";
    kompres(file).then(function(b){
      if(b.size>MAKS){lbl.textContent="Ketuk untuk memilih foto atau PDF"; say("File terlalu besar ("+ukuran(b.size)+"). Maksimal ' . $maksMb . ' MB.","bad"); return;}
      blob=b; if(b!==file) nama=nama.replace(/\\.[A-Za-z0-9]+$/,"")+".jpg";
      lbl.textContent=nama+" · "+ukuran(b.size);
      if(/^image\\//.test(b.type)){prev.src=URL.createObjectURL(b);prev.style.display="block";}
      btn.disabled=false;
    });
  });
  f.addEventListener("submit",function(e){
    e.preventDefault(); if(!blob) return;
    var fd=new FormData(); fd.append("ajax","1"); fd.append("file",blob,nama);
    btn.disabled=true; btn.textContent="Mengirim…"; say("");
    fetch(location.href,{method:"POST",body:fd}).then(function(r){return r.json().catch(function(){return{ok:false,error:"Respons server tidak dikenal"};});})
    .then(function(j){
      if(j.ok){document.getElementById("f").style.display="none"; say("✅ Lampiran terkirim: "+j.name+". Anda bisa menutup halaman ini.","ok");}
      else{btn.disabled=false; btn.textContent="Kirim Lampiran"; say(j.error||"Gagal mengirim.","bad");}
    }).catch(function(){btn.disabled=false; btn.textContent="Kirim Lampiran"; say("Koneksi gagal. Coba lagi.","bad");});
  });
})();
</script>';
    u_halaman('Upload Lampiran ' . $tok['rec_id'], $isi);
} catch (Throwable $e) {
    error_log('[basarnas-u] ' . $e->getMessage());
    u_status_halaman('⚠️', 'Terjadi kesalahan', 'Server tidak dapat memproses permintaan. Coba lagi beberapa saat.', 500);
}
