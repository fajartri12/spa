# AGENTS.md
# KOMITE MAN 7 JOMBANG
## AI Coding Agent Development Guidelines

**Project:** KOMITE MAN 7 JOMBANG  
**Application:** Aplikasi Pengelolaan Pembayaran Tagihan Sekolah  
**Architecture:** Laravel Monolithic Application  
**Database:** MySQL  
**Frontend:** Laravel Blade + Livewire 3  
**Interactivity:** Alpine.js  
**CSS:** Tailwind CSS  
**Language:** PHP, JavaScript, HTML, CSS  
**Timezone:** Asia/Jakarta  
**Currency:** IDR (Indonesian Rupiah)

---

# 1. PROJECT OVERVIEW

KOMITE MAN 7 JOMBANG adalah aplikasi pengelolaan administrasi pembayaran tagihan sekolah.

Aplikasi digunakan untuk mengelola:

- Data siswa.
- Data petugas.
- Data unit pendidikan.
- Data kelas.
- Tahun ajaran.
- Kategori pembayaran.
- Tagihan siswa.
- Potongan pembayaran.
- Proses pembayaran.
- Verifikasi pembayaran.
- Transaksi keuangan.
- Arus kas.
- Laporan keuangan.
- PPDB.
- Portal orang tua dan calon siswa.
- Notifikasi WhatsApp.
- Notifikasi Email.
- Backup database.
- Log aktivitas.
- Pengaturan sistem.

Tujuan aplikasi adalah menyediakan satu sistem terintegrasi untuk seluruh aktivitas administrasi keuangan sekolah.

## 1.1 Development Principles

AI Agent WAJIB mengikuti prinsip berikut:

1. Build functional application, not static prototype.
2. Prioritize data integrity.
3. Follow Laravel best practices.
4. Use clean and maintainable code.
5. Build reusable components.
6. Maintain consistent UI/UX.
7. Implement proper authorization.
8. Never bypass business validation.
9. Never use hardcoded production data.
10. Every module must be connected to the database.
11. Never create fake functionality.
12. Every important feature must have automated tests.

---

# 2. TECHNOLOGY STACK

Gunakan stack berikut secara konsisten.

| Technology | Implementation |
|---|---|
| Backend | Laravel |
| Frontend | Laravel Blade |
| Interactive UI | Livewire 3 |
| JavaScript | Alpine.js |
| CSS | Tailwind CSS |
| Database | MySQL |
| ORM | Eloquent |
| Authentication | Laravel Authentication |
| Authorization | Spatie Laravel Permission |
| Charts | Chart.js |
| Icons | Lucide Icons |
| PDF | Laravel DomPDF |
| Excel | Laravel Excel |
| Queue | Laravel Queue |
| Notification | Laravel Notifications |
| Build Tool | Vite |

## 2.1 Architecture Rules

Gunakan Laravel Monolithic Architecture.

DILARANG:

- Membuat frontend React terpisah.
- Membuat backend API terpisah untuk kebutuhan internal aplikasi.
- Menggunakan database selain MySQL tanpa persetujuan.
- Mengimplementasikan business logic di dalam Blade.
- Menempatkan seluruh business logic dalam controller.
- Membuat satu file PHP yang berisi terlalu banyak fungsi.
- Menggunakan query SQL mentah jika Eloquent dapat menyelesaikannya dengan baik.

Gunakan Blade untuk rendering halaman dan Livewire untuk interaksi dinamis.

API eksternal hanya digunakan jika diperlukan untuk integrasi WhatsApp, email, payment gateway, atau sistem pihak ketiga.

---

# 3. PROJECT STRUCTURE

Ikuti struktur Laravel yang terorganisasi.

```text
app/
├── Actions/
├── Console/
├── Enums/
├── Exports/
├── Imports/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Livewire/
│   ├── Dashboard/
│   ├── Master/
│   ├── Students/
│   ├── Bills/
│   ├── Payments/
│   ├── Reports/
│   ├── Admissions/
│   └── Settings/
├── Models/
├── Notifications/
├── Policies/
├── Providers/
├── Services/
└── Support/

database/
├── factories/
├── migrations/
└── seeders/

resources/
├── css/
├── js/
└── views/
    ├── layouts/
    ├── components/
    ├── dashboard/
    ├── master/
    ├── students/
    ├── bills/
    ├── payments/
    ├── reports/
    ├── admissions/
    ├── tools/
    └── settings/

routes/
├── web.php
└── console.php

tests/
├── Feature/
└── Unit/
```

Tambahkan folder atau class baru sesuai kebutuhan tanpa merusak struktur yang sudah ada.

---

# 4. CODING STANDARDS

## 4.1 PHP Standards

- Gunakan PSR-12.
- Gunakan strict typing jika sesuai.
- Gunakan type declaration.
- Gunakan dependency injection.
- Gunakan nama class yang deskriptif.
- Gunakan Laravel naming conventions.
- Hindari method terlalu panjang.
- Pisahkan proses bisnis kompleks ke Service atau Action.

Contoh:

```php
<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Collection;

class StudentService
{
    public function getActiveStudents(): Collection
    {
        return Student::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
```

## 4.2 Naming Convention

| Element | Convention |
|---|---|
| Model | Student |
| Controller | StudentController |
| Service | StudentService |
| Livewire | StudentIndex |
| Migration | create_students_table |
| Table | students |
| Route | students.index |
| Blade | index.blade.php |
| Variable | $student |
| Constant | MAX_UPLOAD_SIZE |

Gunakan bahasa Inggris untuk nama class, method, tabel, kolom, dan variabel.

Gunakan Bahasa Indonesia untuk label antarmuka aplikasi.

## 4.3 Controller Rules

Controller hanya bertugas:

- Menerima request.
- Memvalidasi request melalui Form Request.
- Memanggil Service atau Action.
- Mengembalikan response.

Jangan menempatkan seluruh proses bisnis di controller.

## 4.4 Blade Rules

- Gunakan reusable components.
- Hindari duplikasi HTML.
- Jangan menempatkan query database di Blade.
- Gunakan layout utama.
- Gunakan partial untuk bagian berulang.
- Gunakan komponen alert, modal, button, form, dan table.

---

# 5. DATABASE RULES

Database utama menggunakan MySQL.

## 5.1 General Rules

1. Semua tabel harus menggunakan migration.
2. Semua relasi harus memiliki foreign key.
3. Gunakan index untuk kolom pencarian.
4. Gunakan unique constraint jika diperlukan.
5. Gunakan decimal untuk nominal uang.
6. Jangan menggunakan FLOAT atau DOUBLE untuk transaksi.
7. Gunakan timestamps.
8. Gunakan soft deletes untuk data yang relevan.
9. Hindari cascading delete pada transaksi keuangan.
10. Simpan histori perubahan data penting.

## 5.2 Main Database Modules

### Authentication
- users
- roles
- permissions

### Master Data
- units
- academic_years
- school_classes
- students
- staff_profiles
- accounts
- payment_categories
- bank_accounts

### Billing
- bills
- bill_items
- student_bills
- bill_discounts
- student_discounts

### Payments
- payments
- payment_details
- payment_allocations
- payment_verifications
- payment_proofs
- payment_methods

### Finance
- cash_transactions
- cash_accounts
- journals
- journal_details

### PPDB
- admission_periods
- applicants
- applicant_documents
- admission_bills
- admission_settings
- admission_contents

### Notifications
- notification_templates
- notification_logs
- reminder_schedules

### System
- app_settings
- activity_logs
- backups

AI Agent wajib membaca struktur database yang sudah ada sebelum membuat migration baru.

Jangan membuat tabel duplikat untuk fungsi yang sudah tersedia.

---

# 6. FINANCIAL BUSINESS RULES

Bagian ini merupakan bagian paling kritis dalam aplikasi.

## 6.1 Billing

Setiap tagihan harus memiliki:

- Nomor tagihan.
- Siswa.
- Kategori.
- Tahun ajaran.
- Periode.
- Nominal awal.
- Potongan.
- Total tagihan.
- Jatuh tempo.
- Status.

Rumus:

```text
Total Tagihan = Nominal Awal - Potongan

Sisa Tagihan = Total Tagihan - Total Pembayaran Terverifikasi
```

## 6.2 Payment

Setiap pembayaran wajib memiliki:

- Nomor transaksi.
- Siswa.
- Tanggal pembayaran.
- Nominal.
- Metode pembayaran.
- Petugas.
- Status.
- Detail alokasi tagihan.

Satu transaksi dapat membayar beberapa tagihan.

Satu tagihan dapat dibayar melalui beberapa transaksi.

Gunakan payment_allocations untuk menyimpan alokasi pembayaran.

## 6.3 Payment Status

Status pembayaran:

- Pending.
- Approved.
- Rejected.
- Cancelled.

Hanya pembayaran Approved yang dihitung sebagai pembayaran sah.

Pembayaran Pending tidak boleh mengurangi saldo tagihan.

## 6.4 Financial Integrity

WAJIB:

- Gunakan DB::transaction().
- Gunakan row locking untuk proses saldo yang berpotensi bersamaan.
- Cegah double submission.
- Gunakan idempotency key jika diperlukan.
- Gunakan unique transaction number.
- Validasi saldo sebelum menyimpan pembayaran.
- Simpan snapshot transaksi.
- Sediakan mekanisme reversal.

DILARANG:

- Menghapus transaksi keuangan yang telah disetujui secara permanen.
- Mengubah nilai pembayaran tanpa histori.
- Menghitung pembayaran pending sebagai pemasukan.
- Mengubah status tagihan tanpa memperhitungkan alokasi pembayaran.
- Membuat pembayaran ganda akibat request berulang.

## 6.5 Cancellation

Pembatalan transaksi harus melalui proses resmi.

Simpan:

- Transaksi asal.
- Alasan pembatalan.
- User pembatal.
- Waktu pembatalan.
- Referensi reversal.
- Status sebelumnya.

Jangan menghapus catatan transaksi asli.

---

# 7. AUTHENTICATION AND AUTHORIZATION

Gunakan Spatie Laravel Permission.

Role awal:

1. Super Administrator.
2. Administrator.
3. Bendahara.
4. Petugas Pembayaran.
5. Verifikator.
6. Kepala Sekolah.
7. Operator PPDB.

Setiap modul wajib menggunakan permission.

Contoh permission:

```text
dashboard.view

students.view
students.create
students.update
students.delete
students.export

bills.view
bills.create
bills.update
bills.cancel

payments.view
payments.create
payments.verify
payments.cancel

reports.view
reports.export

settings.view
settings.update

backup.create
backup.restore

activity-logs.view
```

Menu yang tidak diizinkan tidak boleh ditampilkan.

Namun, menyembunyikan menu bukan pengganti authorization.

Semua route, Livewire action, dan proses backend harus dilindungi.

---

# 8. UI/UX DEVELOPMENT RULES

Gunakan DESIGN.md sebagai sumber utama aturan tampilan.

Semua halaman harus konsisten.

Wajib memiliki:

- Header.
- Navbar.
- Breadcrumb.
- Page title.
- Page description jika diperlukan.
- Content container.
- Action button.
- Filter.
- Loading state.
- Empty state.
- Error state.
- Success notification.

## 8.1 Reusable Components

Buat komponen:

- x-layouts.app
- x-layouts.auth
- x-ui.button
- x-ui.card
- x-ui.badge
- x-ui.alert
- x-ui.modal
- x-ui.input
- x-ui.select
- x-ui.textarea
- x-ui.table
- x-ui.pagination
- x-ui.breadcrumb
- x-ui.dropdown
- x-ui.confirm-dialog

Gunakan komponen yang sama pada seluruh modul.

## 8.2 Data Table

Semua tabel harus mendukung jika relevan:

- Search.
- Pagination.
- Sorting.
- Filtering.
- Empty state.
- Loading state.
- Detail.
- Edit.
- Delete sesuai permission.
- Export.

Gunakan server-side pagination untuk dataset besar.

---

# 9. SECURITY REQUIREMENTS

WAJIB:

- Password hashing.
- CSRF protection.
- XSS protection.
- SQL injection protection.
- Form validation.
- Rate limiting.
- Secure session.
- Authorization policies.
- Secure file upload.
- MIME validation.
- Private storage untuk dokumen sensitif.
- Credential encryption.
- Audit logging.

File bukti pembayaran tidak boleh dapat diakses publik hanya dengan menebak URL.

Gunakan signed URL atau authorized download endpoint.

Jangan menyimpan API key atau password di source code.

Gunakan environment variables atau encrypted database settings.

Jangan mencatat password, API secret, atau data sensitif ke log.

---

# 10. MODULE IMPLEMENTATION ORDER

Kerjakan sesuai urutan berikut.

## PHASE 1 — FOUNDATION

- Setup Laravel.
- Setup MySQL.
- Setup Vite.
- Setup Tailwind CSS.
- Setup Livewire.
- Setup Authentication.
- Setup Spatie Permission.
- Layout utama.
- Navbar.
- Design system.
- Dashboard awal.

## PHASE 2 — MASTER DATA

- Petugas.
- Unit.
- Tahun ajaran.
- Kelas.
- Siswa.
- Akun.
- Kategori.
- Rekening.

## PHASE 3 — BILLING

- Kelola tagihan.
- Generate tagihan massal.
- Tagihan individual.
- Tagihan bulanan.
- Potongan.
- Perhitungan saldo.

## PHASE 4 — PAYMENTS

- Proses pembayaran.
- Pembayaran sebagian.
- Pembayaran multi-tagihan.
- Verifikasi.
- Kwitansi.
- Pembatalan dan reversal.

## PHASE 5 — FINANCIAL REPORTS

- Transaksi.
- Arus kas.
- Laporan penerimaan.
- Laporan tunggakan.
- Export Excel.
- Export PDF.

## PHASE 6 — PPDB

- Periode.
- Pendaftar.
- Dokumen.
- Tagihan PPDB.
- Portal pendaftar.
- Konten informasi.

## PHASE 7 — SYSTEM TOOLS

- Pengingat tagihan.
- WhatsApp.
- Email.
- Queue monitoring.
- Maintenance.
- Backup.
- Server information.
- Activity logs.

## PHASE 8 — FINALIZATION

- Dashboard final.
- Optimasi query.
- Security review.
- Automated tests.
- Responsive testing.
- Deployment.

Jangan melanjutkan ke phase berikutnya sebelum phase sebelumnya lolos pengujian dan tidak memiliki blocker kritis.

---

# 11. TESTING REQUIREMENTS

Gunakan PHPUnit atau Pest.

Buat test untuk:

- Authentication.
- Role permission.
- CRUD siswa.
- CRUD kelas.
- Generate tagihan.
- Pencegahan tagihan duplikat.
- Perhitungan potongan.
- Pembayaran penuh.
- Pembayaran sebagian.
- Pembayaran multi-tagihan.
- Verifikasi.
- Penolakan pembayaran.
- Pembatalan pembayaran.
- Perhitungan tunggakan.
- Arus kas.
- Export laporan.
- Import siswa.
- PPDB.
- Backup.
- Keamanan upload.

Setiap fitur keuangan harus memiliki unit atau feature test.

---

# 12. PERFORMANCE RULES

- Hindari N+1 queries.
- Gunakan eager loading.
- Gunakan database indexes.
- Gunakan pagination.
- Cache konfigurasi yang jarang berubah.
- Gunakan queue untuk pengiriman notifikasi.
- Gunakan scheduler untuk pekerjaan otomatis.
- Optimalkan query dashboard.
- Jangan mengambil seluruh transaksi sekaligus.
- Jangan melakukan query database di dalam loop jika dapat dihindari.

---

# 13. DEVELOPMENT WORKFLOW

Sebelum mengimplementasikan fitur:

1. Periksa file dan struktur project.
2. Baca AGENTS.md.
3. Baca DESIGN.md.
4. Periksa migration dan model yang tersedia.
5. Identifikasi relasi database.
6. Tentukan komponen yang dapat digunakan ulang.
7. Implementasikan backend.
8. Implementasikan frontend.
9. Tambahkan validasi.
10. Tambahkan authorization.
11. Buat automated tests.
12. Jalankan tests.
13. Periksa hasil UI.
14. Perbaiki error sebelum menyelesaikan pekerjaan.

Jangan mengubah kode yang tidak berkaitan tanpa alasan teknis.

Jangan menghapus fitur yang sudah berjalan.

Jangan membuat migration destruktif tanpa persetujuan.

---

# 14. REQUIRED DOCUMENTATION

Pelihara dokumentasi berikut:

- PRD.md
- DESIGN.md
- AGENTS.md
- DATABASE_ERD.md
- IMPLEMENTATION_PLAN.md
- API_INTEGRATION.md
- DEPLOYMENT.md
- CHANGELOG.md

Setiap perubahan besar harus dicatat.

Update dokumentasi ketika struktur database, proses bisnis, atau arsitektur berubah.

---

# 15. DEFINITION OF DONE

Fitur dinyatakan selesai jika:

- Backend berfungsi.
- Frontend berfungsi.
- Data tersimpan dengan benar.
- Validasi berjalan.
- Permission berjalan.
- Error handling tersedia.
- Responsive.
- Tidak ada tombol dummy.
- Tidak ada data hardcoded yang seharusnya berasal dari database.
- Automated tests berhasil.
- Tidak merusak modul lain.
- Dokumentasi diperbarui jika diperlukan.

---

# 16. FINAL INSTRUCTIONS TO AI AGENT

Kamu bertanggung jawab membangun aplikasi KOMITE MAN 7 JOMBANG secara bertahap dan profesional.

Prioritas utama:

1. Akurasi transaksi keuangan.
2. Integritas database.
3. Keamanan data.
4. Konsistensi proses bisnis.
5. Kemudahan penggunaan.
6. Konsistensi UI/UX.
7. Maintainability.
8. Performance.

Jangan hanya menghasilkan kode yang terlihat bagus.

Bangun aplikasi yang benar-benar dapat digunakan dalam aktivitas operasional sekolah.

Sebelum memulai development, analisis project yang tersedia dan identifikasi pekerjaan yang sudah selesai serta yang belum.

Jangan mengulang implementasi yang sudah ada.

**Selalu ikuti AGENTS.md dan DESIGN.md sebagai pedoman utama development.**