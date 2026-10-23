# Dashboard Program BASARNAS (PHP + SQLite)

## Isi paket
| File / folder | Fungsi |
|---|---|
| `index.php` | Halaman dashboard (antarmuka) |
| `api.php` | API simpan/muat data dan upload file |
| `lib.php` | Pustaka database (membuat database otomatis, simpan/muat) |
| `login.php` | Halaman Masuk & Daftar (pengguna membuat ID dan kata sandi sendiri) |
| `logout.php` | Keluar dari akun |
| `kelola_akun.php` | Halaman Kelola Akun (khusus Admin Utama): tambah akun, reset kata sandi, ubah nama & peran, hapus akun |
| `buat_admin.php` | Skrip terminal untuk membuat / mengatur ulang akun Admin Utama (tidak bisa dibuka lewat web) |
| `auth.php` | Pustaka login: sesi, hash kata sandi, CSRF, pembatasan percobaan gagal, pengaturan pendaftaran |
| `database/schema.sql` | Skema tabel (SQLite) |
| `database/seed.json` | Data awal: 151 anggota, 4 kegiatan, contoh data tiap modul |
| `database/basarnas.sql` | Dump lengkap skema + data awal (untuk dibuka di DB Browser for SQLite, atau diimpor ke database lain) |
| `u.php` | Halaman upload lampiran dari tautan yang dikirim bot WA (tanpa login, token 8 karakter, 1 foto/PDF per tautan) |
| `wa_inti.php` | Inti input WA/formulir: spesifikasi tiap modul, parser, validasi, pencatatan, teks balasan |
| `wa_lampiran.php` | Pustaka lampiran WA: token upload, kompres foto, simpan file ke data |
| `data/` | Dibuat/diisi otomatis: `basarnas.db` (database), `auth.db` (akun pengguna) dan `uploads/` (file Sprin, bukti, hasil kegiatan) |
| `.htaccess`, `.user.ini` | Proteksi folder & batas upload (Apache / PHP-FPM) |

## Kebutuhan
- PHP 8.0 atau lebih baru dengan ekstensi **pdo_sqlite** (aktif secara bawaan di XAMPP/Laragon/hosting umum)
- Folder `data/` dapat ditulis oleh web server
- Koneksi internet saat membuka dashboard (pustaka Tailwind, SheetJS, ExcelJS, jsPDF, pdf.js dimuat dari CDN)

## Cara menjalankan
**Cepat (tanpa Apache):**
```
cd basarnas-dashboard
php -S localhost:8000
```
lalu buka http://localhost:8000

**XAMPP / Laragon / Apache:** salin folder `basarnas-dashboard` ke `htdocs` (atau `www`), buka `http://localhost/basarnas-dashboard/`.

**Hosting / Linux:** unggah semua file, lalu pastikan folder `data` dapat ditulis:
```
chmod -R 775 data
chown -R www-data:www-data data
```

Database `data/basarnas.db` dibuat otomatis (lengkap dengan data awal) saat halaman pertama kali dibuka.

## Catatan penting
- **Login & akun.** Dashboard dan API hanya bisa dibuka setelah login. Pengguna mendaftar sendiri di tab "Daftar" (ID 3–32 karakter huruf/angka/`.`/`_`/`-`, kata sandi minimal 8 karakter). Kata sandi disimpan sebagai hash di `data/auth.db`, bukan teks biasa. Setelah 5 kali salah, ID+IP dikunci 10 menit; sesi berakhir setelah 8 jam tanpa aktivitas.
- **Pengaturan pendaftaran** ada di bagian atas `auth.php`: `REGISTRASI_TERBUKA` (false = tutup pendaftaran), `KODE_PENDAFTARAN` (isi kode agar hanya yang tahu kode bisa mendaftar), `SESI_TIMEOUT`.
- **Admin Utama.** Akun dengan peran `admin_utama` memiliki akses penuh: peran "Admin Utama" di pojok kanan atas (otomatis terpilih) dapat melakukan semua fitur — ACC surat menggantikan seluruh pejabat, menyetujui kegiatan, upload/hapus Sprin, mengelola data — serta membuka **Kelola Akun** (tombol di header atau `kelola_akun.php`). Pendaftaran mandiri selalu menghasilkan akun biasa. Sistem menolak menghapus/mencabut Admin Utama terakhir.
- **Membuat / reset Admin Utama (terminal):** `php buat_admin.php` (ID `adminutama`, sandi acak) · `php buat_admin.php IDANDA` · `php buat_admin.php IDANDA "sandi baru"`. Bila ID sudah ada, kata sandinya diganti dan perannya dijadikan Admin Utama — jalan keluar jika semua Admin Utama lupa sandi.
- **Lupa kata sandi (akun biasa):** Admin Utama dapat mengatur ulang lewat Kelola Akun. Cara manual lama: belum ada fitur reset mandiri. Hapus baris akun di `data/auth.db` (tabel `users`, mis. dengan DB Browser for SQLite), lalu pengguna mendaftar ulang dengan ID yang sama.
- **Peran (KKS/KSD/KSO/KSB/Admin/Anggota)** dipilih saat mendaftar (tab "Daftar") dan tersimpan di akun; pilihan di header sudah dihapus. Peran dibaca dari database setiap membuka dashboard. Admin Utama dapat mengubah peran akun lain di Kelola Akun. Karena pendaftar memilih sendiri, isi `KODE_PENDAFTARAN` di `auth.php` bila peran berwenang (mis. KKS/Admin) tidak boleh dipilih sembarang orang. Satu nama lengkap hanya boleh memiliki satu akun (tanpa membedakan huruf besar/kecil).
- Jika dipasang di internet, gunakan HTTPS agar kata sandi tidak terkirim polos.
- **Nginx:** `.htaccess` tidak berlaku. Tambahkan aturan agar folder `data/` dan `database/` serta `lib.php` tidak bisa diakses langsung, mis. `location ~ ^/(data|database)/ { deny all; }` dan `location ~ ^/(lib|auth)\.php$ { deny all; }`.
- **Cadangan data:** cukup salin folder `data/` (berisi database dan semua file upload).
- **Mengulang dari awal:** hentikan penggunaan, hapus `data/basarnas.db`, lalu buka kembali halaman; data awal dibuat ulang.
- **Pembaruan otomatis (tanpa refresh).** Browser menanyakan nomor revisi ke `api.php?action=sync` setiap 4 detik (berhenti saat tab tidak terlihat). Jika pengguna/perangkat lain menyimpan perubahan, data dan tampilan diperbarui sendiri. Jika Anda sedang membuka form/modal, mengetik, atau ada perubahan yang belum tersimpan, pembaruan ditunda sampai selesai. Polling tidak memperpanjang waktu sesi login. Interval diubah lewat `SYNC_INTERVAL` di `index.php`.
- Penyimpanan bersifat satu snapshot (data terakhir yang disimpan menimpa sebelumnya). Cocok untuk satu pengguna / bergantian; jika beberapa orang mengedit bersamaan, perubahan orang yang menyimpan lebih dulu bisa tertimpa.
- Batas upload diatur di `.htaccess` / `.user.ini` (file 25MB, total data kiriman 128MB). Jika hosting mengabaikan file ini, naikkan `upload_max_filesize` dan `post_max_size` di `php.ini`.
- Impor ke MySQL/PostgreSQL: `database/schema.sql` perlu penyesuaian kecil (`AUTOINCREMENT`, `INSERT OR REPLACE`, dan fungsi di view); aplikasi ini sendiri berjalan dengan SQLite.

## Pembinaan Tenaga SAR — tab Absensi
- Tab **Absensi** ada di samping tab Samapta. Nama anggota yang dipilih di Samapta otomatis masuk ke Absensi (kehadiran awal: Hadir).
- Absensi juga bisa ditambah manual lewat tombol "+ Tambah Absensi".
- Data disimpan di kunci `pembinaan_absensi` (tabel `app_state`), tidak perlu mengubah skema database.

---

## Input data lewat WhatsApp

Pesan WhatsApp ke nomor **087891918976** otomatis dicatat ke dashboard (gateway: Fonnte).
Modul yang bisa diinput: **Kegiatan/Tugas, Persuratan, Pembinaan Tenaga SAR, Peralatan, Kendaraan, Peminjaman, Pengajuan**.

### Pemasangan
1. Unggah semua file (termasuk `wa_webhook.php` dan `wa_config.php`). Server wajib memakai HTTPS agar Fonnte dapat memanggil webhook.
2. Di Fonnte, buka Device 087891918976 → *Webhook* dan isi:
   `https://DOMAIN-ANDA/wa_webhook.php?key=<WA_WEBHOOK_KEY>` (nilai kunci ada di `wa_config.php`).
3. Isi **Nomor WhatsApp** tiap orang di menu *Daftar Anggota*. Hanya nomor yang terdaftar (atau tercantum di `WA_NOMOR_DIIZINKAN`) yang bisa menginput.
4. Uji: buka alamat webhook di browser → tampil `OK`. Lalu kirim `BANTUAN` dari nomor terdaftar.

Token dan kunci ada di `wa_config.php` (dilindungi `.htaccess`). Ganti `WA_WEBHOOK_KEY` bila bocor. Log pesan: `data/wa_log.txt`.

### Format pesan
Baris pertama = kata kunci, lalu satu data per baris dengan format `Nama: isi`. Ketik `FORMAT <modul>` untuk contoh.

| Kata kunci | Modul | Wajib |
|---|---|---|
| `KEGIATAN` | Kegiatan/Tugas (Jenis: Sar Go To School, Bimtek, Diklat, dll.; otomatis tercatat juga di modul sesuai Jenis) | Nama |
| `SURAT MASUK` / `SURAT KELUAR` | Persuratan | Nomor, Perihal / Nomor, Tujuan, Perihal |
| `PEMBINAAN` (`PEMBINAAN SAMAPTA`) | Pembinaan Tenaga SAR | Nama |
| `PERALATAN` | Peralatan | Nama |
| `KENDARAAN` | Kendaraan | Nama |
| `PINJAM` | Peminjaman (stok peralatan diperiksa) | Peminjam, Barang |
| `PENGAJUAN KEBUTUHAN` / `PENGAJUAN PEMELIHARAAN` | Pengajuan | Barang |

Kegiatan, Surat Masuk, dan Pengajuan masuk dengan status *Menunggu Persetujuan*; ACC tetap dilakukan di dashboard oleh pimpinan. Bot membalas dengan ID data atau alasan penolakan.

### Balasan bot, lampiran, dan perintah Selesai
Setelah data tersimpan, bot membalas seperti ini (contoh Kegiatan):

```
✅ Data tersimpan di *Kegiatan/Tugas*.
Kegiatan/Tugas: Uji Kompetensi BSG
...
🔖 *Simpan ID ini: KGT-044*
Kalau kegiatan ini sudah selesai dilaksanakan, balas dengan "Selesai KGT-044".

📷 Upload Lampiran PDF:
https://DOMAIN/u.php/ABCD2345
Maksimal 1 foto/file. Foto otomatis dikompres agar hemat penyimpanan.
Atau langsung kirim foto/PDF-nya di chat ini (pesan terpisah, tanpa teks) dalam 60 menit.
```

- **Tautan upload** (`u.php/KODE`): berlaku `WA_UPLOAD_LINK_HARI` hari, sekali pakai (maks. 1 foto/file, JPG/PNG/PDF, `WA_UPLOAD_MAKS_MB` MB). Foto dikecilkan di browser (maks. 1600 px) dan sekali lagi di server bila ekstensi PHP `gd` aktif. Alamat dasar tautan otomatis dari alamat yang dipakai Fonnte memanggil webhook; paksa lewat `WA_BASE_URL` di `wa_config.php` bila perlu (mis. `https://domain/kansar`).
- **Kirim langsung di chat WA**: foto/PDF tanpa teks, dalam `WA_UPLOAD_JENDELA_MENIT` menit sejak data tersimpan, masuk ke data terakhir yang disimpan nomor pengirim (yang belum punya lampiran). Server mengunduh file dari alamat `url` yang dikirim Fonnte (butuh PHP `curl` atau `allow_url_fopen`).
- Lampiran tampil di halaman detail data (Kegiatan, Surat Masuk, Pembinaan, Peralatan, Kendaraan, Peminjaman, Pengajuan) dan di kolom File Surat Keluar; tombol Lihat / Unduh / Hapus (Hapus untuk non-Anggota). Data di database: kolom `lampiran_files`.
- **`Selesai <ID>`** (khusus Kegiatan/Tugas): menandai pelaksanaan *Selesai*. Hanya bila kegiatan sudah *Disetujui*, dan hanya oleh pengaju via WA, penanggung jawab, anggota bertugas, atau nomor di `WA_NOMOR_DIIZINKAN`. `Selesai KEG-4` dianggap sama dengan `Selesai KGT-004`.
- Tautan upload tidak memakai login: siapa pun yang memegang tautan bisa mengirim 1 file untuk data itu. Karena itu masa berlaku dan sekali pakai dibatasi.
- **Nginx:** pastikan `u.php/KODE` diteruskan ke PHP (`try_files $uri $uri/ /index.php?$args;` tidak cukup; pakai `location ~ \.php(/|$) { fastcgi_split_path_info ^(.+\.php)(/.+)$; ... }`) dan blokir `wa_lampiran.php` seperti `lib.php`.

### Formulir lewat tautan (FORM)
Ketik `FORM KEGIATAN` (atau `FORM SURAT MASUK`, `FORM SURAT KELUAR`, `FORM PEMBINAAN`, `FORM PERALATAN`, `FORM KENDARAAN`, `FORM PINJAM`, `FORM PENGAJUAN`) ke nomor bot. Bot membalas tautan `u.php/KODE` berisi formulir kosong; isian, pilihan, dan validasinya sama dengan format pesan WA. Lampiran (1 foto/PDF) opsional di halaman yang sama. Setelah dikirim, data tercatat (status sama seperti input WA) dan bot mengirim konfirmasi berisi ID ke pengirim. Tautan sekali pakai, berlaku `WA_UPLOAD_LINK_HARI` hari, dan hanya dibuat untuk nomor yang terdaftar. Ketik `FORM` saja untuk daftar pilihan.

### Kirim Info Tugas + PDF Sprin ke anggota (tombol di detail Kegiatan)
Tombol **Kirim Info Tugas via WhatsApp** (muncul setelah kegiatan Disetujui, anggota dipilih, dan Sprin diupload) mengirim pesan WA ke nomor WhatsApp tiap anggota bertugas, beserta file PDF Sprin sebagai lampiran, lewat Fonnte (`api.php?action=wa_tugas`). Format pesan:
```
*NOTIFIKASI PENUGASAN*

Nama Kegiatan: ...
Tanggal Kegiatan: ...
Lokasi: ...
Jam: ... WIB
Anggota Yang Bertugas:
1. ...
```
Syarat: kolom **Nomor WhatsApp** anggota terisi di Daftar Anggota, ekstensi PHP `curl` aktif, dan device Fonnte terhubung. Anggota tanpa nomor / yang gagal dilaporkan setelah pengiriman. Template diubah di fungsi `wa_pesan_tugas()` (`wa_inti.php`).

**Dua pesan saat tombol kirim ditekan:** (1) info tugas + PDF Sprin, (2) *Link Update Kegiatan* (`u.php/<TOKEN>`: upload foto/PDF bukti penugasan, boleh berkali-kali → status *Dalam Pelaksanaan*), Setelah anggota mengisi update kegiatan (upload bukti), **otomatis dikirim** pesan ke-3: *Link Upload Laporan* (upload PDF maks. 25MB atau isi link, sekali pakai → status *Selesai*). Tautan dibuat per anggota (tercatat di kolom "oleh"), berlaku sampai 30 hari setelah kegiatan berakhir, dan tautan lama anggota itu dicabut saat info tugas dikirim ulang. Link laporan hanya bisa dipakai setelah kegiatan *Dalam Pelaksanaan* (aturan sama dengan dashboard). Atur alamat dasar tautan lewat `WA_BASE_URL` di `wa_config.php` bila perlu.

## ACC / Tolak lewat WhatsApp (nomor pimpinan 087879545233)

File: `wa_acc.php` (logika), pengaturan di `wa_config.php` (`WA_NOMOR_APPROVER`, `WA_APPROVER_PERAN`).
Berlaku untuk **Surat Masuk**, **Kegiatan/Tugas**, dan **Pengajuan** (Kebutuhan/Pemeliharaan).

1. Begitu ada data berstatus *Menunggu Persetujuan* (dari dashboard, WhatsApp, atau formulir tautan), bot mengirim
   **permintaan persetujuan** ke nomor pimpinan: ringkasan data + perintah balasan.
2. Pimpinan membalas dari nomor tersebut:
   - `ACC KGT-045` — menyetujui
   - `TOLAK KGT-045 alasan penolakan` — menolak
   - `MENUNGGU` — daftar data yang menunggu keputusan
   Pengaruhnya sama dengan tombol di dashboard (Surat Masuk di-ACC → Kegiatan terbentuk otomatis; Kegiatan ditolak →
   data modul turunannya dihapus; Pengajuan Pemeliharaan Kendaraan disetujui → kondisi kendaraan ikut berubah).
3. **Keputusan pertama menang.** Data yang sudah di-ACC/ditolak lewat WhatsApp tidak punya tombol ACC/Tolak lagi di
   dashboard; data yang sudah diputuskan di dashboard ditolak bila dibalas ACC/TOLAK lewat WhatsApp. `api.php` juga
   menjaga di sisi server supaya halaman dashboard yang belum diperbarui tidak menimpa keputusan yang sudah final.
4. **Notifikasi ke pengirim**: setiap keputusan (lewat WhatsApp maupun dashboard) dikirim ke pengaju berisi
   *Disetujui/Ditolak oleh siapa*, alasan (bila ditolak), dan jalurnya (WhatsApp/Dashboard). Bila keputusan diambil di
   dashboard, pimpinan juga diberi tahu bahwa balasan WhatsApp tidak berlaku lagi.

Catatan:
- Nomor pengirim ditemukan dari `wa_pengirim` (data yang diinput via WA/formulir) atau dari kecocokan **nama pengaju
  dengan Daftar Anggota** (kolom Nomor WhatsApp). Pengaju yang namanya/nomornya tidak ada di Daftar Anggota tidak
  mendapat notifikasi (tercatat di `data/wa_log.txt`).
- Nama pimpinan yang tercatat diambil dari Daftar Anggota berdasarkan nomor WhatsApp-nya; peran = `WA_APPROVER_PERAN`.
- Saat pertama kali dipasang, data yang sudah ada hanya dicatat sebagai baseline (tabel `acc_notif`): tidak ada
  pesan massal untuk data lama. Gunakan `MENUNGGU` untuk melihat data lama yang masih menunggu.
- Notifikasi dikirim setelah respons selesai (tidak memperlambat dashboard) dan idempoten (tidak terkirim dua kali).
- Pastikan webhook Fonnte aktif (lihat bagian Input data lewat WhatsApp) agar balasan ACC/TOLAK sampai ke server.
