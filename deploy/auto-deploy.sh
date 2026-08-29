#!/usr/bin/env bash
#
# Deploy otomatis ALDEF POS.
#
# Dijalankan cron tiap 2 menit. Kalau tidak ada commit baru, keluar diam-diam
# dalam sepersekian detik. Kalau ada, aplikasi diperbarui dari GitHub.
#
# Sengaja TIDAK memakai GitHub Actions: tidak ada komputasi di pihak GitHub,
# tidak ada kredensial yang dititipkan ke sana, dan kalau GitHub sempat tidak
# terjangkau, percobaan berikutnya cukup 2 menit lagi.
#
# Bawaannya menyesuaikan aaPanel (path /www/wwwroot, user web "www").
# Untuk server lain, ubah bagian Pengaturan di bawah.
#
# Pasang:
#   sudo install -m 755 deploy/auto-deploy.sh /usr/local/bin/auto-deploy.sh
#   sudo crontab -e
#   */2 * * * * /usr/local/bin/auto-deploy.sh
#
set -euo pipefail

# ============================================================ Pengaturan

APP_DIR="${APP_DIR:-/www/wwwroot/pos.aldeftech.com}"
BRANCH="${BRANCH:-main}"

# Pemilik berkas yang dipakai web server. aaPanel memakai "www", bukan
# "www-data" seperti Ubuntu biasa. Salah di sini membuat unggahan gagal dengan
# "permission denied" setelah deploy pertama.
WEB_USER="${WEB_USER:-www}"
WEB_GROUP="${WEB_GROUP:-www}"

BACKUP_DIR="${BACKUP_DIR:-/www/backup/pos-deploy}"
BACKUP_KEEP="${BACKUP_KEEP:-14}"

LOG="${LOG:-/www/wwwlogs/pos-deploy.log}"
LOG_MAX_BYTES="${LOG_MAX_BYTES:-5242880}" # 5 MB

LOCKDIR="/tmp/pos-deploy.lock.d"
PENANDA_GAGAL="${APP_DIR}/storage/deploy-gagal"

# ================================================== Pencarian program
#
# Cron berjalan dengan PATH minim (biasanya /usr/bin:/bin), sedangkan aaPanel
# menaruh PHP di /www/server/php/<versi>/bin. Mengandalkan `command -v` saja
# membuat skrip ini jalan mulus saat diuji manual lalu gagal diam-diam di cron.

# Kandidat diperiksa lebih dulu, baru PATH. Di aaPanel, `command -v php`
# menunjuk /usr/bin/php - PHP sistem yang ekstensinya belum tentu sama dengan
# PHP yang dipakai situsnya. Artisan yang jalan di PHP keliru gagal dengan
# pesan yang membingungkan.
cari_program() {
    local nama="$1"; shift

    local kandidat berkas
    for kandidat in "$@"; do
        # Versi PHP aaPanel dicari dari yang terbaru.
        for berkas in $(ls -d $kandidat 2>/dev/null | sort -rV); do
            if [ -x "$berkas" ]; then printf '%s' "$berkas"; return 0; fi
        done
    done

    local ketemu
    ketemu=$(command -v "$nama" 2>/dev/null || true)
    if [ -n "$ketemu" ]; then printf '%s' "$ketemu"; return 0; fi

    return 1
}

PHP="${PHP:-$(cari_program php '/www/server/php/*/bin/php' /usr/local/bin/php /usr/bin/php || true)}"
COMPOSER="${COMPOSER:-$(cari_program composer '/home/*/bin/composer' /usr/local/bin/composer /usr/bin/composer || true)}"
MYSQLDUMP="${MYSQLDUMP:-$(cari_program mysqldump /www/server/mysql/bin/mysqldump /usr/bin/mysqldump || true)}"

# Git menolak bekerja di repositori milik user lain ("dubious ownership").
# Berkas situs ini milik www, sedangkan cron berjalan sebagai root - tanpa ini
# setiap perintah git gagal, dan skripnya hanya akan diam mencoba lagi tiap
# 2 menit selamanya. Diberikan per-perintah, bukan mengubah konfigurasi global.
g() {
    git -c safe.directory="$APP_DIR" "$@"
}

# ============================================================== Pembantu

catat() {
    printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >> "$LOG"
}

# Log dipangkas sendiri; cron tiap 2 menit akan menumpuk baris selamanya.
pangkas_log() {
    [ -f "$LOG" ] || return 0
    local ukuran
    ukuran=$(wc -c < "$LOG")
    if [ "$ukuran" -gt "$LOG_MAX_BYTES" ]; then
        tail -n 2000 "$LOG" > "${LOG}.tmp" && mv "${LOG}.tmp" "$LOG"
        catat "Log dipangkas."
    fi
}

# Baca satu nilai dari .env tanpa memuat seluruh berkasnya sebagai skrip.
env_ambil() {
    local kunci="$1"
    local baris
    baris=$(grep -E "^${kunci}=" "${APP_DIR}/.env" 2>/dev/null | tail -n 1) || return 0
    baris="${baris#*=}"
    baris="${baris%\"}"; baris="${baris#\"}"
    baris="${baris%\'}"; baris="${baris#\'}"
    printf '%s' "$baris"
}

# Ditandai saat situs dimatikan sementara, dibaca oleh bersihkan().
SITUS_MATI=0

# Pastikan situs kembali menyala apa pun yang terjadi. Deploy yang gagal boleh
# meninggalkan kode lama, tetapi tidak boleh meninggalkan situs mati.
nyalakan_lagi() {
    ( cd "$APP_DIR" && { "$PHP" artisan up >/dev/null 2>&1 || rm -f storage/framework/down; } )
    SITUS_MATI=0
}

# Satu-satunya trap EXIT di skrip ini. Menggabungkan dua tugas supaya trap
# bersarang tidak saling menimpa - kesalahan klasik yang membuat kunci
# tertinggal atau situs ditinggal mati.
bersihkan() {
    [ "$SITUS_MATI" = "1" ] && nyalakan_lagi
    rm -rf "$LOCKDIR"
}

cadangkan_database() {
    local db user pass host
    db=$(env_ambil DB_DATABASE)
    user=$(env_ambil DB_USERNAME)
    pass=$(env_ambil DB_PASSWORD)
    host=$(env_ambil DB_HOST)
    host="${host:-127.0.0.1}"

    if [ -z "$db" ] || [ -z "${MYSQLDUMP:-}" ]; then
        catat "PERINGATAN: database tidak dicadangkan (mysqldump atau DB_DATABASE tidak ditemukan)."
        return 0
    fi

    mkdir -p "$BACKUP_DIR"
    local berkas="${BACKUP_DIR}/${db}-$(date '+%Y%m%d-%H%M%S').sql.gz"

    if MYSQL_PWD="$pass" "$MYSQLDUMP" --single-transaction --quick --no-tablespaces \
        -h "$host" -u "$user" "$db" 2>>"$LOG" | gzip > "$berkas"; then
        catat "Database dicadangkan: $berkas"
    else
        catat "GAGAL mencadangkan database. Deploy dibatalkan."
        rm -f "$berkas"
        return 1
    fi

    # Simpan sejumlah cadangan terakhir saja supaya disk tidak penuh.
    ls -1t "${BACKUP_DIR}"/*.sql.gz 2>/dev/null | tail -n "+$((BACKUP_KEEP + 1))" \
        | xargs -r rm -f
}

# ================================================================= Deploy

jalankan() {
    cd "$APP_DIR"

    if [ ! -d .git ]; then
        catat "GALAT: ${APP_DIR} bukan hasil git clone. Deploy tidak bisa berjalan."
        return 1
    fi

    if [ -z "${PHP:-}" ] || [ ! -x "$PHP" ]; then
        catat "GALAT: PHP tidak ditemukan. Sebutkan letaknya di bagian Pengaturan skrip ini."
        return 1
    fi

    if ! g fetch --quiet origin "$BRANCH" 2>>"$LOG"; then
        # Bisa gangguan jaringan sesaat (wajar, dicoba lagi 2 menit lagi), bisa
        # juga hak akses yang salah - dan yang kedua tidak akan pernah membaik
        # sendiri. Pesan aslinya sudah tercatat di log tepat di atas baris ini.
        catat "PERINGATAN: git fetch gagal. Periksa pesan di atas bila berulang."
        return 0
    fi

    local sekarang tujuan
    sekarang=$(g rev-parse HEAD)
    tujuan=$(g rev-parse "origin/${BRANCH}")

    # Tidak ada yang baru: keluar tanpa menulis apa pun. Ini jalur yang dilalui
    # 99% dari 720 kali eksekusi per hari.
    [ "$sekarang" = "$tujuan" ] && return 0

    catat "----------------------------------------------------------------"
    catat "Commit baru: ${sekarang:0:8} -> ${tujuan:0:8}"
    catat "$(g log -1 --format='%s' "$tujuan")"

    cadangkan_database || return 1

    # Sejak titik ini situs dimatikan sementara; bersihkan() menjamin nyala lagi.
    SITUS_MATI=1
    "$PHP" artisan down --retry=30 >/dev/null 2>&1 || true

    local berubah
    berubah=$(g diff --name-only "$sekarang" "$tujuan")

    # reset --hard, bukan pull: server harus persis sama dengan repo, dan
    # perubahan lokal di server tidak pernah dimaksudkan untuk dipertahankan.
    # TANPA git clean - berkas pengguna di storage/ tidak terlacak git dan
    # harus tetap ada.
    if ! g reset --hard "$tujuan" >>"$LOG" 2>&1; then
        catat "GAGAL memperbarui kode."
        return 1
    fi

    if echo "$berubah" | grep -q '^composer\.\(json\|lock\)$'; then
        catat "Dependensi berubah, menjalankan composer install."
        if [ -z "${COMPOSER:-}" ] || [ ! -x "$COMPOSER" ]; then
            catat "GAGAL: composer tidak ditemukan, padahal dependensi berubah."
            return 1
        fi
        if ! "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction \
             --no-progress >>"$LOG" 2>&1; then
            catat "GAGAL composer install."
            return 1
        fi
    fi



    if ! "$PHP" artisan migrate --force >>"$LOG" 2>&1; then
        catat "GAGAL migrasi database."
        catat "Database TIDAK dipulihkan otomatis - lihat catatan di bawah."
        return 1
    fi

    "$PHP" artisan config:clear >/dev/null 2>&1 || true
    "$PHP" artisan config:cache >>"$LOG" 2>&1 || catat "PERINGATAN: config:cache gagal."
    "$PHP" artisan route:cache  >>"$LOG" 2>&1 || catat "PERINGATAN: route:cache gagal."
    "$PHP" artisan view:clear   >/dev/null 2>&1 || true
    "$PHP" artisan view:cache   >>"$LOG" 2>&1 || catat "PERINGATAN: view:cache gagal."

    # Cron kemungkinan berjalan sebagai root; tanpa ini berkas baru jadi milik
    # root dan web server tidak bisa menulis ke storage.
    if [ "$(id -u)" = "0" ]; then
        chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache 2>/dev/null || true
    fi
    chmod -R ug+rw storage bootstrap/cache 2>/dev/null || true

    nyalakan_lagi

    rm -f "$PENANDA_GAGAL"
    catat "SELESAI. Sekarang di ${tujuan:0:8}."
    return 0
}

# ================================================================== Utama

pangkas_log

# flock mencegah dua deploy berjalan bersamaan. Deploy bisa memakan lebih dari
# 2 menit, dan cron tidak peduli apakah yang sebelumnya sudah selesai.
# mkdir bersifat atomik di seluruh sistem berkas POSIX dan selalu tersedia.
# flock lebih rapi, tetapi belum tentu terpasang - dan kalau tidak ada, skrip
# ini akan diam-diam tidak pernah men-deploy apa pun.
if ! mkdir "$LOCKDIR" 2>/dev/null; then
    # Kunci yang tertinggal karena proses mati mendadak dibuang setelah 30 menit,
    # supaya satu kegagalan tidak menghentikan deploy selamanya.
    if find "$LOCKDIR" -maxdepth 0 -mmin +30 2>/dev/null | grep -q .; then
        catat "Kunci lama ditemukan (>30 menit), dibersihkan."
        rm -rf "$LOCKDIR"
        mkdir "$LOCKDIR" 2>/dev/null || exit 0
    else
        exit 0
    fi
fi

trap bersihkan EXIT

SEBELUM=$(cd "$APP_DIR" && git -c safe.directory="$APP_DIR" rev-parse HEAD 2>/dev/null || echo '')

if jalankan; then
    exit 0
fi

# ------------------------------------------------------------- Pemulihan
catat "DEPLOY GAGAL. Mengembalikan kode ke ${SEBELUM:0:8}."

cd "$APP_DIR" || exit 1

if [ -n "$SEBELUM" ]; then
    g reset --hard "$SEBELUM" >>"$LOG" 2>&1 || catat "GAGAL mengembalikan kode."
    "$PHP" artisan config:clear >/dev/null 2>&1 || true
    "$PHP" artisan config:cache >>"$LOG" 2>&1 || true
    "$PHP" artisan route:cache  >>"$LOG" 2>&1 || true
    "$PHP" artisan view:cache   >>"$LOG" 2>&1 || true
fi

nyalakan_lagi

# Penanda supaya kegagalan tidak berlalu tanpa diketahui: percobaan berikutnya
# 2 menit lagi akan gagal dengan cara yang sama, dan tanpa penanda ini log
# hanya terisi kegagalan berulang tanpa ada yang menyadarinya.
{
    echo "Deploy gagal pada $(date '+%Y-%m-%d %H:%M:%S')"
    echo "Kode dikembalikan ke ${SEBELUM:0:8}."
    echo
    echo "Kalau kegagalannya pada migrasi, database TIDAK dipulihkan otomatis:"
    echo "pemulihan otomatis akan membuang data yang ditulis pengguna sejak"
    echo "cadangan diambil. Pulihkan manual bila perlu:"
    echo "  gunzip < ${BACKUP_DIR}/<berkas>.sql.gz | mysql -u <user> -p <database>"
    echo
    echo "Lihat rinciannya: tail -n 80 ${LOG}"
} > "$PENANDA_GAGAL"

catat "Penanda kegagalan ditulis: $PENANDA_GAGAL"
exit 1
