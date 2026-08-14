# Kasir POS — SaaS Point of Sale

Aplikasi kasir berbasis **Laravel 11** dengan terminal kasir yang berdiri sendiri,
dashboard pengelola berbasis peran, pembuatan ID/barcode/QR produk otomatis, dan
pelaporan lengkap yang dapat diekspor ke PDF & CSV.

Dibangun **tanpa folder `public`** — dokumen root langsung di root project,
sehingga cukup di-unzip ke `public_html` pada cPanel.

---

## 1. Akun Contoh

Setelah menjalankan seeder, gunakan akun berikut.

| Peran | Masuk lewat | Username | Kata sandi | PIN |
|---|---|---|---|---|
| Owner | `/admin/login` | `owner` | `owner123` | 9999 |
| Supervisor | `/admin/login` | `supervisor` | `super123` | 4321 |
| Kasir 1 | `/pos/login` | `kasir1` | `kasir123` | 1234 |
| Kasir 2 | `/pos/login` | `kasir2` | `kasir123` | 5678 |

> **Ganti seluruh kata sandi dan PIN ini sebelum dipakai sungguhan.**

---

## 2. Alur Aplikasi

| URL | Untuk siapa | Keterangan |
|---|---|---|
| `/` | Umum | Halaman pemilihan: Terminal Kasir atau Dashboard |
| `/pos/login` | Operator | Login kasir — **terpisah** dari dashboard |
| `/pos` | Operator | Terminal transaksi (wajib shift terbuka) |
| `/admin/login` | Owner & Supervisor | Login dashboard |
| `/dashboard` | Owner & Supervisor | Dashboard pengelola |

Kasir **tidak dapat** membuka `/dashboard` — jika mencoba, ia dikembalikan ke
halaman login dashboard. Sebaliknya, login di terminal kasir tidak memberikan
sesi dashboard sama sekali (dua guard terpisah: `pos` dan `web`).

---

## 3. Peran & Hak Akses

| Kemampuan | Owner | Supervisor | Kasir |
|---|:--:|:--:|:--:|
| Terminal kasir & cetak struk | ✅ | ✅ | ✅ |
| Shift sendiri (buka/tutup laci) | ✅ | ✅ | ✅ |
| Dashboard pengelola | ✅ | ✅ | ❌ |
| Produk, kategori, stok, opname | ✅ | ✅ | ❌ |
| Pelanggan | ✅ | ✅ | ❌ |
| Seluruh laporan + ekspor PDF/CSV | ✅ | ✅ | ❌ |
| Lihat modal & laba | ✅ | ✅ | ❌ |
| Setujui pembatalan (void) | ✅ | ✅ | ❌ |
| Lihat semua shift kasir | ✅ | ✅ | ❌ |
| Log aktivitas (audit) | ✅ | ✅ | ❌ |
| Manajemen pengguna & peran | ✅ | ❌ | ❌ |
| Pengaturan toko, pajak, struk | ✅ | ❌ | ❌ |
| Mekanisme ID produk | ✅ | ❌ | ❌ |

Pembatalan transaksi di terminal kasir **wajib** disetujui dengan PIN Owner atau
Supervisor — kasir tidak bisa membatalkan transaksinya sendiri.

---

## 4. Mekanisme ID Produk

Diatur di **Dashboard → Pengaturan → Mekanisme ID Produk**. ID dibentuk dari
empat segmen yang bisa dinyalakan/dimatikan:

```
PREFIX  -  KODE KATEGORI  -  TANGGAL  -  NOMOR URUT
 KSJ    -      KOP        -   2608    -    0001      →  KSJ-KOP-2608-0001
```

- **Prefix** — bebas, misal `KSJ`. Kosongkan untuk melewati.
- **Kode kategori** — diambil dari kolom *Kode ID* pada tiap kategori.
- **Tanggal** — tanpa tanggal / `YY` / `YYMM` / `YYMMDD`.
- **Nomor urut** — panjang digit bebas, dibagikan secara aman (row lock) sehingga
  dua produk tidak akan pernah mendapat nomor sama.

Setiap produk yang tersimpan **otomatis** memperoleh:

1. **ID produk (SKU)** sesuai pola di atas,
2. **Barcode** — `Code 128` (isi = ID produk) atau `EAN-13` (13 digit, prefix
   internal `2` sesuai standar GS1, lengkap dengan check digit),
3. **QR Code** berisi ID produk sehingga langsung bisa dipindai di kasir.

Cetak label massal lewat **Produk → Cetak Label** (lembar A4, 4 kolom). Untuk
mencetak salinan lebih banyak: `/dashboard/products/labels?copies=12`.

---

## 5. Laporan

Sepuluh laporan, semuanya dengan rentang tanggal bebas dan tombol **Export PDF**
serta **CSV**:

Ringkasan Penjualan · Detail Transaksi · Penjualan per Produk ·
Penjualan per Kategori · Kinerja Kasir · Metode Pembayaran ·
Laba & Margin · Nilai Persediaan · Rekap Shift Kasir · Transaksi Dibatalkan

Selain itu tersedia: **Invoice PDF** per transaksi dan **Laporan Tutup Shift PDF**
(lengkap dengan kolom tanda tangan kasir & supervisor).

---

## 6. Deploy ke cPanel

Aplikasi ini **tidak memakai folder `public`**. Seluruh isi ZIP langsung
diletakkan di `public_html` (atau folder domain/subdomain Anda).

1. **Upload & ekstrak**
   Unggah `kasir-pos-vX.zip` ke `public_html`, klik *Extract*.
   Pastikan `index.php` dan `.htaccess` berada langsung di dalam `public_html`.

2. **Buat database MySQL**
   cPanel → *MySQL Databases* → buat database + user, beri **ALL PRIVILEGES**.

3. **Atur `.env`**
   File `.env` sudah disertakan. Sesuaikan minimal:
   ```
   APP_URL=https://domain-anda.com
   DB_DATABASE=nama_database
   DB_USERNAME=user_database
   DB_PASSWORD=kata_sandi
   ```

4. **Set versi PHP ke 8.2+**
   cPanel → *MultiPHP Manager*. Ekstensi wajib: `pdo_mysql`, `mbstring`,
   `openssl`, `gd`, `dom`, `fileinfo`, `zip`.

5. **Jalankan migrasi**
   Lewat *Terminal* cPanel:
   ```bash
   cd ~/public_html
   php artisan key:generate      # hanya jika APP_KEY kosong
   php artisan migrate --force
   php artisan db:seed --force   # opsional: data contoh + akun
   php artisan optimize
   ```

   Bila Terminal tidak tersedia, gunakan *Setup Node/PHP App* atau impor SQL
   secara manual lewat phpMyAdmin.

6. **Izin folder**
   `storage/` dan `uploads/` harus dapat ditulis (`755`, atau `775` bila perlu).

7. **Selesai** — buka `https://domain-anda.com`.

### Catatan keamanan

`.htaccess` di root sudah memblokir akses langsung ke `app/`, `config/`,
`database/`, `routes/`, `storage/`, `vendor/`, `.env`, dan file sensitif lain,
karena folder-folder tersebut kini berada di bawah dokumen root. **Jangan hapus
atau timpa file `.htaccess` tersebut.**

File yang diunggah pengguna disimpan di `uploads/` dan tidak dapat dieksekusi
sebagai PHP (`uploads/.htaccess`).

---

## 7. Menjalankan di Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
# sesuaikan DB_* di .env
php artisan migrate --seed
php artisan serve
```

Tidak ada langkah `npm`/build — CSS dan JavaScript ditulis langsung di
`assets/` tanpa bundler, supaya deploy cukup dengan unzip.

### Menjalankan test

```bash
# buat dulu databasenya sekali saja
mysql -u root -e "CREATE DATABASE kasir_pos_test"
php artisan test
```

39 test mencakup: pemisahan peran & guard, perhitungan checkout (pajak,
diskon, pembulatan, split payment), pengurangan stok, penomoran invoice,
pembatalan transaksi, pembuatan ID/barcode/QR, dan ekspor laporan.

---

## 8. Pintasan Keyboard di Terminal Kasir

| Tombol | Fungsi |
|---|---|
| `F2` | Fokus ke kolom pindai barcode |
| `F4` | Buka dialog pembayaran |
| `F9` | Tahan transaksi (parkir keranjang) |
| `Enter` | Di dialog bayar: selesaikan transaksi |
| `Esc` | Tutup dialog |

Kolom pindai selalu merebut fokus kembali, sehingga scanner barcode
(yang bekerja seperti keyboard) langsung berfungsi tanpa klik.

---

## 9. Struktur Penting

```
index.php              front controller (dokumen root)
.htaccess              rewrite + proteksi folder framework
assets/css|js          design system & runtime, tanpa build step
uploads/               file unggahan (logo, gambar produk)
app/Support/Role.php   matriks peran & izin
app/Services/          SkuGenerator, CodeImageService, CheckoutService,
                       StockService, ReportService
resources/views/pos/   terminal kasir
resources/views/print/ struk termal, invoice PDF, laporan PDF
```

---

## 10. Catatan Teknis

- **Perhitungan harga selalu dihitung ulang di server.** Nominal yang dikirim
  browser hanya untuk tampilan; harga diambil dari database saat checkout.
- **Stok hanya berubah lewat ledger** (`stock_movements`), mencatat saldo
  sebelum dan sesudah, sehingga selalu dapat diaudit.
- **Modal & laba di-snapshot** pada tiap transaksi, sehingga perubahan harga
  modal di kemudian hari tidak mengubah laporan laba masa lalu.
- **Multi-tenant**: seluruh data terikat pada `tenant_id` dengan global scope,
  siap dikembangkan menjadi multi-outlet.
- Laravel 11 masih memiliki dua advisory keamanan yang perbaikannya hanya
  tersedia di Laravel 12+ (CRLF pada aturan validasi `email`, dan signed URL).
  Keduanya tidak dipakai aplikasi ini, namun pertimbangkan upgrade bila nanti
  menambahkan fitur email atau tautan bertanda tangan.
