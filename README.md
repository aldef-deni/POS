<p align="center">
  <a href="https://pos.aldeftech.com" target="_blank">
    <img src="assets/img/aldef-landscape.png" width="520" alt="Logo Aldef Tech">
  </a>
</p>

<h1 align="center">Kasir POS</h1>

<p align="center">
  <strong>SaaS Point of Sale untuk operasional toko dan multi-outlet.</strong>
</p>

<p align="center">
  <a href="https://pos.aldeftech.com"><img src="https://img.shields.io/badge/Website-pos.aldeftech.com-0ea5e9?style=flat-square" alt="Website Kasir POS"></a>
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2 atau lebih baru">
  <img src="https://img.shields.io/badge/POS-Multi--Outlet-22c55e?style=flat-square" alt="POS Multi-Outlet">
</p>

Aplikasi kasir berbasis **Laravel 11** dengan terminal kasir yang berdiri sendiri,
dashboard pengelola berbasis peran, pembuatan ID/barcode/QR produk otomatis, dan
pelaporan lengkap yang dapat diekspor ke PDF & CSV.

## 1. Akun Contoh

Setelah menjalankan seeder, gunakan akun berikut.

| Peran | Masuk lewat | Kredensial | PIN | Outlet |
|---|---|---|---|---|
| Owner | `/admin/login` | `owner` / `owner123` | 9999 | Semua outlet |
| Supervisor | `/admin/login` | `supervisor` / `super123` | 4321 | Cikini |
| Budi Santoso | `/pos/login` | — (PIN saja) | **1234** | Cikini |
| Siti Aminah | `/pos/login` | — (PIN saja) | **5678** | Kemang |
| Dewi Anggraini | `/pos/login` | — (PIN saja) | **2468** | BSD |

**Terminal kasir hanya menerima PIN.** Halaman `/pos/login` menampilkan daftar
pengguna berperan **Kasir** yang sudah memiliki PIN — cukup pilih nama, ketik
PIN, selesai. Tidak ada login kata sandi di terminal, sehingga kata sandi
dashboard tidak pernah diketik di meja kasir.

PIN Owner dan Supervisor tetap dipakai untuk **menyetujui pembatalan
transaksi** di terminal, bukan untuk masuk.

> **Ganti seluruh kata sandi dan PIN ini sebelum dipakai sungguhan.**

---

## 2. Alur Aplikasi

| URL | Untuk siapa | Keterangan |
|---|---|---|
| `/` | Umum | Halaman pemilihan: Terminal Kasir atau Dashboard |
| `/pos/login` | Kasir | Login kasir dengan PIN — **terpisah** dari dashboard |
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
| Kelola outlet / cabang | ✅ | ❌ | ❌ |
| Berpindah antar outlet | ✅ | ❌ (terkunci) | ❌ |
| Masuk terminal kasir (PIN) | — | — | ✅ |
| Setujui pembatalan di kasir (PIN) | ✅ | ✅ | ❌ |
| Shift sendiri (buka/tutup laci) | — | — | ✅ |
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

Terminal kasir sengaja hanya menampilkan pengguna berperan **Kasir**. Bila suatu
saat Owner/Supervisor perlu ikut berjaga di meja kasir, hapus baris
`->where('role', Role::Kasir->value)` pada `PosAuthController::show()` dan
pastikan mereka punya PIN — sisa mekanismenya sudah siap.

---

## 4. Multi Outlet / Cabang

Satu toko dapat memiliki banyak cabang. Yang **dibagi bersama** adalah katalog
produk, harga, kategori, pelanggan, dan pengaturan toko. Yang **terpisah per
outlet** adalah:

**stok · staf · shift & laci kas · transaksi · nomor invoice · laporan**

### Penempatan operator wajib

Saat menambah atau mengubah pengguna, kolom **Outlet Penempatan** wajib diisi
dan tidak punya nilai bawaan — operator harus dipilih secara sadar, bukan
karena dropdown kebetulan berhenti di pilihan pertama. Aturannya:

- **Kasir & Supervisor** wajib satu outlet. Pilihan "Semua Outlet" ditolak
  server, bukan hanya disembunyikan di tampilan.
- **Owner** boleh "Semua Outlet", dan itupun harus dipilih eksplisit.
- Kasir tanpa outlet **tidak bisa membuka terminal** sama sekali.
- Operator **tidak bisa dipindah saat shift masih terbuka** — kalau tidak,
  setoran kasnya akan terbelah di dua cabang.

### Stok per outlet

Stok disimpan di tabel `outlet_stocks` (satu baris per produk per outlet).
Terminal kasir hanya menampilkan dan hanya boleh menjual stok cabangnya
sendiri; menjual barang yang stoknya ada di cabang lain akan ditolak.

Penyesuaian stok dan stok opname juga per outlet — tombolnya nonaktif bila
tampilan sedang "Semua Outlet", karena stok harus punya tujuan yang jelas.

### Cara restok produk yang habis

**Dashboard → Stok & Inventori → Restok Produk**, atau tombol **Restok** pada
kartu *Stok Menipis* di dashboard.

1. Pilih outlet tujuan di pemilih kanan atas (stok selalu masuk ke satu cabang).
2. Halaman terbuka pada tab **Perlu Restok** — produk **Habis** tampil paling
   atas, disusul yang **Menipis**.
3. Isi kolom **Jumlah Masuk**. Kolom *Stok Setelah* memperbarui diri seketika,
   jadi hasilnya terlihat sebelum disimpan.
4. Untuk mengisi banyak produk sekaligus: atur *Isi otomatis sampai* **2×
   minimum**, lalu tekan **Terapkan**.
5. Isi catatan bila perlu (mis. nomor faktur supplier), lalu **Simpan Restok**.

Semua produk tersimpan dalam satu langkah, dan masing-masing menghasilkan satu
baris di buku besar stok lengkap dengan saldo sebelum dan sesudah.

> **Outlet baru selalu mulai dari stok nol.** Katalog produknya sudah ada,
> tetapi rak cabang itu masih kosong. Buka Restok Produk → tab **Semua Produk**
> → **Terapkan** untuk mengisinya sekaligus.

Untuk koreksi satu produk (barang rusak, salah hitung), gunakan **Penyesuaian
Stok**; untuk mencocokkan dengan hitungan fisik, gunakan **Stok Opname**.

### Nomor invoice memuat kode outlet

```
INV-CKN-260814-0001     ← Cikini
INV-KMG-260814-0001     ← Kemang
```

Dua cabang yang bertransaksi bersamaan tidak akan pernah menghasilkan nomor
yang sama, dan struk langsung menunjukkan asal cabangnya.

### Filter laporan

Pemilih outlet di kanan atas dashboard mengatur **seluruh** halaman sekaligus —
dashboard, penjualan, stok, shift, dan kesepuluh laporan. Pilih satu outlet
untuk memfilter, atau **Semua Outlet** untuk melihat gabungan seluruh cabang.

Pengguna yang ditugaskan pada satu outlet melihat pemilih itu sebagai label
terkunci; ia tidak dapat melihat data cabang lain, bahkan dengan mengubah URL.

Saat "Semua Outlet" aktif, dashboard menampilkan **perbandingan performa antar
cabang**, dan halaman Outlet & Cabang memuat tabel omzet, laba, nilai stok,
serta kontribusi tiap cabang.

---

## 5. Profil Pengguna

Setiap pengguna — Owner, Supervisor, **dan Kasir** — punya halaman profilnya
sendiri:

| Peran | Dibuka lewat |
|---|---|
| Owner & Supervisor | Menu akun kanan atas → **Profil Saya** |
| Kasir | Menu terminal → **Profil Saya** (juga tersedia di layar Buka Shift) |

Halaman kasir sengaja diletakkan **di luar penjagaan shift**, supaya kasir bisa
mengganti PIN-nya sebelum membuka laci.

Yang bisa diubah sendiri: **nama, username, email, telepon, foto profil, kata
sandi, dan PIN**.

Yang **tidak** bisa diubah sendiri: **peran dan outlet**. Keduanya keputusan
Owner — kalau bisa diubah sendiri, seluruh model hak akses jadi tidak berarti.
Ditampilkan sebagai informasi saja.

### Foto profil

- Format JPG, PNG, atau WEBP, maksimal 4 MB
- Dipotong persegi dan diperkecil otomatis ke **320×320** — foto ponsel 4 MP
  yang disimpan apa adanya akan dikirim ulang di setiap halaman yang
  menampilkan chip avatar kecil
- File lama dihapus saat diganti, jadi folder `uploads/` tidak menumpuk
- Foto muncul di topbar dashboard, daftar pengguna, layar pilih operator di
  terminal, dan layar buka shift

### Keamanan

- Ganti kata sandi wajib menyertakan **kata sandi saat ini**
- Ganti PIN wajib menyertakan **PIN saat ini** (kasir hanya tahu PIN-nya,
  bukan kata sandinya). Yang belum punya PIN bisa mengaturnya langsung.
- Semua rute profil bekerja pada **pengguna yang sedang masuk** — tidak ada
  ID pengguna di URL, sehingga tidak ada cara menyentuh profil orang lain

---

## 6. Mekanisme ID Produk

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

## 7. Laporan

Sepuluh laporan, semuanya dengan rentang tanggal bebas dan tombol **Export PDF**
serta **CSV**:

Ringkasan Penjualan · Detail Transaksi · Penjualan per Produk ·
Penjualan per Kategori · Kinerja Kasir · Metode Pembayaran ·
Laba & Margin · Nilai Persediaan · Rekap Shift Kasir · Transaksi Dibatalkan

Selain itu tersedia: **Invoice PDF** per transaksi dan **Laporan Tutup Shift PDF**
(lengkap dengan kolom tanda tangan kasir & supervisor).

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

## Kustomisasi

<p align="center">
  <strong>JIKA BERMINAT UNTUK KUSTOMISASI BISA MENGHUBUNGI DENI AFRIZAL</strong>
</p>

<p align="center">
  <a href="https://wa.me/628128968609" target="_blank">
    <img src="https://img.shields.io/badge/WhatsApp-Hubungi_Deni_Afrizal-25D366?style=for-the-badge&logo=whatsapp&logoColor=white" alt="Hubungi Deni Afrizal melalui WhatsApp">
  </a>
</p>

## Kontak

Punya kebutuhan sistem, aplikasi, SaaS, integrasi, atau otomasi AI? Kunjungi [aldeftech.com/contact](https://aldeftech.com/contact) untuk mendiskusikan kebutuhan bisnis Anda bersama Aldef Tech.

---

<p align="center">
  © Aldef Tech. Seluruh hak cipta dilindungi.
</p>
