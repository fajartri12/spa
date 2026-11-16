@php
    use App\Models\AppSetting;

    $primary = preg_match('/^#[a-f0-9]{6}$/i', (string) AppSetting::get('theme_primary_color', '')) === 1
        ? (string) AppSetting::get('theme_primary_color')
        : '#2872c8';

    $safeColor = static function (string $key, string $default): string {
        $value = (string) AppSetting::get($key, $default);

        return preg_match('/^#[a-f0-9]{6}$/i', $value) === 1 ? $value : $default;
    };

    $radius = match ((string) AppSetting::get('theme_radius_style', 'standard')) {
        'rounded' => ['card' => '10px', 'control' => '8px', 'modal' => '12px'],
        'sharp' => ['card' => '2px', 'control' => '2px', 'modal' => '3px'],
        default => ['card' => '6px', 'control' => '5px', 'modal' => '8px'],
    };

    $headerStyle = (string) AppSetting::get('theme_header_style', 'primary');
    $header = match ($headerStyle) {
        'dark' => ['bg' => '#0f172a', 'fg' => '#ffffff', 'chip' => 'rgb(255 255 255 / 0.12)', 'line' => 'rgb(255 255 255 / 0.22)'],
        'light' => ['bg' => '#ffffff', 'fg' => '#1e293b', 'chip' => '#f1f5f9', 'line' => '#e1e6ed'],
        default => ['bg' => $primary, 'fg' => '#ffffff', 'chip' => 'rgb(255 255 255 / 0.15)', 'line' => 'rgb(255 255 255 / 0.25)'],
    };

    $nav = match ((string) AppSetting::get('theme_nav_style', 'light')) {
        'dark' => ['bg' => '#1e293b', 'fg' => '#e2e8f0', 'hover' => '#334155'],
        'subtle' => ['bg' => '#f8fafc', 'fg' => '#475569', 'hover' => '#f1f5f9'],
        default => ['bg' => '#ffffff', 'fg' => '#475569', 'hover' => '#f8fafc'],
    };
@endphp

<style>
    :root {
        --color-primary: {{ $primary }};
        --color-primary-dark: {{ $safeColor('theme_primary_dark', '#20599f') }};
        --color-primary-light: {{ $safeColor('theme_primary_light', '#eaf2fc') }};
        --color-primary-soft: {{ $safeColor('theme_primary_soft', '#f3f7fd') }};

        --radius-card: {{ $radius['card'] }};
        --radius-control: {{ $radius['control'] }};
        --radius-modal: {{ $radius['modal'] }};

        --header-bg: {{ $header['bg'] }};
        --header-fg: {{ $header['fg'] }};
        --header-chip: {{ $header['chip'] }};
        --header-line: {{ $header['line'] }};

        --nav-bg: {{ $nav['bg'] }};
        --nav-fg: {{ $nav['fg'] }};
        --nav-hover: {{ $nav['hover'] }};
        --nav-icon-display: {{ AppSetting::get('theme_nav_icons', 'all') === 'text_only' ? 'none' : 'inline-block' }};
    }

    .app-header {
        background-color: var(--header-bg);
        color: var(--header-fg);
    }

    .app-header .text-white\/75,
    .app-header .text-white\/70,
    .app-header .text-white\/80 { color: var(--header-fg); opacity: 0.72; }

    .app-header .bg-white\/15,
    .app-header .bg-white\/20 { background-color: var(--header-chip); }

    .app-header .border-white\/25,
    .app-header .border-white\/30 { border-color: var(--header-line); }

    .app-nav {
        background-color: var(--nav-bg);
        border-bottom: 1px solid var(--color-line);
    }

    .app-nav .nav-link {
        color: var(--nav-fg);
    }

    .app-nav .nav-link:hover {
        background-color: var(--nav-hover);
    }

    .app-nav .nav-link.is-active {
        color: var(--color-primary);
        border-bottom-color: var(--color-primary);
    }

    .app-nav .nav-link > i:first-child {
        display: var(--nav-icon-display, inline-block);
    }
</style>
