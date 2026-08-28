# Deploy Otomatis

Server memeriksa GitHub setiap 2 menit. Ada commit baru → aplikasi diperbarui
sendiri. Tidak ada commit baru → keluar dalam sepersekian detik tanpa menulis
apa pun.

Tidak memakai GitHub Actions dengan sengaja: tidak ada komputasi di pihak
GitHub, dan tidak ada kredensial server yang perlu dititipkan ke sana.

---

## 1. Ubah folder aplikasi menjadi kloning git

Aplikasi di server sekarang berasal dari ekstrak ZIP, bukan `git clone`, jadi
belum bisa menarik pembaruan. Langkah ini menggantinya **tanpa kehilangan
`.env` maupun berkas pengguna**.

> Jalankan satu per satu dan baca hasilnya. Ini satu-satunya langkah yang
> menyentuh data yang sudah ada.

```bash
# Sesuaikan bila path Anda berbeda
LAMA=/var/www/drive
BARU=/var/www/drive-baru

# a. Cadangkan dulu — database dan seluruh folder
mysqldump -u USER -p NAMA_DATABASE | gzip > ~/cadangan-sebelum-git.sql.gz
sudo tar czf ~/cadangan-folder.tar.gz -C "$(dirname $LAMA)" "$(basename $LAMA)"

# b. Kloning bersih dari GitHub
sudo git clone https://github.com/aldef-deni/Drive.git "$BARU"

# c. Pindahkan yang TIDAK ada di git: konfigurasi dan berkas pengguna
sudo cp "$LAMA/.env" "$BARU/.env"
sudo cp -a "$LAMA/storage/app/." "$BARU/storage/app/"

# d. Tukar
sudo mv "$LAMA" /var/www/drive-lama
sudo mv "$BARU" "$LAMA"

# e. Siapkan
cd "$LAMA"
sudo composer install --no-dev --optimize-autoloader
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rw storage bootstrap/cache
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

Buka situsnya. Kalau normal, hapus `/var/www/drive-lama` setelah beberapa hari
— jangan buru-buru.

### Repo privat

Kalau repo GitHub-nya privat, kloning di atas akan meminta kredensial. Pakai
deploy key baca-saja, bukan akun Anda:

```bash
sudo -u www-data ssh-keygen -t ed25519 -C "deploy-drive" -f /var/www/.ssh/deploy_key -N ""
sudo cat /var/www/.ssh/deploy_key.pub
```

Tempel isinya di GitHub → repo → **Settings → Deploy keys → Add deploy key**.
Biarkan "Allow write access" **tidak** dicentang. Lalu pakai URL SSH:

```bash
cd /var/www/drive
sudo git remote set-url origin git@github.com:aldef-deni/Drive.git
```

---

## 2. Pasang skrip dan cron

```bash
sudo install -m 755 /var/www/drive/deploy/auto-deploy.sh /usr/local/bin/auto-deploy.sh
sudo touch /var/log/aldeftech-deploy.log
```

Sesuaikan bila path atau user Anda berbeda dari bawaannya:

```bash
sudo nano /usr/local/bin/auto-deploy.sh
# APP_DIR="/var/www/drive"
# WEB_USER="www-data"
```

Uji sekali secara manual sebelum dipasang ke cron:

```bash
sudo /usr/local/bin/auto-deploy.sh
echo "kode keluar: $?"      # 0 = aman
sudo tail -20 /var/log/aldeftech-deploy.log
```

Kalau aman, pasang cron:

```bash
sudo crontab -e
```

Tambahkan satu baris:

```
*/2 * * * * /usr/local/bin/auto-deploy.sh
```

---

## 3. Memantau

```bash
# Riwayat deploy
sudo tail -f /var/log/aldeftech-deploy.log

# Ada deploy yang gagal?
cat /var/www/drive/storage/deploy-gagal
```

Berkas `storage/deploy-gagal` hanya ada bila deploy terakhir gagal, dan hilang
sendiri begitu ada deploy yang berhasil. Tanpa penanda ini, kegagalan hanya
akan menumpuk di log tanpa ada yang menyadarinya.

Cadangan database ada di `/var/backups/aldeftech-drive/`, 14 terakhir disimpan.

---

## Yang dilakukan skrip saat ada commit baru

1. Cadangkan database (deploy dibatalkan bila pencadangan gagal)
2. Nyalakan mode pemeliharaan
3. `git reset --hard` ke commit terbaru
4. `composer install` — hanya bila `composer.json/lock` ikut berubah
5. `php artisan migrate --force`
6. Bangun ulang cache config, route, dan view
7. Perbaiki kepemilikan `storage` dan `bootstrap/cache`
8. Matikan mode pemeliharaan

Gagal di langkah mana pun → kode dikembalikan ke commit sebelumnya, cache
dibangun ulang, situs dinyalakan lagi, dan penanda kegagalan ditulis.

## Yang TIDAK dilakukan skrip

**Tidak memulihkan database secara otomatis.** Kalau migrasi gagal separuh
jalan, pemulihan otomatis akan membuang data yang ditulis pengguna sejak
cadangan diambil. Perintah pemulihannya dicetak di penanda kegagalan, tetapi
Anda yang memutuskan menjalankannya.

**Tidak menjalankan `git clean`.** Berkas pengguna di `storage/` tidak
terlacak git; membersihkannya berarti menghapus seluruh isi drive pelanggan.

**Tidak menyentuh `.env`.** Berkas itu di-ignore git, jadi `git reset --hard`
melewatinya.

---

## Mematikan sementara

```bash
sudo crontab -e     # beri tanda # di depan barisnya
```

Deploy manual tetap bisa kapan saja:

```bash
sudo /usr/local/bin/auto-deploy.sh
```
