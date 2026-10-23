<?php
/**
 * Pustaka database Dashboard BASARNAS (SQLite via PDO).
 * - pdo()      : koneksi; membuat database + data awal otomatis saat pertama kali dijalankan
 * - load_db()  : membaca seluruh data dari SQL menjadi struktur yang dipakai antarmuka
 * - save_db()  : menyimpan struktur dari antarmuka kembali ke tabel-tabel SQL
 */
declare(strict_types=1);

const DATA_DIR   = __DIR__ . '/data';
const UPLOAD_DIR = DATA_DIR . '/uploads';
const DB_FILE    = DATA_DIR . '/basarnas.db';
const SCHEMA_SQL = __DIR__ . '/database/schema.sql';
const SEED_JSON  = __DIR__ . '/database/seed.json';

const MODULE_TABLES = ['sgts','bimtek','potensi','narasumber','diklat','rescuer','pembinaan',
    'pemberdayaan','uji_periodik','uji_pemeliharaan','lainnya','peralatan','kendaraan','peminjaman'];
const CORE_KEYS = ['anggota','kegiatan','surat_masuk','uji_kategori','uji_imt'];
const DELETE_ORDER = ['kegiatan_anggota','kegiatan_wa_log','kegiatan_file','surat_masuk_acc','peserta_kegiatan',
    'uji_imt','uji_kategori','kegiatan','surat_masuk','anggota','app_state'];

const JSON_OUT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

function pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    foreach ([DATA_DIR, UPLOAD_DIR] as $d) {
        if (!is_dir($d) && !@mkdir($d, 0775, true)) {
            throw new RuntimeException("Folder '$d' tidak dapat dibuat. Beri izin tulis pada folder aplikasi.");
        }
    }
    if (!is_writable(DATA_DIR)) {
        throw new RuntimeException("Folder 'data' tidak dapat ditulis. Beri izin tulis (chmod 775) untuk user web server.");
    }
    $isNew = !is_file(DB_FILE) || filesize(DB_FILE) === 0;
    $p = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $p->exec('PRAGMA busy_timeout = 8000');
    $p->exec('PRAGMA foreign_keys = ON');
    if ($isNew) {
        try {
            $p->exec((string)file_get_contents(SCHEMA_SQL));
            if (is_file(SEED_JSON)) {
                $seed = json_decode((string)file_get_contents(SEED_JSON), true);
                if (is_array($seed)) save_db($p, $seed);
            }
        } catch (Throwable $e) {
            $p = null;
            @unlink(DB_FILE);
            throw $e;
        }
    }
    // Migrasi: kolom Tanggal Selesai pada Kegiatan/Tugas (database lama belum punya)
    if (!in_array('tanggal_selesai', array_column($p->query('PRAGMA table_info(kegiatan)')->fetchAll(), 'name'), true)) {
        $p->exec('ALTER TABLE kegiatan ADD COLUMN tanggal_selesai TEXT');
        $p->exec("UPDATE kegiatan SET tanggal_selesai = tanggal WHERE tanggal_selesai IS NULL");
    }
    $p->exec('CREATE TABLE IF NOT EXISTS sync_meta (k TEXT PRIMARY KEY, v INTEGER NOT NULL)');
    $p->exec("INSERT OR IGNORE INTO sync_meta (k, v) VALUES ('rev', 1)");
    return $pdo = $p;
}

/** Nomor revisi data: naik 1 setiap kali ada penyimpanan. Dipakai browser untuk mendeteksi perubahan dari pengguna lain. */
function get_rev(PDO $p): int
{
    return (int)$p->query("SELECT v FROM sync_meta WHERE k = 'rev'")->fetchColumn();
}
function bump_rev(PDO $p): int
{
    $p->exec("UPDATE sync_meta SET v = v + 1 WHERE k = 'rev'");
    return get_rev($p);
}

function table_cols(PDO $p, string $t): array
{
    static $cache = [];
    if (!isset($cache[$t])) {
        $cache[$t] = array_column($p->query("PRAGMA table_info($t)")->fetchAll(), 'name');
    }
    return $cache[$t];
}

/** Simpan satu baris: kunci yang cocok dengan kolom -> kolom; sisanya -> extra_json. */
function insert_row(PDO $p, string $table, array $row, array $skip = []): void
{
    $cols = table_cols($p, $table);
    $hasExtra = in_array('extra_json', $cols, true);
    $vals = [];
    $extra = [];
    foreach ($row as $k => $v) {
        if (in_array($k, $skip, true) || $k === 'extra_json') continue;
        if (in_array($k, $cols, true) && (is_scalar($v) || $v === null)) {
            $vals[$k] = ($v === '') ? null : (is_bool($v) ? (int)$v : $v);
        } else {
            $extra[$k] = $v;
        }
    }
    if ($hasExtra) {
        $vals['extra_json'] = $extra ? json_encode($extra, JSON_OUT) : null;
    }
    if (!$vals) return;
    $names = array_keys($vals);
    $sql = "INSERT OR REPLACE INTO $table (" . implode(',', array_map(fn($n) => '"' . $n . '"', $names)) . ') VALUES ('
        . implode(',', array_fill(0, count($names), '?')) . ')';
    $p->prepare($sql)->execute(array_values($vals));
}

function row_out(array $r): array
{
    $extra = [];
    if (isset($r['extra_json']) && $r['extra_json'] !== '') {
        $d = json_decode((string)$r['extra_json'], true);
        if (is_array($d)) $extra = $d;
    }
    unset($r['extra_json']);
    foreach ($r as $k => $v) if ($v === null) $r[$k] = '';
    return array_merge($r, $extra);
}

function fetch_all(PDO $p, string $table): array
{
    return $p->query("SELECT * FROM $table ORDER BY rowid")->fetchAll();
}

function group_by(array $rows, string $key): array
{
    $g = [];
    foreach ($rows as $r) $g[$r[$key]][] = $r;
    return $g;
}

function load_db(): array
{
    $p = pdo();
    $db = [];

    $db['anggota'] = array_map('row_out', fetch_all($p, 'anggota'));

    // Surat masuk
    $acc = group_by(fetch_all($p, 'surat_masuk_acc'), 'surat_id');
    $db['surat_masuk'] = [];
    foreach (fetch_all($p, 'surat_masuk') as $r) {
        $file = ($r['file_data'] !== null && $r['file_data'] !== '')
            ? ['name' => $r['file_name'], 'size' => (int)$r['file_size'], 'data' => $r['file_data']] : null;
        $o = row_out($r);
        unset($o['file_name'], $o['file_size'], $o['file_data']);
        $o['file_pdf'] = $file;
        $o['acc'] = array_map(fn($a) => ['role' => $a['role'], 'waktu' => (string)$a['waktu']], $acc[$r['id']] ?? []);
        $db['surat_masuk'][] = $o;
    }

    // Kegiatan / Tugas
    $ang = group_by(fetch_all($p, 'kegiatan_anggota'), 'kegiatan_id');
    $wa  = group_by(fetch_all($p, 'kegiatan_wa_log'), 'kegiatan_id');
    $fl  = group_by(fetch_all($p, 'kegiatan_file'), 'kegiatan_id');
    $db['kegiatan'] = [];
    foreach (fetch_all($p, 'kegiatan') as $r) {
        $lamp = ($r['lampiran_data'] !== null && $r['lampiran_data'] !== '')
            ? ['name' => $r['lampiran_name'], 'size' => (int)$r['lampiran_size'], 'data' => $r['lampiran_data']] : null;
        $k = row_out($r);
        unset($k['lampiran_name'], $k['lampiran_size'], $k['lampiran_data']);
        $k['file_pdf'] = $lamp;
        $k['anggota_ids'] = array_map(fn($a) => $a['anggota_id'], $ang[$r['id']] ?? []);
        $k['wa_log'] = array_map(fn($w) => ['waktu' => (string)$w['waktu'], 'jumlah' => (int)$w['jumlah']], $wa[$r['id']] ?? []);
        $k['hasil_files'] = [];
        $k['bukti_files'] = [];
        foreach ($fl[$r['id']] ?? [] as $f) {
            $m = ['fid' => $f['fid'], 'name' => (string)$f['name'], 'size' => (int)$f['size'],
                  'type' => (string)$f['mime'], 'uploaded' => (string)$f['uploaded']];
            if ($f['oleh'] !== null && $f['oleh'] !== '') $m['oleh'] = $f['oleh'];
            if ($f['mime'] === 'link') { $m['link'] = (string)$f['path']; $m['type'] = 'link'; } // hasil berupa tautan (bukan file)
            if ((int)$f['laporan'] === 1) $m['laporan'] = true;
            if ($f['kind'] === 'sprin') $k['sprin'] = $m;
            elseif ($f['kind'] === 'bukti') $k['bukti_files'][] = $m;
            else $k['hasil_files'][] = $m;
        }
        $db['kegiatan'][] = $k;
    }

    // Modul program + inventaris
    $peserta = group_by($p->query("SELECT * FROM peserta_kegiatan ORDER BY id")->fetchAll(), 'record_id');
    foreach (MODULE_TABLES as $m) {
        $db[$m] = [];
        foreach (fetch_all($p, $m) as $r) {
            $o = row_out($r);
            if ($m === 'diklat') {
                $pm = [];
                foreach ($peserta[$r['id']] ?? [] as $ps) {
                    if ($ps['record_id'] === $r['id'] && $ps['modul'] === 'diklat') {
                        $d = json_decode((string)$ps['data_json'], true);
                        if (is_array($d)) $pm[] = $d;
                    }
                }
                $o['peserta_matrix'] = $pm;
            }
            $db[$m][] = $o;
        }
    }

    $db['uji_kategori'] = array_map('row_out', fetch_all($p, 'uji_kategori'));

    $imt = new stdClass();
    foreach (fetch_all($p, 'uji_imt') as $r) {
        $jaw = json_decode((string)$r['jawaban_json'], true);
        $o = row_out($r);
        unset($o['jawaban_json'], $o['uji_id'], $o['anggota_id']);
        $o['jawaban'] = is_array($jaw) ? $jaw : [];
        $imt->{$r['uji_id'] . '|' . $r['anggota_id']} = $o;
    }
    $db['uji_imt'] = $imt;

    foreach ($p->query('SELECT key, value FROM app_state')->fetchAll() as $r) {
        $db[$r['key']] = json_decode((string)$r['value']); // objek tetap objek
    }
    return $db;
}

/** Simpan seluruh snapshot (transaksi tunggal). $objs = hasil json_decode non-assoc untuk kunci tambahan. */
function save_db(PDO $p, array $db, ?object $objs = null): int
{
    $p->exec('PRAGMA foreign_keys = OFF');
    $p->beginTransaction();
    try {
        foreach (DELETE_ORDER as $t) $p->exec("DELETE FROM $t");
        foreach (MODULE_TABLES as $t) $p->exec("DELETE FROM $t");

        $list = static function ($v): array {
            return is_array($v) ? array_filter($v, fn($r) => is_array($r) && isset($r['id']) && $r['id'] !== '') : [];
        };

        foreach ($list($db['anggota'] ?? []) as $r) insert_row($p, 'anggota', $r);

        foreach ($list($db['surat_masuk'] ?? []) as $r) {
            $f = $r['file_pdf'] ?? null;
            if (is_array($f)) {
                $r['file_name'] = $f['name'] ?? null;
                $r['file_size'] = $f['size'] ?? null;
                $r['file_data'] = $f['data'] ?? null;
            }
            insert_row($p, 'surat_masuk', $r, ['acc', 'file_pdf']);
            foreach (($r['acc'] ?? []) as $a) {
                if (!is_array($a)) continue;
                $p->prepare('INSERT INTO surat_masuk_acc (surat_id, role, waktu) VALUES (?,?,?)')
                  ->execute([$r['id'], (string)($a['role'] ?? ''), (string)($a['waktu'] ?? '')]);
            }
        }

        foreach ($list($db['kegiatan'] ?? []) as $k) {
            $f = $k['file_pdf'] ?? null;
            if (is_array($f)) {
                $k['lampiran_name'] = $f['name'] ?? null;
                $k['lampiran_size'] = $f['size'] ?? null;
                $k['lampiran_data'] = $f['data'] ?? null;
            }
            insert_row($p, 'kegiatan', $k, ['anggota_ids', 'wa_log', 'hasil_files', 'bukti_files', 'sprin', 'file_pdf']);
            foreach (array_unique((array)($k['anggota_ids'] ?? [])) as $a) {
                $p->prepare('INSERT OR IGNORE INTO kegiatan_anggota (kegiatan_id, anggota_id) VALUES (?,?)')->execute([$k['id'], $a]);
            }
            foreach (($k['wa_log'] ?? []) as $w) {
                if (!is_array($w)) continue;
                $p->prepare('INSERT INTO kegiatan_wa_log (kegiatan_id, waktu, jumlah) VALUES (?,?,?)')
                  ->execute([$k['id'], (string)($w['waktu'] ?? ''), (int)($w['jumlah'] ?? 0)]);
            }
            $files = [];
            foreach (($k['hasil_files'] ?? []) as $f) $files[] = ['hasil', $f];
            foreach (($k['bukti_files'] ?? []) as $f) $files[] = ['bukti', $f];
            if (!empty($k['sprin']) && is_array($k['sprin'])) $files[] = ['sprin', $k['sprin']];
            foreach ($files as [$kind, $f]) {
                if (!is_array($f) || empty($f['fid'])) continue;
                $p->prepare('INSERT OR REPLACE INTO kegiatan_file (fid, kegiatan_id, kind, name, size, mime, uploaded, oleh, laporan, path) VALUES (?,?,?,?,?,?,?,?,?,?)')
                  ->execute([$f['fid'], $k['id'], $kind, $f['name'] ?? null, $f['size'] ?? null, !empty($f['link']) ? 'link' : ($f['type'] ?? null),
                             $f['uploaded'] ?? null, $f['oleh'] ?? null, !empty($f['laporan']) ? 1 : 0,
                             !empty($f['link']) ? (string)$f['link'] : 'uploads/' . $f['fid']]);
            }
        }

        foreach (MODULE_TABLES as $m) {
            foreach ($list($db[$m] ?? []) as $r) {
                $skip = [];
                if ($m === 'diklat') $skip[] = 'peserta_matrix';
                insert_row($p, $m, $r, $skip);
                if ($m === 'diklat') {
                    foreach (($r['peserta_matrix'] ?? []) as $ps) {
                        if (!is_array($ps)) continue;
                        $p->prepare('INSERT INTO peserta_kegiatan (modul, record_id, anggota_id, nama, jabatan, jk, data_json) VALUES (?,?,?,?,?,?,?)')
                          ->execute(['diklat', $r['id'], $ps['anggota_id'] ?? null, $ps['nama'] ?? null, $ps['jabatan'] ?? null,
                                     $ps['jk'] ?? null, json_encode($ps, JSON_OUT)]);
                    }
                }
            }
        }

        foreach ($list($db['uji_kategori'] ?? []) as $r) insert_row($p, 'uji_kategori', $r);

        foreach ((array)($db['uji_imt'] ?? []) as $key => $v) {
            if (!is_array($v)) continue;
            [$uji, $ang] = array_pad(explode('|', (string)$key, 2), 2, '');
            $row = $v;
            unset($row['jawaban']);
            $row['uji_id'] = $uji;
            $row['anggota_id'] = $ang;
            $row['jawaban_json'] = json_encode($v['jawaban'] ?? [], JSON_OUT);
            insert_row($p, 'uji_imt', $row);
        }

        $known = array_merge(CORE_KEYS, MODULE_TABLES);
        foreach ($db as $key => $val) {
            if (in_array($key, $known, true)) continue;
            $src = ($objs !== null && property_exists($objs, (string)$key)) ? $objs->{$key} : $val;
            $p->prepare('INSERT OR REPLACE INTO app_state (key, value) VALUES (?,?)')->execute([(string)$key, json_encode($src, JSON_OUT)]);
        }

        $rev = bump_rev($p);
        $p->commit();
        return $rev;
    } catch (Throwable $e) {
        if ($p->inTransaction()) $p->rollBack();
        throw $e;
    } finally {
        $p->exec('PRAGMA foreign_keys = ON');
    }
}


/**
 * Status WhatsApp "Terkirim" yang sudah dicatat server tidak boleh ditimpa kembali menjadi "Belum Terkirim"
 * (dan riwayat kirimnya tidak boleh hilang) oleh halaman browser yang datanya belum diperbarui.
 * Pengecualian: daftar anggota bertugas memang berubah -> info tugas perlu dikirim ulang.
 */
function wa_jaga_status_kirim(array &$new, array $old): void
{
    $lama = [];
    foreach ($old['kegiatan'] ?? [] as $r) if (is_array($r) && !empty($r['id'])) $lama[(string)$r['id']] = $r;
    if (!$lama || !isset($new['kegiatan']) || !is_array($new['kegiatan'])) return;
    foreach ($new['kegiatan'] as $i => $r) {
        if (!is_array($r)) continue;
        $id = (string)($r['id'] ?? '');
        if (!isset($lama[$id])) continue;
        $o = $lama[$id];
        $a1 = (array)($o['anggota_ids'] ?? []); $a2 = (array)($r['anggota_ids'] ?? []);
        sort($a1); sort($a2);
        if ($a1 !== $a2) continue;
        if (($o['status_wa'] ?? '') === 'Terkirim' && ($r['status_wa'] ?? '') !== 'Terkirim') $new['kegiatan'][$i]['status_wa'] = 'Terkirim';
        if (count((array)($o['wa_log'] ?? [])) > count((array)($r['wa_log'] ?? []))) $new['kegiatan'][$i]['wa_log'] = $o['wa_log'];
    }
}
