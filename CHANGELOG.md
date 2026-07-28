# Changelog

Semua perubahan penting pada proyek ini didokumentasikan dalam berkas ini.

Format changelog mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) dan versi mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-07-28

### Added

- Pengujian regresi dengan tanggal surat dan tanggal diterima yang berbeda untuk memastikan filter surat masuk menggunakan kolom yang tepat.
- Pengujian regresi untuk memastikan aktivitas login dan logout masing-masing hanya dicatat satu kali.
- Pengujian rekonsiliasi referensi database dengan file private, file public, file hilang, file yatim, dan file alih media.

### Changed

- Daftar dan export surat masuk kini difilter serta diurutkan berdasarkan `tanggal_diterima`.
- Label periode pada filter dan file Excel surat masuk kini menggunakan istilah Tanggal Diterima.
- Filter surat keluar tetap menggunakan `tanggal_surat`.
- Command `audit:integritas-production` kini menampilkan jumlah referensi database dan file fisik per folder, termasuk status sesuai atau tidak sesuai.

### Fixed

- Memperbaiki range tanggal surat masuk yang sebelumnya keliru menggunakan `tanggal_surat`.
- Mencegah log login dan logout tercatat dua kali akibat listener didaftarkan secara otomatis sekaligus manual.

## [0.3.0] - 2026-07-28

### Added

- Disk `documents` untuk menyimpan dokumen arsip secara private di `storage/app/private`.
- Pengujian regresi untuk konfigurasi disk dokumen, penggunaan tahun aktif berbasis session, serta cleanup file ketika transaksi database gagal.
- Middleware dan service tahun aktif untuk menyediakan tahun kerja yang konsisten pada seluruh halaman terautentikasi.

### Changed

- Tahun aktif kini disimpan dalam session pengguna dan tidak lagi ditulis ke kolom `users.tahun`.
- Filter dashboard, daftar surat, penyimpanan surat, validasi akses, dan ekspor kini menggunakan tahun aktif dari session.
- Kolom `users.tahun` dihapus melalui migration karena pilihan tahun bersifat sementara untuk setiap session browser.

### Fixed

- Command audit integritas production kini dapat mengakses disk `documents` yang sebelumnya belum memiliki driver.
- File dokumen yang baru diunggah kini dihapus kembali apabila penyimpanan atau pembaruan data surat gagal dalam transaksi database.

## [0.2.1] - 2026-07-28

### Added

- Pengujian regresi untuk memastikan migration hanya membuat tabel `alih_media_statuses` dan seeder dapat dijalankan berulang kali tanpa menghasilkan data duplikat.

### Changed

- Data referensi status alih media Diproses, Selesai, Gagal, dan Ditutup kini sepenuhnya dikelola oleh `AlihMediaStatusSeeder`.
- Pengujian fitur yang membutuhkan status alih media kini menjalankan seeder secara eksplisit.

### Fixed

- Mencegah benturan primary key saat mengimpor data production `alih_media_statuses` ke database fresh yang telah menjalankan migration.

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
