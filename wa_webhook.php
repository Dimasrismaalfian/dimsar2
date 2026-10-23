<?php
/**
 * Webhook WhatsApp Dashboard BASARNAS.
 *
 * Pesan yang masuk ke nomor WA_NOMOR_BOT diterima gateway (Fonnte), diteruskan ke file ini,
 * diurai, lalu dicatat ke modul yang sesuai. Modul yang boleh diinput lewat WhatsApp:
 *   KEGIATAN (Kegiatan/Tugas) · SURAT (Persuratan) · PEMBINAAN (Pembinaan Tenaga SAR)
 *   PERALATAN · KENDARAAN · PINJAM (Peminjaman) · PENGAJUAN
 * Modul lain sengaja tidak dapat diinput dari WhatsApp.
 * Setelah data tersimpan, bot membalas ID + tautan upload lampiran (u.php) dan menerima foto/PDF langsung di chat
 * (pesan terpisah tanpa teks) dalam WA_UPLOAD_JENDELA_MENIT menit. Perintah "SELESAI <ID>" menandai Kegiatan/Tugas selesai.
 *
 * Alamat webhook (daftarkan di Fonnte):  https://DOMAIN/wa_webhook.php?key=<WA_WEBHOOK_KEY>
 * Pengujian cepat: buka alamat yang sama di browser (GET) -> tampil "OK".
 */
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/wa_inti.php';
require __DIR__ . '/wa_acc.php';

/* ============================== Alur utama ============================== */

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $key = (string)($_GET['key'] ?? ($_SERVER['HTTP_X_WEBHOOK_KEY'] ?? ''));
    if (!hash_equals(WA_WEBHOOK_KEY, $key)) wa_out(403, ['status' => false, 'error' => 'Kunci webhook tidak valid']);
    if ($method === 'GET' || $method === 'HEAD') { header('Content-Type: text/plain; charset=utf-8'); echo 'OK'; exit; }
    if ($method !== 'POST') wa_out(405, ['status' => false, 'error' => 'Gunakan POST']);

    acc_sweep_nanti(); // kirim permintaan ACC / notifikasi hasil yang tertunda setelah respons selesai

    // Fonnte mengirim form-urlencoded atau JSON
    $in = $_POST;
    $raw = (string)file_get_contents('php://input');
    if (!$in && $raw !== '') { $j = json_decode($raw, true); if (is_array($j)) $in = $j; }

    $pesan  = trim((string)($in['message'] ?? $in['text'] ?? ''));
    $sender = (string)($in['sender'] ?? $in['from'] ?? '');
    $device = wa_nomor((string)($in['device'] ?? ''));
    $nomor  = wa_nomor($sender);
    $nama   = trim((string)($in['name'] ?? $in['pushname'] ?? ''));

    // Lampiran yang dikirim langsung ke chat: gateway mengirim alamat file di `url`, teks kosong (atau penanda non-teks).
    $urlLamp = trim((string)($in['url'] ?? $in['file'] ?? $in['media'] ?? ''));
    $tanpaTeks = $pesan === '' || (bool)preg_match('/^(non[- ]?text message|\[(image|document|media|file|video|audio|sticker)\])$/i', $pesan);
    $adaLampiran = $urlLamp !== '' && $tanpaTeks;
    if ($nomor === '' || ($pesan === '' && !$adaLampiran)) wa_out(200, ['status' => true, 'info' => 'diabaikan (kosong)']);
    if (!empty($in['member']) || str_contains($sender, '@g.us') || str_contains($sender, '-')) wa_out(200, ['status' => true, 'info' => 'diabaikan (grup)']);
    $bot = wa_nomor(WA_NOMOR_BOT);
    if ($nomor === $bot) wa_out(200, ['status' => true, 'info' => 'diabaikan (pesan sendiri)']);
    if ($device !== '' && $device !== $bot) wa_out(200, ['status' => true, 'info' => 'diabaikan (device lain)']);

    $p = pdo();
    $p->exec('CREATE TABLE IF NOT EXISTS wa_inbox (msg_id TEXT PRIMARY KEY, waktu TEXT)');
    // Kunci duplikat = ID pesan + isi pesan. Jika gateway mengirim ID yang sama untuk pesan berbeda, pesan baru tetap diproses;
    // hanya kiriman ulang (ID dan isi sama) yang diabaikan.
    $msgId = (string)($in['id'] ?? $in['inboxid'] ?? '') . '|' . sha1($nomor . '|' . $pesan . '|' . $urlLamp) . (empty($in['id'] ?? $in['inboxid'] ?? '') ? '|' . date('YmdHi') : '');
    wa_log("TERIMA $nomor: [" . ($adaLampiran ? 'LAMPIRAN ' . mb_substr($urlLamp, 0, 60) : mb_substr(str_replace(["\r", "\n"], ' ', $pesan), 0, 80)) . ']');
    $p->exec("DELETE FROM wa_inbox WHERE msg_id = " . $p->quote($msgId) . " AND waktu < '" . date('c', strtotime('-60 seconds')) . "'"); // kiriman ulang gateway hanya dianggap duplikat dalam 60 detik
    $ins = $p->prepare('INSERT OR IGNORE INTO wa_inbox (msg_id, waktu) VALUES (?, ?)');
    $ins->execute([$msgId, date('c')]);
    if ($ins->rowCount() === 0) { wa_log("DUPLIKAT diabaikan dari $nomor"); wa_out(200, ['status' => true, 'info' => 'duplikat, diabaikan']); }
    $p->exec("DELETE FROM wa_inbox WHERE waktu < '" . date('c', strtotime('-7 days')) . "'");

    // Kunci agar dua pesan bersamaan tidak saling menimpa
    $lock = fopen(DATA_DIR . '/wa.lock', 'c');
    if ($lock) flock($lock, LOCK_EX);

    $db = load_db();

    // Pengirim harus anggota terdaftar (nomor di Daftar Anggota) atau ada di WA_NOMOR_DIIZINKAN
    $pelaku = ['nama' => $nama !== '' ? $nama : $nomor, 'nomor' => $nomor];
    $dikenal = false;
    foreach ($db['anggota'] as $a) {
        foreach (preg_split('/[,;\/\n]+/', (string)($a['whatsapp'] ?? '')) ?: [] as $no) {
            if (wa_nomor($no) === $nomor && wa_nomor($no) !== '') { $pelaku['nama'] = (string)$a['nama']; $dikenal = true; break 2; }
        }
    }
    foreach (WA_NOMOR_DIIZINKAN as $no) if (wa_nomor($no) === $nomor) $dikenal = true;
    $adaApprover = acc_is_approver($nomor); // nomor pimpinan selalu dikenal
    if ($adaApprover) { $dikenal = true; $pelaku['nama'] = acc_identitas_approver($db, $nomor, $nama)['nama']; }
    if (WA_BATASI_PENGIRIM && !$dikenal) {
        wa_log("DITOLAK nomor $nomor tidak terdaftar");
        wa_kirim($nomor, "⛔ Nomor Anda belum terdaftar. Minta admin mengisi Nomor WhatsApp Anda di menu Daftar Anggota dashboard, lalu kirim ulang pesan ini.");
        wa_out(200, ['status' => true, 'info' => 'pengirim tidak terdaftar']);
    }

    // Lampiran langsung di chat -> masuk ke data terakhir yang disimpan pengirim ini (dalam jendela waktu).
    if ($adaLampiran) {
        $balas = wa_terima_lampiran($p, $in, $pelaku);
        wa_kirim($nomor, $balas);
        wa_log("$nomor ({$pelaku['nama']}): lampiran -> " . mb_substr(str_replace("\n", ' ', $balas), 0, 100));
        wa_out(200, ['status' => true, 'info' => 'lampiran diproses']);
    }

    // Kata kunci di baris pertama
    $lines = preg_split('/\R/u', $pesan) ?: [$pesan];
    $first = trim((string)array_shift($lines));
    [$cmd, $rest] = wa_detect($first);
    if ($cmd === '') {
        wa_kirim($nomor, "Pesan tidak dikenali sebagai perintah.\n\n" . wa_bantuan());
        wa_log("$nomor: perintah tidak dikenal [" . mb_substr($first, 0, 60) . ']');
        wa_out(200, ['status' => true, 'info' => 'perintah tidak dikenal']);
    }
    if ($cmd === 'bantuan') { wa_kirim($nomor, wa_bantuan() . ($adaApprover ? "\n\n" . acc_bantuan() : '')); wa_out(200, ['status' => true, 'info' => 'bantuan']); }
    if (in_array($cmd, ['acc', 'tolak', 'menunggu'], true)) {
        if (!$adaApprover) {
            wa_kirim($nomor, "⛔ ACC/TOLAK lewat WhatsApp hanya untuk nomor pimpinan yang terdaftar. Gunakan dashboard bila Anda berwenang.");
            wa_log("$nomor ({$pelaku['nama']}): $cmd ditolak (bukan nomor pimpinan)");
            wa_out(200, ['status' => true, 'info' => 'bukan pimpinan']);
        }
        if ($cmd === 'menunggu') { wa_kirim($nomor, acc_daftar_menunggu($db)); wa_out(200, ['status' => true, 'info' => 'menunggu']); }
        $pi = acc_parse_id($rest);
        if (!$pi) { wa_kirim($nomor, "❌ Sebutkan ID datanya.\nContoh: *ACC KGT-045* atau *TOLAK KGT-045 bentrok jadwal*\nKetik *MENUNGGU* untuk melihat daftar."); wa_out(200, ['status' => true, 'info' => 'acc tanpa id']); }
        [$pre, $no, $alasan] = $pi;
        $hit = acc_cari($db, $pre, $no);
        if (!$hit) { wa_kirim($nomor, "❌ Data dengan ID \"$pre-$no\" tidak ditemukan. Ketik *MENUNGGU* untuk melihat daftar."); wa_out(200, ['status' => true, 'info' => 'id tidak ditemukan']); }
        $idn = acc_identitas_approver($db, $nomor, $nama);
        [$ok, $balas] = acc_putuskan($db, $hit[0], $hit[1], $cmd === 'acc', $alasan, $idn);
        if ($ok) save_db($p, $db);
        wa_kirim($nomor, $balas);
        wa_log("$nomor ({$idn['nama']}): $cmd " . ($ok ? 'OK' : 'ditolak') . " [" . mb_substr($rest, 0, 40) . ']');
        wa_out(200, ['status' => true, 'info' => $ok ? ($cmd === 'acc' ? 'disetujui' : 'ditolak') : 'tidak diproses']);
    }
    if ($cmd === 'format') { wa_kirim($nomor, wa_format($rest)); wa_out(200, ['status' => true, 'info' => 'format']); }
    if ($cmd === 'form') {
        [$c2] = wa_detect($rest);
        if (!in_array($c2, WA_FORM_CMDS, true)) {
            wa_kirim($nomor, "📝 Formulir via tautan. Ketik salah satu:\n*FORM KEGIATAN*\n*FORM SURAT MASUK* / *FORM SURAT KELUAR*\n*FORM PEMBINAAN*\n*FORM PERALATAN*\n*FORM KENDARAAN*\n*FORM PINJAM*\n*FORM PENGAJUAN*");
            wa_out(200, ['status' => true, 'info' => 'form: pilihan']);
        }
        $modul = WA_MODUL_DATA[$c2][1];
        if ($c2 === 'surat' && !preg_match('/keluar/i', $rest)) $modul = 'Persuratan — Surat Masuk';
        try {
            $tk = wl_token_buat($p, '', '', $modul, '', $nomor, $pelaku['nama'], 'form', $c2);
            wa_kirim($nomor, "📝 Formulir *$modul*:\n" . wl_link($tk) . "\n\nIsi data di halaman itu, lampirkan foto/PDF bila ada (opsional), lalu kirim. Tautan sekali pakai, berlaku " . wl_link_hari() . " hari.");
            wa_log("$nomor ({$pelaku['nama']}): form $c2 -> token");
        } catch (Throwable $e) {
            wa_log('FORM TOKEN GAGAL: ' . $e->getMessage());
            wa_kirim($nomor, '⚠️ Formulir belum bisa dibuat, coba lagi sebentar.');
        }
        wa_out(200, ['status' => true, 'info' => 'form']);
    }
    if ($cmd === 'selesai') {
        [$balas, $ubah] = wa_selesai($db, $rest, $pelaku);
        if ($ubah) save_db($p, $db);
        wa_kirim($nomor, $balas);
        wa_log("$nomor ({$pelaku['nama']}): selesai " . ($ubah ? 'OK' : 'ditolak') . ' [' . mb_substr($rest, 0, 30) . ']');
        wa_out(200, ['status' => true, 'info' => $ubah ? 'selesai' : 'selesai ditolak']);
    }

    // Sisa baris pertama: kata kategori (mis. "SURAT KELUAR", "PENGAJUAN PEMELIHARAAN", "PEMBINAAN SAMAPTA") atau langsung "Nama: ..."
    $kategori = '';
    if ($rest !== '') {
        if ($cmd === 'surat' && preg_match('/^[\s\/\-:]*(masuk|keluar)\b[\s\/\-:]*(.*)$/ui', $rest, $m)) {
            if (mb_strtolower($m[1]) === 'keluar') $cmd = 'surat_keluar';
            $rest = trim($m[2]);
        }
        if (in_array($cmd, ['pembinaan', 'pengajuan'], true)) {
            $opsi = $cmd === 'pembinaan' ? ['Pembelajaran', 'Samapta'] : ['Kebutuhan', 'Pemeliharaan'];
            if (preg_match('/^[\s\/\-:]*([a-z]+)[\s\/\-:]*(.*)$/ui', $rest, $m)) {
                $o = wa_match_opsi($m[1], $opsi);
                if ($o !== null) { $kategori = $o; $rest = trim($m[2]); }
            }
        }
        $rest = ltrim($rest, " /-:");
        if ($rest !== '') array_unshift($lines, $rest);
    }

    $spec = wa_spec($cmd);
    if (!$lines) { wa_kirim($nomor, wa_format($cmd === 'surat_keluar' ? 'surat keluar' : $cmd)); wa_log("$nomor: $cmd tanpa isian, dikirim contoh format"); wa_out(200, ['status' => true, 'info' => 'format']); }
    [$data, $err, $unknown] = wa_validasi($spec, wa_baris(implode("\n", $lines)));
    if ($err) {
        wa_kirim($nomor, "❌ {$spec['judul']} belum disimpan:\n- " . implode("\n- ", $err) . "\n\nKetik *FORMAT " . mb_strtoupper($cmd === 'surat_keluar' ? 'surat keluar' : $cmd) . "* untuk contoh pesan.");
        wa_log("$nomor: $cmd galat: " . implode('; ', $err));
        wa_out(200, ['status' => true, 'info' => 'validasi gagal']);
    }

    try {
        $balas = wa_simpan($db, $cmd, $data, $pelaku, $kategori);
    } catch (RuntimeException $e) {
        wa_kirim($nomor, "❌ {$spec['judul']} belum disimpan:\n" . $e->getMessage());
        wa_log("$nomor: $cmd ditolak: " . $e->getMessage());
        wa_out(200, ['status' => true, 'info' => 'ditolak']);
    }
    $rev = save_db($p, $db);
    $balas = wa_balasan_tersimpan($p, $cmd, $balas, $data, $pelaku);
    if ($unknown) $balas .= "\n\nℹ️ Isian tidak dikenali dan diabaikan: " . implode(', ', $unknown);
    wa_kirim($nomor, $balas);
    wa_log("$nomor ({$pelaku['nama']}): $cmd OK rev=$rev");
    wa_out(200, ['status' => true, 'info' => 'tersimpan', 'rev' => $rev]);
} catch (Throwable $e) {
    error_log('[basarnas-wa] ' . $e->getMessage());
    if (function_exists('wa_log')) wa_log('GALAT: ' . $e->getMessage());
    if (!empty($nomor)) wa_kirim($nomor, '⚠️ Terjadi kesalahan di server, data belum tersimpan. Coba lagi beberapa saat.');
    wa_out(200, ['status' => false, 'error' => 'Kesalahan server']);
}
