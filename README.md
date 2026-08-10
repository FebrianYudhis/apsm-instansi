<a id="readme-top"></a>

<p align="center">
    <img src="public/gambar/icon/Logo.png" alt="Logo APSM Instansi" width="120">
</p>

<h1 align="center">APSM Instansi</h1>

<p align="center">
    Aplikasi pengelolaan surat dan arsip untuk pencatatan, pemberkasan, penelusuran, retensi, dan alih media.
</p>

<p align="center">
    <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+">
    <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
    <img src="https://img.shields.io/badge/Tests-Pest_4-F7A41D?style=flat-square" alt="Pest 4">
    <img src="https://img.shields.io/badge/License-MIT-2EA44F?style=flat-square" alt="Lisensi MIT">
</p>

## Tentang aplikasi

APSM menyediakan ruang kerja internal berdasarkan tahun aktif dan portal publik untuk menelusuri arsip. Dokumen disimpan pada storage private dan disajikan melalui route aplikasi agar pemeriksaan akses tetap berlaku.

## Fitur utama

- Pengelolaan surat masuk, surat keluar, surat digital, klasifikasi, dan berkas arsip.
- Pemberkasan, retensi, perubahan status arsip, serta pencatatan lokasi simpan.
- Dukungan surat SRIKANDI dan nomor agenda unik per tahun.
- Dashboard statistik dan prioritas pekerjaan.
- Portal pencarian arsip publik dengan akses dokumen terkontrol.
- Verifikasi MFA dan signed URL untuk dokumen terbatas.
- Alih media PDF melalui queue, termasuk pembuatan watermark.
- Ekspor klasifikasi, surat, berkas, dan alih media ke Excel.
- Activity log untuk autentikasi, perubahan data, penghapusan, dan ekspor.
- API berbasis Laravel Sanctum untuk integrasi surat dan data referensi.
- Audit integritas antara data aplikasi dan file dokumen.

## Persyaratan

- PHP 8.3 atau lebih baru beserta ekstensi yang diminta Composer.
- Composer.
- MySQL atau MariaDB.
- Node.js dan npm hanya jika aset frontend akan dikembangkan atau dibangun ulang.

## Instalasi

Pasang dependency dan buat konfigurasi lokal:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Untuk Windows PowerShell, salin file environment dengan:

```powershell
Copy-Item .env.example .env
```

Atur koneksi database dan konfigurasi aplikasi di `.env`, kemudian jalankan migration dan seeder:

```bash
php artisan migrate --seed
```

Seeder membuat akun awal berikut untuk instalasi baru:

```text
Username: admin
Password: admin123
```

Ganti password tersebut setelah login pertama.

Jalankan aplikasi dan worker alih media pada terminal terpisah:

```bash
php artisan serve
php artisan alih-media:queue
```

## Konfigurasi

Variabel yang perlu diperiksa pada `.env`:

| Variabel | Kegunaan |
| --- | --- |
| `APP_URL` | URL utama aplikasi. |
| `APP_PENCIPTA_ARSIP` | Nama pencipta arsip pada dokumen ekspor. |
| `START_YEAR` | Tahun kerja paling awal yang dapat dipilih. |
| `DB_*` | Koneksi database aplikasi. |
| `QUEUE_CONNECTION` | Koneksi queue; gunakan `database` untuk alih media. |
| `DB_QUEUE_RETRY_AFTER` | Waktu retry queue; nilainya harus lebih dari 900 detik. |
| `DOCUMENT_MAX_UPLOAD_KB` | Batas ukuran unggahan PDF dalam kilobyte. |
| `DOCUMENT_GUEST_LINK_MINUTES` | Masa berlaku signed URL dokumen terbatas. |
| `MFA_SECRET` | Secret Base32 untuk fitur yang dilindungi MFA. |

Setelah mengubah konfigurasi, bersihkan cache aplikasi:

```bash
php artisan optimize:clear
```

## API

API tersedia di bawah prefix `/api/v1` dan memerlukan Personal Access Token dengan ability `surat:create`.

Kirim token melalui header berikut:

```http
Authorization: Bearer TOKEN_ANDA
Accept: application/json
```

Endpoint yang tersedia:

| Method | Endpoint | Keterangan |
| --- | --- | --- |
| `GET` | `/api/v1/me` | Informasi pemilik token. |
| `GET` | `/api/v1/referensi/sifat-akses` | Referensi sifat akses. |
| `GET` | `/api/v1/referensi/berkas-aktif` | Referensi berkas aktif untuk pencatatan surat. |
| `GET` | `/api/v1/berkas` | Detail seluruh berkas non-deleted. |
| `POST` | `/api/v1/surat/masuk` | Mencatat surat masuk. |
| `POST` | `/api/v1/surat/keluar` | Mencatat surat keluar. |

Token dapat dibuat dan dicabut melalui halaman **Token API** setelah pengguna login. Halaman tersebut juga memuat panduan field untuk setiap jenis surat.

## Penyimpanan dokumen

Dokumen disimpan melalui disk `documents` di `storage/app/private`. Direktori ini tidak boleh disajikan langsung oleh web server atau dipindahkan ke `public/`.

Pastikan proses web dan queue memiliki akses baca/tulis ke:

```text
storage/app/private
storage/framework
bootstrap/cache
```

## Perintah operasional

```bash
# Menjalankan worker alih media
php artisan alih-media:queue

# Memeriksa integritas data dan dokumen
php artisan audit:integritas-production

# Menghapus activity log yang lebih lama dari masa retensi
php artisan activitylog:clean
```

Pada production, jalankan queue worker menggunakan process manager dan arahkan web root ke direktori `public/`.

## Pengujian dan format kode

```bash
php artisan test --compact
vendor/bin/pint --format agent
```

Test menggunakan konfigurasi pada `phpunit.xml` dan tidak ditujukan untuk dijalankan terhadap database production.

## Pengembangan frontend

Aset frontend siap pakai tersedia di `public/`. Jika mengubah sumber frontend, pasang dependency dan jalankan Vite:

```bash
npm install
npm run dev
```

Gunakan `npm run build` untuk menghasilkan aset production.

## Struktur proyek

```text
app/Console/Commands   Command queue dan operasional
app/Http               Controller, middleware, request, dan resource API
app/Jobs               Job pemrosesan dokumen
app/Models             Model domain aplikasi
app/Services           Layanan dokumen, berkas, ekspor, dan audit
database               Migration, seeder, dan factory
resources/views        Antarmuka internal dan portal publik
routes                  Route web dan API
storage/app/private    Dokumen arsip private
tests                   Test feature dan unit
```

## Changelog dan lisensi

Riwayat perubahan tersedia di [CHANGELOG.md](CHANGELOG.md). Proyek menggunakan lisensi MIT sebagaimana dinyatakan pada `composer.json`.

<p align="right">
    <a href="#readme-top">Kembali ke atas</a>
</p>
