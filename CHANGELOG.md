# Changelog

Semua perubahan penting pada proyek ini didokumentasikan dalam berkas ini.

Format changelog mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) dan versi mengikuti [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.7.0] - 2026-07-29

### Added

- Menambahkan pengelolaan Personal Access Token berbasis Laravel Sanctum yang dapat dibuat dan dicabut sendiri oleh setiap pengguna.
- Menambahkan API versi 1 untuk mencatat surat masuk dan surat keluar SRIKANDI maupun non-SRIKANDI dengan unggahan PDF.
- Menambahkan endpoint referensi sifat akses dan berkas aktif untuk membantu integrasi mengisi relasi surat secara valid.
- Menambahkan pengujian autentikasi token, pembatasan ability, kepemilikan token, aturan jenis surat, penyimpanan dokumen, dan activity log API.
- Menambahkan modal panduan pengisian API dengan pilihan bertingkat untuk surat masuk/keluar SRIKANDI dan non-SRIKANDI beserta tabel field, contoh nilai, serta ketentuannya.

### Changed

- Pembuatan surat dari antarmuka web dan API kini menggunakan action yang sama agar validasi transaksi, penguncian berkas, serta pembersihan file gagal tetap konsisten.
- Activity log surat yang dibuat melalui API kini mencatat pengguna pemilik token sebagai pelaku beserta nama dan ID token tanpa menyimpan token asli.

### Security

- Token API disimpan dalam bentuk hash, hanya ditampilkan sekali saat dibuat, memiliki masa berlaku, dan dibatasi pada ability `surat:create`.
- Pembuatan token mewajibkan verifikasi kata sandi saat ini, sedangkan daftar dan pencabutan token dibatasi pada pemiliknya.
- Endpoint API hanya menerima Bearer Personal Access Token dan dilindungi oleh autentikasi Sanctum, pemeriksaan ability, rate limiting per pengguna, serta validasi isi dan ukuran PDF.
- Pembuatan dan pencabutan token dibatasi maksimal lima permintaan per menit untuk setiap pengguna.
- Payload SRIKANDI yang mengirim field khusus non-SRIKANDI ditolak dengan respons validasi `422` agar data kontradiktif tidak diterima diam-diam.
- Menambahkan regresi keamanan API untuk token kedaluwarsa/dicabut, relasi berkas tidak valid, foreign key dan tahun, mass assignment, metadata respons, PDF palsu/terlalu besar, duplikasi agenda, rate limiting aktual, serta escaping nama token.
- Halaman yang menampilkan token satu kali kini mengirim header `private`, `no-store`, dan `no-cache`, serta menonaktifkan autocomplete dan spellcheck pada field token.

## [0.6.2] - 2026-07-29

### Added

- Menambahkan halaman khusus yang menggabungkan surat masuk dan surat keluar non-SRIKANDI pada tahun aktif yang belum diberkaskan.
- Menambahkan pengujian regresi untuk tautan prioritas dashboard, cakupan tahun aktif, pengecualian surat SRIKANDI dan surat yang sudah diberkaskan, serta filter dan export berkas tanpa isi.

### Changed

- Tautan **Surat belum diberkaskan** pada dashboard kini membuka daftar surat yang sesuai dan menyediakan aksi untuk melihat detail atau langsung melakukan pemberkasan.
- Tautan **Berkas belum memiliki isi** kini membawa filter `isi=kosong` ke daftar berkas, menampilkan indikator filter aktif, dan mempertahankan filter tersebut pada export.
- Perhitungan dashboard dan halaman tujuan kini menggunakan scope query yang sama agar jumlah ringkasan tetap konsisten dengan data yang ditampilkan.

### Fixed

- Memperbaiki tautan prioritas dashboard yang sebelumnya tetap menampilkan daftar berkas umum tanpa menyaring data sesuai jenis temuan.

## [0.6.1] - 2026-07-29

### Added

- Menambahkan pengujian regresi untuk memastikan redirect autentikasi tetap menggunakan subpath aplikasi.
- Menambahkan pengujian aset CSS agar referensi lokal tidak kembali mengarah ke root host.

### Changed

- Route dashboard kini memiliki nama `dashboard` dan digunakan oleh navigasi serta seluruh redirect autentikasi.

### Fixed

- Memperbaiki redirect login dan middleware pengguna terautentikasi agar mempertahankan base URL seperti `/apsm`.
- Menghapus referensi gambar `down-arrow.png` dan `drag-indicator.png` yang tidak tersedia dari CSS Concept.

## [0.6.0] - 2026-07-29

### Added

- Menambahkan nama file PDF deskriptif yang dibentuk dari maksimal enam kata pertama perihal.
- Menambahkan nama file sebagai segmen URL dokumen admin, publik, dan tautan sementara MFA agar judul tab PDF mudah dikenali.
- Menambahkan pengujian regresi untuk nama file, prefix watermark, URL dokumen, redirect URL lama, dan bagian operasional dashboard.

### Changed

- Nama file ketika PDF dibuka atau diunduh kini menggunakan potongan perihal dengan pemisah tanda hubung serta timestamp waktu dibuka dalam format `ddMMyyyyHHmmss`.
- Dokumen watermark kini menggunakan prefix `wm-` pada nama tampilan, judul tab, dan nama file respons.
- Detail surat kini menampilkan nama file PDF asli dan watermark sebagai pengganti keterangan penyimpanan internal.
- Tautan PDF pada daftar surat, detail surat, surat digital, portal publik, dan akses MFA kini menggunakan URL deskriptif yang konsisten.
- Dashboard didesain ulang menjadi pusat kendali arsip dengan hero tahun aktif, kartu statistik interaktif, prioritas pekerjaan, alur status alih media, akses cepat, retensi akhir, dan daftar surat terbaru.
- Tata letak dashboard kini responsif untuk desktop dan perangkat seluler dengan hierarki informasi, jarak, warna status, serta kondisi kosong yang lebih jelas.
- CSS tema dashboard diperbarui dan varian minified disinkronkan tanpa menambah dependency frontend baru.

### Fixed

- Memperbaiki judul tab PDF pada area pengguna yang sebelumnya dapat tampil sebagai `asli`, `tampil`, atau `watermark`.
- URL dokumen admin lama tanpa nama file kini otomatis diarahkan ke URL deskriptif, termasuk ketika tautan berasal dari halaman yang masih tersimpan di cache.
- Memperbaiki karakter pemisah metadata surat terbaru yang sebelumnya dapat tampil sebagai karakter rusak.

### Security

- Redirect URL dokumen lama tetap memvalidasi jenis, path, dan keberadaan referensi dokumen sebelum menghasilkan URL kanonis.
- Akses dokumen publik, dokumen terbatas bertanda tangan, dan dokumen private tetap menggunakan pemeriksaan otorisasi serta pembatasan storage yang sudah berlaku.

## [0.5.1] - 2026-07-29

### Added

- Menambahkan pengujian regresi untuk memastikan tombol **Data Sekarang** hanya tersedia selama data terkait masih aktif.
- Menambahkan pengujian aset frontend untuk memastikan seluruh referensi CSS dan JavaScript lokal menggunakan berkas minified yang tersedia.

### Changed

- Mengganti `slimScroll` dengan scrolling native browser pada sidebar.
- Menghapus pemuatan `concept.js` karena tidak lagi memiliki fungsi aktif.
- Menggunakan varian `.min.css` dan `.min.js` untuk aset frontend lokal pada layout dan halaman daftar, sehingga ukuran aset hasil transformasi berkurang sekitar 32%.

### Fixed

- Memperbaiki error JavaScript `slimScroll is not a function` yang muncul pada hampir seluruh halaman admin.
- Menyembunyikan tombol **Data Sekarang** dari seluruh riwayat lama ketika data terkait sudah dihapus, sekaligus mempertahankannya untuk data yang masih aktif.

## [0.5.0] - 2026-07-29

### Added

- Audit penghapusan untuk surat masuk, surat keluar, dokumen digital, klasifikasi, dan berkas melalui kolom `deleted_by_user_id` serta `deletion_reason`.
- Relasi pelaku penghapusan dan metadata log yang menyimpan ID pengguna, nama pengguna, serta alasan penghapusan.
- Form konfirmasi penghapusan bersama dengan input alasan yang wajib diisi sepanjang 5 hingga 1000 karakter.
- Pengujian fitur untuk validasi alasan, pencatatan pelaku, metadata penghapusan, serta penyajian Log Aktivitas.

### Changed

- Seluruh proses penghapusan data kini mencatat pengguna yang melakukan penghapusan dan alasan yang diberikan.
- Metadata khusus penghapusan disimpan di dalam `properties.attributes` agar struktur daftar Log Aktivitas tetap umum.
- Kolom Deskripsi pada Log Aktivitas kini menggunakan badge berwarna dengan istilah Bahasa Indonesia.
- Kolom Bagian Data pada Log Aktivitas kini menampilkan nama yang mudah dipahami, seperti Surat Masuk, Surat Keluar, Surat Digital, Klasifikasi, Berkas, Autentikasi, dan Ekspor Data.
- Contoh nilai `APP_PENCIPTA_ARSIP` diperbarui menjadi Stasiun Meteorologi Kelas II H. Asan.

### Fixed

- Menyembunyikan tombol Data Sekarang pada aktivitas penghapusan karena data terkait sudah tidak tersedia pada daftar aktif.

### Security

- Atribut audit penghapusan tidak dapat diisi melalui mass assignment dan hanya dicatat melalui alur penghapusan terkontrol.
- Foreign key pelaku penghapusan menggunakan pembatasan penghapusan pengguna untuk menjaga keutuhan riwayat audit.

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
