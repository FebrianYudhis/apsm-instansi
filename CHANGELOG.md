# Changelog

Semua perubahan penting pada proyek ini didokumentasikan dalam berkas ini.

Format changelog mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) dan versi mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-07-28

### Added

- Pengujian arsitektur untuk menjaga pemisahan tanggung jawab controller dan konsistensi konfigurasi MFA.
- Pengujian perlindungan mass assignment untuk memastikan hanya atribut yang diizinkan yang dapat diisi melalui Eloquent.

### Changed

- Operasi isi berkas dan perubahan status berkas dipisahkan dari `BerkasController` ke controller serta service yang lebih terfokus.
- Pembuatan spreadsheet pencatatan surat masuk dan keluar dipindahkan ke service ekspor bersama untuk mengurangi duplikasi pada controller.
- Konfigurasi locale bawaan pada `.env.example` diubah ke Bahasa Indonesia melalui `id` dan `id_ID`.
- Pencatatan aktivitas model kini hanya merekam atribut yang diizinkan dan benar-benar berubah.

### Fixed

- Menyeragamkan konfigurasi MFA ke `services.mfa.secret` agar halaman pengaturan MFA, akses dokumen terbatas, alih media, dan perubahan status berkas menggunakan `MFA_SECRET` yang sama.

### Security

- Mengganti konfigurasi mass assignment terbuka dengan daftar `$fillable` eksplisit pada model akses, status alih media, klasifikasi, dokumen digital, berkas, surat masuk, surat keluar, dan status berkas.

## [0.1.0] - 2026-07-28

### Added

- Pengelolaan surat masuk, surat keluar, dokumen digital, klasifikasi, dan berkas arsip.
- Portal pencarian arsip publik dengan pagination dan akses dokumen berdasarkan sifat akses.
- Akses dokumen terbatas melalui MFA, signed URL berumur pendek, dan rate limiting.
- Penyimpanan dokumen asli dan hasil watermark pada storage private.
- Alur alih media dengan status Diproses, Selesai, Gagal, dan Ditutup.
- Pemrosesan watermark PDF melalui queue, termasuk retry dan pemulihan status saat job gagal.
- Ekspor Excel untuk klasifikasi, pencatatan surat, daftar berkas, dan daftar arsip alih media.
- Pencatatan aktivitas autentikasi, mutasi arsip, dan ekspor tanpa menyimpan isi dokumen sensitif.
- Halaman profil untuk memperbarui identitas dan password dengan verifikasi password saat ini.
- Perpindahan tahun kerja dengan validasi rentang tahun dan redirect internal yang aman.
- Filter daftar dan ekspor surat berdasarkan rentang tanggal serta status SRIKANDI.
- Dukungan surat SRIKANDI melalui flag `is_srikandi` pada surat masuk dan keluar.
- Status alih media terstruktur melalui relasi `alih_media_status_id` pada berkas.
- Unique index nomor agenda surat masuk per tahun.
- Normalisasi dan unique index kode klasifikasi aktif melalui `active_unique_key`, dengan dukungan penggunaan kembali kode yang sudah soft-delete.
- Penyajian versi watermark secara otomatis untuk akses dokumen publik dan terbatas jika tersedia.
- Validasi upload PDF berdasarkan isi dan batas ukuran file.
- Perlindungan path dokumen agar akses tetap berada dalam direktori storage yang diizinkan.
- Perlindungan formula injection pada seluruh ekspor spreadsheet.
- Validasi relasi, retensi, tahun kerja, filter ekspor, dan alur status arsip.
- Seeder dan factory untuk data referensi serta skenario pengujian arsip.
- Skema database awal dalam 12 migration berurutan untuk MySQL dan SQLite.
- Command `audit:integritas-production` untuk audit read-only data dan file arsip production.
- Command `alih-media:queue` untuk menjalankan worker queue alih media.
- Command `activitylog:clean` untuk menghapus log aktivitas yang berumur lebih dari 12 bulan.
