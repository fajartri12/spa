<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Definisi struktur navigasi utama aplikasi.
 *
 * Menu yang route-nya belum tersedia (modul belum diimplementasikan) atau
 * tidak diizinkan untuk pengguna akan disembunyikan. Ini memastikan tidak ada
 * tautan mati / tombol tanpa fungsi (AGENTS.MD bagian 15 & DESIGN.MD bagian 19).
 */
final class Navigation
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function menu(): array
    {
        return [
            [
                'label' => 'Beranda',
                'route' => 'dashboard',
                'icon' => 'house',
                'permission' => 'dashboard.view',
            ],
            [
                'label' => 'Tabungan',
                'route' => 'savings.index',
                'icon' => 'piggy-bank',
                'permission' => 'savings.view',
                'children' => [
                    ['label' => 'Daftar Tabungan', 'route' => 'savings.index', 'icon' => 'piggy-bank', 'permission' => 'savings.view'],
                    ['label' => 'Transaksi Tabungan', 'route' => 'savings.transaction', 'icon' => 'circle-dollar-sign', 'permission' => 'savings.create'],
                    ['label' => 'Verifikasi Penarikan', 'route' => 'savings.verification', 'icon' => 'badge-check', 'permission' => 'savings.verify'],
                ],
            ],
            [
                'label' => 'Pembayaran',
                'route' => 'payments.index',
                'icon' => 'receipt',
                'children' => [
                    ['label' => 'Semua Pembayaran', 'route' => 'payments.index', 'icon' => 'receipt', 'permission' => 'payments.view'],
                    ['label' => 'Proses Pembayaran', 'route' => 'payments.create', 'icon' => 'circle-dollar-sign', 'permission' => 'payments.create'],
                    ['label' => 'Verifikasi Pembayaran', 'route' => 'payments.verification', 'icon' => 'badge-check', 'permission' => 'payments.verify'],
                    ['label' => 'Tagihan Digital (VA/QRIS)', 'route' => 'payment-intents.index', 'icon' => 'qr-code', 'permission' => 'payment-gateways.view'],
                ],
            ],
            [
                'label' => 'Keuangan',
                'icon' => 'wallet',
                'children' => [
                    ['label' => 'Kas Keluar', 'route' => 'cash.expenses', 'icon' => 'arrow-up-right', 'permission' => 'cash-transactions.view'],
                    ['label' => 'Rekonsiliasi Bank', 'route' => 'bank-reconciliations.index', 'icon' => 'landmark', 'permission' => 'bank-reconciliations.view'],
                    ['label' => 'Honorarium & Gaji', 'route' => 'payrolls.index', 'icon' => 'hand-coins', 'permission' => 'payrolls.view'],
                ],
            ],
            [
                'label' => 'Rekap',
                'icon' => 'chart-pie',
                'children' => [
                    ['label' => 'Ringkasan', 'route' => 'reports.index', 'icon' => 'layout-dashboard', 'permission' => 'reports.view'],
                    ['label' => 'Transaksi', 'route' => 'reports.transactions', 'icon' => 'arrow-left-right', 'permission' => 'reports.view'],
                    ['label' => 'Arus Kas', 'route' => 'reports.cash-flow', 'icon' => 'banknote', 'permission' => 'reports.view'],
                    ['label' => 'Jurnal Umum', 'route' => 'reports.journals', 'icon' => 'book-open', 'permission' => 'reports.view'],
                    ['label' => 'Cetak Laporan', 'route' => 'reports.print', 'icon' => 'printer', 'permission' => 'reports.export'],
                ],
            ],
            [
                'label' => 'Tagihan',
                'route' => 'bills.index',
                'icon' => 'tags',
                'permission' => 'bills.view',
                'children' => [
                    ['label' => 'Daftar Tagihan', 'route' => 'bills.index', 'icon' => 'list', 'permission' => 'bills.view'],
                    ['label' => 'Generate Tagihan', 'route' => 'bills.generate', 'icon' => 'file-plus', 'permission' => 'bills.generate'],
                    ['label' => 'Potongan Tagihan', 'route' => 'discounts.index', 'icon' => 'percent', 'permission' => 'discounts.view'],
                ],
            ],
            [
                'label' => 'Data Master',
                'icon' => 'layout-grid',
                'children' => [
                    ['label' => 'Siswa', 'route' => 'students.index', 'icon' => 'users', 'permission' => 'students.view'],
                    ['label' => 'Orang Tua / Wali', 'route' => 'guardians.index', 'icon' => 'users-round', 'permission' => 'guardians.view'],
                    ['label' => 'Kenaikan Kelas', 'route' => 'students.promotion', 'icon' => 'arrow-up-right', 'permission' => 'students.promote'],
                    ['label' => 'Bebas Tanggungan & SKL', 'route' => 'clearances.index', 'icon' => 'file-badge', 'permission' => 'clearances.view'],
                    ['label' => 'Alumni', 'route' => 'alumni.index', 'icon' => 'graduation-cap', 'permission' => 'alumni.view'],
                    ['label' => 'Kelas', 'route' => 'school-classes.index', 'icon' => 'school', 'permission' => 'school-classes.view'],
                    ['label' => 'Unit Pendidikan', 'route' => 'units.index', 'icon' => 'building-2', 'permission' => 'units.view'],
                    ['label' => 'Tahun Ajaran', 'route' => 'academic-years.index', 'icon' => 'calendar-range', 'permission' => 'academic-years.view'],
                    ['label' => 'Petugas', 'route' => 'staff.index', 'icon' => 'user-cog', 'permission' => 'staff.view'],
                    ['label' => 'Kategori Pembayaran', 'route' => 'payment-categories.index', 'icon' => 'tag', 'permission' => 'payment-categories.view'],
                    ['label' => 'Akun Keuangan', 'route' => 'accounts.index', 'icon' => 'wallet', 'permission' => 'accounts.view'],
                    ['label' => 'Rekening', 'route' => 'bank-accounts.index', 'icon' => 'landmark', 'permission' => 'bank-accounts.view'],
                ],
            ],
            [
                'label' => 'PPDB',
                'icon' => 'graduation-cap',
                'children' => [
                    [
                        'label' => 'Data Pendaftar',
                        'route' => 'admissions.applicants',
                        'icon' => 'users',
                        'permission' => 'admissions.view',
                    ],
                    [
                        'label' => 'Data Tagihan',
                        'route' => 'admissions.bills',
                        'icon' => 'receipt',
                        'permission' => 'admissions.view',
                    ],
                    [
                        'label' => 'Data Periode',
                        'route' => 'admissions.periods',
                        'icon' => 'calendar-range',
                        'permission' => 'admissions.view',
                    ],
                    [
                        'label' => 'Pengumuman',
                        'route' => 'admissions.contents',
                        'icon' => 'megaphone',
                        'permission' => 'admissions.view',
                    ],
                    [
                        'label' => 'Pengaturan',
                        'icon' => 'settings',
                        'children' => [
                            ['label' => 'Pengaturan Umum', 'route' => 'admissions.settings', 'params' => ['section' => 'umum'], 'icon' => 'settings', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Formulir', 'route' => 'admissions.settings', 'params' => ['section' => 'formulir'], 'icon' => 'file-text', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Biaya', 'route' => 'admissions.settings', 'params' => ['section' => 'biaya'], 'icon' => 'banknote', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Persyaratan', 'route' => 'admissions.settings', 'params' => ['section' => 'persyaratan'], 'icon' => 'clipboard-list', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Notifikasi', 'route' => 'admissions.settings', 'params' => ['section' => 'notifikasi'], 'icon' => 'bell', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Kontak', 'route' => 'admissions.settings', 'params' => ['section' => 'kontak'], 'icon' => 'message-circle', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Bidang', 'route' => 'admissions.settings', 'params' => ['section' => 'bidang'], 'icon' => 'layers', 'permission' => 'admissions.view'],
                            ['label' => 'Pengaturan Komponen', 'route' => 'admissions.settings', 'params' => ['section' => 'komponen'], 'icon' => 'layout-grid', 'permission' => 'admissions.view'],
                        ],
                    ],
                    [
                        'label' => 'Akses Portal',
                        'route' => 'admissions.portal',
                        'icon' => 'log-in',
                        'permission' => 'admissions.view',
                    ],
                ],
            ],
            [
                'label' => 'Peralatan',
                'icon' => 'sliders-horizontal',
                'children' => [
                    [
                        'label' => 'Konten Informasi',
                        'icon' => 'layout-template',
                        'children' => [
                            ['label' => 'Banner Informasi', 'route' => 'information-banners.index', 'icon' => 'image', 'permission' => 'admissions.view'],
                            ['label' => 'Pengumuman', 'route' => 'admissions.contents', 'icon' => 'megaphone', 'permission' => 'admissions.view'],
                        ],
                    ],
                    ['label' => 'Log Aktivitas', 'route' => 'activity-logs.index', 'icon' => 'history', 'permission' => 'activity-logs.view'],
                    ['label' => 'Notifikasi & Antrian', 'route' => 'notifications.index', 'icon' => 'bell', 'permission' => 'activity-logs.view'],
                    ['label' => 'Informasi Server', 'route' => 'server-info.index', 'icon' => 'server', 'permission' => 'settings.view'],
                    ['label' => 'Backup Database', 'route' => 'backups.index', 'icon' => 'database', 'permission' => 'backup.create'],
                ],
            ],
            [
                'label' => 'Pengaturan',
                'icon' => 'settings',
                'children' => [
                    ['label' => 'Pengaturan Umum', 'route' => 'settings.index', 'icon' => 'settings', 'permission' => 'settings.view'],
                    ['label' => 'Pengaturan Sistem', 'route' => 'settings.system', 'icon' => 'sliders-horizontal', 'permission' => 'settings.view'],
                    ['label' => 'Pengaturan Notifikasi', 'route' => 'settings.notifications', 'icon' => 'bell', 'permission' => 'settings.view'],
                    ['label' => 'Pengaturan Tampilan', 'route' => 'settings.appearance', 'icon' => 'palette', 'permission' => 'settings.view'],
                    ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'user-cog', 'permission' => 'users.view'],
                    ['label' => 'Role & Hak Akses', 'route' => 'roles.index', 'icon' => 'shield-check', 'permission' => 'roles.view'],
                ],
            ],
        ];
    }

    /**
     * Menu yang sudah difilter sesuai akses & ketersediaan route.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user = null): array
    {
        $user ??= auth()->user();

        return self::filter(self::menu(), $user);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private static function filter(array $items, ?User $user): array
    {
        $result = [];

        foreach ($items as $item) {
            if (! empty($item['children'])) {
                $children = self::filter($item['children'], $user);

                if ($children !== []) {
                    $result[] = [...$item, 'children' => $children];
                }

                continue;
            }

            if (! empty($item['heading'])) {
                $result[] = $item;

                continue;
            }

            if (array_key_exists('visible', $item) && ! $item['visible']) {
                continue;
            }

            if (! self::allowed($item, $user) || ! self::routeExists($item)) {
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function allowed(array $item, ?User $user): bool
    {
        $permission = $item['permission'] ?? null;

        if ($permission === null) {
            return true;
        }

        return $user !== null && $user->can($permission);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function routeExists(array $item): bool
    {
        $route = $item['route'] ?? null;

        return $route !== null && Route::has($route);
    }

    /**
     * Apakah salah satu item menu sedang aktif pada request saat ini.
     *
     * @param  array<string, mixed>  $item
     */
    public static function isActive(array $item): bool
    {
        $current = request()->route()?->getName();

        if ($current === null || $current === '') {
            return false;
        }

        if (($item['route'] ?? null) === $current) {
            return true;
        }

        foreach ($item['children'] ?? [] as $child) {
            if (self::isActive($child)) {
                return true;
            }
        }

        // Prefix modul: "students.index" mengaktifkan "students.*"
        $route = $item['route'] ?? null;
        if ($route !== null && str_contains($route, '.') && str_starts_with($current, explode('.', $route)[0] . '.')) {
            return true;
        }

        return false;
    }
}
