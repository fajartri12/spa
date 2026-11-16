<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'key' => 'app_name', 'value' => 'KOMITE MAN 7 JOMBANG', 'label' => 'Nama Aplikasi'],
            ['group' => 'general', 'key' => 'app_subtitle', 'value' => 'APLIKASI PENGELOLAAN PEMBAYARAN TAGIHAN', 'label' => 'Sub Judul Aplikasi'],
            ['group' => 'general', 'key' => 'school_name', 'value' => 'MAN 7 JOMBANG', 'label' => 'Nama Sekolah'],
            ['group' => 'general', 'key' => 'school_address', 'value' => '', 'label' => 'Alamat Sekolah', 'type' => 'text'],
            ['group' => 'general', 'key' => 'school_phone', 'value' => '', 'label' => 'Telepon Sekolah'],
            ['group' => 'general', 'key' => 'school_email', 'value' => '', 'label' => 'Email Sekolah', 'type' => 'email'],
            ['group' => 'general', 'key' => 'school_logo', 'value' => '', 'label' => 'Logo Sekolah', 'type' => 'image', 'is_public' => true],
            ['group' => 'general', 'key' => 'headmaster_name', 'value' => '', 'label' => 'Nama Kepala Sekolah'],
            ['group' => 'general', 'key' => 'treasurer_name', 'value' => '', 'label' => 'Nama Bendahara'],
            ['group' => 'display', 'key' => 'currency_symbol', 'value' => 'Rp', 'label' => 'Simbol Mata Uang'],
            ['group' => 'display', 'key' => 'date_format', 'value' => 'd F Y', 'label' => 'Format Tanggal'],
            ['group' => 'display', 'key' => 'rows_per_page', 'value' => '15', 'label' => 'Baris per Halaman', 'type' => 'integer'],
            ['group' => 'system', 'key' => 'footer_text', 'value' => '© KOMITE MAN 7 JOMBANG', 'label' => 'Teks Footer', 'is_public' => true],
            ['group' => 'system', 'key' => 'feature_student_savings_enabled', 'value' => '1', 'label' => 'Fitur Tabungan Siswa'],
            ['group' => 'payment', 'key' => 'payment_prefix', 'value' => 'TRX', 'label' => 'Prefix Nomor Transaksi'],
            ['group' => 'payment', 'key' => 'bill_prefix', 'value' => 'TGH', 'label' => 'Prefix Nomor Tagihan'],
        ];

        foreach ($settings as $setting) {
            AppSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'group' => $setting['group'],
                    'value' => $setting['value'],
                    'type' => $setting['type'] ?? 'string',
                    'label' => $setting['label'],
                    'is_public' => $setting['is_public'] ?? false,
                ],
            );
        }

        $this->command?->info('App settings seeded.');
    }
}
