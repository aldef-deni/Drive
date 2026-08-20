# Dekorasi Drive

Aplikasi penyimpanan file dan folder berbasis Laravel 11 untuk Dekorasi.me.
Produksi: <https://drive.dekorasi.me>

## Fitur

- Unggah, unduh, pindah (drag & drop), dan hapus file/folder
- Tiga mode tampilan: Daftar, Ikon Kecil, Ikon Besar
- Kunci file dengan password (file ikut dienkripsi AES-256-CBC)
- Sembunyikan file/folder + **Hidden System** dengan gerbang password
- Berbagi file lewat link (opsional: password, masa berlaku, batas unduhan)
- Pratinjau gambar, video, PDF, dan dokumen Office
- Notifikasi in-app (kuota menipis, file dihapus, user baru mendaftar, dll.)
- Panel admin: manajemen user, kuota, aktivasi akun
- API untuk aplikasi mobile (`routes/api.php`, guard token `auth:api`)

## Kebutuhan Sistem

- PHP 8.2+ dengan ekstensi `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `gd`
- MySQL 5.7+ / MariaDB
- Composer

## Instalasi Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # membuat akun admin@dekorasi.me / password
php artisan storage:link            # WAJIB, agar avatar bisa tampil
php artisan serve
```

## Menjalankan Test

Test memakai database terpisah bernama `dekorasi_drive_test` (lihat `phpunit.xml`).
Buat dulu databasenya, lalu:

```bash
php artisan test
```

## Deploy ke cPanel

1. Unggah dan ekstrak arsip perubahan ke root aplikasi.
2. Jalankan migrasi bila ada perubahan struktur: `php artisan migrate --force`
3. Bersihkan cache lama, lalu bangun ulang:

   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. Pastikan symlink storage aktif: `php artisan storage:link`
5. Pastikan folder berikut bisa ditulis: `storage/`, `bootstrap/cache/`

### Pengaturan `.env` untuk produksi

`.env` tidak ikut di-commit. Di server produksi wajib diisi:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://drive.dekorasi.me
```

> **Penting:** selama `APP_DEBUG=true`, setiap error menampilkan seluruh stack
> trace beserta potongan kode sumber ke pengunjung. Ini yang membuat halaman
> error sebelumnya membocorkan isi file Blade. Selalu `false` di produksi.

> **Penting:** jangan mengganti `APP_KEY` setelah ada file yang dikunci.
> Kunci enkripsi file diturunkan dari `APP_KEY`, sehingga mengubahnya membuat
> file terkunci yang lama tidak bisa dibuka lagi.

## Struktur Singkat

| Path | Isi |
| --- | --- |
| `app/Http/Controllers/DriveController.php` | Operasi drive (upload, lock, share, move) |
| `app/Http/Controllers/Api/` | Endpoint untuk aplikasi mobile |
| `app/Services/StorageService.php` | Penyimpanan fisik + kuota |
| `app/Services/FileEncryptionService.php` | Enkripsi/dekripsi file |
| `resources/views/drive/` | Halaman drive + partial kartu file/folder |
| `resources/views/layouts/app.blade.php` | Layout, sistem desain (navy + gold) |
| `drive-mobile/` | Aplikasi React Native (Expo) |

## Lisensi

Proprietary — Dekorasi.me.
