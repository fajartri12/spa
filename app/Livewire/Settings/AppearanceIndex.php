<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Tampilan')]
class AppearanceIndex extends Component
{
    public string $activeTab = 'app';

    // 1. Tema Aplikasi
    public string $theme_preset = 'blue';
    public string $theme_primary_color = '#2872c8';
    public string $theme_primary_dark = '#20599f';
    public string $theme_primary_light = '#eaf2fc';
    public string $theme_primary_soft = '#f3f7fd';
    public string $theme_radius_style = 'standard';
    public string $theme_header_style = 'primary';

    // 2. Tema Menu Navigasi
    public string $theme_nav_style = 'light';
    public bool $theme_nav_sticky = true;
    public string $theme_nav_icons = 'all';

    // 3. Tema Halaman Masuk Akun
    public string $theme_login_style = 'split';
    public string $theme_login_bg = 'primary';
    public string $theme_login_bg_color = '#2872c8';
    public string $theme_login_title = 'Pengelolaan Pembayaran Tagihan Sekolah';
    public string $theme_login_subtitle = 'Satu sistem terintegrasi untuk tagihan, pembayaran, verifikasi, dan laporan keuangan madrasah.';
    public string $theme_login_feature_1 = 'Tagihan & potongan terkelola rapi';
    public string $theme_login_feature_2 = 'Pembayaran multi-tagihan dan sebagian';
    public string $theme_login_feature_3 = 'Laporan keuangan & tunggakan real-time';
    public string $theme_login_footer = 'KOMITE MAN 7 JOMBANG';

    /**
     * @var array<string, array{name: string, primary: string, dark: string, light: string, soft: string}>
     */
    public const PRESETS = [
        'blue' => [
            'name' => 'Biru Komite (Default)',
            'primary' => '#2872c8',
            'dark' => '#20599f',
            'light' => '#eaf2fc',
            'soft' => '#f3f7fd',
        ],
        'emerald' => [
            'name' => 'Hijau Kemenag',
            'primary' => '#107c41',
            'dark' => '#0d6233',
            'light' => '#e8f5ec',
            'soft' => '#f2f9f4',
        ],
        'teal' => [
            'name' => 'Teal Madrasah',
            'primary' => '#0d9488',
            'dark' => '#0f766e',
            'light' => '#e6f7f6',
            'soft' => '#f0faf9',
        ],
        'indigo' => [
            'name' => 'Indigo Modern',
            'primary' => '#4f46e5',
            'dark' => '#4338ca',
            'light' => '#eef2ff',
            'soft' => '#f5f7ff',
        ],
        'slate' => [
            'name' => 'Slate Elegan',
            'primary' => '#334155',
            'dark' => '#1e293b',
            'light' => '#f1f5f9',
            'soft' => '#f8fafc',
        ],
        'maroon' => [
            'name' => 'Merah Marun',
            'primary' => '#991b1b',
            'dark' => '#7f1d1d',
            'light' => '#fee2e2',
            'soft' => '#fef2f2',
        ],
        'purple' => [
            'name' => 'Ungu Royal',
            'primary' => '#7c3aed',
            'dark' => '#6d28d9',
            'light' => '#f5f3ff',
            'soft' => '#faf5ff',
        ],
    ];

    public function mount(): void
    {
        $this->authorize('settings.view');

        $this->theme_preset = (string) AppSetting::get('theme_preset', 'blue');
        $this->theme_primary_color = (string) AppSetting::get('theme_primary_color', '#2872c8');
        $this->theme_primary_dark = (string) AppSetting::get('theme_primary_dark', '#20599f');
        $this->theme_primary_light = (string) AppSetting::get('theme_primary_light', '#eaf2fc');
        $this->theme_primary_soft = (string) AppSetting::get('theme_primary_soft', '#f3f7fd');
        $this->theme_radius_style = (string) AppSetting::get('theme_radius_style', 'standard');
        $this->theme_header_style = (string) AppSetting::get('theme_header_style', 'primary');

        $this->theme_nav_style = (string) AppSetting::get('theme_nav_style', 'light');
        $this->theme_nav_sticky = (bool) AppSetting::get('theme_nav_sticky', true);
        $this->theme_nav_icons = (string) AppSetting::get('theme_nav_icons', 'all');

        $this->theme_login_style = (string) AppSetting::get('theme_login_style', 'split');
        $this->theme_login_bg = (string) AppSetting::get('theme_login_bg', 'primary');
        $this->theme_login_bg_color = (string) AppSetting::get('theme_login_bg_color', '#2872c8');
        $this->theme_login_title = (string) AppSetting::get('theme_login_title', 'Pengelolaan Pembayaran Tagihan Sekolah');
        $this->theme_login_subtitle = (string) AppSetting::get('theme_login_subtitle', 'Satu sistem terintegrasi untuk tagihan, pembayaran, verifikasi, dan laporan keuangan madrasah.');

        $features = json_decode((string) AppSetting::get('theme_login_features', ''), true);
        if (is_array($features) && count($features) >= 3) {
            $this->theme_login_feature_1 = (string) ($features[0] ?? '');
            $this->theme_login_feature_2 = (string) ($features[1] ?? '');
            $this->theme_login_feature_3 = (string) ($features[2] ?? '');
        }

        $this->theme_login_footer = (string) AppSetting::get('theme_login_footer', 'KOMITE MAN 7 JOMBANG');
    }

    public function selectPreset(string $preset): void
    {
        if (! isset(self::PRESETS[$preset])) {
            return;
        }

        $this->theme_preset = $preset;
        $this->theme_primary_color = self::PRESETS[$preset]['primary'];
        $this->theme_primary_dark = self::PRESETS[$preset]['dark'];
        $this->theme_primary_light = self::PRESETS[$preset]['light'];
        $this->theme_primary_soft = self::PRESETS[$preset]['soft'];
    }

    public function updatedThemePrimaryColor(): void
    {
        $this->theme_preset = 'custom';
        $this->theme_primary_color = strtolower($this->theme_primary_color);
        if (! preg_match('/^#[a-f0-9]{6}$/i', $this->theme_primary_color)) {
            return;
        }

        $this->theme_primary_dark = $this->adjustBrightness($this->theme_primary_color, -0.22);
        $this->theme_primary_light = $this->mixWithWhite($this->theme_primary_color, 0.88);
        $this->theme_primary_soft = $this->mixWithWhite($this->theme_primary_color, 0.95);
    }

    public function resetToDefault(): void
    {
        $this->authorize('settings.update');

        $this->selectPreset('blue');
        $this->theme_radius_style = 'standard';
        $this->theme_header_style = 'primary';

        $this->theme_nav_style = 'light';
        $this->theme_nav_sticky = true;
        $this->theme_nav_icons = 'all';

        $this->theme_login_style = 'split';
        $this->theme_login_bg = 'primary';
        $this->theme_login_bg_color = '#2872c8';
        $this->theme_login_title = 'Pengelolaan Pembayaran Tagihan Sekolah';
        $this->theme_login_subtitle = 'Satu sistem terintegrasi untuk tagihan, pembayaran, verifikasi, dan laporan keuangan madrasah.';
        $this->theme_login_feature_1 = 'Tagihan & potongan terkelola rapi';
        $this->theme_login_feature_2 = 'Pembayaran multi-tagihan dan sebagian';
        $this->theme_login_feature_3 = 'Laporan keuangan & tunggakan real-time';
        $this->theme_login_footer = 'KOMITE MAN 7 JOMBANG';

        $this->dispatch('toast', type: 'info', message: 'Tampilan direset ke pengaturan bawaan.');
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        $this->validate([
            'theme_primary_color' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
            'theme_primary_dark' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
            'theme_primary_light' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
            'theme_primary_soft' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
            'theme_radius_style' => ['required', 'string', 'in:standard,rounded,sharp'],
            'theme_header_style' => ['required', 'string', 'in:primary,dark,light'],
            'theme_nav_style' => ['required', 'string', 'in:light,dark,subtle'],
            'theme_nav_sticky' => ['required', 'boolean'],
            'theme_nav_icons' => ['required', 'string', 'in:all,text_only'],
            'theme_login_style' => ['required', 'string', 'in:split,centered'],
            'theme_login_bg' => ['required', 'string', 'in:primary,gradient-blue,gradient-emerald,gradient-dark,custom'],
            'theme_login_bg_color' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
            'theme_login_title' => ['required', 'string', 'max:120'],
            'theme_login_subtitle' => ['required', 'string', 'max:250'],
            'theme_login_feature_1' => ['required', 'string', 'max:80'],
            'theme_login_feature_2' => ['required', 'string', 'max:80'],
            'theme_login_feature_3' => ['required', 'string', 'max:80'],
            'theme_login_footer' => ['required', 'string', 'max:120'],
        ]);

        $features = [
            $this->theme_login_feature_1,
            $this->theme_login_feature_2,
            $this->theme_login_feature_3,
        ];

        AppSetting::set('theme_preset', $this->theme_preset);
        AppSetting::set('theme_primary_color', $this->theme_primary_color);
        AppSetting::set('theme_primary_dark', $this->theme_primary_dark);
        AppSetting::set('theme_primary_light', $this->theme_primary_light);
        AppSetting::set('theme_primary_soft', $this->theme_primary_soft);
        AppSetting::set('theme_radius_style', $this->theme_radius_style);
        AppSetting::set('theme_header_style', $this->theme_header_style);

        AppSetting::set('theme_nav_style', $this->theme_nav_style);
        AppSetting::set('theme_nav_sticky', $this->theme_nav_sticky ? '1' : '0');
        AppSetting::set('theme_nav_icons', $this->theme_nav_icons);

        AppSetting::set('theme_login_style', $this->theme_login_style);
        AppSetting::set('theme_login_bg', $this->theme_login_bg);
        AppSetting::set('theme_login_bg_color', $this->theme_login_bg_color);
        AppSetting::set('theme_login_title', $this->theme_login_title);
        AppSetting::set('theme_login_subtitle', $this->theme_login_subtitle);
        AppSetting::set('theme_login_features', json_encode($features, JSON_UNESCAPED_UNICODE));
        AppSetting::set('theme_login_footer', $this->theme_login_footer);

        $logger->log('settings.updated', 'Memperbarui pengaturan tema dan tampilan aplikasi');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan tampilan berhasil disimpan.');
    }

    public function render(): View
    {
        return view('livewire.settings.appearance-index', [
            'presets' => self::PRESETS,
        ]);
    }

    private function adjustBrightness(string $hex, float $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#2872c8';
        }
        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, (int) round($r * (1 + $percent))));
        $g = max(0, min(255, (int) round($g * (1 + $percent))));
        $b = max(0, min(255, (int) round($b * (1 + $percent))));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function mixWithWhite(string $hex, float $weight): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#f3f7fd';
        }
        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        $r = (int) round($r * (1 - $weight) + 255 * $weight);
        $g = (int) round($g * (1 - $weight) + 255 * $weight);
        $b = (int) round($b * (1 - $weight) + 255 * $weight);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
