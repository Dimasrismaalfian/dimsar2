<?php
/**
 * Pustaka lampiran WhatsApp (dipakai wa_webhook.php dan u.php).
 *
 * Alur:
 *  1. Setelah data tersimpan lewat WhatsApp, bot membuat "token upload" (tabel wa_upload) untuk data tersebut
 *     dan mengirim tautan  <BASE>/u.php/<TOKEN>  .
 *  2. Lampiran (1 foto/PDF per token) masuk lewat tautan itu ATAU dikirim langsung ke chat WA bot
 *     (pesan terpisah tanpa teks) dalam WA_UPLOAD_JENDELA_MENIT menit sejak data tersimpan.
 *  3. File disimpan di data/uploads (sama seperti file hasil/bukti kegiatan) dan metadatanya ditambahkan ke
 *     kolom `lampiran_files` pada data terkait. Dashboard menampilkannya di halaman detail.
 *
 * Wajib sudah memuat lib.php dan wa_config.php sebelum file ini.
 */
declare(strict_types=1);

const WL_JENDELA_MENIT_BAWAAN = 60;     // jendela kirim lampiran langsung di chat WA
const WL_LINK_HARI_BAWAAN     = 7;      // masa berlaku tautan upload
const WL_MAKS_MB_BAWAAN       = 10;     // ukuran maksimum file
const WL_SISI_MAKS_PX         = 1600;   // foto lebih besar dari ini diperkecil
const WL_KUALITAS_JPEG        = 75;

function wl_jendela_menit(): int { return defined('WA_UPLOAD_JENDELA_MENIT') ? (int)WA_UPLOAD_JENDELA_MENIT : WL_JENDELA_MENIT_BAWAAN; }
function wl_link_hari(): int     { return defined('WA_UPLOAD_LINK_HARI') ? (int)WA_UPLOAD_LINK_HARI : WL_LINK_HARI_BAWAAN; }
function wl_maks_byte(): int     { return (defined('WA_UPLOAD_MAKS_MB') ? (int)WA_UPLOAD_MAKS_MB : WL_MAKS_MB_BAWAAN) * 1048576; }

function wl_now(): string
{
    $bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $d = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    return $d->format('j') . ' ' . $bln[(int)$d->format('n') - 1] . ' ' . $d->format('Y') . ', ' . $d->format('H.i');
}

/* ============================== Token upload ============================== */

function wl_tabel(PDO $p): void
{
    $p->exec('CREATE TABLE IF NOT EXISTS wa_upload (
        token TEXT PRIMARY KEY,
        db_key TEXT NOT NULL,
        rec_id TEXT NOT NULL,
        modul TEXT,
        judul TEXT,
        nomor TEXT,
        nama TEXT,
        dibuat INTEGER NOT NULL,
        kedaluwarsa INTEGER NOT NULL,
        dipakai INTEGER NOT NULL DEFAULT 0
    )');
    $p->exec('CREATE INDEX IF NOT EXISTS wa_upload_nomor ON wa_upload (nomor, dibuat)');
    $kol = array_column($p->query('PRAGMA table_info(wa_upload)')->fetchAll(), 'name');
    if (!in_array('jenis', $kol, true)) $p->exec("ALTER TABLE wa_upload ADD COLUMN jenis TEXT NOT NULL DEFAULT 'lampiran'");
    if (!in_array('cmd', $kol, true)) $p->exec('ALTER TABLE wa_upload ADD COLUMN cmd TEXT');
}

function wl_base_url(): string
{
    if (defined('WA_BASE_URL') && trim((string)WA_BASE_URL) !== '') return rtrim((string)WA_BASE_URL, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function wl_link(string $token): string { return wl_base_url() . '/u.php/' . $token; }

/** $jenis: 'lampiran' (lampiran untuk data tersimpan) atau 'form' (formulir isian modul $cmd). */
function wl_token_buat(PDO $p, string $dbKey, string $recId, string $modul, string $judul, string $nomor, string $nama, string $jenis = 'lampiran', string $cmd = ''): string
{
    wl_tabel($p);
    $abjad = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa huruf/angka yang mudah tertukar (I, O, 0, 1)
    $now = time();
    $p->prepare('DELETE FROM wa_upload WHERE kedaluwarsa < ?')->execute([$now - 30 * 86400]);
    for ($try = 0; $try < 8; $try++) {
        $t = '';
        for ($i = 0; $i < 8; $i++) $t .= $abjad[random_int(0, strlen($abjad) - 1)];
        $st = $p->prepare('INSERT OR IGNORE INTO wa_upload (token, db_key, rec_id, modul, judul, nomor, nama, dibuat, kedaluwarsa, jenis, cmd) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$t, $dbKey, $recId, $modul, $judul, $nomor, $nama, $now, $now + wl_link_hari() * 86400, $jenis, $cmd]);
        if ($st->rowCount() === 1) return $t;
    }
    throw new RuntimeException('Gagal membuat token upload');
}

/** Ambil token (apa pun statusnya). */
function wl_token_ambil(PDO $p, string $token): ?array
{
    wl_tabel($p);
    $st = $p->prepare('SELECT * FROM wa_upload WHERE token = ?');
    $st->execute([$token]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Status token: 'ok' | 'dipakai' | 'kedaluwarsa'. */
function wl_token_status(array $t): string
{
    if ((int)$t['dipakai'] === 1) return 'dipakai';
    if ((int)$t['kedaluwarsa'] < time()) return 'kedaluwarsa';
    return 'ok';
}

/** Token terbaru milik nomor ini yang masih dalam jendela kirim-langsung dan belum dipakai. */
function wl_token_chat_terbaru(PDO $p, string $nomor): ?array
{
    wl_tabel($p);
    $st = $p->prepare('SELECT * FROM wa_upload WHERE jenis = \'lampiran\' AND nomor = ? AND dipakai = 0 AND dibuat >= ? AND kedaluwarsa >= ? ORDER BY dibuat DESC, rowid DESC LIMIT 1');
    $st->execute([$nomor, time() - wl_jendela_menit() * 60, time()]);
    $r = $st->fetch();
    return $r ?: null;
}

/* ============================== File ============================== */

/** Jenis file dari isi (bukan dari nama/ekstensi): pdf | jpeg | png | webp | ''. */
function wl_jenis(string $path): string
{
    $h = (string)@file_get_contents($path, false, null, 0, 16);
    if (str_starts_with($h, '%PDF')) return 'pdf';
    if (str_starts_with($h, "\xFF\xD8\xFF")) return 'jpeg';
    if (str_starts_with($h, "\x89PNG")) return 'png';
    if (str_starts_with($h, 'RIFF') && substr($h, 8, 4) === 'WEBP') return 'webp';
    return '';
}

const WL_MIME = ['pdf' => 'application/pdf', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

/**
 * Kecilkan foto (maks. WL_SISI_MAKS_PX, JPEG) bila GD tersedia. Mengembalikan jenis akhir ('jpeg' bila diubah).
 * PDF tidak diubah. Bila GD tidak ada / gagal, file dibiarkan apa adanya.
 */
function wl_kompres(string $path, string $jenis): string
{
    if ($jenis === 'pdf' || !function_exists('imagecreatetruecolor')) return $jenis;
    $buka = ['jpeg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$jenis] ?? '';
    if ($buka === '' || !function_exists($buka)) return $jenis;
    $info = @getimagesize($path);
    if (!$info) return $jenis;
    [$w, $h] = [(int)$info[0], (int)$info[1]];
    if ($w < 1 || $h < 1 || $w * $h > 60000000) return $jenis; // terlalu besar untuk diproses aman di memori
    $besar = max($w, $h) > WL_SISI_MAKS_PX || filesize($path) > 1500000 || $jenis !== 'jpeg';
    if (!$besar) return $jenis;
    $im = @$buka($path);
    if (!$im) return $jenis;
    if ($jenis === 'jpeg' && function_exists('exif_read_data')) { // putar sesuai orientasi kamera
        $ex = @exif_read_data($path);
        $o = (int)($ex['Orientation'] ?? 1);
        $sudut = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($sudut !== 0 && function_exists('imagerotate')) {
            $r = @imagerotate($im, $sudut, 0);
            if ($r) { $im = $r; [$w, $h] = [imagesx($im), imagesy($im)]; }
        }
    }
    $skala = min(1.0, WL_SISI_MAKS_PX / max($w, $h));
    $nw = max(1, (int)round($w * $skala));
    $nh = max(1, (int)round($h * $skala));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // latar putih untuk PNG transparan
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, imagesx($im), imagesy($im));
    $tmp = $path . '.k';
    $ok = imagejpeg($out, $tmp, WL_KUALITAS_JPEG);
    if (!$ok || !is_file($tmp) || filesize($tmp) === 0) { @unlink($tmp); return $jenis; }
    if (filesize($tmp) >= filesize($path) && $jenis === 'jpeg') { @unlink($tmp); return $jenis; } // hasil tidak lebih kecil: pakai asli
    rename($tmp, $path);
    return 'jpeg';
}

function wl_nama_aman(string $n, string $jenis): string
{
    $n = basename(str_replace('\\', '/', $n));
    $n = preg_replace('/[\x00-\x1F\x7F<>:"|?*]+/u', '', $n) ?? '';
    $n = trim($n);
    $ext = ['pdf' => 'pdf', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'][$jenis] ?? 'bin';
    $dasar = preg_replace('/\.[A-Za-z0-9]{1,5}$/', '', $n) ?? $n;
    if ($dasar === '') $dasar = 'lampiran';
    if (mb_strlen($dasar) > 80) $dasar = mb_substr($dasar, 0, 80);
    return $dasar . '.' . $ext;
}

/* ============================== Simpan ke data ============================== */

/**
 * Pasang file $tmp (sudah ada di server) sebagai lampiran data yang ditunjuk token, lalu tandai token terpakai.
 * $via: 'tautan' | 'chat'.  $kunci=false jika pemanggil sudah memegang kunci data/wa.lock.
 * Kembalikan ['ok'=>bool, 'error'=>string, 'meta'=>array, 'token'=>array].
 */
function wl_simpan(PDO $p, array $tok, string $tmp, string $namaAsli, string $via, bool $kunci = true): array
{
    $gagal = static function (string $m): array { return ['ok' => false, 'error' => $m]; };

    if (!is_file($tmp)) return $gagal('File tidak ditemukan.');
    if (filesize($tmp) === 0) return $gagal('File kosong.');
    if (filesize($tmp) > wl_maks_byte()) return $gagal('File melebihi ' . (int)(wl_maks_byte() / 1048576) . ' MB.');
    $jenis = wl_jenis($tmp);
    if ($jenis === '') return $gagal('Format tidak didukung. Kirim foto JPG/PNG atau file PDF.');

    $lock = null;
    if ($kunci) {
        $lock = fopen(DATA_DIR . '/wa.lock', 'c');
        if ($lock) flock($lock, LOCK_EX);
    }
    try {
        // Periksa ulang token di dalam kunci supaya satu token tidak bisa dipakai dua kali bersamaan.
        $t = wl_token_ambil($p, (string)$tok['token']);
        if (!$t) return $gagal('Tautan tidak dikenal.');
        $s = wl_token_status($t);
        if ($s === 'dipakai') return $gagal('Lampiran untuk data ini sudah terkirim (maksimal 1 foto/file).');
        if ($s === 'kedaluwarsa') return $gagal('Tautan sudah kedaluwarsa.');

        $db = load_db();
        $key = (string)$t['db_key'];
        $rows = $db[$key] ?? null;
        if (!is_array($rows)) return $gagal('Data tidak ditemukan.');
        $idx = null;
        foreach ($rows as $i => $r) {
            $id = is_object($r) ? ($r->id ?? '') : ($r['id'] ?? '');
            if ((string)$id === (string)$t['rec_id']) { $idx = $i; break; }
        }
        if ($idx === null) return $gagal('Data ' . $t['rec_id'] . ' sudah tidak ada di dashboard (mungkin dihapus).');

        $jenis = wl_kompres($tmp, $jenis);
        clearstatcache(true, $tmp);
        $fid = 'W' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(4));
        $nama = wl_nama_aman($namaAsli, $jenis);
        $ukuran = (int)filesize($tmp);
        pdo(); // memastikan folder data/uploads ada
        $dest = UPLOAD_DIR . '/' . $fid;
        if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) return $gagal('Gagal menyimpan file di server.');
        @chmod($dest, 0664);
        file_put_contents($dest . '.json', json_encode(['name' => $nama, 'size' => $ukuran], JSON_OUT));

        $meta = ['fid' => $fid, 'name' => $nama, 'size' => $ukuran, 'type' => WL_MIME[$jenis], 'uploaded' => wl_now(),
                 'oleh' => (string)($t['nama'] ?: $t['nomor']), 'sumber' => 'WhatsApp', 'via' => $via];

        if (is_object($rows[$idx])) { // data yang disimpan sebagai objek JSON (mis. Surat Keluar)
            $lama = isset($rows[$idx]->lampiran_files) && is_array($rows[$idx]->lampiran_files) ? $rows[$idx]->lampiran_files : [];
            $rows[$idx]->lampiran_files = array_merge($lama, [(object)$meta]);
        } else {
            $lama = isset($rows[$idx]['lampiran_files']) && is_array($rows[$idx]['lampiran_files']) ? $rows[$idx]['lampiran_files'] : [];
            $rows[$idx]['lampiran_files'] = array_merge($lama, [$meta]);
        }
        $db[$key] = $rows;
        try {
            save_db($p, $db);
        } catch (Throwable $e) {
            @unlink($dest);
            @unlink($dest . '.json');
            throw $e;
        }
        $p->prepare('UPDATE wa_upload SET dipakai = 1 WHERE token = ?')->execute([$t['token']]);
        return ['ok' => true, 'meta' => $meta, 'token' => $t];
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
}

/**
 * Unduh file dari URL (lampiran yang dikirim gateway WA) ke $dest. Mengembalikan null bila berhasil, atau pesan galat.
 * Hanya http(s) ke alamat publik (bukan jaringan lokal), tanpa mengikuti pengalihan.
 */
function wl_unduh(string $url, string $dest): ?string
{
    $u = parse_url($url);
    if (!$u || !in_array(strtolower((string)($u['scheme'] ?? '')), ['http', 'https'], true) || empty($u['host'])) return 'Alamat lampiran tidak valid.';
    $ip = gethostbyname((string)$u['host']);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return 'Alamat lampiran ditolak.';
    $maks = wl_maks_byte();
    $fh = @fopen($dest, 'wb');
    if (!$fh) return 'Gagal menyiapkan penyimpanan sementara.';
    $err = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $ditulis = 0;
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($c, string $d) use ($fh, &$ditulis, $maks) {
                $ditulis += strlen($d);
                if ($ditulis > $maks) return 0; // hentikan: terlalu besar
                return fwrite($fh, $d);
            },
        ]);
        $ok = curl_exec($ch);
        $kode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($ok === false) $err = $ditulis > $maks ? 'File melebihi ' . (int)($maks / 1048576) . ' MB.' : 'Gagal mengunduh lampiran dari WhatsApp.';
        elseif ($kode < 200 || $kode >= 300) $err = 'Gagal mengunduh lampiran (kode ' . $kode . ').';
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 40, 'follow_location' => 0]]);
        $in = @fopen($url, 'rb', false, $ctx);
        if (!$in) $err = 'Gagal mengunduh lampiran dari WhatsApp.';
        else {
            $n = stream_copy_to_stream($in, $fh, $maks + 1);
            fclose($in);
            if ($n === false || $n > $maks) $err = 'File melebihi ' . (int)($maks / 1048576) . ' MB.';
        }
    }
    fclose($fh);
    if ($err !== null) { @unlink($dest); return $err; }
    return null;
}

/* ============================== Tautan Update Kegiatan & Upload Laporan ==============================
 * Dikirim ke tiap anggota bertugas (terpisah dari pesan penugasan). Token per anggota supaya tercatat siapa yang mengupload.
 *  - jenis 'update'  : upload bukti/foto penugasan (JPG/PNG/PDF, boleh berkali-kali) → status Dalam Pelaksanaan
 *  - jenis 'laporan' : upload laporan PDF atau kirim link laporan (sekali pakai)        → status Selesai
 * Aturan statusnya sama dengan upload di dashboard (uploadBukti / uploadLaporanSelesai).
 */
const WL_KEGIATAN_JENIS = ['update', 'laporan']; // jenis 'sprin' (unduhan Sprin) ditangani terpisah di u.php
const WL_LAPORAN_MAKS_BYTE = 25 * 1048576;
const WL_MASA_KEGIATAN_HARI = 30; // berlaku sampai 30 hari setelah kegiatan berakhir

/** Cabut tautan update/laporan lama (belum dipakai) milik nomor ini untuk kegiatan ini, sebelum membuat yang baru. */
function wl_token_kegiatan_cabut(PDO $p, string $kegiatanId, string $nomor): void
{
    wl_tabel($p);
    $p->prepare("UPDATE wa_upload SET kedaluwarsa = 0 WHERE db_key = 'kegiatan' AND rec_id = ? AND nomor = ? AND jenis IN ('update','laporan') AND dipakai = 0")
      ->execute([$kegiatanId, $nomor]);
}

function wl_token_kegiatan(PDO $p, array $k, string $namaAnggota, string $nomor, string $jenis): string
{
    if (!in_array($jenis, [...WL_KEGIATAN_JENIS, 'sprin'], true)) throw new InvalidArgumentException('Jenis tautan tidak dikenal');
    $t = wl_token_buat($p, 'kegiatan', (string)$k['id'], 'Kegiatan/Tugas', (string)($k['nama'] ?? ''), $nomor, $namaAnggota, $jenis);
    $akhir = strtotime(substr((string)(($k['tanggal_selesai'] ?? '') ?: ($k['tanggal'] ?? '')), 0, 10)) ?: time();
    $exp = max(time(), $akhir) + WL_MASA_KEGIATAN_HARI * 86400;
    $p->prepare('UPDATE wa_upload SET kedaluwarsa = ? WHERE token = ?')->execute([$exp, $t]);
    return $t;
}

/** Samakan status data di modul terkait kegiatan (seperti syncModuleStatusFromKegiatan di dashboard). */
function wl_sync_modul(array &$db, array $k, string $statusPelaksanaan): void
{
    $lid = (string)($k['linked_record_id'] ?? ''); $jn = (string)($k['jenis'] ?? '');
    $target = ['Selesai' => 'Selesai', 'Dalam Pelaksanaan' => 'Berlangsung'][$statusPelaksanaan] ?? '';
    if ($lid === '' || $jn === '' || $target === '' || !isset($db[$jn]) || !is_array($db[$jn])) return;
    foreach ($db[$jn] as $i => $r) {
        $r = (array)$r;
        if (($r['id'] ?? '') === $lid && in_array($r['status'] ?? '', ['Direncanakan', 'Berlangsung'], true)) {
            if (is_object($db[$jn][$i])) $db[$jn][$i]->status = $target; else $db[$jn][$i]['status'] = $target;
            break;
        }
    }
}

/** Pemeriksaan status kegiatan untuk tautan ini. Mengembalikan pesan galat, atau null bila boleh melanjutkan. */
function wl_kegiatan_boleh(array $k, string $jenis): ?string
{
    if (($k['status_persetujuan'] ?? '') !== 'Disetujui') return 'Kegiatan ini belum disetujui.';
    $st = (string)($k['status_pelaksanaan'] ?? 'Draft');
    if ($jenis === 'update') {
        if ($st === 'Selesai') return 'Kegiatan ini sudah berstatus Selesai.';
        if ($st === 'Draft' && empty($k['sprin'])) return 'Sprin belum tersedia.';
        return null;
    }
    if ($st === 'Selesai') return 'Laporan sudah diterima, kegiatan ini sudah berstatus Selesai.';
    if ($st !== 'Dalam Pelaksanaan') return 'Kegiatan belum berstatus Dalam Pelaksanaan. Upload bukti/update kegiatan lebih dulu lewat tautan update.';
    return null;
}

/**
 * Simpan kiriman dari tautan update/laporan.
 *  $tmp  = file sementara (atau null bila $link dipakai); $link = tautan laporan (hanya jenis 'laporan').
 * Mengembalikan ['ok'=>true,'status'=>..., 'name'=>...] atau ['ok'=>false,'error'=>...].
 */
function wl_simpan_kegiatan(PDO $p, array $tok, ?string $tmp, string $namaAsli, ?string $link): array
{
    $gagal = static function (string $m): array { return ['ok' => false, 'error' => $m]; };
    $jenisTok = (string)($tok['jenis'] ?? '');
    if (!in_array($jenisTok, WL_KEGIATAN_JENIS, true)) return $gagal('Tautan tidak dikenal.');
    if ($link !== null) {
        if ($jenisTok !== 'laporan') return $gagal('Tautan ini hanya untuk upload file.');
        $u = @parse_url($link);
        if (!$u || !in_array(strtolower((string)($u['scheme'] ?? '')), ['http', 'https'], true) || empty($u['host']) || strlen($link) > 1000)
            return $gagal('Link tidak valid. Awali dengan http:// atau https://');
    } else {
        if ($tmp === null || !is_file($tmp)) return $gagal('File tidak ditemukan.');
        if (filesize($tmp) === 0) return $gagal('File kosong.');
        $jenis = wl_jenis($tmp);
        if ($jenisTok === 'laporan') {
            if ($jenis !== 'pdf') return $gagal('Laporan harus berformat PDF (atau kirim sebagai link).');
            if (filesize($tmp) > WL_LAPORAN_MAKS_BYTE) return $gagal('File melebihi 25 MB.');
        } else {
            if (!in_array($jenis, ['pdf', 'jpeg', 'png', 'webp'], true)) return $gagal('Format tidak didukung. Kirim foto JPG/PNG atau file PDF.');
            if (filesize($tmp) > wl_maks_byte()) return $gagal('File melebihi ' . (int)(wl_maks_byte() / 1048576) . ' MB.');
        }
    }

    $lock = fopen(DATA_DIR . '/wa.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);
    try {
        $t = wl_token_ambil($p, (string)$tok['token']);
        if (!$t) return $gagal('Tautan tidak dikenal.');
        $s = wl_token_status($t);
        if ($s === 'dipakai') return $gagal('Laporan sudah dikirim lewat tautan ini.');
        if ($s === 'kedaluwarsa') return $gagal('Tautan sudah tidak berlaku. Minta pengiriman ulang info tugas ke admin.');

        $db = load_db();
        $idx = null;
        foreach ($db['kegiatan'] ?? [] as $i => $r) if (($r['id'] ?? '') === (string)$t['rec_id']) { $idx = $i; break; }
        if ($idx === null) return $gagal('Kegiatan ' . $t['rec_id'] . ' sudah tidak ada di dashboard.');
        $k = $db['kegiatan'][$idx];
        if ($e = wl_kegiatan_boleh($k, $jenisTok)) return $gagal($e);

        $oleh = (string)($t['nama'] ?: $t['nomor']);
        $meta = null;
        $dest = null;
        if ($link !== null) {
            $meta = ['fid' => 'U' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(4)), 'name' => $link, 'link' => $link, 'size' => 0,
                     'type' => 'link', 'uploaded' => wl_now(), 'laporan' => true, 'oleh' => $oleh, 'sumber' => 'WhatsApp'];
        } else {
            $jenis = $jenisTok === 'laporan' ? 'pdf' : wl_kompres($tmp, wl_jenis($tmp));
            clearstatcache(true, $tmp);
            if ($jenisTok === 'update' && !in_array($jenis, ['pdf', 'jpeg', 'png'], true)) return $gagal('Format tidak didukung. Kirim foto JPG/PNG atau file PDF.');
            $fid = ($jenisTok === 'laporan' ? 'L' : 'B') . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(4));
            $nama = wl_nama_aman($namaAsli, $jenis);
            $ukuran = (int)filesize($tmp);
            pdo(); // memastikan folder data/uploads ada
            $dest = UPLOAD_DIR . '/' . $fid;
            if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) return $gagal('Gagal menyimpan file di server.');
            @chmod($dest, 0664);
            file_put_contents($dest . '.json', json_encode(['name' => $nama, 'size' => $ukuran], JSON_OUT));
            $meta = ['fid' => $fid, 'name' => $nama, 'size' => $ukuran, 'type' => WL_MIME[$jenis], 'uploaded' => wl_now(), 'oleh' => $oleh, 'sumber' => 'WhatsApp'];
            if ($jenisTok === 'laporan') $meta['laporan'] = true;
        }

        if ($jenisTok === 'laporan') {
            $k['hasil_files'] = array_merge((array)($k['hasil_files'] ?? []), [$meta]);
            $k['status_pelaksanaan'] = 'Selesai';
        } else {
            $k['bukti_files'] = array_merge((array)($k['bukti_files'] ?? []), [$meta]);
            if (($k['status_pelaksanaan'] ?? 'Draft') === 'Draft') $k['status_pelaksanaan'] = 'Dalam Pelaksanaan';
        }
        $k['diperbarui'] = wl_now();
        $db['kegiatan'][$idx] = $k;
        wl_sync_modul($db, $k, (string)$k['status_pelaksanaan']);
        try {
            save_db($p, $db);
        } catch (Throwable $e) {
            if ($dest) { @unlink($dest); @unlink($dest . '.json'); }
            throw $e;
        }
        if ($jenisTok === 'laporan') $p->prepare('UPDATE wa_upload SET dipakai = 1 WHERE token = ?')->execute([$t['token']]);
        if ($jenisTok === 'update') {
            // Update kegiatan sudah terisi -> kirim link upload laporan ke anggota ini (sekali; tidak dikirim ulang bila sudah ada tautan aktif/terpakai)
            try {
                $ada = $p->prepare("SELECT COUNT(*) FROM wa_upload WHERE db_key = 'kegiatan' AND rec_id = ? AND nomor = ? AND jenis = 'laporan' AND dipakai = 0 AND kedaluwarsa > ?");
                $ada->execute([(string)$k['id'], (string)$t['nomor'], time()]);
                if ((int)$ada->fetchColumn() === 0 && ($k['status_pelaksanaan'] ?? '') === 'Dalam Pelaksanaan') {
                    $tl = wl_token_kegiatan($p, $k, (string)($t['nama'] ?? ''), (string)$t['nomor'], 'laporan');
                    [$okL, $ketL] = wa_kirim_lampiran((string)$t['nomor'], wa_pesan_link_laporan($k, wl_link($tl)));
                    wa_log("LINK LAPORAN {$k['id']} -> {$t['nomor']}: " . ($okL ? 'terkirim' : 'gagal ' . $ketL));
                }
            } catch (Throwable $e) { wa_log('LINK LAPORAN GALAT: ' . $e->getMessage()); }
        }
        $hasilOk = ['ok' => true, 'status' => (string)$k['status_pelaksanaan'], 'name' => $meta['name'], 'jenis' => $jenisTok];
        $kNotif = $k; $olehNotif = $oleh;
    } finally {
        if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    }
    // Bukti penugasan sudah terinput -> beri tahu semua anggota yang bertugas (setelah lock dilepas; kegagalan kirim tidak menggagalkan upload)
    if ($jenisTok === 'update') {
        try { wl_notif_bukti($p, $kNotif, $olehNotif); } catch (Throwable $e) { wa_log('NOTIF BUKTI GALAT: ' . $e->getMessage()); }
    } elseif ($jenisTok === 'laporan') {
        try { wl_notif_selesai($p, $kNotif, $olehNotif); } catch (Throwable $e) { wa_log('NOTIF SELESAI GALAT: ' . $e->getMessage()); }
    }
    return $hasilOk;
}

/**
 * Notifikasi WhatsApp ke SEMUA anggota yang bertugas pada kegiatan $k bahwa bukti penugasan sudah terinput.
 * Dipanggil dari tautan WA (wl_simpan_kegiatan) dan dari dashboard (api.php?action=wa_notif_bukti).
 * Anti-spam: bila beberapa file masuk berurutan (WA mengirim satu permintaan per file), hanya 1 notifikasi
 * per kegiatan per WL_NOTIF_BUKTI_JEDA_DETIK. Mengembalikan ['terkirim'=>n,'total'=>n,'dilewati'=>bool,'hasil'=>[...]].
 */
const WL_NOTIF_BUKTI_JEDA_DETIK = 300;

/** Anggota yang bertugas pada kegiatan $k (baris Daftar Anggota). */
function wl_petugas(array $k): array
{
    $byId = [];
    foreach (load_db()['anggota'] ?? [] as $a) $byId[(string)($a['id'] ?? '')] = $a;
    $petugas = [];
    foreach ($k['anggota_ids'] ?? [] as $aid) if (isset($byId[$aid])) $petugas[] = $byId[$aid];
    return $petugas;
}

/**
 * Kirim tautan upload laporan ke SEMUA anggota bertugas yang belum punya tautan laporan (aktif/terpakai) untuk kegiatan ini.
 * Hanya bila kegiatan berstatus Dalam Pelaksanaan. Tidak mencabut tautan update.
 */
function wl_link_laporan_semua(PDO $p, array $k, array $petugas): int
{
    if (($k['status_pelaksanaan'] ?? '') !== 'Dalam Pelaksanaan') return 0;
    wl_tabel($p);
    $n = 0; $sudah = [];
    $ada = $p->prepare("SELECT COUNT(*) FROM wa_upload WHERE db_key = 'kegiatan' AND rec_id = ? AND nomor = ? AND jenis = 'laporan' AND dipakai = 0 AND kedaluwarsa > ?");
    foreach ($petugas as $ang) {
        $no = wa_nomor((string)($ang['whatsapp'] ?? ''));
        if ($no === '' || isset($sudah[$no])) continue;
        $sudah[$no] = true;
        $ada->execute([(string)$k['id'], $no, time()]);
        if ((int)$ada->fetchColumn() > 0) { wa_log("LINK LAPORAN {$k['id']} -> $no: dilewati (tautan laporan aktif sudah ada)"); continue; }
        $tl = wl_token_kegiatan($p, $k, (string)($ang['nama'] ?? ''), $no, 'laporan');
        [$okL, $ketL] = wa_kirim_lampiran($no, wa_pesan_link_laporan($k, wl_link($tl)));
        wa_log("LINK LAPORAN {$k['id']} -> $no: " . ($okL ? 'terkirim' : 'gagal ' . $ketL));
        if ($okL) $n++;
        usleep(500000);
    }
    return $n;
}

function wl_notif_bukti(PDO $p, array $k, string $oleh): array
{
    $p->exec('CREATE TABLE IF NOT EXISTS wa_notif_bukti (kegiatan_id TEXT PRIMARY KEY, waktu INTEGER NOT NULL)');
    $kid = (string)($k['id'] ?? '');
    if ($kid === '') return ['terkirim' => 0, 'total' => 0, 'dilewati' => true, 'hasil' => []];

    $petugas = wl_petugas($k);
    if (!$petugas) return ['terkirim' => 0, 'total' => 0, 'dilewati' => true, 'hasil' => []];
    if (($k['status_pelaksanaan'] ?? '') === 'Dalam Pelaksanaan') { // siklus baru: notifikasi "selesai" boleh dikirim lagi nanti
        $p->exec('CREATE TABLE IF NOT EXISTS wa_notif_selesai (kegiatan_id TEXT PRIMARY KEY, waktu INTEGER NOT NULL)');
        $p->prepare('DELETE FROM wa_notif_selesai WHERE kegiatan_id = ?')->execute([$kid]);
    }

    // Tautan upload laporan untuk semua anggota bertugas (sekali per anggota; tidak terkena jeda notifikasi)
    $kirimLink = static function () use ($p, $k, $petugas): void {
        try { wl_link_laporan_semua($p, $k, $petugas); } catch (Throwable $e) { wa_log('LINK LAPORAN SEMUA GALAT: ' . $e->getMessage()); }
    };

    // Klaim slot kirim secara atomik (hindari dobel saat beberapa file masuk bersamaan)
    $now = time();
    $p->prepare('INSERT OR IGNORE INTO wa_notif_bukti (kegiatan_id, waktu) VALUES (?, 0)')->execute([$kid]);
    $u = $p->prepare('UPDATE wa_notif_bukti SET waktu = ? WHERE kegiatan_id = ? AND waktu <= ?');
    $u->execute([$now, $kid, $now - WL_NOTIF_BUKTI_JEDA_DETIK]);
    if ($u->rowCount() === 0) { $kirimLink(); return ['terkirim' => 0, 'total' => count($petugas), 'dilewati' => true, 'hasil' => []]; }

    $a = (string)($k['tanggal'] ?? ''); $b = (string)($k['tanggal_selesai'] ?? '');
    $tgl = ($b !== '' && substr($b, 0, 10) > substr($a, 0, 10)) ? wa_tgl_panjang($a) . ' s.d. ' . wa_tgl_panjang($b) : wa_tgl_panjang($a);
    $jumlah = count((array)($k['bukti_files'] ?? []));
    $pesan = "*BUKTI KEGIATAN SUDAH TERINPUT*\n\n"
        . 'ID: ' . $kid . "\n"
        . 'Kegiatan: ' . ($k['nama'] ?? '-') . "\n"
        . 'Tanggal: ' . ($tgl !== '' ? $tgl : '-') . "\n"
        . 'Lokasi: ' . (trim((string)($k['lokasi'] ?? '')) ?: '-') . "\n"
        . 'Diinput oleh: ' . ($oleh !== '' ? $oleh : '-') . "\n"
        . 'Total bukti: ' . $jumlah . " file\n"
        . 'Status: ' . ($k['status_pelaksanaan'] ?? '-') . "\n\n"
        . 'Bukti penugasan sudah masuk ke dashboard. Terima kasih.';

    $hasil = []; $ok = 0; $dikirim = [];
    foreach ($petugas as $ang) {
        $no = wa_nomor((string)($ang['whatsapp'] ?? ''));
        if ($no === '') { $hasil[] = ['nama' => $ang['nama'] ?? '', 'ok' => false, 'ket' => 'Nomor WhatsApp belum diisi di Daftar Anggota']; continue; }
        if (isset($dikirim[$no])) continue; // nomor ganda
        $dikirim[$no] = true;
        [$okK, $ketK] = wa_kirim_lampiran($no, $pesan);
        if ($okK) $ok++;
        $hasil[] = ['nama' => $ang['nama'] ?? '', 'ok' => $okK, 'ket' => $ketK];
        usleep(500000);
    }
    if ($ok === 0) $p->prepare('UPDATE wa_notif_bukti SET waktu = 0 WHERE kegiatan_id = ?')->execute([$kid]); // gagal semua: boleh dicoba lagi
    $kirimLink(); // setelah notifikasi bukti: tautan laporan ke semua anggota bertugas
    wa_log("NOTIF BUKTI $kid: $ok/" . count($petugas) . ' terkirim (oleh ' . $oleh . ')');
    return ['terkirim' => $ok, 'total' => count($petugas), 'dilewati' => false, 'hasil' => $hasil];
}


/**
 * Notifikasi WhatsApp ke SEMUA anggota bertugas bahwa kegiatan sudah SELESAI (laporan terinput), lengkap dengan ID kegiatan.
 * Hanya sekali per kegiatan. Dipanggil dari tautan laporan WA dan dari dashboard (api.php?action=wa_notif_selesai).
 */
function wl_notif_selesai(PDO $p, array $k, string $oleh): array
{
    $kid = (string)($k['id'] ?? '');
    $petugas = $kid !== '' ? wl_petugas($k) : [];
    if (!$petugas || ($k['status_pelaksanaan'] ?? '') !== 'Selesai') return ['terkirim' => 0, 'total' => count($petugas), 'dilewati' => true, 'hasil' => []];
    $p->exec('CREATE TABLE IF NOT EXISTS wa_notif_selesai (kegiatan_id TEXT PRIMARY KEY, waktu INTEGER NOT NULL)');
    $ins = $p->prepare('INSERT OR IGNORE INTO wa_notif_selesai (kegiatan_id, waktu) VALUES (?, ?)');
    $ins->execute([$kid, time()]);
    if ($ins->rowCount() === 0) return ['terkirim' => 0, 'total' => count($petugas), 'dilewati' => true, 'hasil' => []];

    $a = (string)($k['tanggal'] ?? ''); $b = (string)($k['tanggal_selesai'] ?? '');
    $tgl = ($b !== '' && substr($b, 0, 10) > substr($a, 0, 10)) ? wa_tgl_panjang($a) . ' s.d. ' . wa_tgl_panjang($b) : wa_tgl_panjang($a);
    $pesan = "*KEGIATAN SELESAI*\n\n"
        . 'ID: ' . $kid . "\n"
        . 'Kegiatan: ' . ($k['nama'] ?? '-') . "\n"
        . 'Tanggal: ' . ($tgl !== '' ? $tgl : '-') . "\n"
        . 'Lokasi: ' . (trim((string)($k['lokasi'] ?? '')) ?: '-') . "\n"
        . 'Laporan diinput oleh: ' . ($oleh !== '' ? $oleh : '-') . "\n"
        . "Status: Selesai\n\n"
        . 'Laporan penugasan sudah masuk ke dashboard. Terima kasih atas tugasnya.';
    $hasil = []; $ok = 0; $sudah = [];
    foreach ($petugas as $ang) {
        $no = wa_nomor((string)($ang['whatsapp'] ?? ''));
        if ($no === '') { $hasil[] = ['nama' => $ang['nama'] ?? '', 'ok' => false, 'ket' => 'Nomor WhatsApp belum diisi di Daftar Anggota']; continue; }
        if (isset($sudah[$no])) continue;
        $sudah[$no] = true;
        [$okK, $ketK] = wa_kirim_lampiran($no, $pesan);
        if ($okK) $ok++;
        $hasil[] = ['nama' => $ang['nama'] ?? '', 'ok' => $okK, 'ket' => $ketK];
        usleep(500000);
    }
    if ($ok === 0) $p->prepare('DELETE FROM wa_notif_selesai WHERE kegiatan_id = ?')->execute([$kid]); // gagal semua: boleh dicoba lagi
    wa_log("NOTIF SELESAI $kid: $ok/" . count($petugas) . ' terkirim (oleh ' . $oleh . ')');
    return ['terkirim' => $ok, 'total' => count($petugas), 'dilewati' => false, 'hasil' => $hasil];
}
