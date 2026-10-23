<?php
/**
 * Pengaturan input data lewat WhatsApp (Dashboard BASARNAS).
 * File ini berisi token rahasia: jangan dibagikan dan jangan di-commit ke repositori publik.
 * Akses langsung lewat browser ditolak oleh .htaccess.
 */
declare(strict_types=1);

/** Penyedia gateway WhatsApp. Saat ini: 'fonnte' (device + token). */
const WA_PROVIDER = 'fonnte';

/** Nomor WhatsApp yang menerima pesan (nomor bot / device di Fonnte). */
const WA_NOMOR_BOT = '087891918976';

/** Token device dari gateway (Fonnte > Device > Token). */
const WA_TOKEN = '4y7WqFufp5mKpN4gEhsW';

/** Endpoint pengiriman balasan. */
const WA_API_URL = 'https://api.fonnte.com/send';

/**
 * Kunci rahasia webhook. Daftarkan URL webhook di Fonnte sebagai:
 *   https://DOMAIN-ANDA/wa_webhook.php?key=<WA_WEBHOOK_KEY>
 * Permintaan tanpa kunci yang cocok ditolak, sehingga orang lain tidak bisa menyuntik data.
 * Ganti nilai ini bila bocor.
 */
const WA_WEBHOOK_KEY = '8e898ae7e51bd2731594ff11b5dddbfd';

/**
 * true  = hanya nomor yang tercantum di Daftar Anggota (kolom Nomor WhatsApp) atau di
 *         WA_NOMOR_DIIZINKAN yang boleh menginput data.
 * false = siapa pun yang mengirim pesan ke nomor di atas boleh menginput (tidak disarankan).
 */
const WA_BATASI_PENGIRIM = true;

/** Nomor tambahan yang diizinkan (format bebas: 0812..., 62812..., +62 812...). */
const WA_NOMOR_DIIZINKAN = [
    // '081234567890',
];

/**
 * Nomor pimpinan yang berwenang ACC/TOLAK lewat WhatsApp. Bot mengirim permintaan persetujuan ke nomor ini
 * dan hanya menerima perintah ACC/TOLAK dari nomor ini (format bebas: 0878..., 62878..., +62 878...).
 */
const WA_NOMOR_APPROVER = ['087879545233'];

/** Peran yang dicatat di data untuk keputusan dari WhatsApp (mis. KKS, KSD, Pimpinan). Tampil sebagai "Nama (peran) — via WhatsApp". */
const WA_APPROVER_PERAN = 'Pimpinan';

/**
 * Tujuan grup WhatsApp untuk tombol "Kirim ke Grup" pada panel "Sprin Belum Dibuat" di Dashboard.
 * Isi dengan ID grup dari Fonnte (Device > Get Group), bentuknya 1203630xxxxxxxxx@g.us.
 * Kosong = tombol menampilkan pesan bahwa grup belum diatur.
 */
const WA_GRUP_SPRIN = '';

/** true = bot membalas pengirim (konfirmasi ID data / pesan galat / panduan format). */
const WA_BALAS = true;

/**
 * Alamat dasar dashboard untuk tautan upload lampiran yang dikirim bot (tanpa garis miring di akhir),
 * mis. 'https://contoh.ngrok-free.dev/kansar'. Kosongkan = otomatis dari alamat yang dipakai Fonnte memanggil webhook.
 * Tautan yang dikirim: <alamat dasar>/u.php/<KODE>
 */
const WA_BASE_URL = '';

/** Jendela waktu (menit) mengirim foto/PDF langsung ke chat WA setelah data tersimpan. */
const WA_UPLOAD_JENDELA_MENIT = 60;

/** Masa berlaku tautan upload (hari) dan ukuran maksimum satu file (MB). */
const WA_UPLOAD_LINK_HARI = 7;
const WA_UPLOAD_MAKS_MB = 10;
