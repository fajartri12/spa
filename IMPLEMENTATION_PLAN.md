# IMPLEMENTATION PLAN — KOMITE MAN 7 JOMBANG

Rencana implementasi mengikuti urutan fase pada `AGENTS.MD` bagian 10.
Fase berikutnya tidak dimulai sebelum fase sebelumnya lolos pengujian dan
tidak memiliki blocker kritis.

**Status keseluruhan:** Fase 1 s.d. Fase 9 **SELESAI** — 474 test / 1394 assertion lulus.

---

## Ringkasan Status Fase

| Fase | Nama | Status | Test | Catatan |
|---|---|---|---|---|
| 1 | Foundation | ✅ Selesai | 11 | Auth, design system, layout, dashboard |
| 2 | Master Data | ✅ Selesai | 70 | 8 modul CRUD lengkap |
| 3 | Billing | ✅ Selesai | 11 | Tagihan massal, individual, bulanan, potongan, detail & pembatalan |
| 4 | Payments | ✅ Selesai | 16 | Pencatatan, alokasi, antrian verifikasi, otorisasi, kwitansi PDF, bukti bayar |
| 5 | Financial Reports | ✅ Selesai | 18 | Arus kas, penerimaan, tunggakan, rekap transaksi (tagihan/lainnya, tunai/non-tunai), cetak PDF |
| 6 | PPDB | ✅ Selesai | 36 | Periode, pendaftar, detail pendaftar, dokumen private, tagihan PPDB, konten, portal publik, submenu PPDB (pendaftar/tagihan/periode/pengumuman/8 pengaturan/akses portal) |
| 7 | System Tools | ✅ Selesai | 17 | Log aktivitas, backup/restore, CSV siswa, notifikasi, antrian, info server |
| 8 | Finalization | ✅ Selesai | 10 | Optimasi query, security review, pengaturan/pengguna/role, dokumentasi deployment |
| 9 | Pengembangan Lanjutan | ✅ Selesai | 62 | Payment gateway (VA/QRIS + webhook), rekonsiliasi bank otomatis, payroll/honorarium + slip PDF, bebas tanggungan & SKL |

Total 474 test / 1394 assertion — termasuk 1 test bawaan Laravel (`ExampleTest`).

Keterangan: ✅ selesai · 🔄 berjalan · ⏳ berikutnya · ⬜ belum dimulai

---

## FASE 1 — FOUNDATION ✅ SELESAI

| # | Pekerjaan | Status |
|---|---|---|
| 1.1 | Instalasi Laravel 12, konfigurasi `.env`, MySQL `komite_man7` | ✅ |
| 1.2 | Setup Vite + Tailwind CSS 4 + Alpine.js | ✅ |
| 1.3 | Instalasi Livewire 3 | ✅ |
| 1.4 | Autentikasi Laravel + halaman masuk Livewire + rate limiting | ✅ |
| 1.5 | Instalasi Spatie Laravel Permission | ✅ |
| 1.6 | Katalog permission `app/Enums/PermissionName.php` | ✅ |
| 1.7 | 7 role awal via `RoleSeeder` | ✅ |
| 1.8 | Design token & komponen UI reusable (15 komponen) | ✅ |
| 1.9 | Layout utama: header, navbar, breadcrumb, footer WIB | ✅ |
| 1.10 | Dashboard awal dengan statistik & Chart.js | ✅ |
| 1.11 | Halaman profil pengguna | ✅ |
| 1.12 | `AuthenticationTest` | ✅ |

**Definisi selesai terpenuhi:** login/logout berfungsi, otorisasi berbasis
permission, dashboard menampilkan data nyata dari basis data.

---

## FASE 2 — MASTER DATA ✅ SELESAI

### 2.1 Unit Pendidikan

| # | Pekerjaan | Status |
|---|---|---|
| 2.1.1 | Migrasi `units`, model `Unit` | ✅ |
| 2.1.2 | `UnitIndex` Livewire: search, CRUD, soft delete | ✅ |
| 2.1.3 | Guard hapus: blokir bila masih memiliki kelas | ✅ |
| 2.1.4 | Guard hapus: blokir bila masih memiliki siswa | ✅ |
| 2.1.5 | Route `units.index`, permission `units.*`, menu | ✅ |
| 2.1.6 | `UnitManagementTest` (8 test) | ✅ |

### 2.2 Tahun Ajaran

| # | Pekerjaan | Status |
|---|---|---|
| 2.2.1 | Migrasi `academic_years`, model `AcademicYear` | ✅ |
| 2.2.2 | `AcademicYearIndex` Livewire: search, CRUD | ✅ |
| 2.2.3 | Validasi `end_date > start_date` | ✅ |
| 2.2.4 | Aksi `activate` — menonaktifkan periode lain secara atomik | ✅ |
| 2.2.5 | Guard hapus: blokir bila masih memiliki kelas | ✅ |
| 2.2.6 | Route `academic-years.index`, permission, menu | ✅ |
| 2.2.7 | `AcademicYearManagementTest` (9 test) | ✅ |

### 2.3 Kelas

| # | Pekerjaan | Status |
|---|---|---|
| 2.3.1 | Migrasi `school_classes` + unique `(unit, year, name)` | ✅ |
| 2.3.2 | `SchoolClassIndex` Livewire: filter unit, tahun ajaran, status | ✅ |
| 2.3.3 | Validasi relasi unit + tahun ajaran | ✅ |
| 2.3.4 | Guard hapus: blokir bila masih memiliki siswa | ✅ |
| 2.3.5 | Route `school-classes.index`, permission, menu | ✅ |
| 2.3.6 | `SchoolClassManagementTest` (8 test) | ✅ |

### 2.4 Petugas

| # | Pekerjaan | Status |
|---|---|---|
| 2.4.1 | Migrasi `staff_profiles`, model `StaffProfile` | ✅ |
| 2.4.2 | Enum `StaffPosition`, enum `Gender` | ✅ |
| 2.4.3 | `StaffIndex` Livewire: filter unit, jabatan, status | ✅ |
| 2.4.4 | Relasi opsional ke `users` dan `units` | ✅ |
| 2.4.5 | Route `staff.index`, permission `staff.*`, menu | ✅ |
| 2.4.6 | `StaffManagementTest` (8 test) | ✅ |

### 2.5 Siswa

| # | Pekerjaan | Status |
|---|---|---|
| 2.5.1 | Migrasi `students`, model `Student` | ✅ |
| 2.5.2 | Enum `StudentStatus` | ✅ |
| 2.5.3 | `StudentIndex` Livewire: filter cari/unit/kelas/status | ✅ |
| 2.5.4 | Validasi: kelas harus dari unit yang sama | ✅ |
| 2.5.5 | Unik `nis` dan `nisn` | ✅ |
| 2.5.6 | Route `students.index`, permission `students.*`, menu | ✅ |
| 2.5.7 | `StudentManagementTest` (9 test) | ✅ |
| 2.5.8 | Import/export siswa (Excel) | ✅ CSV (Fase 7) |

### 2.6 Kategori Pembayaran

| # | Pekerjaan | Status |
|---|---|---|
| 2.6.1 | Migrasi `payment_categories`, model `PaymentCategory` | ✅ |
| 2.6.2 | Enum `PaymentCategoryType` (once/monthly/annual) | ✅ |
| 2.6.3 | `PaymentCategoryIndex` Livewire: filter cari/jenis/unit | ✅ |
| 2.6.4 | `Formatter::parseRupiah()` untuk input nominal | ✅ |
| 2.6.5 | Route `payment-categories.index`, permission, menu | ✅ |
| 2.6.6 | `PaymentCategoryManagementTest` (10 test) | ✅ |

### 2.7 Akun Keuangan

| # | Pekerjaan | Status |
|---|---|---|
| 2.7.1 | Migrasi `accounts`, model `Account` | ✅ |
| 2.7.2 | Enum `AccountType` (cash/bank/receivable/revenue/expense) | ✅ |
| 2.7.3 | `AccountIndex` Livewire: filter cari/tipe | ✅ |
| 2.7.4 | Kolom jumlah rekening via `withCount('bankAccounts')` | ✅ |
| 2.7.5 | Guard hapus: blokir bila masih dipakai rekening | ✅ |
| 2.7.6 | Route `accounts.index`, permission, menu | ✅ |
| 2.7.7 | `AccountManagementTest` (10 test) | ✅ |
| 2.7.8 | Sinkronisasi permission `accounts.*` ke basis data | ✅ |

### 2.8 Rekening Bank

| # | Pekerjaan | Status |
|---|---|---|
| 2.8.1 | Migrasi `bank_accounts` + unique `(bank_name, account_number)` | ✅ |
| 2.8.2 | `BankAccountIndex` Livewire: filter cari/unit | ✅ |
| 2.8.3 | Relasi ke `accounts` dan `units` | ✅ |
| 2.8.4 | Route `bank-accounts.index`, permission, menu | ✅ |
| 2.8.5 | `BankAccountManagementTest` (8 test) | ✅ |

### 2.9 Verifikasi Fase 2

| # | Pekerjaan | Status |
|---|---|---|
| 2.9.1 | Seluruh 8 halaman master data diuji di browser (HTTP 200) | ✅ |
| 2.9.2 | Perbaikan 403 permission `accounts.*` | ✅ |
| 2.9.3 | Perbaikan tautan akun pada `BankAccountSeeder` | ✅ |
| 2.9.4 | Penambahan 3 periode tahun ajaran pada seeder | ✅ |
| 2.9.5 | Suite pengujian hijau: 82 test / 189 assertion | ✅ |
| 2.9.6 | `CHANGELOG.md`, `DATABASE_ERD.md`, `IMPLEMENTATION_PLAN.md` | ✅ |

---

## FASE 3 — BILLING ✅ SELESAI

Landasan aturan: `AGENTS.MD` bagian 6.1.

```text
Total Tagihan  = Nominal Awal - Potongan
Sisa Tagihan   = Total Tagihan - Total Pembayaran Terverifikasi
```

### 3.1 Skema Basis Data

| # | Pekerjaan | Status |
|---|---|---|
| 3.1.1 | Migrasi `bills` — induk/paket tagihan, `bill_number` unik | ✅ |
| 3.1.2 | Migrasi `student_bills` — tagihan riil per siswa | ✅ |
| 3.1.3 | Migrasi `bill_items` — rincian komponen tagihan | ✅ |
| 3.1.4 | Migrasi `student_discounts` — potongan per siswa | ✅ |
| 3.1.5 | Migrasi `bill_discounts` — potongan per tagihan | ✅ |
| 3.1.6 | Enum `BillStatus` (unpaid, partial, paid, overdue, cancelled) | ✅ |
| 3.1.7 | Model `Bill`, `StudentBill`, `BillItem`, `StudentDiscount` + relasi | ✅ |
| 3.1.8 | Index pada `(student_id, status)`, `(academic_year_id, status)` | ✅ |

### 3.2 Service & Action

| # | Pekerjaan | Status |
|---|---|---|
| 3.2.1 | `BillNumberGenerator` — nomor tagihan unik & idempoten | ✅ |
| 3.2.2 | `BillingService` — perhitungan total, potongan, sisa | ✅ |
| 3.2.3 | `GenerateMassBillAction` — tagihan massal per unit/kelas | ✅ |
| 3.2.4 | `GenerateIndividualBillAction` — tagihan per siswa | ✅ |
| 3.2.5 | `GenerateMonthlyBillAction` — tagihan bulanan berulang | ✅ |
| 3.2.6 | Pencegahan tagihan duplikat (idempotency key per siswa+kategori+periode) | ✅ |
| 3.2.7 | Penerapan potongan (`student_discounts`) saat generasi | ✅ |
| 3.2.8 | `DB::transaction()` + row locking pada semua proses generasi | ✅ |

### 3.3 Antarmuka Livewire

| # | Pekerjaan | Status |
|---|---|---|
| 3.3.1 | `BillIndex` — daftar tagihan, filter siswa/unit/kelas/kategori/status | ✅ |
| 3.3.2 | `BillGenerate` — wizard generasi massal dengan pratinjau | ✅ |
| 3.3.3 | `BillShow` — detail tagihan + riwayat pembayaran | ✅ |
| 3.3.4 | Aksi batalkan tagihan (hanya bila belum ada pembayaran) | ✅ |
| 3.3.5 | Ekspor daftar tagihan (Excel/PDF) | ✅ CSV |
| 3.3.6 | Route `bills.*`, permission, menu navigasi | ✅ |

### 3.4 Pengujian

| # | Pekerjaan | Status |
|---|---|---|
| 3.4.1 | Test generasi tagihan massal | ✅ |
| 3.4.2 | Test pencegahan tagihan duplikat | ✅ |
| 3.4.3 | Test perhitungan potongan | ✅ |
| 3.4.4 | Test perhitungan sisa tagihan | ✅ |
| 3.4.5 | Test pembatalan tagihan & guard | ✅ |
| 3.4.6 | Test otorisasi permission `bills.*` | ✅ |

---

## FASE 4 — PAYMENTS ✅ SELESAI

### 4.1 Skema & Model

| # | Pekerjaan | Status |
|---|---|---|
| 4.1.1 | Migrasi `payment_methods`, `payments`, `payment_allocations` | ✅ |
| 4.1.2 | Migrasi `payment_details`, `payment_verifications`, `payment_proofs` | ✅ |
| 4.1.3 | Enum `PaymentStatus` (pending, approved, rejected, cancelled) | ✅ |
| 4.1.4 | Model + relasi (`Payment`, `PaymentAllocation`, `PaymentMethod`, dst.) | ✅ |
| 4.1.5 | `PaymentMethodSeeder` (tunai, transfer, QRIS, lainnya) | ✅ |

### 4.2 Service & Workflow

| # | Pekerjaan | Status |
|---|---|---|
| 4.2.1 | `PaymentService::create()` — transaksi atomik + row locking | ✅ |
| 4.2.2 | Validasi saldo: alokasi tidak boleh melebihi sisa tagihan | ✅ |
| 4.2.3 | Pembayaran sebagian & multi-tagihan dalam satu transaksi | ✅ |
| 4.2.4 | Hanya status **approved** yang mengurangi saldo tagihan | ✅ |
| 4.2.5 | `approve()` / `reject()` dengan histori `payment_verifications` | ✅ |
| 4.2.6 | `cancel()` dengan reversal saldo & jejak audit (`cancelled_*`) | ✅ |
| 4.2.7 | Nomor transaksi unik + pencegahan persetujuan ganda | ✅ |

### 4.3 Antarmuka Livewire

| # | Pekerjaan | Status |
|---|---|---|
| 4.3.1 | `PaymentIndex` — daftar, cari, filter status, pagination | ✅ |
| 4.3.2 | `PaymentCreate` — pilih siswa, alokasi tagihan, metode, catatan | ✅ |
| 4.3.3 | Route `payments.*`, permission, menu navigasi | ✅ |
| 4.3.4 | `PaymentShow` — detail transaksi + aksi verifikasi/tolak/batal | ✅ |
| 4.3.5 | Kwitansi PDF (`PaymentReceiptController`) | ✅ |
| 4.3.6 | Unggah bukti pembayaran + unduhan terotorisasi (`PaymentProofController`) | ✅ |

### 4.4 Pengujian

| # | Pekerjaan | Status |
|---|---|---|
| 4.4.1 | Test pembayaran pending tidak mengurangi saldo | ✅ |
| 4.4.2 | Test persetujuan pembayaran sebagian & penuh | ✅ |
| 4.4.3 | Test penolakan pembayaran tidak mengubah saldo | ✅ |
| 4.4.4 | Test alokasi melebihi sisa & nomor transaksi duplikat | ✅ |
| 4.4.5 | Test otorisasi permission `payments.*` | ✅ |

---

## FASE 5 — FINANCIAL REPORTS ✅ SELESAI

- `cash_transactions`, `journals`, `journal_details`. ✅
- Laporan transaksi, arus kas, penerimaan, dan tunggakan. ✅ (`/laporan`)
- Export CSV penerimaan & tunggakan (`/laporan/export`, permission `reports.export`). ✅
- Submenu Rekap: Transaksi (`/laporan/transaksi`), Arus Kas (`/laporan/arus-kas`),
  Cetak Laporan (`/laporan/cetak`). ✅
- Ringkasan Rekap Transaksi dibagi 5 kartu: Total Transaksi, Transaksi Tagihan,
  Transaksi Lainnya, Transaksi Tunai, dan Transaksi Non-Tunai. ✅
- Export PDF DomPDF (A4 landscape) untuk penerimaan, tunggakan, dan arus kas
  (`/laporan/cetak/pdf`, permission `reports.export`). ✅

---

## FASE 6 — PPDB ✅ SELESAI

- `admission_periods`, `applicants`, `applicant_documents`,
  `admission_bills`, `admission_settings`, `admission_contents`. ✅
- Admin periode & pendaftar (`/ppdb`). ✅
- Portal pendaftaran publik (`/ppdb/daftar`) dengan rate limiting `throttle:10,1`. ✅
- Halaman sukses bertanda tangan (signed URL) & halaman periode ditutup. ✅
  - Detail calon siswa (`/ppdb/{applicant}`): ubah status, unggah/verifikasi/hapus
    dokumen persyaratan, buat & lunasi tagihan PPDB. ✅
  - Dokumen disimpan pada private storage (`local` disk) dan hanya dapat diunduh
    pengguna berizin melalui endpoint `applicant-documents.download`. ✅
  - Konten & pengumuman PPDB (`AdmissionContent`) dengan slug unik, publikasi,
    keterkaitan periode opsional; tampil pada portal `/ppdb/daftar`. ✅

---

## FASE 7 — SYSTEM TOOLS ✅ SELESAI

- Halaman log aktivitas (`/peralatan/log-aktivitas`). ✅
- Backup & restore basis data (`/peralatan/backup`) via `mysqldump`/`mysql`. ✅
- Import/export siswa CSV (`students.import` / `students.export`). ✅
- Notifikasi WhatsApp & Email: `notification_templates`, `notification_logs`,
  `reminder_schedules`, `NotificationService`, job `SendFeeReminder`. ✅
- Penjadwalan pengingat: `php artisan reminders:send` setiap 08:00 pada `routes/console.php`. ✅
- Monitoring notifikasi & antrian (`/peralatan/notifikasi`). ✅
- Informasi server & pembersihan cache (`/peralatan/server`). ✅

---

## FASE 8 — FINALIZATION ✅ SELESAI

- Dashboard memakai `student_bills` sebagai sumber tunggakan & status tagihan. ✅
- Optimasi query: eager loading pada seluruh daftar, pagination server-side. ✅
- Security review: otorisasi per route & Livewire action, signed URL PPDB,
  validasi upload CSV, bukti pembayaran pada private storage. ✅
- Pengaturan terpusat (`/pengaturan`) — identitas aplikasi, penandatangan
  kwitansi, format & penomoran dokumen (`settings.index`). ✅
- Manajemen pengguna (`/pengaturan/pengguna`) — CRUD akun, penetapan role,
  filter, guard penonaktifan/penghapusan diri sendiri (`users.index`). ✅
- Matriks role & hak akses (`/pengaturan/role`) — 50 permission terkelompok,
  toggle per grup, guard kelengkapan Super Administrator (`roles.index`). ✅
- Automated test: 157 test / 394 assertion lulus. ✅
- Dokumentasi diperbarui (`DEPLOYMENT.md`, `README.md`, `CHANGELOG.md`). ✅

---

## FASE 9 — PENGEMBANGAN LANJUTAN ✅ SELESAI

### 9.1 Payment Gateway (VA / QRIS Dinamis + Webhook)

- Driver gateway: `TripayGateway`, `MidtransGateway`, `FakeGateway` di bawah
  kontrak `App\Services\Gateway\PaymentGateway` + `GatewayManager`. ✅
- Provider dipilih per unit pendidikan (`units.payment_provider`,
  `units.payment_channel`, `units.payment_gateway_enabled`). ✅
- Penerbitan tagihan digital dari detail tagihan (`/tagihan/{bill}`) —
  tombol **Terbitkan VA/QRIS**, sinkronisasi channel, dan daftar
  `/pembayaran/tagihan-digital` (`payment-intents.index`). ✅
- Webhook `POST /webhook/pembayaran/{provider}` — verifikasi signature,
  idempoten, dikecualikan dari CSRF. ✅
- Penyelesaian otomatis memakai `PaymentService::create()` + `approve()` agar
  aturan keuangan tetap satu pintu; nominal tidak cocok **tidak** auto-approve. ✅
- Kode bayar tampil di portal siswa dan portal orang tua melalui
  `PortalService::openIntents()` + partial `livewire.public.partials.pay-code`. ✅
- Pengujian: `PaymentGatewayTest` (15), `GatewayDriverTest` (11), dan 3 test
  kode bayar pada `GuardianPortalTest`. ✅

### 9.2 Rekonsiliasi Bank Otomatis

- Profil template mutasi bank (BSI, BRI, Bank Jatim) pada
  `bank_statement_profiles`, dapat dioverride per import. ✅
- Import CSV/XLSX (`StatementParser`) dengan normalisasi tanggal & nominal
  Indonesia, dedupe berdasarkan `file_hash`. ✅
- Auto-matching tiga aturan berurutan: `reference` (100%), `unique_amount`
  (90%), `amount_date` (75%); kandidat ganda dibiarkan unmatched. ✅
- Aksi match manual, unmatch, dan ignore pada `/keuangan/rekonsiliasi-bank`. ✅
- Pengujian: `BankReconciliationTest` — 11 test. ✅

### 9.3 Payroll / Honorarium

- Periode honorarium (`payroll_periods`) + item per pegawai (`payroll_items`). ✅
- Approve periode menghasilkan **satu** `cash_transactions` pengeluaran dan
  **satu** `journals` (debit beban honorarium, kredit kas) melalui
  `JournalService`. ✅
- Pembatalan periode melakukan reversal (void + soft delete), bukan hapus fisik. ✅
- Antarmuka `/keuangan/honorarium` dan `/keuangan/honorarium/{period}`. ✅
- Cetak slip PDF A4 per penerima + rekap total & terbilang pada
  `/keuangan/honorarium/{period}/slip` (`payrolls.slips`). ✅
- Pengujian: `PayrollTest` — 15 test. ✅

### 9.4 Bebas Tanggungan & SKL

- `ClearanceService` menghitung sisa tanggungan dari `student_bills`
  berstatus belum lunas dan menyimpan snapshot pada `graduation_clearances`. ✅
- Override manual oleh pejabat berwenang disertai alasan & pelaku. ✅
- Kelulusan (`StudentStatus::Graduated`) melalui `PromotionService::promote()`
  ditolak bila masih ada tanggungan; wizard menampilkan panel peringatan
  **Belum bebas tanggungan**. ✅
- Penerbitan SKL bernomor otomatis + cetak PDF A4
  (`/data-master/siswa/{student}/skl`). ✅
- Antarmuka `/data-master/siswa/bebas-tanggungan` (`clearances.index`). ✅
- Pengujian: `ClearanceTest` (13) dan 5 test gerbang kelulusan pada
  `StudentPromotionTest`. ✅

---

## Catatan Teknis Lintas Fase

### Permission
Setiap penambahan case pada `app/Enums/PermissionName.php` **wajib** diikuti:

```bash
php artisan db:seed --class=PermissionSeeder --force
php artisan db:seed --class=RoleSeeder --force
php artisan permission:cache-reset
```

Tanpa langkah ini, route yang dilindungi permission baru akan mengembalikan
HTTP 403 walaupun pengguna adalah Super Administrator.

### Uang
Seluruh nominal disimpan sebagai `decimal(15, 2)`. Input dari pengguna diproses
melalui `Formatter::parseRupiah()` agar format Indonesia (`1.500.000`) terbaca benar.

### Tahun Ajaran Siswa
`students` tidak memiliki kolom `academic_year_id`. Tahun ajaran siswa diperoleh
melalui `students.school_class_id → school_classes.academic_year_id`. Setiap
filter tahun ajaran pada siswa wajib melakukan join ke `school_classes`.

### Pengujian
`DemoDataSeeder` hanya berjalan pada environment `local`. Pengujian memakai
`RefreshDatabase` dan nama fixture jauh di masa depan (mis: `2099/2100`) untuk
menghindari tabrakan unique constraint dengan seeder dinamis.
