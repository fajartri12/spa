# CHANGELOG

Semua perubahan penting pada aplikasi **KOMITE MAN 7 JOMBANG** dicatat di file ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/) dan
[Semantic Versioning](https://semver.org/lang/id/).

---

## [Unreleased]

### Ditambahkan
- **Integrasi payment gateway (Tripay & Midtrans) dengan Virtual Account / QRIS dinamis per tagihan.** Verifikasi non-tunai tidak lagi bergantung pada unggah bukti manual:
  - Tabel `payment_intents` menyimpan satu tagihan digital per `student_bills` (referensi `PG{ymd}-{acak}`, kode bayar VA, payload QRIS, kedaluwarsa, status, jejak payload provider). Kolom `payments.payment_intent_id` menautkan pembayaran hasil webhook.
  - Layer driver `App\Services\Gateway` — `PaymentGateway` (kontrak), `GatewayManager` (pemilihan driver), `TripayGateway` (Closed Payment, HMAC-SHA256 dari raw body), `MidtransGateway` (Core API `/v2/charge`, signature SHA-512), dan `FakeGateway` (simulator untuk pengujian tanpa kredensial).
  - `PaymentGatewayService` menjadi satu-satunya pintu: `createIntent()` (menolak tagihan lunas/dibatalkan, memakai ulang intent terbuka), `handleWebhook()` (idempoten, nominal tidak sesuai **tidak** auto-approve), `settle()` (membuat pembayaran lewat `PaymentService` + auto-approve), `expireOverdue()`, dan `cancel()`.
  - Endpoint webhook `POST /webhook/pembayaran/{provider}` (di luar CSRF, throttle 120/menit, signature tidak valid → 403, referensi tak dikenal → 422).
  - Halaman **Tagihan Digital** (`/pembayaran/tagihan-digital`, permission `payment-gateways.*`) dan tombol **Terbitkan VA / QRIS** pada detail tagihan.
  - Pengaturan gateway per unit: `units.payment_provider`, `units.payment_channel`, `units.payment_gateway_enabled`; kredensial hanya dari environment (`config/payment_gateway.php`). Form **Unit Pendidikan** kini memiliki bagian *Tagihan Digital* (aktif/nonaktif, penyedia, kanal bawaan) beserta peringatan bila kredensial penyedia belum lengkap, dan kolom ringkas pada tabel unit.
  - Perintah `php artisan gateway:expire-intents` dijadwalkan setiap jam untuk menandai VA/QRIS yang melewati masa berlaku sebagai `expired`.
- **Rekonsiliasi bank otomatis (import mutasi CSV/Excel).** Bendahara tidak lagi mencocokkan mutasi satu per satu:
  - Tabel `bank_statement_profiles` (pemetaan kolom per bank), `bank_statement_imports` (berkas + ringkasan), `bank_statement_lines` (satu baris mutasi, unik per `import`+`row_number`).
  - `StatementParser` membaca CSV/XLSX (PhpSpreadsheet), menormalkan tanggal & nominal Indonesia, membuang baris kosong/duplikat, dan mendukung penimpaan nama kolom per import.
  - `BankReconciliationService` mencocokkan berurutan: **referensi** (nomor transaksi / referensi intent pada berita) → **nominal unik** → **nominal + toleransi tanggal**; kandidat ganda sengaja dibiarkan `unmatched`. Tersedia `match()`, `unmatch()`, `ignore()`, dan penolakan berkas duplikat lewat `file_hash`.
  - Halaman **Rekonsiliasi Bank** (`/keuangan/rekonsiliasi-bank`, permission `bank-reconciliations.*`) dengan unggah berkas, daftar import, filter status, pencocokan manual, dan "Cocokkan Otomatis".
  - Seeder `BankStatementProfileSeeder` untuk BSI, BRI, dan Bank Jatim.
- **Modul penggajian / honorarium guru & karyawan.** Terhubung ke bagan akun dan buku kas pengeluaran:
  - Tabel `payroll_periods` (periode, unit, akun kas & beban, status, total, penerima, jejak persetujuan/pembatalan, tautan kas & jurnal) dan `payroll_items` (slip per penerima: bruto, potongan, neto, rekening).
  - `PayrollService`: `createPeriod()`, `addItem()`/`updateItem()`/`removeItem()` (hanya draf), `approve()` (mengunci slip, membentuk **satu** `cash_transactions` keluar + **satu** jurnal batch debit beban/kredit kas), dan `cancel()` (jurnal → `voided`, kas di-soft-delete, alasan wajib).
  - Halaman **Honorarium & Gaji** (`/keuangan/honorarium`, permission `payrolls.*`) dan detail slip dengan rekap per jabatan serta total dalam kata.
  - Akun baru `5102 Beban Honorarium Guru & Karyawan` pada `AccountSeeder`.
- **Bebas tanggungan sebagai prasyarat SKL.** Modul kenaikan kelas/kelulusan kini terhubung ke tagihan:
  - Tabel `graduation_clearances` (satu baris per siswa) menyimpan snapshot `remaining_amount`, `outstanding_bill_count`, dan `outstanding_bills` (JSON) saat pemeriksaan.
  - `ClearanceService`: `check()` (hitung sisa tagihan non-batal), `checkMany()`, `override()` (alasan wajib, permission `clearances.override`), dan `issueSkl()` (nomor `SKL/{tahun}/0001`, idempoten, menolak bila masih ada tanggungan).
  - Halaman **Bebas Tanggungan & SKL** (`/data-master/siswa/bebas-tanggungan`, permission `clearances.*`) dengan ringkasan status, periksa massal per siswa, pengecualian, penerbitan SKL, dan rincian tagihan tertunggak.
  - Cetak **Surat Keterangan Lulus** PDF A4 (`/data-master/siswa/{student}/skl`, DomPDF) yang menolak pencetakan bila SKL belum diterbitkan atau masih ada tanggungan.
- Permission baru: `payment-gateways.*`, `bank-reconciliations.*`, `payrolls.*`, `clearances.*`; diberikan ke Bendahara (operasional penuh), Petugas Pembayaran (terbitkan VA/QRIS), dan Verifikator (pantau tagihan digital).
- Menu navigasi baru: Pembayaran → Tagihan Digital, grup **Keuangan** (Kas Keluar, Rekonsiliasi Bank, Honorarium & Gaji), dan Data Master → Bebas Tanggungan & SKL.
- 59 tes baru: `PaymentGatewayTest` (15), `GatewayDriverTest` (11), `BankReconciliationTest` (11), `PayrollTest` (12), `ClearanceTest` (13). Total suite 449 test / 1315 assertion lulus.
- **Kode bayar VA/QRIS tampil di portal siswa & orang tua.** Setelah petugas menerbitkan tagihan digital, orang tua tidak perlu lagi menunggu verifikasi manual:
  - `PortalService::openIntents()` mengumpulkan VA/QRIS yang masih berlaku per tagihan (satu kode terbaru per `student_bill_id`); portal hanya menampilkan kode yang sudah terbit.
  - Portal siswa dan portal orang tua menampilkan kartu **Kode Bayar {penyedia}** (kanal, nominal, masa berlaku WIB, referensi, tombol salin) pada tampilan kartu maupun tabel tagihan, plus kolom **Kode Bayar** pada tabel desktop. Kode yang kedaluwarsa/dibatalkan tidak ditampilkan lagi.
- **Kelulusan wajib bebas tanggungan.** `PromotionService::promote()` memeriksa bebas tanggungan setiap kali status tujuan `graduated`:
  - Siswa dengan sisa tagihan (atau belum diperiksa) menggagalkan seluruh operasi melalui `InvalidArgumentException` yang menyebutkan nama dan sisa tagihan; status dan kelas tidak berubah karena pemeriksaan berjalan di dalam transaksi.
  - Pengecualian (`overridden`) tetap dihormati, sedangkan kenaikan kelas biasa tidak terpengaruh. Parameter `enforceClearance` disediakan untuk penyesuaian data lama.
  - Wizard kenaikan kelas menampilkan panel peringatan **Belum bebas tanggungan** berisi daftar siswa yang akan ditolak sebelum tombol proses ditekan.
- **Cetak slip honorarium PDF.** `/keuangan/honorarium/{periode}/slip` (permission `payrolls.view`) menghasilkan PDF A4 berisi satu slip per penerima (bruto, potongan, neto, rekening, kolom tanda tangan) diikuti rekap total dan terbilang; periode tanpa penerima mengembalikan 404. Tombol **Cetak Slip** tersedia pada halaman detail periode.
- 11 tes baru: `GuardianPortalTest` (+3 kode bayar portal), `StudentPromotionTest` (+5 gerbang bebas tanggungan), `PayrollTest` (+3 slip PDF). Total suite **463 test / 1349 assertion** lulus.

### Diubah
- `Payment` mendapat relasi `intent()` dan `reconciliationLines()`; `Student` mendapat `studentBills()`; `StudentBill` mendapat `paymentIntents()`; `StaffProfile` mendapat `payrollItems()`; `Unit` mendapat kolom & cast gateway.
- `bootstrap/app.php` mengecualikan `webhook/pembayaran/*` dari verifikasi CSRF.

### Ditambahkan (sebelumnya)
- **Pemisahan portal siswa dan portal orang tua/wali.** Sebelumnya satu halaman (/portal/siswa) melayani siswa sekaligus orang tua. Sekarang ada dua portal terpisah dengan autentikasi masing-masing:
  - **Portal Siswa** (/portal/login → /portal/siswa) — login dengan NISN/NIS, hanya melihat data dirinya sendiri: tagihan, riwayat pembayaran, tabungan, profil, dan kontak orang tua.
  - **Portal Orang Tua/Wali** (/portal/orang-tua/login → /portal/orang-tua) — login dengan nomor HP + password, satu akun dapat tertaut ke banyak anak. Menampilkan ringkasan gabungan seluruh anak (total sisa tagihan, total tabungan), pemilih anak (?anak={id}), dan rincian per anak.
  - **Kwitansi** dapat diunduh dari kedua portal melalui route ber-sesi public.receipt (/portal/kwitansi/{payment}, throttle 30/menit). Hanya pembayaran berstatus Approved yang dapat diunduh, dan kepemilikan diverifikasi terhadap sesi siswa atau relasi wali–siswa.
  - **Manajemen data wali** (/data-master/orang-tua, permission guardians.*) — CRUD wali, penautan anak, penerbitan password sekali-tampil, filter unit/status, pencarian, dan ekspor CSV.
  - **Perintah** php artisan guardians:issue-accounts [--force] untuk membuat akun wali massal dari data students.parent_* dan menulis password ke CSV di disk privat.

### Diubah
- layouts/public.blade.php kini menurunkan label header dari nama route (public.guardian.* → "Portal Orang Tua / Wali", public.student.* → "Portal Siswa", selain itu tetap "Penerimaan Peserta Didik Baru"). Sebelumnya label selalu menampilkan teks PPDB.
- StudentPortal memakai PortalService sebagai satu-satunya sumber perhitungan tagihan/tabungan agar angka di kedua portal konsisten.
- Label "Portal Siswa & Orang Tua" pada halaman login siswa diubah menjadi "Portal Siswa".

### Diperbaiki
- **GuardianPortal dirender di dalam shell admin.** Atribut #[Layout('layouts.public')] dipakai tanpa use Livewire\Attributes\Layout;, sehingga PHP memperlakukannya sebagai atribut tak dikenal dan Livewire jatuh ke layout default. Sekarang layout publik benar-benar diterapkan.
- **Error 500 saat berpindah anak di portal wali.** selectStudent() memanggil esetPage() padahal komponen tidak memakai trait WithPagination. Panggilan yang tidak terpakai tersebut dihapus.

### Pengujian
- 43 tes baru: GuardianPortalTest (16), PortalReceiptTest (7), GuardianManagementTest (15), PortalSeparationTest (5). Total suite: **390 passed / 1156 assertions**.
### Keamanan
- **Audit keamanan menyeluruh + remediasi.** Rincian temuan dan perbaikannya:
  - **Portal siswa (H-01)** — dihapus total fallback password bersama (`STUDENT_PORTAL_DEFAULT_PASSWORD`). Login portal hanya berhasil bila siswa punya `portal_password` sendiri; akun tanpa password dianggap belum diaktivasi. Ditambah perintah `php artisan students:issue-portal-passwords [--force]` yang menerbitkan password acak per siswa dan menuliskannya ke CSV di disk privat (bukan ke layar/log).
  - **Konfigurasi produksi (H-02)** — `.env.example` memakai `APP_DEBUG=false`; `.env.production.example` baru berisi template produksi. `AppServiceProvider` menolak start bila `APP_ENV=production` dengan `APP_DEBUG=true`, dan memaksa `session.secure` di produksi.
  - **Editor role (M-01)** — `RoleIndex` kini menolak menyunting role milik aktor sendiri dan menolak mendelegasikan permission yang tidak dimiliki aktor (kecuali Super Administrator). Tombol role sendiri dinonaktifkan di UI.
  - **Manajemen pengguna (M-02)** — role Super Administrator hanya bisa diberikan/dikelola oleh Super Administrator; aktor tidak dapat mengubah role atau menonaktifkan akunnya sendiri; opsi role di form difilter sesuai aktor.
  - **Ekspor CSV (M-03)** — helper baru `App\Support\Csv::safe()` menetralkan formula injection (`=`, `+`, `-`, `@`, tab, CR) pada ekspor laporan dan CSV password portal.
  - **Middleware `active` (M-04)** — `EnsureUserIsActive` didaftarkan pada Livewire persistent middleware sehingga request `POST /livewire/update` dari akun nonaktif ikut ditolak; `Gate::before` juga menutup seluruh ability untuk user nonaktif.
  - **Filter cetak laporan (L-02)** — `unit_id`/`payment_category_id`/`status` divalidasi ketat (`exists`, `in:`) dan kini benar-benar dikirim dari halaman cetak, bukan hanya divalidasi.
  - **Hak akses hapus backup (L-03)** — permission baru `backup.delete`; tombol hapus, aksi Livewire, dan route tidak lagi memakai `backup.create`.
  - **Informasi server (L-04)** — rincian runtime (env, versi Laravel/PHP, driver DB/cache/queue) hanya terbuka untuk pemegang `settings.update`.
  - **Retensi data pribadi (L-08)** — `ActivityLog` (365 hari) dan `NotificationLog` (90 hari) memakai `MassPrunable`, dijadwalkan lewat `model:prune` di `routes/console.php`; penerima notifikasi dimasking di UI.
  - **Lain-lain** — `student_default_password` (plaintext, tak terpakai) dihapus dari halaman Pengaturan Sistem; `StaffProfile` dipakai konsisten untuk scoping ekspor siswa (L-05); nilai warna tema di-whitelist hex sebelum masuk `<style>` (L-09); middleware `SecurityHeaders` menambahkan `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, dan HSTS saat HTTPS (L-07).
- Test baru: autentikasi portal tanpa password, penolakan password bersama, guard privilege role/pengguna, escaping CSV, masking penerima notifikasi, dan header keamanan.

### Added
- **Komponen `x-ui.stat-card`**: kartu statistik bergradien dengan watermark icon (tone: primary, primary-dark, success, danger, info, warning, purple, orange) beserta kelas `.stat-card*` di `resources/css/app.css`. Dipakai pada halaman Arus Kas dan Data Tagihan PPDB.
- **Perbaikan tampilan Data Tagihan PPDB** (`/ppdb/tagihan`): ringkasan memakai `x-ui.stat-card`, nominal & tanggal diformat lewat `Formatter`, tombol aksi per baris berupa ikon (tandai lunas / batalkan) menggantikan dropdown, tabel diberi `min-w-[880px]` agar tidak terpotong pada 768-1024px, dan filter memakai grid `sm:grid-cols-2 lg:grid-cols-4`.
- **Arus Kas disesuaikan dengan daftar arus kas legacy** (`/laporan/arus-kas`): 4 kartu ringkasan bergradien, panel "Filter Data" yang bisa dibuka/tutup, pemilih jumlah baris per halaman, kolom Nama Unit / Kode Akun / Nama Akun / Tujuan Transaksi / Jenis Transaksi / Jml. Transaksi / Waktu Transaksi, pengurutan server-side, serta ekspor CSV.
- **PPDB submenu & fungsi**: menu PPDB kini berupa dropdown 13 submenu dengan halaman Livewire terpisah - Data Pendaftar (/ppdb/pendaftar, filter periode/status + pendaftaran calon siswa), Data Tagihan (/ppdb/tagihan, terbit tagihan + ubah status lunas/batal), Data Periode (/ppdb/periode, CRUD dengan proteksi hapus saat masih ada pendaftar), Konten Informasi > Pengumuman (/ppdb/konten/pengumuman, CRUD + toggle terbit + slug unik), delapan Pengaturan PPDB (/ppdb/pengaturan/{section} - umum, formulir, biaya, persyaratan, notifikasi, kontak, bidang, komponen) yang disimpan ke tabel dmission_settings dengan prefix ppdb.{section}.{field}, dan Akses Portal (/ppdb/portal, status periode + tautan pendaftaran publik + pratinjau pengumuman). /ppdb lama tetap tersedia untuk kompatibilitas. Dropdown navbar (desktop & mobile) mendukung parameter route sehingga hanya submenu pengaturan yang sedang dibuka yang ter-highlight.
- **Dashboard Beranda Cards**: ringkasan kartu teratas di `/beranda` diganti menjadi 6 kartu berlatar putih dengan watermark icon biru pucat di kanan bawah sesuai tata letak yang diminta — Data Unit, Data Kelas, Data Tahun, Data Siswa, Akun Petugas (user aktif ber-role), dan Akun Siswa (0, belum ada modul login siswa di skema). Metrik `DashboardService::statistics()` diperbarui menghitung master data tersebut dengan filter soft-delete yang tepat.
- **Rekap submenu** (`/laporan/transaksi`, `/laporan/arus-kas`, `/laporan/cetak`): `TransactionIndex` lists payments with search/status/date/academic-year filters plus approved/pending totals; `CashFlowIndex` lists cash transactions with income/expense/balance summary; `ReportPrintIndex` + `ReportPrintController` render A4-landscape PDFs (DomPDF) for approved payments, outstanding bills, and cash flow behind `reports.export`. "Rekap" in the navbar is now a dropdown with four children.
- **Pembayaran submenu** (`/pembayaran/catat`, `/pembayaran/verifikasi`): navbar "Pembayaran" is now a dropdown with Semua Pembayaran / Proses Pembayaran / Verifikasi Pembayaran. New `PaymentVerification` Livewire page (`payments.verify`) lists pending payments with search plus waiting-count/waiting-total cards, approves via `PaymentService::approve()` (row-locked, updates `student_bills.paid_amount`/`remaining_amount`/`status`), and rejects through a reason modal via `PaymentService::reject()`; both actions write a `payment_verifications` snapshot and an activity log.
- **Rekap Transaksi cards**: ringkasan `/laporan/transaksi` kini 5 kartu sesuai kebutuhan operasional — Total Transaksi, Transaksi Tagihan (punya alokasi), Transaksi Lainnya, Transaksi Tunai (`payment_methods.code = cash`), dan Transaksi Non-Tunai; semua nilai memakai transaksi berstatus `approved`.
- **PPDB applicant detail** (`/ppdb/{applicant}`, `ApplicantShow`): change applicant status, upload/replace requirement documents (private `local` disk, `mimes:jpg,jpeg,png,pdf`, max 5 MB, sha256 checksum), verify/reject/delete documents via new `admissions.verify` permission, and create/settle PPDB bills (`AdmissionBill`). Documents download through authorized `applicant-documents.download` endpoint (`ApplicantDocumentController`), never public URLs.
- **PPDB content & announcements** (`AdmissionContent`): third tab on `/ppdb` for create/edit/delete with unique slug generation, publish toggle, and optional period scoping; published entries render on the public `/ppdb/daftar` page.
- **Phase 7 notifications & queue monitoring**: `notification_templates`, `notification_logs`, `reminder_schedules` tables; `NotificationTemplate`/`NotificationLog`/`ReminderSchedule` models; `NotificationService` (template placeholder rendering, email via Mail, WhatsApp via configurable HTTP gateway, per-attempt logging); `SendFeeReminder` queued job; `/peralatan/notifikasi` page with delivery log, templates, reminder schedules, and pending/failed queue counters.
- **Scheduled fee reminders**: `reminders:send` artisan command queuing reminders for bills due within N days, scheduled daily at 08:00 in `routes/console.php`; `NotificationTemplateSeeder` seeds WhatsApp/Email templates and H-3/H-1 schedules.
- **Server information page** (`/peralatan/server`) with runtime details, disk usage, and `optimize:clear` action.
- `config/services.php` gains `whatsapp.endpoint` / `whatsapp.token`; `.env.example` documents `WHATSAPP_ENDPOINT`, `WHATSAPP_TOKEN`, `MYSQLDUMP_PATH`, `MYSQL_PATH`.
- Added CSV export for Phase 5 financial reports (`/laporan/export`, `reports.export`): approved payments and outstanding bills, streamed with `fputcsv` (no new dependency).
- Student CSV import/export controller (`students.import` / `students.export`) with strict header-order validation, duplicate-NIS skipping, per-row `unit_code` errors, and transactional import; header actions on the student index now expose an import modal and export link.
- Added Phase 5 financial reports (cash/journal schema, `FinancialReportService`, `/laporan`) and Phase 7 system tools: activity log viewer, backup/restore, and student CSV import/export.
- Dashboard now aggregates `student_bills` for accurate totals, status composition, and receivables.
- Added Phase 6 PPDB admin core: Livewire admission period and applicant management, protected route/menu integration, automatic registration numbers, and focused feature tests.
- Public PPDB registration portal (`/ppdb/daftar`) with rate limiting, signed success page, and closed-period page.

### Ditambahkan

- **Detail tagihan & pembatalan** — halaman `/tagihan/{studentBill}` (`BillShow`) menampilkan info siswa, rincian nominal, riwayat potongan, dan aksi batalkan; `BillingService::cancel()` menolak pembatalan bila tagihan sudah memiliki alokasi pembayaran `pending`/`approved` atau `paid_amount > 0`.
- **Ekspor daftar tagihan CSV** (`/tagihan/export`, permission `bills.view`) memakai `fputcsv` dengan filter status, mengikuti pola ekspor laporan Fase 5.
- **Kwitansi PDF** (`/pembayaran/{payment}/kwitansi`, permission `payments.print`) via DomPDF, memuat rincian alokasi dan nominal dalam huruf (`Formatter::terbilang()`).
- **Bukti pembayaran** — unggah/hapus bukti di halaman detail pembayaran (disk `local`, `mimes:jpg,jpeg,png,pdf`, maks 5 MB) dan unduhan terotorisasi melalui `PaymentProofController` (permission `payments.view`), bukan URL publik.
- **Fase 4 — Pembayaran: SELESAI** — skema pembayaran, metode pembayaran, alokasi multi-tagihan, status pending/approved/rejected/cancelled, verifikasi, pembaruan saldo atomik dengan row locking, halaman pencatatan dan daftar pembayaran.
- Feature tests pembayaran mencakup pending, persetujuan parsial/penuh, penolakan, duplikasi transaksi, dan validasi alokasi.
- **Fase 3 — Tagihan: SELESAI** — skema tagihan, model, generator nomor,
  service perhitungan, generasi massal/individual/bulanan, pencegahan duplikasi,
  transaksi atomik, row locking, halaman daftar/generasi, dan permission `bills.*`.
- Feature tests billing mencakup generasi massal, pencegahan duplikasi,
  potongan/sisa tagihan, dan otorisasi.

---

## [0.2.0] — 2026-11-05

**Fase 2 — Master Data: SELESAI**

### Ditambahkan

#### Master Data Inti

- **Unit Pendidikan** (`/data-master/unit`) — CRUD unit, kode unik, level,
  urutan tampil, status aktif. Penghapusan diblokir bila unit masih memiliki kelas.
- **Tahun Ajaran** (`/data-master/tahun-ajaran`) — CRUD periode, validasi
  `end_date > start_date`, aktivasi tunggal (mengaktifkan satu periode otomatis
  menonaktifkan yang lain), penghapusan diblokir bila masih memiliki kelas.
- **Kelas** (`/data-master/kelas`) — CRUD kelas terikat unit + tahun ajaran,
  wali kelas, kapasitas, unik pada `(unit_id, academic_year_id, name)`.
  Penghapusan diblokir bila masih memiliki siswa.
- **Petugas** (`/data-master/petugas`) — CRUD profil petugas, relasi opsional ke
  akun pengguna dan unit, NIP unik, jabatan (enum `StaffPosition`).
- **Siswa** (`/data-master/siswa`) — CRUD data induk siswa, NIS/NISN unik,
  penempatan kelas dengan validasi kelas harus berasal dari unit yang sama,
  data orang tua, status (enum `StudentStatus`), soft delete.
- **Kategori Pembayaran** (`/data-master/kategori-pembayaran`) — CRUD kategori,
  jenis (enum `PaymentCategoryType`: sekali / bulanan / tahunan), nominal standar,
  tanggal jatuh tempo bulanan, unit opsional.
- **Akun Keuangan** (`/data-master/akun`) — CRUD bagan akun, tipe
  (enum `AccountType`: kas / bank / piutang / pendapatan / beban), saldo awal,
  penghapusan diblokir bila akun masih dipakai rekening bank.
- **Rekening Bank** (`/data-master/rekening`) — CRUD rekening tujuan pembayaran,
  terhubung ke akun keuangan dan unit, unik pada `(bank_name, account_number)`.

#### Fondasi Pendukung

- `app/Support/Formatter.php` — `rupiah()`, `date()`, `dateTime()`, `time()`,
  `compact()`, dan `parseRupiah()` untuk parsing nominal format Indonesia.
- `app/Services/DashboardService.php` — statistik dashboard nyata dari basis data,
  termasuk perbaikan `countStudents()` yang harus melalui join ke `school_classes`
  (tabel `students` tidak menyimpan `academic_year_id`).
- `app/Services/ActivityLogger.php` — pencatatan audit trail untuk seluruh aksi
  CRUD master data.
- Menu navigasi `Data Master` dengan penyaringan otomatis berdasarkan ketersediaan
  route dan permission pengguna.

#### Pengujian

- `tests/Feature/UnitManagementTest.php` (8 test)
- `tests/Feature/AcademicYearManagementTest.php` (9 test)
- `tests/Feature/SchoolClassManagementTest.php` (8 test)
- `tests/Feature/StaffManagementTest.php` (8 test)
- `tests/Feature/StudentManagementTest.php` (9 test)
- `tests/Feature/PaymentCategoryManagementTest.php` (10 test)
- `tests/Feature/AccountManagementTest.php` (10 test)
- `tests/Feature/BankAccountManagementTest.php` (8 test)

Total suite: **82 test lulus, 189 assertion**.

### Diperbaiki

- **403 pada `/data-master/akun`** — permission `accounts.*` sudah ada di enum
  `PermissionName` tetapi belum tersinkron ke basis data (60 dari 64 permission).
  Diselesaikan dengan menjalankan ulang `PermissionSeeder` + `RoleSeeder` lalu
  `php artisan permission:cache-reset`.
- **`BankAccountSeeder`** — rekening PPDB (`0011002244`, Bank Jatim) sebelumnya
  tertaut ke akun `1103 — Bank BRI`. Diperbaiki menjadi `1102 — Bank Jatim`.
- **`AcademicYearSeeder`** — sebelumnya hanya membuat satu tahun ajaran sehingga
  halaman Tahun Ajaran dan filter tahun tidak memiliki data pembanding. Sekarang
  membuat tiga periode: tahun lalu (nonaktif), tahun aktif, dan tahun depan (nonaktif)
  agar siap untuk PPDB dan penagihan di muka.

### Catatan Teknis

- Seluruh tabel master data memakai `softDeletes()` dan `timestamps()`.
- Kolom nominal memakai `decimal(15, 2)` — tidak ada `FLOAT`/`DOUBLE`.
- Foreign key master data memakai `restrictOnDelete()` agar integritas data terjaga.
- `DemoDataSeeder` hanya dijalankan pada environment `local` supaya pengujian
  tetap deterministik.

---

## [0.1.0] — 2026-11-05

**Fase 1 — Fondasi: SELESAI**

### Ditambahkan

- Instalasi Laravel 12 + MySQL (`komite_man7`, pengujian `komite_man7_test`).
- Vite + Tailwind CSS 4 + Alpine.js + Livewire 3.
- Autentikasi Laravel dengan halaman masuk Livewire, middleware `active`,
  dan pembatasan percobaan masuk (rate limiting).
- Spatie Laravel Permission: 7 role awal dan katalog permission
  `app/Enums/PermissionName.php`.
- Design system: token warna, tipografi, spacing sesuai `DESIGN.MD`.
- 15 komponen UI reusable (`x-ui.button`, `card`, `badge`, `alert`, `modal`,
  `input`, `select`, `textarea`, `table`, `pagination`, `breadcrumb`,
  `dropdown`, `confirm-dialog`, `empty-state`, `toast`).
- Layout utama: header, navbar, breadcrumb, footer dengan jam WIB.
- Dashboard awal dengan kartu statistik dan panel grafik (Chart.js).
- Halaman profil pengguna.
- `app/Support/Navigation.php` — definisi menu terpusat.
- `tests/Feature/AuthenticationTest.php`.
