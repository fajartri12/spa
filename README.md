

Aplikasi Pengelolaan Pembayaran Tagihan Sekolah — 

Sistem terintegrasi untuk mengelola data induk sekolah, tagihan siswa,
pembayaran, verifikasi, keuangan, laporan, PPDB, serta notifikasi kepada
orang tua/wali.

---

## Teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 12 |
| Frontend | Laravel Blade |
| Interaktivitas | Livewire 3 + Alpine.js |
| Styling | Tailwind CSS 4 |
| Build | Vite |
| Basis Data | MySQL 8 |
| Otorisasi | Spatie Laravel Permission |
| Grafik | Chart.js |
| Ikon | Lucide Icons |

---

## Status Pengembangan

| Fase | Modul | Status |
|---|---|---|
| 1 | Foundation — auth, design system, layout, dashboard | ✅ Selesai |
| 2 | Master Data — 8 modul CRUD | ✅ Selesai |
| 3 | Billing — tagihan & potongan | ✅ Selesai |
| 4 | Payments — pembayaran, verifikasi, reversal | ✅ Selesai |
| 5 | Financial Reports — arus kas, tunggakan, export | ✅ Selesai |
| 6 | PPDB — periode, pendaftar, portal publik | ✅ Selesai |
| 7 | System Tools — notifikasi, backup, log, info server | ✅ Selesai |
| 8 | Finalization — optimasi, security review, deployment | ✅ Selesai |

Suite pengujian: **191 test lulus, 527 assertion**.

Rincian lengkap: [IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md)

---

## Modul Tersedia

### Master Data (`/data-master/*`)

| Halaman | Route | Keterangan |
|---|---|---|
| Unit Pendidikan | `units.index` | Unit di bawah naungan madrasah |
| Tahun Ajaran | `academic-years.index` | Periode, satu aktif pada satu waktu |
| Kelas | `school-classes.index` | Rombel per unit & tahun ajaran |
| Petugas | `staff.index` | Profil tenaga pendidik & kependidikan |
| Siswa | `students.index` | Data induk siswa & penempatan kelas |
| Kategori Pembayaran | `payment-categories.index` | Tarif tagihan (SPP, gedung, seragam) |
| Akun Keuangan | `accounts.index` | Bagan akun standar |
| Rekening Bank | `bank-accounts.index` | Rekening tujuan pembayaran |

### Billing, Pembayaran & Laporan

| Halaman | Route | Keterangan |
|---|---|---|
| Tagihan | `bills.index` | Daftar tagihan & status per siswa |
| Generate Tagihan | `bills.generate` | Pembuatan tagihan massal |
| Pembayaran | `payments.index` | Riwayat seluruh transaksi pembayaran |
| Proses Pembayaran | `payments.create` | Pencatatan pembayaran + alokasi multi-tagihan |
| Verifikasi Pembayaran | `payments.verification` | Antrian setujui / tolak pembayaran pending |
| Rekap Keuangan | `reports.index` | Penerimaan, arus kas, tunggakan |
| Ekspor Rekap | `reports.export` | CSV penerimaan / tunggakan |

### PPDB

| Halaman | Route | Keterangan |
|---|---|---|
| Data Pendaftar | `admissions.applicants` | Pendaftaran calon siswa, filter periode & status |
| Data Tagihan | `admissions.bills` | Terbit tagihan PPDB & ubah status bayar |
| Data Periode | `admissions.periods` | CRUD periode penerimaan |
| Konten Informasi | `admissions.contents` | Pengumuman portal pendaftaran |
| Pengaturan PPDB | `admissions.settings` | 8 bagian: umum, formulir, biaya, persyaratan, notifikasi, kontak, bidang, komponen |
| Akses Portal | `admissions.portal` | Status periode & tautan pendaftaran publik |
| Admin PPDB (lama) | `admissions.index` | Halaman gabungan periode & pendaftar |
| Pendaftaran Publik | `ppdb.register` | Formulir calon siswa (tanpa login) |

### Peralatan & Sistem

| Halaman | Route | Keterangan |
|---|---|---|
| Log Aktivitas | `activity-logs.index` | Jejak audit seluruh modul |
| Notifikasi & Antrian | `notifications.index` | Log kirim, template, jadwal pengingat |
| Backup Database | `backups.index` | Buat & pulihkan cadangan |
| Informasi Server | `server-info.index` | Runtime & pembersihan cache |

### Pengaturan (`/pengaturan/*`)

| Halaman | Route | Keterangan |
|---|---|---|
| Pengaturan Umum | `settings.index` | Identitas aplikasi, penandatangan, format nomor |
| Pengguna | `users.index` | Akun petugas & penetapan role |
| Role & Hak Akses | `roles.index` | Matriks permission per role |

### Lainnya

| Halaman | Route |
|---|---|
| Masuk | `login` |
| Beranda / Dashboard | `dashboard` |
| Profil Pengguna | `profile.edit` |

---

## Instalasi Cepat

```bash
# Dependensi
composer install
npm install

# Environment
copy .env.example .env
php artisan key:generate

# Basis data (sesuaikan kredensial pada .env)
mysql -u root -e "CREATE DATABASE komite_man7 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed

# Aset
npm run build

# Jalankan
php artisan serve
```

Panduan lengkap termasuk konfigurasi produksi: [DEPLOYMENT.md](./DEPLOYMENT.md)

---

## Akun Awal

Disediakan oleh `UserSeeder` (kata sandi default `password`) — **hanya dibuat
pada `APP_ENV=local`/`testing`**, jadi di produksi akun admin pertama harus
dibuat manual dan kata sandinya wajib diganti.

| Role | Email |
|---|---|
| Super Administrator | `superadmin@email.com` |
| Administrator | `admin@email.com` |
| Bendahara | `bendahara@email.com` |
| Petugas Pembayaran | `petugas@email.com` |
| Verifikator | `verifikator@email.com` |
| Kepala Sekolah | `kepsek@email.com` |
| Operator PPDB | `ppdb@email.com` |

---

## Pengujian

```bash
php artisan test
```

Basis data pengujian: `komite_man7_test` (dikonfigurasi pada `phpunit.xml`).

---

## Struktur Direktori Penting

```text
app/
├── Enums/          PermissionName, RoleName, AccountType, StudentStatus, ...
├── Livewire/
│   ├── Auth/       Login
│   ├── Dashboard/  DashboardIndex
│   ├── Master/     8 modul master data
│   ├── Profile/    ProfileEdit
├── Models/         Unit, AcademicYear, SchoolClass, Student, ...
├── Services/       DashboardService, ActivityLogger
└── Support/        Formatter, Navigation
```

---

## Aturan Penting

### Penambahan Permission

Setiap case baru pada `app/Enums/PermissionName.php` **wajib** diikuti:

```bash
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan permission:cache-reset
```

Tanpa langkah ini, route yang dilindungi permission baru akan mengembalikan
HTTP 403 walaupun pengguna adalah Super Administrator.

### Nominal Uang

Seluruh kolom nominal memakai `decimal(15, 2)`. Input pengguna diproses melalui
`Formatter::parseRupiah()` agar format Indonesia (`1.500.000`) terbaca benar.

### Kata Sandi Portal Siswa

Portal siswa (`/portal/login`) tidak memakai kata sandi bersama. Setiap siswa
memiliki `portal_password` sendiri (hash) dan akun tanpa password tidak dapat
login. Terbitkan/atur ulang secara massal:

```bash
php artisan students:issue-portal-passwords          # hanya yang belum punya
php artisan students:issue-portal-passwords --force  # terbitkan ulang semua
```

Perintah menulis CSV `nis,nisn,nama,password` ke disk privat
(`storage/app/private/`) dan menampilkan path absolutnya. Bagikan per siswa lalu
hapus berkasnya.

### Tahun Ajaran Siswa

Tabel `students` tidak menyimpan `academic_year_id`. Tahun ajaran siswa
diturunkan dari `students.school_class_id → school_classes.academic_year_id`.
Setiap filter tahun ajaran pada siswa wajib melakukan join ke `school_classes`.

---

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [AGENTS.MD](./AGENTS.MD) | Aturan pengembangan untuk AI Agent |
| [DESIGN.MD](./DESIGN.MD) | Design system dan aturan antarmuka |
| [PRD.md](./PRD.md) | Kebutuhan produk dan ruang lingkup |
| [DATABASE_ERD.md](./DATABASE_ERD.md) | Skema basis data dan relasi |
| [IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md) | Rencana dan status per fase |
| [API_INTEGRATION.md](./API_INTEGRATION.md) | Integrasi WhatsApp, Email, payment gateway |
| [DEPLOYMENT.md](./DEPLOYMENT.md) | Panduan instalasi dan deployment |
| [CHANGELOG.md](./CHANGELOG.md) | Riwayat perubahan |
