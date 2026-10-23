-- =====================================================================
-- Skema database Dashboard Program BASARNAS (SQLite 3)
-- Kolom extra_json menampung isian tambahan yang tidak punya kolom sendiri
-- (kolom dinamis Uji Periodik, field turunan, dsb.) agar tidak ada data hilang.
-- =====================================================================
PRAGMA foreign_keys = ON;

CREATE TABLE ref_jenis_kegiatan (
  jenis TEXT PRIMARY KEY,
  label TEXT NOT NULL
);

CREATE TABLE anggota (
  id TEXT PRIMARY KEY,
  nama TEXT,
  nip TEXT,
  jabatan TEXT,
  unit TEXT,
  whatsapp TEXT,
  email TEXT,
  kompetensi TEXT,
  spesialisasi TEXT,
  sertifikasi TEXT,
  status TEXT CHECK (status IS NULL OR status IN ('Aktif','Nonaktif')),
  keterangan TEXT,
  extra_json TEXT
);

CREATE TABLE surat_masuk (
  id TEXT PRIMARY KEY,
  nomor_surat TEXT,
  asal_surat TEXT,
  tanggal_surat TEXT,
  tanggal_diterima TEXT,
  perihal TEXT,
  jenis TEXT REFERENCES ref_jenis_kegiatan(jenis),
  jenis_label TEXT,
  tanggal_kegiatan TEXT,
  waktu TEXT,
  lokasi TEXT,
  penanggung_jawab TEXT,
  no_hp TEXT,
  jumlah_dibutuhkan INTEGER,
  deskripsi TEXT,
  file_name TEXT,
  file_size INTEGER,
  file_data TEXT,
  status TEXT CHECK (status IS NULL OR status IN ('Menunggu Persetujuan','Disetujui','Ditolak')),
  keterangan_persetujuan TEXT,
  kegiatan_id TEXT,
  dibuat TEXT,
  diperbarui TEXT,
  extra_json TEXT
);

CREATE TABLE surat_masuk_acc (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  surat_id TEXT NOT NULL REFERENCES surat_masuk(id) ON DELETE CASCADE,
  role TEXT NOT NULL,
  waktu TEXT
);

CREATE TABLE kegiatan (
  id TEXT PRIMARY KEY,
  nomor TEXT,
  jenis TEXT REFERENCES ref_jenis_kegiatan(jenis),
  jenis_label TEXT,
  nama TEXT,
  tanggal TEXT,
  tanggal_selesai TEXT,
  waktu TEXT,
  lokasi TEXT,
  penanggung_jawab TEXT,
  no_hp TEXT,
  deskripsi TEXT,
  jumlah_dibutuhkan INTEGER,
  status_persetujuan TEXT CHECK (status_persetujuan IS NULL OR status_persetujuan IN ('Menunggu Persetujuan','Disetujui','Ditolak')),
  keterangan_persetujuan TEXT,
  status_pelaksanaan TEXT CHECK (status_pelaksanaan IS NULL OR status_pelaksanaan IN ('Draft','Dalam Pelaksanaan','Selesai')),
  status_wa TEXT,
  dokumentasi TEXT,
  surat_id TEXT,
  linked_record_id TEXT,
  lampiran_name TEXT,
  lampiran_size INTEGER,
  lampiran_data TEXT,
  dibuat TEXT,
  diperbarui TEXT,
  extra_json TEXT
);

CREATE TABLE kegiatan_anggota (
  kegiatan_id TEXT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
  anggota_id TEXT NOT NULL,
  PRIMARY KEY (kegiatan_id, anggota_id)
);

CREATE TABLE kegiatan_wa_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kegiatan_id TEXT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
  waktu TEXT,
  jumlah INTEGER
);

CREATE TABLE kegiatan_file (
  fid TEXT PRIMARY KEY,
  kegiatan_id TEXT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
  kind TEXT NOT NULL CHECK (kind IN ('sprin','bukti','hasil')),
  name TEXT,
  size INTEGER,
  mime TEXT,
  uploaded TEXT,
  oleh TEXT,
  laporan INTEGER DEFAULT 0,
  path TEXT
);

CREATE TABLE sgts (
  id TEXT PRIMARY KEY,
  nama_sekolah TEXT,
  alamat_sekolah TEXT,
  tanggal TEXT,
  waktu TEXT,
  jumlah_peserta INTEGER,
  materi TEXT,
  penanggung_jawab TEXT,
  narasumber TEXT,
  anggota_bertugas TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE bimtek (
  id TEXT PRIMARY KEY,
  nama_bimtek TEXT,
  jenis_bimtek TEXT,
  materi TEXT,
  tanggal TEXT,
  waktu TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  narasumber TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  petugas TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE potensi (
  id TEXT PRIMARY KEY,
  nama_potensi TEXT,
  kategori TEXT,
  nama_kontak TEXT,
  nomor_kontak TEXT,
  alamat TEXT,
  wilayah TEXT,
  kompetensi TEXT,
  jumlah_tersertifikasi INTEGER,
  jumlah_belum_sertifikasi INTEGER,
  status TEXT,
  alut_sapras TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE narasumber (
  id TEXT PRIMARY KEY,
  nama TEXT,
  instansi TEXT,
  jabatan TEXT,
  keahlian TEXT,
  nomor_kontak TEXT,
  email TEXT,
  materi TEXT,
  kegiatan_terkait TEXT,
  status TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE diklat (
  id TEXT PRIMARY KEY,
  nama_diklat TEXT,
  jenis_diklat TEXT,
  materi TEXT,
  tanggal_mulai TEXT,
  tanggal_selesai TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  instruktur TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  anggota_bertugas TEXT,
  sertifikat TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE rescuer (
  id TEXT PRIMARY KEY,
  anggota_id TEXT,
  jabatan TEXT,
  kompetensi TEXT,
  spesialisasi TEXT,
  sertifikasi TEXT,
  masa_berlaku TEXT,
  status TEXT,
  riwayat TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE pembinaan (
  id TEXT PRIMARY KEY,
  nama_kegiatan TEXT,
  jenis_pembinaan TEXT,
  sasaran TEXT,
  tanggal TEXT,
  lokasi TEXT,
  materi TEXT,
  pembina TEXT,
  peserta TEXT,
  anggota_bertugas TEXT,
  hasil TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE pemberdayaan (
  id TEXT PRIMARY KEY,
  nama_kegiatan TEXT,
  sasaran TEXT,
  lokasi TEXT,
  tanggal TEXT,
  jumlah_peserta INTEGER,
  materi TEXT,
  narasumber TEXT,
  petugas TEXT,
  hasil TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE uji_periodik (
  id TEXT PRIMARY KEY,
  nama_uji TEXT,
  jenis_uji TEXT,
  materi TEXT,
  tanggal_mulai TEXT,
  tanggal_selesai TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  penguji TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  anggota_bertugas TEXT,
  sertifikat TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE uji_pemeliharaan (
  id TEXT PRIMARY KEY,
  nama_peralatan TEXT,
  nomor_inventaris TEXT,
  jenis_peralatan TEXT,
  tanggal_pemeriksaan TEXT,
  kondisi TEXT,
  jenis_pemeliharaan TEXT,
  petugas TEXT,
  hasil TEXT,
  tindakan TEXT,
  jadwal_berikutnya TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE lainnya (
  id TEXT PRIMARY KEY,
  nama_kegiatan TEXT,
  jenis_kegiatan TEXT,
  tanggal TEXT,
  waktu TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  penanggung_jawab TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  anggota_bertugas TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE peralatan (
  id TEXT PRIMARY KEY,
  nama_barang TEXT,
  kode_rak TEXT,
  lokasi TEXT,
  kategori TEXT,
  jumlah_baik INTEGER,
  jumlah_rusak INTEGER,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE kendaraan (
  id TEXT PRIMARY KEY,
  nama_kendaraan TEXT,
  sapras TEXT,
  jenis_kendaraan TEXT,
  nomor_polisi TEXT,
  merk_tipe TEXT,
  kondisi TEXT,
  lokasi TEXT,
  tanggal TEXT,
  km_saat_ini REAL,
  bbm REAL,
  endurance REAL,
  speed REAL,
  range REAL,
  konsumsi_bbm REAL,
  cadangan_bbm REAL,
  persen_bbm REAL,
  kecepatan_angin REAL,
  arah_angin TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE peminjaman (
  id TEXT PRIMARY KEY,
  nama_peminjam TEXT,
  jenis_barang TEXT,
  nama_barang TEXT,
  jumlah INTEGER,
  tanggal TEXT,
  tanggal_kembali_rencana TEXT,
  tanggal_kembali TEXT,
  keperluan TEXT,
  penanggung_jawab TEXT,
  status TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);

CREATE TABLE peserta_kegiatan (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  modul TEXT NOT NULL CHECK (modul IN ('diklat','uji_periodik')),
  record_id TEXT NOT NULL,
  anggota_id TEXT,
  nama TEXT,
  jabatan TEXT,
  jk TEXT,
  data_json TEXT
);

CREATE TABLE uji_kategori (
  id TEXT PRIMARY KEY,
  kategori TEXT,
  uji_id TEXT,
  anggota_id TEXT,
  nama TEXT,
  jabatan TEXT,
  jk TEXT,
  rata_rata REAL,
  predikat TEXT,
  dibuat TEXT,
  diperbarui TEXT,
  extra_json TEXT
);

CREATE TABLE uji_imt (
  uji_id TEXT NOT NULL,
  anggota_id TEXT NOT NULL,
  jawaban_json TEXT,
  usia REAL,
  tb REAL,
  bb REAL,
  imt REAL,
  kategori TEXT,
  td REAL,
  nadi REAL,
  tanggal TEXT,
  diperbarui TEXT,
  extra_json TEXT,
  PRIMARY KEY (uji_id, anggota_id)
);

CREATE TABLE app_state (
  key TEXT PRIMARY KEY,
  value TEXT
);

CREATE INDEX idx_kegiatan_tanggal ON kegiatan(tanggal);
CREATE INDEX idx_kegiatan_status ON kegiatan(status_persetujuan, status_pelaksanaan);
CREATE INDEX idx_kegiatan_anggota ON kegiatan_anggota(anggota_id);
CREATE INDEX idx_peserta ON peserta_kegiatan(modul, record_id);
CREATE INDEX idx_uji_kat ON uji_kategori(uji_id, kategori);

CREATE VIEW v_potensi AS SELECT *, COALESCE(jumlah_tersertifikasi,0)+COALESCE(jumlah_belum_sertifikasi,0) AS jumlah_total FROM potensi;
CREATE VIEW v_rescuer AS SELECT a.id AS anggota_id, a.nama AS nama_anggota, a.nip, r.* FROM rescuer r JOIN anggota a ON a.id=r.anggota_id;
CREATE VIEW v_peralatan AS SELECT p.*, COALESCE(jumlah_baik,0)+COALESCE(jumlah_rusak,0) AS jumlah,
  MAX(0, COALESCE(jumlah_baik,0)+COALESCE(jumlah_rusak,0) - COALESCE((SELECT SUM(COALESCE(m.jumlah,1)) FROM peminjaman m
   WHERE m.jenis_barang='Peralatan' AND lower(trim(m.nama_barang))=lower(trim(p.nama_barang)) AND m.status<>'Dikembalikan' AND m.tanggal_kembali IS NULL),0)) AS tersedia
FROM peralatan p;
CREATE VIEW v_kegiatan_anggota AS SELECT ka.kegiatan_id, k.nama AS kegiatan, k.jenis, a.id AS anggota_id, a.nama
FROM kegiatan_anggota ka JOIN kegiatan k ON k.id=ka.kegiatan_id JOIN anggota a ON a.id=ka.anggota_id;

INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('sgts','Sar Go To School');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('bimtek','Bimtek');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('potensi','Potensi');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('narasumber','Narasumber');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('diklat','Diklat');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('rescuer','Rescuer');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('pembinaan','Pembinaan Tenaga SAR');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('pemberdayaan','Pemberdayaan Masyarakat');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('uji_periodik','Uji Periodik');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('uji_pemeliharaan','Uji Pembinaan & Pemeliharaan');
INSERT INTO ref_jenis_kegiatan (jenis,label) VALUES ('lainnya','Lainnya');
