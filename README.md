<a id="readme-top"></a>

<p align="center">
    <img src="public/gambar/icon/Logo.png" alt="Logo APSM Instansi" width="120">
</p>

<h1 align="center">APSM Instansi</h1>

<p align="center">
    <strong>Aplikasi Pengelolaan Surat Menyurat</strong><br>
    Pencatatan, pemberkasan, penelusuran, dan alih media arsip instansi dalam satu aplikasi.
</p>

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
    <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
    <img src="https://img.shields.io/badge/Database-MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white" alt="MySQL">
    <img src="https://img.shields.io/badge/Tests-Pest_4-F7A41D?style=flat-square" alt="Pest 4">
    <img src="https://img.shields.io/badge/License-MIT-2EA44F?style=flat-square" alt="Lisensi MIT">
</p>

<p align="center">
    <a href="#fitur-utama"><strong>Fitur</strong></a>
    &nbsp;&bull;&nbsp;
    <a href="#instalasi-baru"><strong>Instalasi</strong></a>
    &nbsp;&bull;&nbsp;
    <a href="#konfigurasi-env"><strong>Konfigurasi</strong></a>
    &nbsp;&bull;&nbsp;
    <a href="#queue-alih-media"><strong>Queue</strong></a>
    &nbsp;&bull;&nbsp;
    <a href="CHANGELOG.md">Changelog</a>
</p>

---

APSM menyediakan ruang kerja internal berdasarkan tahun serta portal publik untuk penelusuran arsip. Dokumen disimpan secara private dan hanya disajikan melalui alur akses yang dikendalikan aplikasi.

Panduan ini ditujukan untuk **instalasi baru** dengan database kosong dan direktori dokumen yang belum berisi data.

<a id="fitur-utama"></a>

## ✨ Fitur Utama

- **Persuratan** — Pengelolaan surat masuk, surat keluar, dokumen digital, klasifikasi, dan berkas arsip.
- **Ruang kerja tahunan** — Tahun aktif berbasis session, pemberkasan, status arsip, lokasi simpan, dan retensi.
- **Integrasi SRIKANDI** — Dukungan surat SRIKANDI serta nomor agenda surat masuk yang unik per tahun.
- **Dashboard operasional** — Statistik, prioritas pekerjaan, alih media, retensi, dan surat terbaru.
- **Portal publik** — Pencarian arsip dan akses dokumen berdasarkan sifat akses.
- **Akses dokumen** — Dokumen private, akses publik terkontrol, atau signed URL setelah verifikasi MFA.
- **Alih media** — Pemrosesan PDF melalui database queue, termasuk watermark dan status proses terstruktur.
- **Ekspor data** — Excel untuk klasifikasi, pencatatan surat, daftar berkas, dan daftar arsip alih media.
- **Jejak aktivitas** — Pencatatan autentikasi, perubahan data, penghapusan beserta alasannya, dan ekspor.
- **Audit integritas** — Pemeriksaan read-only untuk relasi database, referensi dokumen, file hilang, dan file yatim.
- **Perlindungan data** — Validasi PDF, rate limiting, dan pencegahan formula injection pada spreadsheet.

## 🧱 Teknologi dan Persyaratan

| Komponen | Kebutuhan |
| --- | --- |
| PHP | Versi 8.3 atau lebih baru |
| Framework | Laravel 13 |
| Dependency manager | Composer |
| Database | MySQL atau MariaDB |
| Queue | Database queue untuk proses alih media |
| Pengujian | Pest 4 dengan SQLite in-memory |

Antarmuka aplikasi saat ini memakai aset CSS dan JavaScript statis yang sudah tersedia di `public/`. Karena itu, **Node.js, `npm ci`, dan `npm run build` tidak diperlukan untuk deployment normal**. Tooling Vite/Tailwind di `package.json` hanya diperlukan jika aset pada `resources/css` atau `resources/js` akan dikembangkan dan dibangun ulang.

Pastikan ekstensi PHP yang diminta Composer tersedia. Untuk fitur aplikasi ini, ekstensi yang perlu diperhatikan antara lain `fileinfo`, `gd`, `mbstring`, `dom`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, dan `zip`.

---

<a id="instalasi-baru"></a>

## 🚀 Instalasi Baru

### 1. Siapkan aplikasi

Pasang dependency PHP dan buat file konfigurasi:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Pada server yang tidak membutuhkan dependency pengembangan, gunakan `composer install --no-dev --optimize-autoloader --no-interaction` sebagai pengganti `composer install`.

Pada Windows PowerShell, gunakan:

```powershell
Copy-Item .env.example .env
```

### 2. Buat database awal

Sesuaikan database dan konfigurasi aplikasi pada `.env`, lalu buat skema beserta data awal:

```bash
php artisan migrate --seed
```

Migration dan seeder tersebut ditujukan untuk database baru yang masih kosong.
Jika `APP_ENV=production`, tambahkan opsi `--force`:

```bash
php artisan migrate --seed --force
```

### 3. Amankan akun awal

Seeder membuat data referensi serta akun awal berikut:

```text
Username: admin
Password: admin123
```

Ganti password tersebut segera. Seeder tidak mengganti akun `admin` yang sudah ada dan akan berhenti bila username `admin` tidak menggunakan ID `1`.

### 4. Jalankan aplikasi

Untuk development sederhana, aplikasi dan queue dapat dijalankan pada terminal terpisah:

```bash
php artisan serve
php artisan alih-media:queue
```

<a id="konfigurasi-env"></a>

## ⚙️ Konfigurasi `.env`

### Konfigurasi dasar

Contoh konfigurasi dasar:

```dotenv
APP_NAME=APSM
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_PENCIPTA_ARSIP="Nama Instansi"
START_YEAR=2025

APP_LOCALE=id
APP_FALLBACK_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apsm-instansi
DB_USERNAME=apsm
DB_PASSWORD=

SESSION_DRIVER=file
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=960

DOCUMENT_MAX_UPLOAD_KB=102400
DOCUMENT_GUEST_LINK_MINUTES=2
MFA_SECRET=
```

### Penjelasan konfigurasi

- `APP_KEY` dibuat oleh `php artisan key:generate`. Simpan sebagai secret dan jangan commit `.env`.
- `APP_URL` harus berisi URL lengkap aplikasi tanpa garis miring di akhir. Sertakan subpath bila aplikasi tidak dipasang di root domain.
- Gunakan `SESSION_PATH=/` untuk instalasi di root domain, atau samakan dengan subpath aplikasi, misalnya `/apsm`.
- Gunakan `SESSION_SECURE_COOKIE=true` pada HTTPS dan `false` pada HTTP.
- `SESSION_DOMAIN=null` sesuai untuk instalasi pada satu host atau alamat IP.
- `APP_PENCIPTA_ARSIP` digunakan pada dokumen ekspor.
- `START_YEAR` menentukan tahun kerja paling awal yang dapat dipilih.
- `DOCUMENT_MAX_UPLOAD_KB` adalah batas upload PDF dalam kilobyte.
- `DOCUMENT_GUEST_LINK_MINUTES` adalah masa berlaku signed URL dokumen terbatas.
- `MFA_SECRET` harus berupa secret Base32 yang kuat dan wajib tersedia untuk fitur yang dilindungi MFA.
- `DB_QUEUE_RETRY_AFTER` harus lebih besar daripada timeout job watermark 900 detik.

Setelah mengubah `.env`, bersihkan cache konfigurasi sebelum memeriksa hasilnya:

```bash
php artisan optimize:clear
```

## 🔒 Penyimpanan Dokumen

Dokumen arsip disimpan melalui disk `documents` di:

```text
storage/app/private
```

File private tidak boleh dipindahkan ke `public/` atau disajikan langsung oleh web server. Akses dokumen harus melalui route aplikasi agar otorisasi, pembatasan path, signed URL, dan pemilihan versi watermark tetap berlaku.

User web server dan queue worker harus memiliki izin baca/tulis ke:

```text
storage/app/private
storage/framework
bootstrap/cache
```

`php artisan storage:link` tidak diperlukan untuk dokumen arsip private.

## ✅ Finalisasi Instalasi di Server

Setelah mengikuti langkah instalasi baru dan mengatur `.env`, jalankan:

```bash
php artisan optimize
php artisan audit:integritas-production
```

Aset frontend tidak perlu dibangun ulang karena layout utama memakai aset statis yang sudah tersedia di `public/`.

Checklist sebelum aplikasi dibuka:

- [ ] Database baru sudah dibuat dan migration beserta seeder berhasil.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` sudah sesuai URL instalasi.
- [ ] `APP_KEY`, `MFA_SECRET`, serta kredensial database tidak menggunakan nilai contoh.
- [ ] Password awal akun `admin` sudah diganti.
- [ ] Web root hanya mengekspos direktori `public/`.
- [ ] User web server dan queue memiliki permission storage yang diperlukan.
- [ ] Queue worker berjalan sebagai service persisten.
- [ ] Audit integritas selesai tanpa masalah.
- [ ] Login, portal publik, dokumen private, dokumen publik, signed URL MFA, dan health check sudah diuji.

---

<a id="queue-alih-media"></a>

## ⚡ Queue Alih Media

### Menjalankan worker

Watermark PDF diproses asynchronous melalui database queue:

```bash
php artisan alih-media:queue
```

Opsi untuk kebutuhan operasional:

```bash
php artisan alih-media:queue --once
php artisan alih-media:queue --stop-when-empty
```

Worker menggunakan queue `default`, timeout 900 detik, dan satu percobaan per job. Pada production, jalankan sebagai service yang dipantau Supervisor, systemd, atau process manager setara.

### Contoh Supervisor

```ini
[program:apsm-alih-media]
command=/usr/bin/php /var/www/apsm-instansi/artisan alih-media:queue
directory=/var/www/apsm-instansi
user=www-data
autostart=true
autorestart=true
stopwaitsecs=960
redirect_stderr=true
stdout_logfile=/var/log/apsm-alih-media.log
```

## 🛠️ Command Operasional

Audit integritas database dan file:

```bash
php artisan audit:integritas-production
php artisan audit:integritas-production --year=2026
php artisan audit:integritas-production --format=json
php artisan audit:integritas-production --no-orphans
```

`--no-orphans` melewati pemindaian file yatim untuk audit yang lebih cepat. Command mengembalikan exit code gagal bila menemukan masalah, sehingga dapat dipakai oleh monitoring.

Hapus activity log yang lebih lama dari 12 bulan:

```bash
php artisan activitylog:clean
```

## 🧪 Pengujian

Suite Pest menggunakan SQLite in-memory sehingga tidak menyentuh database development:

```bash
php artisan test --compact
```

Setelah mengubah PHP, jalankan formatter:

```bash
vendor/bin/pint --format agent
```

## 🗂️ Struktur Modul

```text
app/Console/Commands   Command operasional, queue, dan audit
app/Http/Controllers  Alur web, dokumen, ekspor, dan autentikasi
app/Jobs              Pemrosesan watermark asynchronous
app/Models            Model domain arsip
app/Services          Dokumen, filter, locking, audit, dan spreadsheet
database/migrations   Skema database
database/seeders      Data referensi dan akun awal
public                Aset statis dan front controller aplikasi
resources/views       Antarmuka internal dan portal publik
storage/app/private   Dokumen arsip private
tests/Feature         Pengujian alur, keamanan, ekspor, dan integritas
```

## 📄 Lisensi

Proyek menggunakan lisensi MIT sebagaimana dinyatakan pada `composer.json`.

<p align="right">
    <a href="#readme-top">Kembali ke atas ↑</a>
</p>
