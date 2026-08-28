# Deploy Otomatis

Ada dua jalan, dan **yang pertama lebih disarankan** karena memakai mesin yang
sudah ada di server.

---

## Jalan 1 — aaPanel Git Manager (disarankan)

aaPanel sudah punya mesin deploy sendiri: menarik kode dari GitHub, menyimpan
riwayat, dan bisa rollback beberapa versi ke belakang. Ditambah **Webhook**,
deploy terjadi **seketika** setiap push — bukan menunggu giliran cron.

Yang tidak diketahui aaPanel adalah langkah-langkah Laravel sesudahnya. Itu
diisi lewat tab **Script**.

### a. Isi tab Script

Site → **Git Manager** → tab **Script**. Tempel isi berkas
[`aapanel-post-deploy.sh`](aapanel-post-deploy.sh).

Skrip itu mengerjakan, berurutan:

1. Cadangkan database (gagal mencadangkan → migrasi dibatalkan)
2. Nyalakan mode pemeliharaan
3. `composer install --no-dev`
4. `php artisan migrate --force`
5. Bangun ulang cache config, route, view
6. Kembalikan kepemilikan berkas ke `www`
7. Matikan mode pemeliharaan

Jalur lengkap dipakai untuk PHP dan composer, bukan nama programnya saja —
`php` polos di server ini menunjuk `/usr/bin/php`, PHP sistem, bukan PHP 8.4
yang dipakai situsnya.

### b. Nyalakan Webhook

Site → Git Manager → tab **Webhook**. Salin URL yang muncul, lalu di GitHub:
repo → **Settings → Webhooks → Add webhook**, tempel URL-nya, pilih
*Just the push event*.

Setelah itu setiap push langsung ter-deploy.

### c. Deploy pertama

Tekan **Deploy latest** di Git Manager. Server sekarang tertinggal beberapa
commit, jadi ini menyusulkannya sekaligus menguji seluruh rangkaiannya.

### Catatan

**Rollback aaPanel hanya mengembalikan kode, bukan database.** Kalau migrasi
gagal separuh jalan, pulihkan database dari cadangan di
`/www/backup/drive-deploy/`:

```bash
gunzip < /www/backup/drive-deploy/<berkas>.sql.gz | mysql -u <user> -p <database>
```

**Ignored path changes** sudah terisi `/.env`, `/storage`, `/bootstrap/cache` —
biarkan begitu. Ketiganya memang tidak boleh ikut ditimpa.

---

## Jalan 2 — cron sendiri

Dipakai bila Git Manager tidak dipakai, atau di server tanpa aaPanel.

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

### 0. Periksa dulu

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

### 1. Ubah folder aplikasi menjadi kloning git

> **Lewati langkah ini bila folder aplikasi sudah kloning git.** Periksa dengan:
>
> ```bash
> cd /www/wwwroot/drive.aldeftech.com
> git -c safe.directory='*' remote -v
> ```
>
> Kalau muncul URL repo, folder ini sudah kloning git — langsung ke langkah 2.
>
> Catatan: menjalankan `git remote -v` tanpa `safe.directory` bisa menghasilkan
> `detected dubious ownership` dan terbaca seolah bukan kloning git. Berkas
> situs milik `www`, sedangkan perintahnya dijalankan user lain.

Diperlukan hanya bila aplikasi di server berasal dari ekstrak ZIP. Langkah ini
menggantinya **tanpa kehilangan `.env` maupun berkas pengguna**.

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

#### Repo privat

Repo privat butuh kredensial setiap kali `git fetch`. Cron berjalan sebagai
`root`, jadi yang harus punya kredensial itu **root** — bukan user SSH Anda,
dan bukan aaPanel.

Periksa lebih dulu, dari Terminal aaPanel (yang memang root):

```bash
cd /www/wwwroot/drive.aldeftech.com
git -c safe.directory='*' fetch --dry-run
```

Selesai tanpa pesan → sudah beres, lanjut ke langkah 2. Kalau muncul
`Username for 'https://github.com'` atau `Authentication failed`, pilih salah
satu di bawah.

##### Pilihan A — deploy key SSH (disarankan)

Tidak kedaluwarsa, dan aksesnya bisa dibatasi baca-saja pada satu repo:

```bash
sudo mkdir -p /root/.ssh
sudo ssh-keygen -t ed25519 -C "deploy-drive" -f /root/.ssh/deploy_key -N ""
sudo cat /root/.ssh/deploy_key.pub
```

Tempel isinya di GitHub → repo → **Settings → Deploy keys → Add deploy key**.
Biarkan "Allow write access" **tidak** dicentang. Lalu pakai URL SSH:

```bash
cd /www/wwwroot/drive.aldeftech.com
git remote set-url origin git@github.com:aldef-deni/Drive.git

cat >> /root/.ssh/config <<'EOF'
Host github.com
    IdentityFile /root/.ssh/deploy_key
    IdentitiesOnly yes
EOF
chmod 600 /root/.ssh/config /root/.ssh/deploy_key

# Terima sidik jari GitHub sekali, supaya cron tidak menunggu jawaban selamanya
ssh-keyscan github.com >> /root/.ssh/known_hosts

# Uji
git -c safe.directory='*' fetch --dry-run
```

##### Pilihan B — token lewat HTTPS

Lebih cepat, tetapi tokennya bisa kedaluwarsa dan tersimpan sebagai teks biasa
di `/root/.git-credentials` (hanya root yang bisa membacanya).

Buat **Fine-grained personal access token** di GitHub dengan izin
*Contents: Read-only* pada repo ini saja, lalu:

```bash
git config --global credential.helper store
cd /www/wwwroot/drive.aldeftech.com
git -c safe.directory='*' fetch
# Username: aldef-deni
# Password: <tempel token, bukan password akun>
```

Fetch berikutnya tidak akan bertanya lagi.

---

### 2. Pasang skrip dan cron

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

### 3. Memantau

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

### Yang dilakukan skrip saat ada commit baru

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

### Yang TIDAK dilakukan skrip

**Tidak memulihkan database secara otomatis.** Kalau migrasi gagal separuh
jalan, pemulihan otomatis akan membuang data yang ditulis pengguna sejak
cadangan diambil. Perintah pemulihannya dicetak di penanda kegagalan, tetapi
Anda yang memutuskan menjalankannya.

**Tidak menjalankan `git clean`.** Berkas pengguna di `storage/` tidak
terlacak git; membersihkannya berarti menghapus seluruh isi drive pelanggan.

**Tidak menyentuh `.env`.** Berkas itu di-ignore git, jadi `git reset --hard`
melewatinya.

---

### Mematikan sementara

```bash
sudo crontab -e     # beri tanda # di depan barisnya
```

Deploy manual tetap bisa kapan saja:

```bash
sudo /usr/local/bin/auto-deploy.sh
```
