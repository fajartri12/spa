<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionSetting;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan PPDB')]
class SettingIndex extends Component
{
    public string $section = 'umum';

    /** @var array<string, mixed> */
    public array $values = [];

    /**
     * Definisi field tiap bagian pengaturan PPDB.
     *
     * @return array<string, array{label: string, description: string, icon: string, fields: array<int, array<string, mixed>>}>
     */
    public static function sections(): array
    {
        return [
            'umum' => [
                'label' => 'Pengaturan Umum',
                'description' => 'Identitas penyelenggara dan ketentuan dasar PPDB.',
                'icon' => 'settings',
                'fields' => [
                    ['key' => 'nama_lembaga', 'label' => 'Nama Lembaga', 'type' => 'text', 'hint' => 'Nama yang tampil pada portal PPDB.'],
                    ['key' => 'tahun_ajaran', 'label' => 'Tahun Ajaran', 'type' => 'text', 'hint' => 'Contoh: 2026/2027.'],
                    ['key' => 'kuota', 'label' => 'Kuota Penerimaan', 'type' => 'number', 'hint' => 'Jumlah maksimal calon siswa diterima.'],
                    ['key' => 'deskripsi', 'label' => 'Deskripsi Singkat', 'type' => 'textarea', 'hint' => 'Ringkasan informasi PPDB.'],
                ],
            ],
            'formulir' => [
                'label' => 'Pengaturan Formulir',
                'description' => 'Atur data yang wajib diisi calon pendaftar.',
                'icon' => 'file-text',
                'fields' => [
                    ['key' => 'petunjuk', 'label' => 'Petunjuk Pengisian', 'type' => 'textarea', 'hint' => 'Tampil di atas formulir pendaftaran.'],
                    ['key' => 'wajib_email', 'label' => 'Email Wajib Diisi', 'type' => 'checkbox'],
                    ['key' => 'wajib_telepon', 'label' => 'Nomor Telepon Wajib Diisi', 'type' => 'checkbox'],
                    ['key' => 'wajib_alamat', 'label' => 'Alamat Wajib Diisi', 'type' => 'checkbox'],
                    ['key' => 'wajib_orang_tua', 'label' => 'Data Orang Tua Wajib Diisi', 'type' => 'checkbox'],
                ],
            ],
            'biaya' => [
                'label' => 'Pengaturan Biaya',
                'description' => 'Nominal biaya standar PPDB yang dipakai saat menerbitkan tagihan.',
                'icon' => 'banknote',
                'fields' => [
                    ['key' => 'biaya_formulir', 'label' => 'Biaya Formulir (Rp)', 'type' => 'number'],
                    ['key' => 'biaya_daftar_ulang', 'label' => 'Biaya Daftar Ulang (Rp)', 'type' => 'number'],
                    ['key' => 'nama_bank', 'label' => 'Nama Bank', 'type' => 'text'],
                    ['key' => 'nomor_rekening', 'label' => 'Nomor Rekening', 'type' => 'text'],
                    ['key' => 'atas_nama', 'label' => 'Atas Nama', 'type' => 'text'],
                ],
            ],
            'persyaratan' => [
                'label' => 'Pengaturan Persyaratan',
                'description' => 'Daftar berkas dan syarat pendaftaran.',
                'icon' => 'clipboard-list',
                'fields' => [
                    ['key' => 'daftar_syarat', 'label' => 'Daftar Persyaratan', 'type' => 'textarea', 'hint' => 'Satu persyaratan per baris.'],
                    ['key' => 'catatan', 'label' => 'Catatan Tambahan', 'type' => 'textarea'],
                ],
            ],
            'notifikasi' => [
                'label' => 'Pengaturan Notifikasi',
                'description' => 'Kanal pemberitahuan otomatis untuk pendaftar.',
                'icon' => 'bell',
                'fields' => [
                    ['key' => 'whatsapp_aktif', 'label' => 'Notifikasi WhatsApp Aktif', 'type' => 'checkbox'],
                    ['key' => 'email_aktif', 'label' => 'Notifikasi Email Aktif', 'type' => 'checkbox'],
                    ['key' => 'template_pesan', 'label' => 'Template Pesan', 'type' => 'textarea', 'hint' => 'Gunakan :nama dan :nomor sebagai placeholder.'],
                ],
            ],
            'kontak' => [
                'label' => 'Pengaturan Kontak',
                'description' => 'Kontak panitia PPDB yang tampil pada portal.',
                'icon' => 'message-circle',
                'fields' => [
                    ['key' => 'telepon', 'label' => 'Nomor Telepon Panitia', 'type' => 'text'],
                    ['key' => 'email', 'label' => 'Email Panitia', 'type' => 'text'],
                    ['key' => 'alamat', 'label' => 'Alamat Sekretariat', 'type' => 'textarea'],
                    ['key' => 'jam_layanan', 'label' => 'Jam Layanan', 'type' => 'text', 'hint' => 'Contoh: Senin - Jumat, 08.00 - 14.00.'],
                ],
            ],
            'bidang' => [
                'label' => 'Pengaturan Bidang',
                'description' => 'Bidang atau jalur pendaftaran yang tersedia.',
                'icon' => 'layers',
                'fields' => [
                    ['key' => 'daftar_bidang', 'label' => 'Daftar Bidang / Jalur', 'type' => 'textarea', 'hint' => 'Satu bidang per baris.'],
                    ['key' => 'kuota_prestasi', 'label' => 'Kuota Jalur Prestasi', 'type' => 'number'],
                ],
            ],
            'komponen' => [
                'label' => 'Pengaturan Komponen',
                'description' => 'Komponen biaya yang menjadi bagian tagihan PPDB.',
                'icon' => 'layout-grid',
                'fields' => [
                    ['key' => 'daftar_komponen', 'label' => 'Daftar Komponen Biaya', 'type' => 'textarea', 'hint' => 'Satu komponen per baris, contoh: Seragam - 350000.'],
                    ['key' => 'keterangan', 'label' => 'Keterangan', 'type' => 'textarea'],
                ],
            ],
        ];
    }

    public function mount(string $section = 'umum'): void
    {
        $this->authorize('admissions.view');
        $this->section = array_key_exists($section, self::sections()) ? $section : 'umum';
        $this->loadValues();
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('admissions.update');

        $keys = array_column(self::sections()[$this->section]['fields'], 'key');

        foreach ($keys as $key) {
            $value = $this->values[$key] ?? null;
            $value = is_bool($value) ? ($value ? '1' : '0') : (is_scalar($value) ? trim((string) $value) : '');

            AdmissionSetting::query()->updateOrCreate(
                ['key' => "ppdb.{$this->section}.{$key}"],
                ['value' => $value, 'is_encrypted' => false],
            );
        }

        $logger->log('admissions.settings_updated', "Memperbarui pengaturan PPDB bagian {$this->section}");

        $this->dispatch('toast', type: 'success', message: 'Pengaturan PPDB berhasil disimpan.');
    }

    private function loadValues(): void
    {
        $stored = AdmissionSetting::query()
            ->where('key', 'like', "ppdb.{$this->section}.%")
            ->pluck('value', 'key');

        $values = [];

        foreach (self::sections()[$this->section]['fields'] as $field) {
            $raw = $stored["ppdb.{$this->section}.{$field['key']}"] ?? null;
            $values[$field['key']] = $field['type'] === 'checkbox' ? (bool) $raw : (string) $raw;
        }

        $this->values = $values;
    }

    public function render(): View
    {
        $this->authorize('admissions.view');

        return view('livewire.admissions.setting-index', [
            'definition' => self::sections()[$this->section],
            'sectionList' => self::sections(),
        ]);
    }
}
