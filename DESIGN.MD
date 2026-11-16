# DESIGN.md
# KOMITE MAN 7 JOMBANG
## UI/UX Design System & Interface Guidelines

**Project:** KOMITE MAN 7 JOMBANG  
**Product:** School Financial Management System  
**Design Direction:** Modern, Clean, Professional, Administrative  
**Primary Color:** Blue  
**Secondary Color:** Light Gray  
**Framework:** Laravel Blade + Livewire + Tailwind CSS  
**Responsive:** Desktop, Tablet, Mobile

---

# 1. DESIGN VISION

KOMITE MAN 7 JOMBANG merupakan aplikasi administrasi keuangan sekolah yang memiliki banyak modul dan aktivitas operasional.

Desain harus memberikan pengalaman:

- Profesional.
- Sederhana.
- Mudah dipahami.
- Konsisten.
- Cepat digunakan.
- Tidak membingungkan.
- Efisien untuk pekerjaan administrasi.

Gunakan screenshot aplikasi referensi sebagai acuan visual utama.

Pertahankan karakteristik:

- Header biru.
- Navigasi horizontal.
- Dropdown menu.
- Dashboard dengan colorful statistics cards.
- Background abu-abu muda.
- Konten putih.
- Tabel administrasi.
- Layout desktop-first.

Namun, tingkatkan kualitas visual dan usability.

Jangan membuat desain terlalu ramai.

Hindari penggunaan gradient berlebihan, shadow tebal, warna neon, dan animasi yang tidak diperlukan.

---

# 2. BRAND IDENTITY

## 2.1 Application Identity

Nama:

KOMITE MAN 7 JOMBANG

Subtitle:

APLIKASI PENGELOLAAN PEMBAYARAN TAGIHAN

Logo:
Gunakan logo sekolah jika tersedia.

Jika logo belum tersedia, gunakan placeholder yang profesional dan mudah diganti.

## 2.2 Color Palette

### Primary Colors

| Token | Hex | Usage |
|---|---|---|
| Primary | #2872C8 | Primary buttons, navbar |
| Primary Dark | #20599F | Hover, active |
| Primary Light | #EAF2FC | Highlight |
| Primary Soft | #F3F7FD | Background accent |

### Secondary Colors

| Token | Hex | Usage |
|---|---|---|
| Background | #F4F6F9 | Main background |
| Surface | #FFFFFF | Cards and panels |
| Border | #E1E6ED | Borders |
| Muted | #8994A3 | Secondary text |

### Semantic Colors

| Token | Hex | Usage |
|---|---|---|
| Success | #78B82A | Paid, approved |
| Success Light | #EDF7E5 | Success background |
| Warning | #F39C12 | Pending |
| Warning Light | #FFF5E5 | Warning background |
| Danger | #E74C3C | Failed, overdue |
| Danger Light | #FDEDEC | Error background |
| Info | #3997D5 | Information |
| Purple | #A66BC7 | PPDB |
| Pink | #D83E78 | Outstanding statistics |

## 2.3 Color Usage Rules

- Primary digunakan sebagai warna identitas.
- Hijau digunakan untuk pembayaran berhasil.
- Oranye digunakan untuk pending atau peringatan.
- Merah digunakan untuk gagal, jatuh tempo, dan kesalahan.
- Warna netral digunakan untuk area konten.
- Jangan menggunakan warna status secara berlebihan.
- Pastikan teks memiliki kontras yang baik.

---

# 3. TYPOGRAPHY

Gunakan font Inter.

Fallback:
- Arial.
- Sans-serif.

## 3.1 Typography Scale

| Element | Size | Weight |
|---|---:|---|
| Page Title | 24px | 600 |
| Section Title | 18px | 600 |
| Card Title | 14px | 500 |
| Body | 14px | 400 |
| Table Header | 13px | 600 |
| Table Body | 13px | 400 |
| Caption | 12px | 400 |
| Navigation | 14px | 400 |
| Button | 14px | 500 |

## 3.2 Typography Rules

- Hindari ukuran font terlalu kecil.
- Gunakan line-height yang nyaman.
- Gunakan font weight secara konsisten.
- Gunakan format angka yang mudah dibaca.
- Gunakan alignment kanan untuk angka keuangan.
- Gunakan tabular numbers untuk nominal.

Format mata uang:

Rp 1.136.625.000

Format tanggal:

05 November 2026

Format waktu:

09:30 WIB

---

# 4. LAYOUT SYSTEM

## 4.1 Main Application Layout

Struktur layout:

```text
+------------------------------------------------------+
| HEADER                                               |
| LOGO | KOMITE MAN 7 JOMBANG           USER PROFILE   |
+------------------------------------------------------+
| NAVIGATION                                           |
| Beranda Pembayaran Rekap Tagihan Data Master ...     |
+------------------------------------------------------+
|                                                      |
| BREADCRUMB                                           |
|                                                      |
| PAGE TITLE                                           |
|                                                      |
| MAIN CONTENT                                         |
|                                                      |
|                                                      |
+------------------------------------------------------+
| FOOTER                                               |
+------------------------------------------------------+
```

## 4.2 Header

Header menggunakan primary blue.

Spesifikasi:

- Height desktop: 80px.
- Background: #2872C8.
- Text: White.
- Padding horizontal: 24px.
- Logo di sebelah kiri.
- User profile di sebelah kanan.

Header menampilkan:

KOMITE MAN 7 JOMBANG

APLIKASI PENGELOLAAN PEMBAYARAN TAGIHAN

User profile:

- Avatar.
- Nama.
- Email.
- Dropdown.
- Logout.

## 4.3 Navigation Bar

Navbar berada tepat di bawah header.

Spesifikasi:

- Height: 56px.
- Background: #FFFFFF.
- Border bottom: #E1E6ED.
- Position: Sticky.
- Z-index sesuai kebutuhan.
- Icon dan label sejajar horizontal.

Menu:

1. Beranda.
2. Pembayaran.
3. Rekap.
4. Tagihan.
5. Data Master.
6. PPDB.
7. Peralatan.
8. Pengaturan.

### Navigation State

Default:
- Text gray.
- Icon sesuai kategori.

Hover:
- Text primary.
- Background soft primary.

Active:
- Text primary.
- Bottom border 2px primary.

Dropdown:
- White background.
- Border tipis.
- Shadow lembut.
- Radius 6px.
- Menu item height sekitar 44px.
- Hover background light gray.

Dropdown harus memiliki behavior:
- Klik untuk membuka.
- Klik di luar untuk menutup.
- ESC untuk menutup.
- Tidak terpotong oleh container.
- Berfungsi di desktop dan mobile.

## 4.4 Main Content

- Background: #F4F6F9.
- Padding desktop: 24px.
- Padding tablet: 20px.
- Padding mobile: 14px.
- Content width: 100%.
- Gunakan container yang fleksibel.

---

# 5. DASHBOARD DESIGN

Dashboard adalah halaman utama aplikasi.

## 5.1 Dashboard Header

Tampilkan:

Selamat Datang!

Administrator, Anda masuk ke aplikasi sebagai petugas admin.

Tambahkan informasi tanggal dan tahun ajaran aktif.

## 5.2 Statistics Cards

Buat lima kartu statistik.

1. Jumlah Siswa.
2. Total Tagihan.
3. Tagihan Dibayar.
4. Belum Dibayar.
5. Tunggakan Hingga Bulan Ini.

### Card Style

| Property | Value |
|---|---|
| Radius | 6px |
| Height | 82px–100px |
| Padding | 20px |
| Shadow | Subtle |
| Text | White |
| Icon | Large decorative |
| Layout | Horizontal |

Warna:

- Jumlah Siswa: #3997D5.
- Total Tagihan: #2872C8.
- Tagihan Dibayar: #78B82A.
- Belum Dibayar: #F36C16.
- Tunggakan: #D83E78.

Setiap kartu memiliki:
- Label.
- Nominal atau jumlah.
- Icon.
- Tooltip jika diperlukan.

Gunakan angka dinamis dari database.

### Implementasi komponen

Kartu statistik dirender lewat komponen Blade `x-ui.stat-card` agar seluruh modul konsisten.

```blade
<x-ui.stat-card
    label="Total Tagihan Terbit"
    value="128 tagihan"
    icon="tags"
    tone="primary"
    hint="Diperbarui otomatis"
/>
```

| Prop | Nilai | Keterangan |
|---|---|---|
| `label` | string | Label kecil di atas nilai. |
| `value` | string | Nilai utama (gunakan `Formatter::rupiah()` untuk nominal). |
| `icon` | nama kebab-case Lucide | Watermark besar di kanan kartu. |
| `tone` | `primary`, `primary-dark`, `success`, `danger`, `info`, `warning`, `purple`, `orange` | Menentukan gradien latar. |
| `hint` | string/null | Keterangan kecil di bawah nilai. |

Kelas CSS terkait: `.stat-card`, `.stat-card-label`, `.stat-card-value`, `.stat-card-hint`, `.stat-card-watermark`.
Grid pembungkus yang dipakai: `grid gap-3 sm:grid-cols-2 xl:grid-cols-4` (4 kartu) atau `sm:grid-cols-3` (3 kartu).

## 5.3 Dashboard Charts

Buat:

### Monthly Payment Chart
Grafik pemasukan per bulan.

### Payment Category Chart
Distribusi pembayaran berdasarkan kategori.

### Billing Status Chart
Perbandingan:
- Belum dibayar.
- Dibayar sebagian.
- Lunas.
- Jatuh tempo.

### Class Payment Chart
Perbandingan pembayaran berdasarkan kelas.

## 5.4 Dashboard Tables

Tambahkan:

### Transaksi Terbaru

Kolom:
- Tanggal.
- Nomor transaksi.
- Nama siswa.
- Kategori.
- Nominal.
- Status.

### Tunggakan Siswa

Kolom:
- NIS.
- Nama siswa.
- Kelas.
- Jumlah tagihan.
- Total tunggakan.
- Action.

### Pembayaran Pending

Kolom:
- Nomor transaksi.
- Nama siswa.
- Tanggal.
- Nominal.
- Action verifikasi.

## 5.5 Dashboard Filters

Sediakan:
- Tahun ajaran.
- Unit.
- Kelas.
- Periode.
- Custom date range.

Semua komponen dashboard mengikuti filter yang dipilih.

---

# 6. BUTTON SYSTEM

Gunakan button konsisten.

## Primary Button

- Background: Primary.
- Text: White.
- Radius: 5px.
- Height: 38px.

Contoh:
- Tambah Data.
- Simpan.
- Proses Pembayaran.

## Secondary Button

- Background: White.
- Text: Primary.
- Border: Primary.

Contoh:
- Kembali.
- Filter.
- Reset.

## Success Button

Untuk tindakan konfirmasi atau approval.

## Danger Button

Untuk tindakan berisiko seperti pembatalan.

## Icon Button

Digunakan untuk:
- Detail.
- Edit.
- Delete.
- Print.
- Download.

Semua tombol harus memiliki:
- Hover state.
- Focus state.
- Disabled state.
- Loading state jika memproses request.

Gunakan confirmation dialog untuk tindakan penting.

---

# 7. FORM DESIGN

Gunakan form yang sederhana dan terstruktur.

## Form Layout

- Label di atas input.
- Required indicator.
- Helper text.
- Validation error.
- Consistent input height.

Input:
- Height: 40px.
- Radius: 5px.
- Border: #D9E0E8.
- Focus: Primary.

Form harus mendukung:
- Text.
- Number.
- Currency.
- Date.
- Select.
- Searchable select.
- Textarea.
- File upload.
- Checkbox.
- Radio.

## Form Validation

Error ditampilkan di bawah field.

Contoh:

Nominal Tagihan

[ Rp 500.000 ]

Nominal wajib diisi.

Jangan hanya menggunakan alert browser untuk validasi.

---

# 8. DATA TABLE DESIGN

Tabel merupakan komponen utama aplikasi administrasi.

## 8.1 Table Layout

Header tabel:
- Background: #F8FAFC.
- Text: #475569.
- Font weight: 600.
- Border bottom.

Body:
- Background: White.
- Font: 13px.
- Padding: 12px.
- Row hover: #F8FAFC.

## 8.2 Table Toolbar

Setiap halaman tabel memiliki:

- Search input.
- Filter.
- Export.
- Add button.
- Refresh jika diperlukan.

## 8.3 Table Features

- Server-side pagination.
- Sorting.
- Searching.
- Filter berdasarkan kolom.
- Empty state.
- Loading state.
- Responsive horizontal scrolling.

## 8.4 Action Column

Gunakan icon button untuk:
- View.
- Edit.
- Delete.
- Print.

Jangan menampilkan action yang tidak memiliki permission.

---

# 9. BADGE AND STATUS DESIGN

Gunakan badge untuk status.

| Status | Background | Text |
|---|---|---|
| Lunas | #EDF7E5 | #438A21 |
| Belum Dibayar | #FDEDEC | #C0392B |
| Sebagian | #FFF5E5 | #B9770E |
| Pending | #FFF5E5 | #B9770E |
| Approved | #EDF7E5 | #438A21 |
| Rejected | #FDEDEC | #C0392B |
| Aktif | #EDF7E5 | #438A21 |
| Nonaktif | #F1F3F5 | #64748B |

Badge tidak boleh menjadi satu-satunya penanda status. Gunakan teks yang jelas.

---

# 10. MODAL DESIGN

Modal digunakan untuk CRUD sederhana dan konfirmasi.

## Standard Modal

- Background putih.
- Radius 8px.
- Shadow medium.
- Header.
- Content.
- Footer actions.

Ukuran:

- Small: 400px.
- Medium: 600px.
- Large: 800px.

Gunakan modal untuk:
- Tambah kategori.
- Edit rekening.
- Konfirmasi penghapusan.
- Konfirmasi pembayaran.
- Approval transaksi.

Gunakan halaman tersendiri untuk form yang kompleks.

---

# 11. PAGE DESIGN GUIDELINES

## 11.1 Data Siswa

Layout:

Page title: Data Siswa

Toolbar:
- Tambah Siswa.
- Import Excel.
- Export Excel.

Filter:
- Tahun ajaran.
- Unit.
- Kelas.
- Status.

Table:
- NIS.
- NISN.
- Nama.
- Unit.
- Kelas.
- Status.
- Action.

Detail siswa menggunakan tab:

1. Informasi Pribadi.
2. Informasi Akademik.
3. Tagihan.
4. Riwayat Pembayaran.
5. Dokumen.

## 11.2 Kelola Tagihan

Page title: Tagihan Siswa

Statistik:
- Total Tagihan.
- Sudah Lunas.
- Belum Lunas.
- Tunggakan.

Action:
- Buat Tagihan.
- Generate Massal.
- Export.

Filter:
- Tahun ajaran.
- Unit.
- Kelas.
- Kategori.
- Periode.
- Status.

Tabel:
- Nomor tagihan.
- Siswa.
- Kategori.
- Periode.
- Nominal.
- Dibayar.
- Sisa.
- Jatuh tempo.
- Status.

## 11.3 Proses Pembayaran

Desain halaman kasir.

Layout dua kolom pada desktop.

Kolom kiri:
- Pencarian siswa.
- Profil siswa.
- Daftar tagihan.
- Checkbox pemilihan tagihan.

Kolom kanan:
- Tagihan terpilih.
- Total tagihan.
- Total pembayaran.
- Metode pembayaran.
- Rekening penerimaan.
- Tombol proses pembayaran.

Gunakan format nominal Rupiah.

Pastikan nominal dan alokasi pembayaran terlihat jelas sebelum konfirmasi.

## 11.4 Verifikasi Pembayaran

Tampilkan:
- Nomor transaksi.
- Nama siswa.
- Tanggal.
- Nominal.
- Metode.
- Bukti pembayaran.
- Status.

Action:
- Detail.
- Approve.
- Reject.

Gunakan confirmation dialog untuk approval dan rejection.

## 11.5 Transaksi

Tabel transaksi dengan filter periode, kategori, petugas, dan metode pembayaran.

Nominal ditampilkan rata kanan.

Status menggunakan badge.

## 11.6 Arus Kas

Gunakan empat `x-ui.stat-card` pada grid `grid gap-3 sm:grid-cols-2 xl:grid-cols-4`:

| Kartu | Tone | Icon |
|---|---|---|
| Total Kas Akhir | `primary` | `wallet` |
| Transaksi Pemasukan | `success` | `trending-up` |
| Transaksi Pengeluaran | `danger` | `trending-down` |
| Total Transaksi | `primary-dark` | `arrow-left-right` |

Toolbar halaman: tombol **Ekspor** (CSV), **Cetak Laporan** (PDF), dan **Akun Kas** (tautan ke `/akun`).

Panel **Filter Data** bersifat collapsible (default tertutup) berisi pencarian, rentang tanggal, jenis transaksi, akun kas, dan tombol Reset Filter.

Tabel:
- Nomor transaksi.
- Nama unit.
- Kode akun.
- Nama akun.
- Tujuan transaksi.
- Jenis transaksi (badge).
- Jumlah transaksi.
- Waktu transaksi.
- Aksi (ikon mata & printer).

Kolom Nomor Transaksi, Jumlah, Waktu, dan Aksi wajib `whitespace-nowrap`. Tabel memakai `min-w-[880px]` sehingga dapat digulir horizontal pada layar kecil.

## 11.7 PPDB

Desain halaman PPDB memiliki:

- Statistik pendaftar.
- Pendaftar baru.
- Pendaftar diterima.
- Pendaftar menunggu.
- Tagihan pendaftaran.
- Status pembayaran.

### Data Tagihan PPDB (`/ppdb/tagihan`)

- Header: judul, deskripsi, dan tombol **Terbitkan Tagihan** (`icon="plus"`).
- Tiga `x-ui.stat-card`: **Total Tagihan Terbit** (`primary`, `tags`), **Belum Dibayar** (`warning`, `clock`), **Sudah Lunas** (`success`, `badge-check`) pada grid `sm:grid-cols-3`.
- Filter: pencarian (span 2 kolom di `sm`), Periode, dan Status Tagihan; tombol Reset Filter muncul hanya saat ada filter aktif.
- Tabel `min-w-[880px]`: No. Tagihan, Judul Tagihan, Calon Siswa, Nominal, Jatuh Tempo, Status (badge), Aksi.
- Aksi per baris berupa ikon dalam grup berbingkai: centang hijau (tandai lunas) dan silang merah (batalkan), masing-masing dengan `wire:confirm`.
- Nominal dan tanggal wajib memakai `App\Support\Formatter` (`rupiah()`, `date()`), bukan `number_format()` atau `format()` mentah.

Portal publik harus menggunakan desain yang konsisten dengan branding sekolah.

Kartu **Kode Bayar** (VA/QRIS) pada portal siswa dan orang tua memakai latar biru muda
(`border-blue-200 bg-blue-50/70`), nomor bayar bertipe `font-mono` dengan tombol
**Salin** (Alpine.js `navigator.clipboard`), serta menampilkan kanal, nominal, masa
berlaku dalam WIB, dan referensi. Teks penutup menegaskan pembayaran terverifikasi
otomatis sehingga orang tua tidak mengunggah bukti transfer. Partial yang dipakai
bersama: `resources/views/livewire/public/partials/pay-code.blade.php`.

## 11.8 Pengaturan

Gunakan tab atau sidebar pengaturan.

Kelompok:
- Umum.
- Tampilan.
- Sistem.
- Fitur.
- Pembayaran.
- Notifikasi.
- WhatsApp.
- Email.
- Lisensi.
- Pencadangan.

---

# 12. ICON SYSTEM

Gunakan Lucide Icons.

Rekomendasi:

| Menu | Icon |
|---|---|
| Beranda | house |
| Pembayaran | receipt |
| Rekap | chart-pie |
| Tagihan | tags |
| Data Master | layout-grid |
| PPDB | graduation-cap |
| Peralatan | sliders-horizontal |
| Pengaturan | settings |
| Siswa | users |
| Kelas | school |
| Rekening | wallet |
| Laporan | chart-column |
| Backup | database |
| Log | history |
| WhatsApp | message-circle |
| Email | mail |

Gunakan icon dengan ukuran konsisten.

---

# 13. RESPONSIVE DESIGN

Aplikasi harus mendukung:

- Desktop.
- Laptop.
- Tablet.
- Smartphone.

## Desktop

- Header full width.
- Navbar horizontal.
- Dashboard 5-column statistics jika lebar mencukupi.
- Tabel penuh.

## Tablet

- Navbar menyesuaikan lebar.
- Statistik 2–3 kolom.
- Tabel dapat scroll horizontal.

## Mobile

- Header lebih ringkas.
- Navigation menggunakan drawer atau menu collapse.
- Statistik 1–2 kolom.
- Form satu kolom.
- Tabel menggunakan horizontal scrolling atau card layout.
- Tombol action mudah dijangkau.
- Modal tidak melebihi viewport.

Jangan membuat halaman desktop yang hanya diperkecil tanpa penyesuaian layout.

---

# 14. INTERACTION AND ANIMATION

Gunakan animasi ringan.

- Dropdown transition.
- Modal fade.
- Button hover.
- Card hover jika relevan.
- Loading indicator.
- Toast notification.
- Skeleton loading.

Hindari:
- Animasi berlebihan.
- Parallax.
- Efek visual yang mengganggu pekerjaan administrasi.
- Animasi lambat.

Prioritaskan kecepatan dan kenyamanan.

---

# 15. NOTIFICATION SYSTEM

Gunakan toast notification.

Success:
Data berhasil disimpan.

Error:
Terjadi kesalahan saat menyimpan data.

Warning:
Pastikan kembali data yang dimasukkan.

Info:
Data sedang diproses.

Untuk proses pembayaran, verifikasi, dan pembatalan, gunakan notifikasi yang jelas.

---

# 16. EMPTY AND LOADING STATES

Setiap halaman data wajib memiliki empty state.

Contoh:

Belum Ada Data Siswa

Belum ada data siswa yang tersedia. Silakan tambahkan data siswa terlebih dahulu.

Tampilkan tombol:
Tambah Siswa.

Loading state:
- Skeleton table.
- Spinner pada button.
- Disabled button ketika request berlangsung.

Jangan membiarkan pengguna mengirim form berkali-kali.

---

# 17. ACCESSIBILITY

Pastikan:

- Kontras warna memadai.
- Form memiliki label.
- Button memiliki accessible name.
- Icon button memiliki tooltip atau label.
- Navigasi mendukung keyboard.
- Focus indicator terlihat.
- Status tidak hanya dibedakan berdasarkan warna.

---

# 18. FINANCIAL UI GUIDELINES

Karena aplikasi digunakan untuk administrasi keuangan, tampilkan angka secara konsisten.

Format:

```text
Rp 1.000.000
Rp 125.500
Rp 0
```

Gunakan alignment kanan.

Jangan memotong nominal penting.

Gunakan warna untuk menandai:
- Pemasukan.
- Pengeluaran.
- Tunggakan.
- Pembayaran berhasil.
- Pembayaran pending.

Tampilkan total dan subtotal secara jelas.

Setiap tabel keuangan harus memiliki total sesuai filter yang aktif.

---

# 19. DESIGN CONSISTENCY RULES

AI Agent WAJIB:

1. Menggunakan design tokens yang sama.
2. Menggunakan komponen reusable.
3. Menggunakan spacing konsisten.
4. Menggunakan typography yang sama.
5. Menggunakan status badge yang konsisten.
6. Menggunakan format nominal Rupiah.
7. Menggunakan format tanggal Indonesia.
8. Mengikuti layout utama.
9. Menjaga konsistensi antarhalaman.
10. Memastikan seluruh halaman responsive.

DILARANG:

- Membuat desain berbeda untuk setiap modul.
- Menggunakan warna secara acak.
- Menggunakan terlalu banyak font.
- Menggunakan shadow berlebihan.
- Membuat tabel tanpa pagination untuk dataset besar.
- Membuat tombol tanpa fungsi.
- Membuat form tanpa validasi.
- Menggunakan dummy statistics pada dashboard produksi.

---

# 20. FINAL DESIGN DIRECTION

Hasil akhir harus memiliki karakter visual:

**Modern School Financial Administration Dashboard**

Referensi visual:
- Clean administrative dashboard.
- Professional finance management system.
- Modern school management application.
- Minimalist enterprise web application.

Pertahankan ciri khas aplikasi referensi:
- Header biru.
- Navbar horizontal.
- Dropdown navigasi.
- Colorful dashboard statistics.
- Background abu-abu muda.
- White content panels.

Tingkatkan:
- Konsistensi spacing.
- Kualitas typography.
- Pengalaman tabel.
- Kemudahan pencarian.
- Navigasi mobile.
- Kejelasan status pembayaran.
- Kemudahan operasional.

**TARGET AKHIR:**

Menciptakan antarmuka KOMITE MAN 7 JOMBANG yang modern, profesional, ringan, responsif, mudah digunakan oleh petugas administrasi, bendahara, verifikator, dan pimpinan sekolah.