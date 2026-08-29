# Deploy Otomatis — ALDEF POS

Server: `/www/wwwroot/pos.aldeftech.com` (aaPanel, Ubuntu, GCP), branch `main`.

---

## Jalan 1 — cron (dipakai sekarang)

Server memeriksa GitHub setiap 2 menit. Ada commit baru → diperbarui sendiri.
Tidak ada → keluar dalam sepersekian detik tanpa menulis apa pun.

Tidak memakai webhook aaPanel dengan sengaja: Security Entrance panel menolak
semua path di luar entrance-nya, sehingga permintaan dari GitHub selalu dibalas
404. Cron tidak butuh koneksi masuk sama sekali.

Pemasangan, dari sesi `ubuntu` (satu-satunya user dengan sudo):

```bash
sudo install -m 755 /www/wwwroot/pos.aldeftech.com/deploy/auto-deploy.sh \
     /usr/local/bin/pos-deploy.sh
sudo touch /www/wwwlogs/pos-deploy.log

# Uji sekali sebelum dijadwalkan
sudo /usr/local/bin/pos-deploy.sh
echo "kode keluar: $?"          # 0 = aman
sudo tail -20 /www/wwwlogs/pos-deploy.log
```

Kalau bersih:

```bash
sudo crontab -l 2>/dev/null | grep -q pos-deploy.sh || \
  ( sudo crontab -l 2>/dev/null; echo "*/2 * * * * /usr/local/bin/pos-deploy.sh" ) | sudo crontab -

sudo crontab -l
```

Yang dikerjakan tiap ada commit baru:

1. Cadangkan database (gagal mencadangkan → deploy dibatalkan)
2. Mode pemeliharaan
3. `git reset --hard` ke commit terbaru
4. `composer install --no-dev` — hanya bila `composer.json/lock` ikut berubah
5. `php artisan migrate --force`
6. Bangun ulang cache config, route, view
7. Kembalikan kepemilikan `storage` dan `bootstrap/cache` ke `www`
8. Matikan mode pemeliharaan

Gagal di langkah mana pun → kode kembali ke commit sebelumnya, situs dinyalakan
lagi, dan penanda ditulis di `storage/deploy-gagal`.

**Database tidak dipulihkan otomatis.** Pemulihan sepihak akan membuang data
yang ditulis kasir sejak cadangan diambil. Perintahnya dicetak di penanda
kegagalan; keputusannya di tangan manusia.

> **Jebakan Rollback.** Tombol Rollback di aaPanel akan dibatalkan cron dalam
> dua menit, karena cron menyamakan kode dengan `origin/main`. Mundur versi yang
> bertahan harus lewat `git revert` lalu push.

---

## Jalan 2 — aaPanel Git Manager

Dipakai untuk deploy manual lewat tombol **Deploy latest**. Isi tab **Script**
dengan satu baris:

```
bash /www/wwwroot/pos.aldeftech.com/deploy/aapanel-post-deploy.sh
```

Biarkan **Webhook = None** selama cron aktif, supaya tidak ada dua mesin yang
menarik kode ke folder yang sama.

Untuk deploy manual, lebih baik pakai skrip cron-nya langsung — ia memegang
kunci yang sama sehingga tidak mungkin bertabrakan dengan jadwal yang lewat:

```bash
sudo /usr/local/bin/pos-deploy.sh
```

---

## Catatan khusus proyek ini

**Tidak ada folder `public/`.** Document root menunjuk ke akar proyek, dan
`index.php` di akar bertindak sebagai front controller. Proteksi terhadap
`app/`, `config/`, `storage/`, dan `.env` mengandalkan `.htaccess`.

`.htaccess` hanya berlaku di Apache. **Kalau situs ini dilayani nginx,
berkas-berkas itu tidak terlindungi** dan `.env` bisa diunduh siapa pun. Uji
sekali:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://pos.aldeftech.com/.env
```

`403` atau `404` berarti aman. **`200` berarti kredensial database Anda terbuka
ke internet** dan harus segera ditutup lewat aturan nginx.

**Tidak ada build aset** — tidak ada `package.json`; CSS dan JS disajikan
langsung dari `assets/`. Jadi deploy tidak perlu Node.js.

---

## Akun demo

```
Dashboard : /admin/login
  Owner       demo       / demo12345
  Supervisor  demo-spv   / demo12345
Terminal  : /pos/login  (PIN)
  Kasir Demo 1  PIN 1234
  Kasir Demo 2  PIN 5678
```

Tenant tersendiri (`demo-aldeftech`), terpisah dari pelanggan. Akun demo
berperan Owner, dan Owner melihat seluruh outlet berikut laporan labanya —
menaruhnya di tenant asli berarti membuka data penjualan pelanggan kepada siapa
pun yang mencoba demo.

Isinya dibangun ulang setiap 24 jam, dipicu saat ada yang masuk memakai
username atau email demo. Pemeriksaannya berjalan **sebelum** kredensial
diperiksa: kalau menunggu login berhasil, pengunjung yang mengganti kata sandi
akan mengunci semua orang dan pemulihannya tidak akan pernah terpicu lagi.

Isi contohnya sengaja ringan — 2 outlet, 25 produk, riwayat 7 hari — karena
pemulihan berjalan di tengah permintaan masuk. Tiga minggu perdagangan seperti
`DatabaseSeeder` akan membuat pengunjung menunggu terlalu lama.

Menyiapkan atau memulihkan segera:

```bash
cd /www/wwwroot/pos.aldeftech.com
sudo -u www /www/server/php/84/bin/php artisan pos:demo-reset --force
```

Untuk mematikan seluruh fitur demo, isi `.env` dengan `DEMO_USERNAME=` kosong.
