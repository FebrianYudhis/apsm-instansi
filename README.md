# APSM Instansi

APSM (Aplikasi Pengelolaan Surat Menyurat) adalah aplikasi web untuk mengelola surat masuk, surat keluar, dokumen digital, klasifikasi, berkas arsip, dan proses alih media. Aplikasi menyediakan ruang kerja internal berdasarkan tahun serta portal publik untuk menelusuri arsip yang dapat diakses masyarakat.

Versi saat ini: **0.1.0**. Catatan rilis tersedia di [CHANGELOG.md](CHANGELOG.md).

## Fitur Utama

- Pengelolaan surat masuk, surat keluar, dokumen digital, klasifikasi, dan berkas.
- Pemberkasan surat, perpindahan status arsip, retensi, dan perpindahan tahun kerja.
- Dukungan surat SRIKANDI dan nomor agenda unik per tahun.
- Portal arsip publik dengan pencarian, pagination, dan pembatasan atribut hasil.
- Dokumen private dengan akses admin, akses publik, atau signed URL setelah verifikasi MFA.
- Alih media PDF melalui queue dengan watermark, retry, dan status proses terstruktur.
- Ekspor Excel untuk klasifikasi, pencatatan surat, daftar berkas, dan alih media.
- Activity log untuk autentikasi, mutasi arsip, dan ekspor.
- Audit integritas read-only untuk data serta file arsip production.
- Validasi PDF berdasarkan isi dan ukuran, rate limiting, serta perlindungan formula injection pada spreadsheet.

## Teknologi

- PHP 8.3 atau lebih baru
- Laravel 13
- MySQL/MariaDB untuk penggunaan utama; SQLite didukung untuk pengujian
- Laravel database queue
- Vite 8, Tailwind CSS 4, Bootstrap 4, dan jQuery
- Pest 4

Ekstensi PHP penting mencakup `fileinfo`, `gd`, `dom`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, dan `zip`.

## Instalasi

Persyaratan lokal:

- PHP dan Composer
- Node.js dan npm
- MySQL atau MariaDB
- Ekstensi PHP yang dibutuhkan Composer

Clone repository, lalu jalankan setup otomatis:

```bash
composer run setup
```

Script tersebut memasang dependency PHP dan JavaScript, membuat `.env`, menghasilkan `APP_KEY`, menjalankan migration, dan membangun aset frontend.

Sesuaikan koneksi database dan konfigurasi aplikasi di `.env`, kemudian isi data referensi:

```bash
php artisan db:seed
```

Jalankan aplikasi untuk development:

```bash
composer run dev
```

Perintah tersebut menjalankan server Laravel, queue listener, dan Vite secara bersamaan.

### Setup Manual

Gunakan langkah berikut bila tidak memakai `composer run setup`:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate --force
php artisan db:seed
npm run build
```

Pada Windows PowerShell, gunakan `Copy-Item .env.example .env` sebagai pengganti `cp`.

## Konfigurasi

Nilai berikut perlu diperiksa sebelum aplikasi digunakan:

```dotenv
APP_NAME=APSM
APP_URL=http://localhost:8000
APP_PENCIPTA_ARSIP="Nama Instansi"
START_YEAR=2025

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apsm-instansi
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=960

DOCUMENT_MAX_UPLOAD_KB=102400
DOCUMENT_GUEST_LINK_MINUTES=2
MFA_SECRET=
```

Keterangan:

- `APP_PENCIPTA_ARSIP` digunakan pada dokumen ekspor.
- `START_YEAR` menentukan tahun kerja paling awal yang dapat dipilih.
- `DOCUMENT_MAX_UPLOAD_KB` menentukan batas upload PDF dalam kilobyte.
- `DOCUMENT_GUEST_LINK_MINUTES` menentukan masa berlaku signed URL dokumen terbatas.
- `MFA_SECRET` wajib diisi untuk akses dokumen terbatas dan tindakan yang dilindungi MFA.
- `QUEUE_CONNECTION` harus menggunakan `database` agar proses watermark berjalan sesuai konfigurasi aplikasi.

Gunakan secret MFA Base32 yang kuat dan unik. Jangan commit `.env` atau nilai secret ke repository.

## Penyimpanan Dokumen

Dokumen arsip disimpan melalui disk `documents` pada storage private. File tidak boleh dipindahkan ke `public/` atau disajikan langsung melalui symbolic link. Semua akses dokumen harus melewati route dan `DocumentService` agar pemeriksaan hak akses, path, signed URL, dan versi watermark tetap berlaku.

Pastikan proses web dan queue worker memiliki izin baca/tulis ke:

```text
storage/app/private
storage/framework
bootstrap/cache
```

## Akun Awal

`php artisan db:seed` membuat data referensi dan akun development berikut:

```text
Username: admin
Password: admin123
```

Ganti password akun tersebut segera setelah deployment. Seeder tidak akan mereset akun admin yang sudah ada.

## Queue Alih Media

Watermark PDF diproses secara asynchronous. Jalankan worker khusus aplikasi:

```bash
php artisan alih-media:queue
```

Opsi yang tersedia:

```bash
php artisan alih-media:queue --once
php artisan alih-media:queue --stop-when-empty
```

Untuk production, jalankan command worker sebagai service yang dipantau Supervisor, systemd, atau process manager setara. Worker menggunakan timeout 900 detik dan satu percobaan per job; kegagalan dicatat dan status berkas dipulihkan oleh job.

## Command Operasional

Audit integritas database dan file arsip tanpa mengubah data:

```bash
php artisan audit:integritas-production
php artisan audit:integritas-production --year=2026
php artisan audit:integritas-production --format=json
php artisan audit:integritas-production --no-orphans
```

Command menghasilkan exit code gagal bila menemukan masalah integritas, sehingga dapat digunakan pada monitoring atau CI terjadwal.

Hapus activity log yang berumur lebih dari 12 bulan:

```bash
php artisan activitylog:clean
```

## Pengujian

Suite pengujian menggunakan SQLite in-memory sehingga tidak menyentuh database development:

```bash
php artisan test --compact
```

Jalankan formatter PHP setelah mengubah kode:

```bash
vendor/bin/pint --format agent
```

Build aset production:

```bash
npm run build
```

## Deployment

Minimal langkah deployment:

```bash
composer install --no-dev --optimize-autoloader
npm install --ignore-scripts
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Setelah itu:

1. Pastikan `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` sesuai domain HTTPS.
2. Ganti kredensial admin awal dan gunakan `MFA_SECRET` production.
3. Berikan izin storage kepada user proses web dan queue.
4. Jalankan `php artisan alih-media:queue` sebagai service persisten.
5. Jadwalkan `php artisan activitylog:clean` sesuai kebijakan operasional.
6. Jalankan `php artisan audit:integritas-production` setelah deployment dan secara berkala.

Jangan menjalankan `storage:link` untuk mengekspos dokumen arsip. Disk public hanya untuk aset yang memang ditujukan bagi publik.

## Struktur Modul

```text
app/Console/Commands   Command operasional dan audit
app/Http/Controllers  Alur web, dokumen, ekspor, dan autentikasi
app/Jobs              Pemrosesan watermark asynchronous
app/Models            Model domain arsip
app/Services          Dokumen, filter, locking, audit, dan spreadsheet
database/migrations   Skema database terurut
database/seeders      Data referensi dan akun awal
resources/views       Antarmuka internal dan portal publik
tests/Feature         Pengujian alur, keamanan, ekspor, dan integritas
```

## Lisensi

Proyek menggunakan lisensi MIT sebagaimana dinyatakan pada `composer.json`.
