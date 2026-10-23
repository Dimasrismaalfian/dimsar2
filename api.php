<?php
/**
 * API Dashboard BASARNAS.
 *   GET  api.php?action=load
 *   GET  api.php?action=sync&since=REV   (polling perubahan; data lengkap hanya jika revisi berubah)
 *   POST api.php?action=save        (body: JSON snapshot seluruh data)
 *   POST api.php?action=file_put    (multipart: fid, kid, file)
 *   GET  api.php?action=file_get&fid=...
 *   POST api.php?action=file_del&fid=...
 *   POST api.php?action=wa_tugas    (body: {id}) kirim info tugas + PDF Sprin ke WA anggota bertugas
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/wa_inti.php';
require_once __DIR__ . '/wa_acc.php';

function jsonOut(array $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_OUT);
    exit;
}
function failOut(int $code, string $msg): never { jsonOut(['ok' => false, 'error' => $msg], $code); }

function validFid(string $fid): bool { return (bool)preg_match('/^[A-Za-z0-9]{3,48}$/', $fid); }

/** Hanya PDF/PNG/JPEG (dikenali dari isi file) yang boleh ditampilkan langsung di browser. */
function safeMime(string $path): string
{
    $h = (string)@file_get_contents($path, false, null, 0, 8);
    if (str_starts_with($h, '%PDF')) return 'application/pdf';
    if (str_starts_with($h, "\x89PNG")) return 'image/png';
    if (str_starts_with($h, "\xFF\xD8\xFF")) return 'image/jpeg';
    return 'application/octet-stream';
}

try {
    $action = $_GET['action'] ?? '';
    if ($action === 'sync') define('NO_TOUCH', true);
    $user = require_login(true); // semua aksi API wajib login
    if ($action === 'sync') session_write_close(); // hanya baca: jangan mengunci sesi
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'basarnas') {
        failOut(403, 'Permintaan tidak valid');
    }

    switch ($action) {
        case 'load':
            jsonOut(load_db());

        case 'sync': {
            // Polling ringan: kirim data lengkap hanya bila revisi di server berbeda dari milik browser.
            $p = pdo();
            $rev = get_rev($p);
            if ((int)($_GET['since'] ?? -1) === $rev) jsonOut(['ok' => true, 'rev' => $rev, 'changed' => false]);
            jsonOut(['ok' => true, 'rev' => $rev, 'changed' => true, 'db' => load_db()]);
        }

        case 'save': {
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            $raw = (string)file_get_contents('php://input');
            if ($raw === '') failOut(400, 'Data kosong (periksa post_max_size di PHP)');
            $db = json_decode($raw, true);
            if (!is_array($db) || !isset($db['anggota']) || !is_array($db['anggota'])) failOut(400, 'Format data tidak valid');
            $known = array_merge(CORE_KEYS, MODULE_TABLES);
            $objs = array_diff(array_keys($db), $known) ? json_decode($raw) : null;
            // Keputusan ACC/Tolak yang sudah final di server (mis. lewat WhatsApp) tidak boleh ditimpa halaman yang belum diperbarui.
            $lock = fopen(DATA_DIR . '/wa.lock', 'c');
            if ($lock) flock($lock, LOCK_EX);
            try {
                $p = pdo();
                $dbLama = load_db();
                $konflik = acc_jaga_keputusan($db, $objs, $dbLama);
                wa_jaga_status_kirim($db, $dbLama);
                $rev = save_db($p, $db, $objs);
            } finally {
                if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
            }
            acc_sweep_nanti(); // permintaan ACC ke pimpinan + notifikasi hasil ke pengirim (setelah respons selesai)
            jsonOut(['ok' => true, 'saved_at' => date('c'), 'rev' => $rev, 'konflik' => $konflik]);
        }

        case 'file_put': {
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            $fid = (string)($_POST['fid'] ?? '');
            if (!validFid($fid)) failOut(400, 'ID file tidak valid');
            $f = $_FILES['file'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) failOut(400, 'Upload gagal (kode ' . ($f['error'] ?? '?') . '). Periksa upload_max_filesize di PHP.');
            if ($f['size'] > 25 * 1024 * 1024) failOut(413, 'File melebihi 25MB');
            pdo(); // memastikan folder data/uploads ada
            $dest = UPLOAD_DIR . '/' . $fid;
            if (!move_uploaded_file($f['tmp_name'], $dest)) failOut(500, 'Gagal menyimpan file di server');
            file_put_contents($dest . '.json', json_encode(['name' => (string)$f['name'], 'size' => (int)$f['size']], JSON_OUT));
            jsonOut(['ok' => true]);
        }

        case 'file_get': {
            $fid = (string)($_GET['fid'] ?? '');
            if (!validFid($fid) || !is_file(UPLOAD_DIR . '/' . $fid)) failOut(404, 'File tidak ditemukan');
            $path = UPLOAD_DIR . '/' . $fid;
            $meta = json_decode((string)@file_get_contents($path . '.json'), true) ?: [];
            header('Content-Type: ' . safeMime($path));
            header('X-Content-Type-Options: nosniff');
            header('X-File-Name: ' . rawurlencode((string)($meta['name'] ?? $fid)));
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: private, max-age=0');
            readfile($path);
            exit;
        }

        case 'file_del': {
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            $fid = (string)($_GET['fid'] ?? '');
            if (!validFid($fid)) failOut(400, 'ID file tidak valid');
            @unlink(UPLOAD_DIR . '/' . $fid);
            @unlink(UPLOAD_DIR . '/' . $fid . '.json');
            jsonOut(['ok' => true]);
        }

        case 'wa_tugas': {
            // Kirim notifikasi penugasan + PDF Sprin ke tiap anggota yang ditugaskan (via WhatsApp/Fonnte).
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            if (!is_admin_utama($user) && ($user['jabatan'] ?? 'Anggota') === 'Anggota') failOut(403, 'Hanya pimpinan/Admin yang dapat mengirim info tugas');
            $in = json_decode((string)file_get_contents('php://input'), true);
            $kid = is_array($in) ? (string)($in['id'] ?? '') : '';
            if ($kid === '') failOut(400, 'ID kegiatan kosong');

            // Data diambil dari server (bukan dari browser) agar isi pesan & penerima tidak bisa dipalsukan.
            $db = load_db();
            $k = null;
            foreach ($db['kegiatan'] ?? [] as $row) if (($row['id'] ?? '') === $kid) { $k = $row; break; }
            if (!$k) failOut(404, 'Kegiatan tidak ditemukan (simpan data lalu coba lagi)');
            if (($k['status_persetujuan'] ?? '') !== 'Disetujui') failOut(422, 'Kegiatan belum disetujui');
            $sprin = $k['sprin'] ?? null;
            if (!$sprin || !validFid((string)($sprin['fid'] ?? ''))) failOut(422, 'Sprin belum diupload');
            $path = UPLOAD_DIR . '/' . $sprin['fid'];
            if (!is_file($path) || safeMime($path) !== 'application/pdf') failOut(422, 'File Sprin tidak ditemukan atau bukan PDF');

            $byId = [];
            foreach ($db['anggota'] ?? [] as $a) $byId[$a['id'] ?? ''] = $a;
            $petugas = [];
            foreach ($k['anggota_ids'] ?? [] as $aid) if (isset($byId[$aid])) $petugas[] = $byId[$aid];
            if (!$petugas) failOut(422, 'Belum ada anggota yang ditugaskan');

            $pesan = wa_pesan_tugas($k, array_map(fn($a) => (string)$a['nama'], $petugas));
            $fname = trim((string)($sprin['name'] ?? '')) ?: 'Sprin.pdf';
            @set_time_limit(60 + 30 * count($petugas));

            $hasil = [];
            $terkirim = 0;
            $pdo = pdo();
            foreach ($petugas as $a) {
                $no = wa_nomor((string)($a['whatsapp'] ?? ''));
                if ($no === '') { $hasil[] = ['nama' => $a['nama'], 'ok' => false, 'ket' => 'Nomor WhatsApp belum diisi di Daftar Anggota']; continue; }
                // 1) Notifikasi penugasan + PDF Sprin
                $tSp = wl_token_kegiatan($pdo, $k, (string)$a['nama'], $no, 'sprin');
                $urlSprin = wl_link($tSp);
                [$ok, $ket] = wa_kirim_lampiran($no, $pesan . "\n\nUnduh Sprin (jika lampiran tidak muncul):\n" . $urlSprin, $path, $fname, $urlSprin);
                if ($ok) {
                    // 2) Tautan update kegiatan (pesan terpisah, token khusus anggota ini).
                    //    Link upload laporan TIDAK dikirim di sini: otomatis dikirim setelah anggota mengisi update kegiatan (wl_simpan).
                    wl_token_kegiatan_cabut($pdo, $kid, $no);
                    $tUpd = wl_token_kegiatan($pdo, $k, (string)$a['nama'], $no, 'update');
                    usleep(700000);
                    [$ok2, $ket2] = wa_kirim_lampiran($no, wa_pesan_link_update($k, wl_link($tUpd)));
                    if (!$ok2) { $ok = false; $ket = 'Info tugas + Sprin terkirim, tetapi gagal mengirim link update (' . $ket2 . ')'; }
                    else $ket = 'info tugas + Sprin dan link update terkirim';
                }
                if ($ok) $terkirim++;
                $hasil[] = ['nama' => $a['nama'], 'ok' => $ok, 'ket' => $ket];
            }
            wa_log('TUGAS ' . $kid . ' oleh ' . ($user['username'] ?? '?') . ': ' . $terkirim . '/' . count($petugas) . ' terkirim');

            // Status WhatsApp dicatat oleh SERVER (bukan menunggu browser), supaya tidak hilang bila browser
            // menutup/menyegarkan halaman atau memuat ulang data saat pengiriman masih berjalan.
            $statusWa = null;
            $logWa = null;
            if ($terkirim > 0) {
                $lock = fopen(DATA_DIR . '/wa.lock', 'c');
                if ($lock) flock($lock, LOCK_EX);
                try {
                    $db2 = load_db();
                    foreach ($db2['kegiatan'] ?? [] as $i => $row) {
                        if (($row['id'] ?? '') !== $kid) continue;
                        $row['status_wa'] = ($terkirim === count($petugas)) ? 'Terkirim' : 'Belum Terkirim';
                        $row['wa_log'] = array_merge((array)($row['wa_log'] ?? []), [['waktu' => date('d-m-Y H:i'), 'jumlah' => $terkirim]]);
                        $row['diperbarui'] = date('d-m-Y H:i');
                        $db2['kegiatan'][$i] = $row;
                        save_db(pdo(), $db2);
                        $statusWa = $row['status_wa'];
                        $logWa = $row['wa_log'];
                        break;
                    }
                } catch (Throwable $e) {
                    wa_log('STATUS WA ' . $kid . ' GALAT: ' . $e->getMessage());
                } finally {
                    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
                }
            }
            jsonOut(['ok' => true, 'terkirim' => $terkirim, 'total' => count($petugas), 'hasil' => $hasil, 'status_wa' => $statusWa, 'wa_log' => $logWa]);
        }

        case 'wa_notif_bukti': {
            // Dashboard: bukti penugasan baru diupload -> notifikasi WA ke semua anggota yang bertugas. Data dibaca dari server.
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            $in = json_decode((string)file_get_contents('php://input'), true);
            $kid = is_array($in) ? (string)($in['id'] ?? '') : '';
            if ($kid === '') failOut(400, 'ID kegiatan kosong');
            $k = null;
            foreach (load_db()['kegiatan'] ?? [] as $row) if (($row['id'] ?? '') === $kid) { $k = $row; break; }
            if (!$k) failOut(404, 'Kegiatan tidak ditemukan');
            if (empty($k['bukti_files'])) failOut(422, 'Belum ada bukti yang tersimpan di server');
            @set_time_limit(60 + 30 * count((array)($k['anggota_ids'] ?? [])));
            $oleh = trim((string)($user['nama'] ?? $user['username'] ?? ''));
            jsonOut(['ok' => true] + wl_notif_bukti(pdo(), $k, $oleh));
        }

        case 'wa_notif_selesai': {
            // Dashboard: laporan diupload / kegiatan Selesai -> notifikasi WA (dengan ID) ke semua anggota yang bertugas.
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            $in = json_decode((string)file_get_contents('php://input'), true);
            $kid = is_array($in) ? (string)($in['id'] ?? '') : '';
            if ($kid === '') failOut(400, 'ID kegiatan kosong');
            $k = null;
            foreach (load_db()['kegiatan'] ?? [] as $row) if (($row['id'] ?? '') === $kid) { $k = $row; break; }
            if (!$k) failOut(404, 'Kegiatan tidak ditemukan');
            if (($k['status_pelaksanaan'] ?? '') !== 'Selesai') failOut(422, 'Kegiatan belum berstatus Selesai di server');
            @set_time_limit(60 + 30 * count((array)($k['anggota_ids'] ?? [])));
            $oleh = trim((string)($user['nama'] ?? $user['username'] ?? ''));
            jsonOut(['ok' => true] + wl_notif_selesai(pdo(), $k, $oleh));
        }

        case 'wa_grup_sprin': {
            // Kirim daftar "Sprin Belum Dibuat" ke grup WhatsApp. Isi pesan disusun dari data server (bukan dari browser).
            if ($method !== 'POST') failOut(405, 'Gunakan POST');
            if (!is_admin_utama($user) && ($user['jabatan'] ?? 'Anggota') === 'Anggota') failOut(403, 'Hanya pimpinan/Admin yang dapat mengirim ke grup');
            if (!defined('WA_GRUP_SPRIN') || trim((string)WA_GRUP_SPRIN) === '') failOut(422, 'Grup WhatsApp belum diatur (isi WA_GRUP_SPRIN di wa_config.php)');
            $in = json_decode((string)file_get_contents('php://input'), true);
            $ids = is_array($in) && is_array($in['ids'] ?? null) ? array_map('strval', $in['ids']) : [];
            $filter = is_array($in) ? (string)($in['filter'] ?? '') : '';
            if (!$ids) failOut(422, 'Tidak ada Sprin yang dikirim');
            $db = load_db();
            $baris = [];
            $n = 0;
            foreach ($db['kegiatan'] ?? [] as $k) {
                if (!in_array((string)($k['id'] ?? ''), $ids, true)) continue;
                if (($k['status_persetujuan'] ?? '') !== 'Disetujui' || !empty($k['sprin']) || empty($k['anggota_ids'])) continue;
                $st = (($k['sprin_proses'] ?? '') === 'Sedang Di Proses') ? 'Sedang Di Proses' : 'Belum Di Proses';
                $a = (string)($k['tanggal'] ?? ''); $b = (string)($k['tanggal_selesai'] ?? '');
                $tgl = ($b !== '' && substr($b, 0, 10) > substr($a, 0, 10)) ? wa_tgl_panjang($a) . ' s.d. ' . wa_tgl_panjang($b) : wa_tgl_panjang($a);
                $n++;
                $baris[] = $n . '. ' . ($k['nama'] ?? '-') . "\n   " . $tgl . ' · ' . count($k['anggota_ids']) . ' anggota · ' . $st;
            }
            if (!$n) failOut(422, 'Semua Sprin yang dipilih sudah dibuat');
            $judul = in_array($filter, ['Belum Di Proses', 'Sedang Di Proses'], true) ? ' (' . $filter . ')' : '';
            $pesan = "*SPRIN BELUM DIBUAT*" . $judul . "\n" . date('d-m-Y') . "\n\n" . implode("\n", $baris) . "\n\nTotal: " . $n . " Sprin. Mohon ditindaklanjuti.";
            [$ok, $ket] = wa_kirim_lampiran(trim((string)WA_GRUP_SPRIN), $pesan);
            wa_log('GRUP SPRIN oleh ' . ($user['username'] ?? '?') . ': ' . ($ok ? 'terkirim ' . $n : 'gagal ' . $ket));
            if (!$ok) failOut(502, 'Gagal mengirim ke grup: ' . $ket);
            jsonOut(['ok' => true, 'jumlah' => $n]);
        }

        default:
            failOut(400, 'Aksi tidak dikenal');
    }
} catch (Throwable $e) {
    error_log('[basarnas] ' . $e->getMessage());
    failOut(500, 'Kesalahan server: ' . $e->getMessage());
}
