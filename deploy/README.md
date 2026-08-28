# Deploy Otomatis

Server memeriksa GitHub setiap 2 menit. Ada commit baru → aplikasi diperbarui
sendiri. Tidak ada commit baru → keluar dalam sepersekian detik tanpa menulis
apa pun.

Tidak memakai GitHub Actions dengan sengaja: tidak ada komputasi di pihak
GitHub, dan tidak ada kredensial server yang perlu dititipkan ke sana.

Bawaan skrip sudah disetel untuk **aaPanel**:

| Pengaturan | Nilai bawaan |
| --- | --- |
| `APP_DIR` | `/www/wwwroot/drive.aldeftech.com` |
| `WEB_USER` / `WEB_GROUP` | `www` (aaPanel, bukan `www-data`) |
| `BACKUP_DIR` | `/www/backup/drive-deploy` |
| `LOG` | `/www/wwwlogs/aldeftech-deploy.log` |

PHP, composer, dan mysqldump dicari sendiri, dan **lokasi aaPanel diutamakan
di atas PATH**. Dua alasan:

- Cron berjalan dengan PATH yang sangat minim, sehingga skrip yang mengandalkan
  `php` polos akan jalan mulus saat diuji manual lalu gagal diam-diam begitu
  dijadwalkan.
- Di server ini `command -v php` menunjuk `/usr/bin/php` — PHP sistem, bukan
  `/www/server/php/84/bin/php` yang dipakai situsnya. Ekstensinya belum tentu
  sama, dan artisan yang jalan di PHP keliru gagal dengan pesan membingungkan.

Setiap perintah git dijalankan dengan `safe.directory`. Berkas situs milik
`www` sedangkan cron berjalan sebagai `root`, dan tanpa itu git menolak bekerja
dengan pesan *"detected dubious ownership"* — kegagalan yang akan terulang diam
diam tiap 2 menit selamanya.

---

## 0. Periksa dulu

Jalankan ini di server, dan cocokkan hasilnya dengan tabel di atas:

```bash
cd /www/wwwroot/drive.aldeftech.com
echo "PHP        : $(command -v php)  $(ls -d /www/server/php/*/bin/php 2>/dev/null | tail -1)"
echo "COMPOSER   : $(command -v composer)"
echo "MYSQLDUMP  : $(command -v mysqldump)  $(ls /www/server/mysql/bin/mysqldump 2>/dev/null)"
echo "PEMILIK    : $(stat -c '%U:%G' .)"
echo "GIT        : $([ -d .git ] && echo 'sudah kloning git' || echo 'BELUM - lihat langkah 1')"
echo "SUDO       : $(sudo -n true 2>/dev/null && echo ada || echo 'perlu password')"
```

Kalau ada yang berbeda, sunting bagian **Pengaturan** di kepala
`auto-deploy.sh` sebelum dipasang.

---

## 1. Ubah folder aplikasi menjadi kloning git

Aplikasi di server sekarang berasal dari ekstrak ZIP, bukan `git clone`, jadi
belum bisa menarik pembaruan. Langkah ini menggantinya **tanpa kehilangan
`.env` maupun berkas pengguna**.

> Jalankan satu per satu dan baca hasilnya. Ini satu-satunya langkah yang
> menyentuh data yang sudah ada.

```bash
LAMA=/www/wwwroot/drive.aldeftech.com
BARU=/www/wwwroot/drive-baru

# a. Cadangkan dulu — database dan seluruh folder
mysqldump -u USER -p NAMA_DATABASE | gzip > ~/cadangan-sebelum-git.sql.gz
sudo tar czf ~/cadangan-folder.tar.gz -C "$(dirname $LAMA)" "$(basename $LAMA)"

# b. Kloning bersih dari GitHub
sudo git clone https://github.com/aldef-deni/Drive.git "$BARU"

# c. Pindahkan yang TIDAK ada di git: konfigurasi dan berkas pengguna
sudo cp "$LAMA/.env" "$BARU/.env"
sudo cp -a "$LAMA/storage/app/." "$BARU/storage/app/"

# d. Tukar
sudo mv "$LAMA" /www/wwwroot/drive-lama
sudo mv "$BARU" "$LAMA"

# e. Siapkan
cd "$LAMA"
PHP=$(ls -d /www/server/php/*/bin/php 2>/dev/null | sort -rV | head -1 || command -v php)

sudo composer install --no-dev --optimize-autoloader
sudo chown -R www:www .
sudo chmod -R ug+rw storage bootstrap/cache
sudo -u www "$PHP" artisan migrate --force
sudo -u www "$PHP" artisan config:cache
sudo -u www "$PHP" artisan route:cache
sudo -u www "$PHP" artisan view:cache
```

> **Berkas khas aaPanel.** Kalau di folder lama ada `.user.ini` atau
> `.htaccess` buatan aaPanel, salin juga ke folder baru — keduanya tidak ada
> di git. `.user.ini` kadang dikunci (`chattr +i`); lepas dulu dengan
> `sudo chattr -i .user.ini` bila perlu.

Buka situsnya. Kalau normal, hapus `/www/wwwroot/drive-lama` setelah beberapa
hari — jangan buru-buru.

### Repo privat

Kalau repo GitHub-nya privat, kloning di atas akan meminta kredensial. Pakai
deploy key baca-saja, bukan akun Anda:

```bash
sudo mkdir -p /root/.ssh
sudo ssh-keygen -t ed25519 -C "deploy-drive" -f /root/.ssh/deploy_key -N ""
sudo cat /root/.ssh/deploy_key.pub
```

Tempel isinya di GitHub → repo → **Settings → Deploy keys → Add deploy key**.
Biarkan "Allow write access" **tidak** dicentang. Lalu pakai URL SSH:

```bash
cd /www/wwwroot/drive.aldeftech.com
sudo git remote set-url origin git@github.com:aldef-deni/Drive.git
```

Beri tahu SSH kunci mana yang dipakai untuk GitHub — cron berjalan sebagai
root, jadi berkas ini milik root:

```bash
sudo tee -a /root/.ssh/config >/dev/null <<'EOF'
Host github.com
    IdentityFile /root/.ssh/deploy_key
    IdentitiesOnly yes
EOF
sudo chmod 600 /root/.ssh/config /root/.ssh/deploy_key

# Terima sidik jari GitHub sekali, supaya cron tidak menunggu jawaban selamanya
sudo ssh-keyscan github.com | sudo tee -a /root/.ssh/known_hosts >/dev/null
```

---

## 2. Pasang skrip dan cron

> **User `aldeftech` tidak punya sudo.** Seluruh langkah di bawah dijalankan
> dari **panel aaPanel**, bukan dari SSH-in-browser:
>
> - **Terminal** di aaPanel berjalan sebagai `root`
> - **Cron** di aaPanel juga berjalan sebagai `root`
>
> Jadi abaikan awalan `sudo` bila Anda menjalankannya dari Terminal aaPanel —
> Anda memang sudah root di sana.

```bash
sudo install -m 755 /www/wwwroot/drive.aldeftech.com/deploy/auto-deploy.sh \
     /usr/local/bin/auto-deploy.sh
sudo touch /www/wwwlogs/aldeftech-deploy.log
```

Sesuaikan hanya bila hasil langkah 0 berbeda dari bawaannya:

```bash
sudo nano /usr/local/bin/auto-deploy.sh
```

Uji sekali secara manual sebelum dipasang ke cron:

```bash
sudo /usr/local/bin/auto-deploy.sh
echo "kode keluar: $?"      # 0 = aman
sudo tail -20 /www/wwwlogs/aldeftech-deploy.log
```

Kalau aman, pasang jadwalnya lewat **aaPanel → Cron**:

| Kolom | Isi |
| --- | --- |
| Type of Task | Shell Script |
| Name | Deploy Aldef Tech Drive |
| Period | N Minutes → **2** |
| Script | `/usr/local/bin/auto-deploy.sh` |

Kalau nanti Anda punya akses root lewat SSH, `crontab -e` juga bisa dipakai:

```
*/2 * * * * /usr/local/bin/auto-deploy.sh
```

> Pakai **salah satu** saja. Dua penjadwal yang menjalankan skrip yang sama
> hanya menambah kemungkinan bertabrakan — meskipun kuncinya sudah mencegah
> dua deploy berjalan bersamaan.

---

## 3. Memantau

```bash
# Riwayat deploy
sudo tail -f /www/wwwlogs/aldeftech-deploy.log

# Ada deploy yang gagal?
cat /www/wwwroot/drive.aldeftech.com/storage/deploy-gagal
```

Berkas `storage/deploy-gagal` hanya ada bila deploy terakhir gagal, dan hilang
sendiri begitu ada deploy yang berhasil. Tanpa penanda ini, kegagalan hanya
akan menumpuk di log tanpa ada yang menyadarinya.

Cadangan database ada di `/www/backup/drive-deploy/`, 14 terakhir disimpan.

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
