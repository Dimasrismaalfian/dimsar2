<?php
/**
 * ACC / TOLAK lewat WhatsApp (Dashboard BASARNAS).
 *
 * Alur:
 *  1. Ada data yang menunggu persetujuan (Surat Masuk, Kegiatan/Tugas, Pengajuan) -> bot mengirim permintaan ACC
 *     ke nomor pimpinan (WA_NOMOR_APPROVER) berisi ringkasan + perintah balasan.
 *  2. Pimpinan membalas "ACC <ID>" atau "TOLAK <ID> alasan" -> keputusan diterapkan (wa_webhook.php).
 *  3. Keputusan PERTAMA (WhatsApp atau dashboard) bersifat final: tombol ACC/Tolak di dashboard hilang untuk data itu,
 *     dan balasan ACC/TOLAK di WhatsApp untuk data yang sudah diputuskan di dashboard ditolak.
 *     api.php juga menjaga di sisi server agar halaman dashboard yang belum diperbarui tidak menimpa keputusan.
 *  4. Setiap keputusan (dari WhatsApp maupun dashboard) diberitahukan ke pengirim/pengaju: sudah di-ACC / ditolak oleh siapa.
 *
 * Pengiriman notifikasi dilakukan oleh acc_sweep() yang idempoten (tabel acc_notif), dipanggil setelah respons
 * dikirim (acc_sweep_nanti) oleh api.php (simpan dashboard), wa_webhook.php dan u.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/wa_inti.php';

/** tipe => [kunci data, kolom status, label, prefix ID]. */
const ACC_TIPE = [
    'surat'     => ['key' => 'surat_masuk',  'status' => 'status',             'label' => 'Surat Masuk',     'prefix' => 'SM'],
    'kegiatan'  => ['key' => 'kegiatan',     'status' => 'status_persetujuan', 'label' => 'Kegiatan/Tugas',  'prefix' => 'KGT'],
    'pengajuan' => ['key' => 'pemeliharaan', 'status' => 'status_persetujuan', 'label' => 'Pengajuan',       'prefix' => 'PGJ'],
];
const ACC_MENUNGGU = 'Menunggu Persetujuan';

/* ============================== Utilitas ============================== */

/** Baca/tulis kolom pada baris yang bisa berupa array atau objek (data pengajuan disimpan sebagai objek). */
function acc_g(mixed $r, string $k, mixed $d = ''): mixed
{
    if (is_object($r)) return $r->$k ?? $d;
    return is_array($r) ? ($r[$k] ?? $d) : $d;
}
function acc_s(mixed &$r, string $k, mixed $v): void
{
    if (is_object($r)) $r->$k = $v; else $r[$k] = $v;
}
function acc_unset(mixed &$r, string $k): void
{
    if (is_object($r)) unset($r->$k); else unset($r[$k]);
}

/** Nomor-nomor pimpinan yang berwenang ACC/TOLAK lewat WhatsApp (sudah dinormalisasi 62...). */
function acc_approvers(): array
{
    $o = [];
    foreach (WA_NOMOR_APPROVER as $n) { $n = wa_nomor((string)$n); if ($n !== '') $o[$n] = $n; }
    return array_values($o);
}
function acc_is_approver(string $nomor): bool { return $nomor !== '' && in_array($nomor, acc_approvers(), true); }

/** Status persetujuan sebuah baris. Pengajuan lama tanpa status dianggap Disetujui (seperti pgjSP() di dashboard). */
function acc_status(string $tipe, mixed $r): string
{
    $s = (string)acc_g($r, ACC_TIPE[$tipe]['status'], '');
    return ($s === '' && $tipe === 'pengajuan') ? 'Disetujui' : $s;
}
/** Pengajuan lama (tanpa status tersimpan) tidak pernah diminta persetujuannya. */
function acc_status_eksplisit(string $tipe, mixed $r): bool { return (string)acc_g($r, ACC_TIPE[$tipe]['status'], '') !== ''; }

/** Semua data yang punya alur persetujuan: [[tipe, indeks, baris], ...]. */
function acc_semua(array $db): array
{
    $o = [];
    foreach (ACC_TIPE as $tipe => $t) {
        $rows = $db[$t['key']] ?? [];
        if (!is_array($rows)) continue;
        foreach ($rows as $i => $r) if ((string)acc_g($r, 'id', '') !== '') $o[] = [$tipe, $i, $r];
    }
    return $o;
}

/** Ubah "kgt 7" / "KGT-007" menjadi [PREFIX, nomor]. */
function acc_parse_id(string $teks): ?array
{
    if (!preg_match('/^[\s:\-#]*([A-Za-z]{2,5})[\s\-]*0*(\d{1,6})\b\s*(.*)$/su', $teks, $m)) return null;
    $pre = strtoupper($m[1]);
    if ($pre === 'KEG') $pre = 'KGT';
    return [$pre, (int)$m[2], trim($m[3])];
}

/** Cari data berdasarkan ID di ketiga modul. Kembalikan [tipe, indeks] atau null. */
function acc_cari(array $db, string $pre, int $no): ?array
{
    foreach (acc_semua($db) as [$tipe, $i, $r]) {
        if (preg_match('/^([A-Za-z]+)-0*(\d+)$/', (string)acc_g($r, 'id', ''), $x) && strtoupper($x[1]) === $pre && (int)$x[2] === $no) return [$tipe, $i];
    }
    return null;
}

/** Identitas pimpinan yang memutuskan: nama dari Daftar Anggota (berdasar nomor WA), peran dari konfigurasi. */
function acc_identitas_approver(array $db, string $nomor, string $namaPush = ''): array
{
    $nama = '';
    foreach ($db['anggota'] ?? [] as $a) {
        foreach (preg_split('/[,;\/\n]+/', (string)acc_g($a, 'whatsapp', '')) ?: [] as $no) {
            if (wa_nomor($no) === $nomor && $nomor !== '') { $nama = (string)acc_g($a, 'nama', ''); break 2; }
        }
    }
    if ($nama === '') $nama = trim($namaPush) !== '' ? trim($namaPush) : $nomor;
    return ['nama' => $nama, 'peran' => WA_APPROVER_PERAN, 'nomor' => $nomor];
}

/** Nomor WA pengirim/pengaju sebuah data: dari wa_pengirim (input via WA) atau dicocokkan lewat nama di Daftar Anggota. */
function acc_pengirim(array $db, mixed $r): array
{
    $oleh = acc_g($r, 'diajukan_oleh', null);
    $nama = trim((string)(is_array($oleh) || is_object($oleh) ? acc_g($oleh, 'nama', '') : (string)$oleh));
    $nomor = wa_nomor((string)acc_g($r, 'wa_pengirim', ''));
    if ($nomor === '' && $nama !== '' && $nama !== '-') {
        $n = wa_norm_nama($nama);
        foreach ($db['anggota'] ?? [] as $a) {
            if (wa_norm_nama((string)acc_g($a, 'nama', '')) !== $n) continue;
            foreach (preg_split('/[,;\/\n]+/', (string)acc_g($a, 'whatsapp', '')) ?: [] as $no) if (wa_nomor($no) !== '') { $nomor = wa_nomor($no); break 2; }
        }
    }
    return [$nama, $nomor];
}

/** Ringkasan isi data untuk pesan WA. */
function acc_ringkas(string $tipe, mixed $r): array
{
    $v = static fn(string $k): string => trim((string)acc_g($r, $k, ''));
    $tgl = static fn(string $k): string => wa_tgl_id($v($k) !== '' ? $v($k) : null);
    $o = [];
    switch ($tipe) {
        case 'kegiatan':
            $o[] = 'Nama: ' . ($v('nama') ?: '-');
            $o[] = 'Jenis: ' . ($v('jenis_label') ?: '-');
            $o[] = 'Tanggal: ' . $tgl('tanggal') . ($v('waktu') !== '' ? ' ' . $v('waktu') : '');
            $o[] = 'Lokasi: ' . ($v('lokasi') ?: '-');
            $o[] = 'PJ: ' . ($v('penanggung_jawab') ?: '-');
            if ($v('jumlah_dibutuhkan') !== '') $o[] = 'Jumlah anggota: ' . $v('jumlah_dibutuhkan');
            break;
        case 'surat':
            $o[] = 'Nomor: ' . ($v('nomor_surat') ?: '-');
            $o[] = 'Asal: ' . ($v('asal_surat') ?: '-');
            $o[] = 'Perihal: ' . ($v('perihal') ?: '-');
            $o[] = 'Jenis: ' . ($v('jenis_label') ?: '-');
            if ($v('tanggal_kegiatan') !== '') $o[] = 'Tanggal kegiatan: ' . $tgl('tanggal_kegiatan') . ($v('waktu') !== '' ? ' ' . $v('waktu') : '');
            if ($v('lokasi') !== '') $o[] = 'Lokasi: ' . $v('lokasi');
            break;
        case 'pengajuan':
            $kat = $v('kategori_pengajuan') ?: 'Pemeliharaan';
            $o[] = 'Kategori: ' . $kat;
            $o[] = ($v('jenis_barang') ?: 'Barang') . ': ' . ($v('nama_barang') ?: '-');
            if ($kat === 'Kebutuhan') {
                if ($v('jumlah_kebutuhan') !== '') $o[] = 'Jumlah: ' . $v('jumlah_kebutuhan');
                if ($v('tingkat_urgensi') !== '') $o[] = 'Urgensi: ' . $v('tingkat_urgensi');
                if ($v('alasan') !== '') $o[] = 'Alasan: ' . $v('alasan');
            } else {
                if ($v('jenis_pemeliharaan') !== '') $o[] = 'Jenis pemeliharaan: ' . $v('jenis_pemeliharaan');
                if ($v('uraian') !== '') $o[] = 'Uraian: ' . $v('uraian');
            }
            break;
    }
    return $o;
}

/** Siapa yang memutuskan (teks) + lewat mana, dibaca dari data yang sudah diputuskan. */
function acc_pemutus(string $tipe, mixed $r): string
{
    $teks = '';
    if ($tipe === 'kegiatan') {
        $p = acc_g($r, 'disetujui_oleh', null);
        $arr = is_array($p) && array_is_list($p) ? $p : ($p ? [$p] : []);
        $t = [];
        foreach ($arr as $x) { $nm = trim((string)acc_g($x, 'nama', '')); $pr = trim((string)acc_g($x, 'peran', '')); if ($nm !== '' || $pr !== '') $t[] = ($nm !== '' ? $nm : '-') . ($pr !== '' ? " ($pr)" : ''); }
        $teks = implode(', ', $t);
    } elseif ($tipe === 'pengajuan') {
        $nm = trim((string)acc_g($r, 'acc_nama', '')); $pr = trim((string)acc_g($r, 'acc_oleh', ''));
        $teks = $nm !== '' ? $nm . ($pr !== '' ? " ($pr)" : '') : $pr;
    }
    if ($teks === '') {
        $ket = (string)acc_g($r, 'keterangan_persetujuan', '');
        if (preg_match('/(?:Di-?ACC|Disetujui|Ditolak) oleh (.+?)(?: pada |: |\.$|$)/u', $ket, $m)) $teks = trim($m[1]);
    }
    if ($teks === '' && $tipe === 'surat') {
        $roles = array_map(fn($a) => (string)acc_g($a, 'role', ''), (array)acc_g($r, 'acc', []));
        $teks = implode(', ', array_filter($roles));
    }
    $via = (string)acc_g($r, 'acc_via', '');
    return ($teks !== '' ? $teks : 'pimpinan') . ($via !== '' ? " — via $via" : ' — via Dashboard');
}

/** Alasan penolakan (jika ada) dari data yang ditolak. */
function acc_alasan(string $tipe, mixed $r): string
{
    if ($tipe === 'pengajuan' && trim((string)acc_g($r, 'alasan_penolakan', '')) !== '') return trim((string)acc_g($r, 'alasan_penolakan', ''));
    return preg_match('/Ditolak oleh [^:]+: (.+)$/su', (string)acc_g($r, 'keterangan_persetujuan', ''), $m) ? trim($m[1]) : '';
}

/* ============================== Penerapan keputusan (dari WhatsApp) ============================== */

/** Hitung stok Peralatan yang masih tersedia (sama dengan pemeriksaan di accPengajuan() dashboard). */
function acc_sisa_stok(array $db, string $nama, string $exceptId): ?int
{
    $n = wa_norm_nama($nama);
    $alat = null;
    foreach ($db['peralatan'] ?? [] as $a) if (wa_norm_nama((string)acc_g($a, 'nama_barang', '')) === $n) { $alat = $a; break; }
    if (!$alat) return null;
    $dipinjam = 0; $dipelihara = 0;
    foreach ($db['peminjaman'] ?? [] as $p) {
        if (acc_g($p, 'jenis_barang') === 'Peralatan' && wa_norm_nama((string)acc_g($p, 'nama_barang', '')) === $n
            && acc_g($p, 'status') !== 'Dikembalikan' && acc_g($p, 'tanggal_kembali') === '') $dipinjam += (int)acc_g($p, 'jumlah', 1) ?: 1;
    }
    foreach ($db['pemeliharaan'] ?? [] as $p) {
        if ((string)acc_g($p, 'id') === $exceptId) continue;
        if (acc_status('pengajuan', $p) === 'Disetujui' && (acc_g($p, 'kategori_pengajuan') ?: 'Pemeliharaan') === 'Pemeliharaan'
            && acc_g($p, 'jenis_barang') === 'Peralatan' && wa_norm_nama((string)acc_g($p, 'nama_barang', '')) === $n
            && acc_g($p, 'status') === 'Sedang Dikerjakan') $dipelihara += (float)acc_g($p, 'jumlah', 1) ?: 1;
    }
    return (int)floor((float)acc_g($alat, 'jumlah_baik', 0) - $dipinjam - $dipelihara);
}

/** Pengajuan Pemeliharaan Kendaraan yang disetujui mengubah kondisi kendaraan (syncKendaraanDariPemeliharaan di dashboard). */
function acc_sync_kendaraan(array &$db, mixed $r, array $oleh): void
{
    if (acc_g($r, 'jenis_barang') !== 'Kendaraan' || (acc_g($r, 'kategori_pengajuan') ?: 'Pemeliharaan') !== 'Pemeliharaan') return;
    $n = wa_norm_nama((string)acc_g($r, 'nama_barang', ''));
    foreach ($db['kendaraan'] ?? [] as $i => $k) {
        if (wa_norm_nama((string)acc_g($k, 'nama_kendaraan', '')) !== $n) continue;
        $kond = (string)acc_g($k, 'kondisi', '');
        if ($kond === 'Scrap') return;
        $baru = $kond;
        $st = (string)acc_g($r, 'status', '');
        if ($st === 'Sedang Dikerjakan') $baru = 'Unserviceable';
        elseif ($st === 'Selesai') { $ks = (string)acc_g($r, 'kondisi_setelah', ''); $baru = $ks === 'Baik / Layak Pakai' ? 'Serviceable' : ($ks !== '' ? 'Unserviceable' : $kond); }
        if ($baru !== $kond) {
            $db['kendaraan'][$i]['kondisi'] = $baru;
            $db['kendaraan'][$i]['diperbarui_oleh'] = ['nama' => $oleh['nama'], 'peran' => $oleh['peran']];
            $db['kendaraan'][$i]['diperbarui_waktu'] = wa_now_str();
        }
        return;
    }
}

/** Surat Masuk disetujui -> Kegiatan/Tugas terbentuk otomatis (padanan buatKegiatanDariSurat di dashboard). */
function acc_buat_kegiatan_dari_surat(array &$db, mixed $sm, array $oleh, string $ket): string
{
    $rows = wa_rows($db, 'kegiatan');
    $jenis = (string)acc_g($sm, 'jenis', 'lainnya');
    $now = wa_now_str();
    $k = [
        'id' => wa_next_id('KGT', $rows), 'nomor' => str_pad((string)(count($rows) + 1), 3, '0', STR_PAD_LEFT) . '/KGT/' . date('Y'),
        'jenis' => $jenis, 'jenis_label' => (string)acc_g($sm, 'jenis_label', WA_JENIS_KEGIATAN[$jenis] ?? 'Lainnya'),
        'nama' => (string)acc_g($sm, 'perihal', ''), 'tanggal' => (string)acc_g($sm, 'tanggal_kegiatan', ''), 'tanggal_selesai' => (string)acc_g($sm, 'tanggal_kegiatan', ''),
        'waktu' => (string)acc_g($sm, 'waktu', ''), 'lokasi' => (string)acc_g($sm, 'lokasi', ''), 'penanggung_jawab' => (string)acc_g($sm, 'penanggung_jawab', ''),
        'no_hp' => (string)acc_g($sm, 'no_hp', ''), 'deskripsi' => (string)acc_g($sm, 'deskripsi', ''), 'jumlah_dibutuhkan' => max(1, (int)acc_g($sm, 'jumlah_dibutuhkan', 1)),
        'status_persetujuan' => 'Disetujui', 'keterangan_persetujuan' => $ket, 'anggota_ids' => [],
        'status_pelaksanaan' => 'Draft', 'status_wa' => 'Belum Terkirim', 'wa_log' => [], 'dokumentasi' => '',
        'file_pdf' => acc_g($sm, 'file_pdf', null), 'surat_id' => (string)acc_g($sm, 'id', ''),
        'diajukan_oleh' => acc_g($sm, 'diajukan_oleh', null), 'disetujui_oleh' => ['nama' => $oleh['nama'], 'peran' => $oleh['peran']],
        'acc_via' => 'WhatsApp', 'acc_nomor' => $oleh['nomor'], 'dibuat' => $now, 'diperbarui' => $now,
    ];
    if ((string)acc_g($sm, 'wa_pengirim', '') !== '') { $k['wa_pengirim'] = (string)acc_g($sm, 'wa_pengirim', ''); $k['sumber'] = 'WhatsApp'; }
    $linked = wa_autofill($db, $jenis, $k);
    if ($linked) $k['linked_record_id'] = $linked;
    $db['kegiatan'] = array_merge($rows, [$k]);
    return $k['id'];
}

/**
 * Terapkan ACC/TOLAK dari WhatsApp. $db diubah langsung.
 * Kembalikan [berhasil(bool), pesan balasan untuk pimpinan].
 */
function acc_putuskan(array &$db, string $tipe, int $idx, bool $setuju, string $alasan, array $oleh): array
{
    $t = ACC_TIPE[$tipe];
    $r = &$db[$t['key']][$idx];
    $id = (string)acc_g($r, 'id', '');
    $st = acc_status($tipe, $r);
    if ($st !== ACC_MENUNGGU) {
        return [false, "ℹ️ {$t['label']} $id sudah *$st* oleh " . acc_pemutus($tipe, $r) . ".\nKeputusan yang sudah final tidak dapat diubah lagi."];
    }
    $now = wa_now_str();
    $ya = $setuju ? 'Disetujui' : 'Ditolak';
    $alasan = trim($alasan) !== '' ? trim($alasan) : '(tanpa keterangan)';
    $orang = "{$oleh['peran']} ({$oleh['nama']})";
    $extra = '';

    if ($tipe === 'pengajuan' && $setuju
        && (acc_g($r, 'kategori_pengajuan') ?: 'Pemeliharaan') === 'Pemeliharaan' && acc_g($r, 'jenis_barang') === 'Peralatan' && acc_g($r, 'status') === 'Sedang Dikerjakan') {
        $sisa = acc_sisa_stok($db, (string)acc_g($r, 'nama_barang', ''), $id);
        if ($sisa !== null && ((float)acc_g($r, 'jumlah', 1) ?: 1) > $sisa)
            return [false, "⚠️ Pengajuan $id belum bisa di-ACC: stok tersedia hanya " . max(0, $sisa) . ", jumlah unit melebihi stok. Ubah pengajuan di dashboard dulu."];
    }

    acc_s($r, 'acc_via', 'WhatsApp');
    acc_s($r, 'acc_nomor', $oleh['nomor']);
    switch ($tipe) {
        case 'surat':
            acc_s($r, 'status', $ya);
            acc_s($r, 'diperbarui', $now);
            if ($setuju) {
                $acc = (array)acc_g($r, 'acc', []);
                $acc[] = ['role' => $oleh['peran'], 'nama' => $oleh['nama'], 'waktu' => $now];
                acc_s($r, 'acc', $acc);
                $ket = "Di-ACC oleh {$oleh['peran']} ({$oleh['nama']}) pada $now.";
                acc_s($r, 'keterangan_persetujuan', $ket);
                $kid = acc_buat_kegiatan_dari_surat($db, $r, $oleh, "Disetujui (ACC {$oleh['peran']}) berdasarkan Surat Masuk $id nomor " . acc_g($r, 'nomor_surat', '') . '.');
                $r = &$db[$t['key']][$idx]; // $db['kegiatan'] berubah; referensi surat tetap dipegang ulang
                acc_s($r, 'kegiatan_id', $kid);
                $extra = "\nKegiatan/Tugas *$kid* terbentuk otomatis.";
            } else {
                acc_s($r, 'keterangan_persetujuan', "Ditolak oleh {$oleh['peran']} ({$oleh['nama']}): $alasan");
            }
            break;
        case 'kegiatan':
            acc_s($r, 'status_persetujuan', $ya);
            acc_s($r, 'disetujui_oleh', ['nama' => $oleh['nama'], 'peran' => $oleh['peran']]);
            acc_s($r, 'diperbarui', $now);
            if ($setuju) {
                acc_s($r, 'keterangan_persetujuan', "Disetujui oleh {$oleh['peran']} pada $now.");
            } else {
                acc_s($r, 'keterangan_persetujuan', "Ditolak oleh {$oleh['peran']}: $alasan");
                acc_s($r, 'status_pelaksanaan', 'Draft');
                // Data di modul jenis yang terbentuk otomatis dari kegiatan ini ikut dihapus (seperti rejectKegiatan di dashboard).
                $jn = (string)acc_g($r, 'jenis', ''); $lid = (string)acc_g($r, 'linked_record_id', '');
                foreach (array_keys(WA_AUTOFILL) as $mk) {
                    $mr = wa_rows($db, $mk);
                    $baru = array_values(array_filter($mr, fn($x) => !(($mk === $jn && $lid !== '' && acc_g($x, 'id') === $lid) || acc_g($x, 'dari_kegiatan_id') === $id)));
                    if (count($baru) !== count($mr)) $db[$mk] = $baru;
                }
                acc_s($r, 'linked_record_id', '');
            }
            break;
        case 'pengajuan':
            acc_s($r, 'status_persetujuan', $ya);
            acc_s($r, 'acc_oleh', $oleh['peran']);
            acc_s($r, 'acc_nama', $oleh['nama']);
            acc_s($r, 'acc_waktu', $now);
            if ($setuju) {
                acc_s($r, 'keterangan_persetujuan', "Disetujui oleh {$oleh['peran']} ({$oleh['nama']}) pada $now.");
                acc_s($r, 'alasan_penolakan', '');
                acc_sync_kendaraan($db, $r, $oleh);
            } else {
                acc_s($r, 'alasan_penolakan', $alasan);
                acc_s($r, 'keterangan_persetujuan', "Ditolak oleh {$oleh['peran']} ({$oleh['nama']}) pada $now: $alasan");
            }
            break;
    }
    return [true, ($setuju ? "✅ *$id DISETUJUI*" : "❌ *$id DITOLAK*") . "\n{$t['label']}: " . preg_replace('/^[^:]+: /u', '', acc_ringkas($tipe, $r)[0] ?? '')
        . ($setuju ? '' : "\nAlasan: $alasan") . $extra
        . "\n\nPengirim akan diberi tahu. Data ini sudah final: tombol ACC/Tolak di dashboard tidak aktif lagi."];
}

/** Daftar data yang menunggu keputusan (perintah MENUNGGU). */
function acc_daftar_menunggu(array $db): string
{
    $baris = []; $total = 0;
    foreach (acc_semua($db) as [$tipe, $i, $r]) {
        if (!acc_status_eksplisit($tipe, $r) || acc_status($tipe, $r) !== ACC_MENUNGGU) continue;
        $total++;
        if (count($baris) >= 15) continue;
        $judul = (string)acc_g($r, $tipe === 'surat' ? 'perihal' : ($tipe === 'kegiatan' ? 'nama' : 'nama_barang'), '');
        $baris[] = '• *' . acc_g($r, 'id') . '* (' . ACC_TIPE[$tipe]['label'] . ') ' . mb_strimwidth($judul, 0, 50, '…');
    }
    if ($total === 0) return '✅ Tidak ada data yang menunggu persetujuan.';
    return "⏳ *Menunggu persetujuan ($total)*\n" . implode("\n", $baris) . ($total > 15 ? "\n… dan " . ($total - 15) . ' lainnya (lihat dashboard)' : '')
        . "\n\nBalas *ACC <ID>* atau *TOLAK <ID> alasan*.";
}

function acc_bantuan(): string
{
    return "🔐 *Perintah pimpinan*\n• *ACC <ID>* — menyetujui (mis. ACC KGT-045)\n• *TOLAK <ID> alasan* — menolak (mis. TOLAK KGT-045 bentrok jadwal)\n• *MENUNGGU* — daftar data yang menunggu keputusan\n\nKeputusan pertama (WhatsApp atau dashboard) bersifat final.";
}

/* ============================== Notifikasi (idempoten) ============================== */

function acc_pesan_permintaan(array $db, string $tipe, mixed $r): string
{
    $t = ACC_TIPE[$tipe];
    $id = (string)acc_g($r, 'id');
    [$namaPengaju] = acc_pengirim($db, $r);
    return "🔔 *PERMINTAAN PERSETUJUAN*\n{$t['label']} · ID: *$id*\n" . implode("\n", acc_ringkas($tipe, $r))
        . "\nDiajukan oleh: " . ($namaPengaju !== '' ? $namaPengaju : '-')
        . "\n\nBalas pesan ini:\n✅ *ACC $id*\n❌ *TOLAK $id alasan penolakan*\n\nKeputusan pertama (WhatsApp atau dashboard) bersifat final; setelah itu tombol ACC/Tolak di sisi lain tidak aktif.";
}

function acc_pesan_hasil(string $tipe, mixed $r): string
{
    $t = ACC_TIPE[$tipe];
    $id = (string)acc_g($r, 'id');
    $setuju = acc_status($tipe, $r) === 'Disetujui';
    $alasan = $setuju ? '' : acc_alasan($tipe, $r);
    return ($setuju ? "✅ *{$t['label']} DISETUJUI*" : "❌ *{$t['label']} DITOLAK*") . "\nID: *$id*\n" . implode("\n", acc_ringkas($tipe, $r))
        . "\n" . ($setuju ? 'Disetujui' : 'Ditolak') . ' oleh: ' . acc_pemutus($tipe, $r)
        . ($alasan !== '' ? "\nAlasan: $alasan" : '')
        . ($tipe === 'surat' && $setuju && (string)acc_g($r, 'kegiatan_id', '') !== '' ? "\nKegiatan/Tugas terbentuk: " . acc_g($r, 'kegiatan_id') : '');
}

/**
 * Kirim notifikasi yang belum terkirim. Aman dipanggil berulang/bersamaan: tiap peristiwa "diklaim" lewat tabel acc_notif
 * sebelum dikirim. Pemanggilan pertama setelah fitur dipasang hanya mencatat kondisi saat ini (tanpa mengirim apa pun),
 * agar data lama tidak membanjiri WhatsApp.
 */
function acc_sweep(): void
{
    $p = pdo();
    $ada = (bool)$p->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'acc_notif'")->fetchColumn();
    $p->exec('CREATE TABLE IF NOT EXISTS acc_notif (kunci TEXT PRIMARY KEY, waktu TEXT NOT NULL)');
    $baseline = !$ada;
    $db = load_db();
    $klaim = $p->prepare('INSERT OR IGNORE INTO acc_notif (kunci, waktu) VALUES (?, ?)');
    $cek = $p->prepare("SELECT COUNT(*) FROM acc_notif WHERE kunci LIKE ? ESCAPE '\\' AND waktu <> 'baseline'");
    $approvers = acc_approvers();

    foreach (acc_semua($db) as [$tipe, $i, $r]) {
        if (!acc_status_eksplisit($tipe, $r)) continue;
        $st = acc_status($tipe, $r);
        $id = (string)acc_g($r, 'id');
        $likeId = 'M|' . $tipe . '|' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $id) . '|%';

        if ($st === ACC_MENUNGGU) {
            $stempel = (string)(acc_g($r, 'diajukan_waktu', '') ?: acc_g($r, 'dibuat', '') ?: acc_g($r, 'diperbarui', ''));
            $klaim->execute(['M|' . $tipe . '|' . $id . '|' . $stempel, $baseline ? 'baseline' : date('c')]);
            if ($klaim->rowCount() === 0 || $baseline) continue;
            $pesan = acc_pesan_permintaan($db, $tipe, $r);
            foreach ($approvers as $no) wa_kirim($no, $pesan);
            wa_log("ACC permintaan $id -> " . implode(',', $approvers));
        } elseif ($st === 'Disetujui' || $st === 'Ditolak') {
            if ($tipe === 'kegiatan' && (string)acc_g($r, 'surat_id', '') !== '') continue; // kegiatan hasil ACC surat: sudah diberitahukan lewat surat
            $klaim->execute(['F|' . $tipe . '|' . $id . '|' . $st . '|' . md5((string)acc_g($r, 'keterangan_persetujuan', '')), $baseline ? 'baseline' : date('c')]);
            if ($klaim->rowCount() === 0 || $baseline) continue;
            $viaWa = (string)acc_g($r, 'acc_via', '') === 'WhatsApp';
            $pesan = acc_pesan_hasil($tipe, $r);
            [, $noPengirim] = acc_pengirim($db, $r);
            $dikirim = [];
            if ($noPengirim !== '' && !($viaWa && $noPengirim === wa_nomor((string)acc_g($r, 'acc_nomor', '')))) {
                wa_kirim($noPengirim, $pesan);
                $dikirim[] = $noPengirim;
            }
            if (!$viaWa) { // diputuskan di dashboard: beri tahu pimpinan yang tadi menerima permintaan
                $cek->execute([$likeId]);
                if ((int)$cek->fetchColumn() > 0) {
                    foreach ($approvers as $no) if (!in_array($no, $dikirim, true))
                        wa_kirim($no, "ℹ️ {$id} sudah *" . mb_strtolower($st) . "* di dashboard oleh " . acc_pemutus($tipe, $r) . ".\nBalasan ACC/TOLAK lewat WhatsApp untuk data ini tidak berlaku lagi.");
                }
            }
            wa_log("ACC hasil $id ($st) -> pengirim " . ($noPengirim !== '' ? $noPengirim : 'tidak ada nomor WA'));
        }
    }
}

/** Jalankan acc_sweep() setelah respons HTTP selesai dikirim (tidak memperlambat dashboard/webhook). */
function acc_sweep_nanti(): void
{
    static $terdaftar = false;
    if ($terdaftar) return;
    $terdaftar = true;
    ignore_user_abort(true);
    register_shutdown_function(static function (): void {
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        @set_time_limit(120);
        try { acc_sweep(); } catch (Throwable $e) { wa_log('ACC SWEEP GALAT: ' . $e->getMessage()); }
    });
}

/* ============================== Penjaga keputusan di sisi server ============================== */

/** Kolom persetujuan per tipe yang dilindungi setelah ada keputusan final. */
const ACC_KOLOM = [
    'surat'     => ['status', 'acc', 'keterangan_persetujuan', 'kegiatan_id', 'acc_via', 'acc_nomor'],
    'kegiatan'  => ['status_persetujuan', 'keterangan_persetujuan', 'disetujui_oleh', 'acc_via', 'acc_nomor'],
    'pengajuan' => ['status_persetujuan', 'keterangan_persetujuan', 'alasan_penolakan', 'acc_oleh', 'acc_nama', 'acc_waktu', 'acc_via', 'acc_nomor'],
];

/**
 * Dipanggil api.php sebelum menyimpan snapshot dari dashboard. Data yang di server sudah Disetujui/Ditolak
 * tidak boleh berubah keputusannya oleh halaman dashboard yang belum diperbarui ("keputusan pertama menang").
 * Satu-satunya pengecualian: Pengajuan yang ditolak lalu diperbaiki & diajukan ulang (Ditolak -> Menunggu).
 * $objs = hasil json_decode non-assoc yang dipakai save_db untuk kunci tambahan (pemeliharaan).
 * Kembalikan jumlah data yang dikembalikan ke keputusan server.
 */
function acc_jaga_keputusan(array &$new, ?object $objs, array $old): int
{
    $konflik = 0;
    $suratKonflik = [];
    foreach (ACC_TIPE as $tipe => $t) {
        $key = $t['key'];
        $oldFinal = [];
        foreach ($old[$key] ?? [] as $r) {
            $id = (string)acc_g($r, 'id', '');
            if ($id !== '' && acc_status_eksplisit($tipe, $r) && in_array(acc_status($tipe, $r), ['Disetujui', 'Ditolak'], true)) $oldFinal[$id] = $r;
        }
        if (!$oldFinal) continue;
        $targets = [];
        if (isset($new[$key]) && is_array($new[$key]) && $tipe !== 'pengajuan') $targets[] = &$new[$key];
        if ($tipe === 'pengajuan' && $objs !== null && isset($objs->$key) && is_array($objs->$key)) $targets[] = &$objs->$key;
        foreach ($targets as &$rows) {
            foreach ($rows as &$r) {
                $id = (string)acc_g($r, 'id', '');
                if (!isset($oldFinal[$id])) continue;
                $o = $oldFinal[$id];
                if ($tipe === 'pengajuan' && acc_status($tipe, $r) === ACC_MENUNGGU && acc_status($tipe, $o) === 'Ditolak') continue; // diajukan ulang
                $beda = false;
                foreach (ACC_KOLOM[$tipe] as $k) if (json_encode(acc_g($r, $k, null)) !== json_encode(acc_g($o, $k, null))) { $beda = true; break; }
                if (!$beda) continue;
                foreach (ACC_KOLOM[$tipe] as $k) {
                    $has = is_object($o) ? property_exists($o, $k) : array_key_exists($k, $o);
                    if ($has) acc_s($r, $k, acc_g($o, $k, null)); else acc_unset($r, $k);
                }
                if ($tipe === 'kegiatan' && acc_status($tipe, $o) === 'Ditolak') acc_s($r, 'status_pelaksanaan', 'Draft');
                if ($tipe === 'surat') $suratKonflik[$id] = true;
                $konflik++;
            }
            unset($r);
        }
        unset($rows);
    }
    // Kegiatan ganda hasil ACC surat di dashboard yang sudah usang: buang beserta data modul turunannya.
    $final = [];
    foreach ($old['surat_masuk'] ?? [] as $s) if (in_array((string)acc_g($s, 'status'), ['Disetujui', 'Ditolak'], true)) $final[(string)acc_g($s, 'id')] = true;
    $kegLama = [];
    foreach ($old['kegiatan'] ?? [] as $k) $kegLama[(string)acc_g($k, 'id')] = true;
    $hapus = [];
    foreach ($new['kegiatan'] ?? [] as $k) {
        $sid = (string)acc_g($k, 'surat_id', '');
        $kid = (string)acc_g($k, 'id', '');
        if ($sid !== '' && isset($suratKonflik[$sid]) && isset($final[$sid]) && !isset($kegLama[$kid])) $hapus[$kid] = true;
    }
    if ($hapus) {
        $new['kegiatan'] = array_values(array_filter($new['kegiatan'], fn($k) => !isset($hapus[(string)acc_g($k, 'id', '')])));
        foreach (array_keys(WA_AUTOFILL) as $mk) {
            if (!isset($new[$mk]) || !is_array($new[$mk])) continue;
            $new[$mk] = array_values(array_filter($new[$mk], fn($x) => !isset($hapus[(string)acc_g($x, 'dari_kegiatan_id', '')])));
        }
    }
    return $konflik;
}
