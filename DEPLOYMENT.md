# DEPLOYMENT — KOMITE MAN 7 JOMBANG

Panduan instalasi, konfigurasi, dan deployment aplikasi.

---

## 1. Kebutuhan Server

| Komponen | Versi Minimum | Catatan |
|---|---|---|
| PHP | 8.2 | Ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `gd`, `intl` |
| MySQL / MariaDB | 8.0 / 10.6 | `utf8mb4`, engine `InnoDB` |
| Composer | 2.x | |
| Node.js | 20.x | Untuk build aset (Vite) |
| Web Server | Apache / Nginx | Document root ke `public/` |

---

## 2. Instalasi Lokal (Development)

```bash
# 1. Dependensi PHP
composer install

# 2. Konfigurasi environment
copy .env.example .env
php artisan key:generate
```

> ⚠️ **Catatan Penting untuk XAMPP**: Pastikan layanan MySQL sudah berjalan sebelum melanjutkan.
> - Buka `XAMPP Control Panel`
> - Klik tombol `Start` di kolom `MySQL`
> - Verifikasi statusnya berubah menjadi `Running`

Sesuaikan `.env`:

```env
APP_NAME="KOMITE MAN 7 JOMBANG"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=komite_man7
DB_USERNAME=root
DB_PASSWORD=

# Backup database (path biner MySQL)
MYSQLDUMP_PATH=/usr/bin/mysqldump
MYSQL_PATH=/usr/bin/mysql

# Gateway WhatsApp (opsional; kosong = notifikasi ditandai gagal, bukan sukses palsu)
WHATSAPP_ENDPOINT=
WHATSAPP_TOKEN=
```

> `APP_DEBUG=true` hanya boleh dipakai sementara saat menelusuri masalah.
> Aplikasi menolak start bila `APP_ENV=production` dengan `APP_DEBUG=true`.
> Template lengkap untuk server produksi tersedia di `.env.production.example`.

### Password Portal Siswa

Tidak ada password bersama untuk portal siswa. Setiap siswa memiliki password
sendiri yang tersimpan sebagai hash dan hanya bisa diterbitkan ulang oleh
petugas. Akun tanpa password **tidak bisa login**.

```bash
# Terbitkan password baru untuk seluruh siswa aktif (menimpa yang lama)
php artisan students:issue-portal-passwords --force
```

Perintah ini menuliskan CSV `nis,nisn,nama,password` ke disk privat
(`storage/app/private/portal-passwords-*.csv`), menampilkan path absolutnya, dan
mengingatkan agar file dibagikan secara aman lalu dihapus. Bagikan satu baris
kepada siswa/orang tua yang bersangkutan; jangan menempelkan seluruh file di
grup pesan.

```bash
# 3. Buat basis data
mysql -u root -e "CREATE DATABASE komite_man7 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Migrasi + data awal
php artisan migrate
php artisan db:seed

# 5. Dependensi frontend & build
npm install
npm run build

# 6. Jalankan
php artisan serve
```

Akses `http://127.0.0.1:8000`. Kredensial awal ada di `database/seeders/UserSeeder.php`
dan **hanya dibuat pada environment `local`/`testing`**; di produksi `UserSeeder`
melewati pembuatan akun demo sehingga akun admin pertama harus dibuat manual
(mis. lewat `php artisan tinker` dengan `User::create()` + `assignRole()`).

### Mode pengembangan aset

```bash
npm run dev
```

---

## 3. Struktur Seeder

| Seeder | Lingkup |
|---|---|
| `PermissionSeeder` | 71 permission dari `PermissionName` enum |
| `RoleSeeder` | 7 role + pemetaan permission |
| `UserSeeder` | Akun pengguna awal |
| `UnitSeeder` | Unit pendidikan |
| `AcademicYearSeeder` | Tahun lalu, aktif, tahun depan |
| `AppSettingSeeder` | Pengaturan sistem default |
| `NotificationTemplateSeeder` | Template WhatsApp/Email & jadwal pengingat H-3/H-1 |
| `DemoDataSeeder` | Data contoh — **hanya environment `local`** |

`DemoDataSeeder` memanggil: `AccountSeeder`, `PaymentCategorySeeder`,
`BankAccountSeeder`, `StaffProfileSeeder`, `SchoolClassSeeder`, `StudentSeeder`.

> **Penting:** setelah mengubah `app/Enums/PermissionName.php`, selalu jalankan:
>
> ```bash
> php artisan db:seed --class=PermissionSeeder --force
> php artisan db:seed --class=RoleSeeder --force
> php artisan permission:cache-reset
> ```

---

## 4. Pengujian

Basis data pengujian terpisah (`komite_man7_test`) dikonfigurasi di `phpunit.xml`.

```bash
# Seluruh suite
php artisan test

# Modul tertentu
php artisan test --filter=StudentManagementTest

# Dengan coverage
php artisan test --coverage
```

Status saat ini: **seluruh suite lulus** (lihat output `php artisan test` terakhir).

---

## 5. Deployment Produksi

```bash
# 1. Dependensi tanpa paket development
composer install --no-dev --optimize-autoloader

# 2. Build aset
npm ci
npm run build

# 3. Konfigurasi produksi
```

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://komite.man7jombang.sch.id
LOG_LEVEL=warning

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Template ini tersedia lengkap di `.env.production.example`; salin dan isi
kredensial aslinya. Jangan commit `.env` hasil salinan. Aplikasi akan menolak
start bila `APP_ENV=production` disertai `APP_DEBUG=true`.

### User basis data

Jangan memakai `root` di produksi. Buat user dengan hak minimum:

```sql
CREATE USER 'komite'@'localhost' IDENTIFIED BY '<password-kuat>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON komite_man7.* TO 'komite'@'localhost';
```

```bash
# 4. Migrasi tanpa interaksi
php artisan migrate --force

# 5. Optimasi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Storage link (untuk file publik)
php artisan storage:link
```

### Permission direktori

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Web server

**Nginx** — arahkan root ke folder `public`:

```nginx
server {
    listen 443 ssl;
    server_name komite.man7jombang.sch.id;
    root /var/www/komite/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

**Apache** — `public/.htaccess` sudah disertakan Laravel. Pastikan
`mod_rewrite` aktif dan `AllowOverride All` untuk direktori aplikasi.

---

## 6. Scheduler & Queue

Tambahkan ke crontab server:

```cron
* * * * * cd /var/www/komite && php artisan schedule:run >> /dev/null 2>&1
```

Pemrosesan queue (notifikasi WhatsApp/Email, Fase 7):

```bash
php artisan queue:work --tries=3 --timeout=120
```

Disarankan dijalankan sebagai service `supervisor`, bukan proses manual.

---

## 7. Backup

Basis data wajib dibackup berkala sebelum setiap rilis:

```bash
mysqldump -u root -p --single-transaction --routines --triggers komite_man7 > backup_$(date +%F).sql
```

Simpan di luar direktori `public/`. Modul backup terintegrasi tersedia di
`/peralatan/backup` (permission `backup.create` untuk membuat/mengunduh,
`backup.delete` untuk menghapus, `backup.restore` untuk memulihkan) dan
membutuhkan `MYSQLDUMP_PATH` serta `MYSQL_PATH` yang menunjuk ke biner MySQL.

### Retensi log

Perintah `model:prune` dijadwalkan harian lewat `routes/console.php`:

| Tabel | Retensi | Alasan |
|---|---|---|
| `activity_logs` | 365 hari | Jejak audit satu tahun ajaran |
| `notification_logs` | 90 hari | Mengandung nomor/email penerima |

Scheduler **wajib** aktif agar retensi ini berjalan; tanpa itu data pribadi
penerima notifikasi akan tersimpan selamanya.

---

## 8. Checklist Rilis

- [ ] `php artisan test` hijau seluruhnya
- [ ] `APP_DEBUG=false` dan `APP_ENV=production`
- [ ] `.env` produksi disalin dari `.env.production.example` (bukan `.env.example`)
- [ ] Password portal siswa sudah diterbitkan (`students:issue-portal-passwords`)
- [ ] `php artisan migrate --force` berhasil tanpa error
- [ ] `php artisan db:seed --class=PermissionSeeder --force` + `RoleSeeder --force`
- [ ] `config:cache`, `route:cache`, `view:cache` dijalankan ulang
- [ ] Backup basis data dibuat
- [ ] Storage link tersedia
- [ ] Scheduler aktif (termasuk `model:prune`)
- [ ] Queue worker berjalan
- [ ] HTTPS aktif dan `SESSION_SECURE_COOKIE=true`
- [ ] Permission `storage/` dan `bootstrap/cache/` benar
