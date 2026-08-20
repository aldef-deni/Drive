# Dekorasi Drive

Aplikasi penyimpanan file dan folder berbasis Laravel 11 untuk Dekorasi.me.
Produksi: <https://drive.dekorasi.me>

## Fitur

- Unggah, unduh, pindah (drag & drop), dan hapus file/folder
- Tiga mode tampilan: Daftar, Ikon Kecil, Ikon Besar
- Kunci file dengan password (file ikut dienkripsi AES-256-CBC)
- Sembunyikan file/folder, dimunculkan lagi lewat **kata kunci rahasia**
- Registrasi wajib diverifikasi admin sebelum akun bisa login
- Berbagi file lewat link (opsional: password, masa berlaku, batas unduhan)
- Pratinjau gambar, video, PDF, dan dokumen Office
- Notifikasi in-app (kuota menipis, file dihapus, user baru mendaftar, dll.)
- Panel admin: manajemen user, kuota, aktivasi akun
- API untuk aplikasi mobile (`routes/api.php`, guard token `auth:api`)

## Hidden System

Menyembunyikan file/folder bekerja seperti ini:

1. Klik kanan item di Drive &rarr; **Sembunyikan**. Item hilang dari daftar
   maupun hasil pencarian biasa.
2. Ketik **kata kunci rahasia** di kolom pencarian lalu tekan Enter. Mode rahasia
   menyala dan item tersembunyi ikut ditampilkan sampai ditutup lewat tombol
   silang pada banner, atau sampai logout.
3. Selama mode rahasia menyala, klik kanan &rarr; **Tampilkan** mengembalikan
   item ke Drive.

Kata kunci diatur admin lewat menu **Admin &rarr; Hidden System**
(`/admin/hidden-system`). Nilainya disimpan sebagai hash, jadi hanya bisa
diganti, tidak bisa dilihat. Selama admin belum pernah menggantinya, kata kunci
bawaan `deniafrizal` masih berlaku.

## Verifikasi Akun

Akun hasil registrasi dibuat dalam keadaan **non-aktif** dan belum bisa login.
Admin memverifikasinya lewat kartu *Menunggu Verifikasi* di dashboard admin, atau
lewat filter **Menunggu** pada halaman Manajemen User. Setelah diaktifkan, user
menerima notifikasi bahwa akunnya sudah bisa dipakai.

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
php artisan storage:link            # opsional; avatar tidak bergantung padanya
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
   (wajib untuk rilis ini &mdash; ada tabel baru `settings`)
3. Bersihkan cache lama, lalu bangun ulang:

   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. Pastikan folder berikut bisa ditulis: `storage/`, `bootstrap/cache/`
   (avatar disimpan di `storage/app/public/avatars`)

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

## Catatan Teknis

**Avatar tidak memakai `public/storage`.** File disimpan di
`storage/app/public/avatars` dan disajikan lewat route `GET /avatar/{user}`.
Alasannya, symlink `public/storage` kerap tidak aktif di hosting cPanel sehingga
gambar profil gagal dimuat. `App\Models\User::avatarUrl()` adalah satu-satunya
tempat URL avatar dibentuk — pakai itu, jangan `asset('storage/...')`.

**Jangan panggil `$middleware->statefulApi()`** di `bootstrap/app.php`. Fungsi itu
memasang middleware milik Laravel Sanctum, sementara paket Sanctum tidak dipasang
di proyek ini, sehingga seluruh route `/api/*` akan membalas error 500.
Autentikasi API memakai guard token bawaan (`auth:api`).

## Struktur Singkat

| Path | Isi |
| --- | --- |
| `app/Http/Controllers/DriveController.php` | Operasi drive (upload, lock, share, move) |
| `app/Http/Controllers/Api/` | Endpoint untuk aplikasi mobile |
| `app/Services/StorageService.php` | Penyimpanan fisik + kuota |
| `app/Services/FileEncryptionService.php` | Enkripsi/dekripsi file |
| `resources/views/drive/` | Halaman drive + partial kartu file/folder |
| `resources/views/layouts/app.blade.php` | Layout, sistem desain (navy + gold) |
| `app/Models/Setting.php` | Pengaturan aplikasi, termasuk kata kunci rahasia |
| `app/Http/Controllers/ProfileController.php` | Profil, avatar, dan penyajian file avatar |
| `drive-mobile/` | Aplikasi React Native (Expo) |

## Lisensi

Proprietary — Dekorasi.me.
