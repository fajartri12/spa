<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\NotificationTemplate;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Notifikasi')]
class NotificationTemplateIndex extends Component
{
    /**
     * Template pesan pembayaran beserta isi bawaannya.
     *
     * @var array<string, array{name: string, subject: string, body: string}>
     */
    public const TEMPLATES = [
        'PAYMENT_PROCESSED' => [
            'name' => 'Pesan Notifikasi Proses Pembayaran',
            'subject' => 'Pembayaran tagihan periode [PERIODE_TAGIHAN]',
            'body' => "Kepada\n[NAMA_SISWA],\n\nPembayaran tagihan periode [PERIODE_TAGIHAN] sudah berhasil, berikut adalah rincian pembayaran anda :\n\nRincian Tagihan\n[RINCIAN_TAGIHAN]\n\nJumlah Tagihan\n[NOMINAL_TAGIHAN]\n\nJumlah Pembayaran\n[NOMINAL_PEMBAYARAN]\n\nMetode Pembayaran\n[METODE_PEMBAYARAN]\n\nWaktu Pembayaran\n[WAKTU_PEMBAYARAN]\n\nTerima kasih.\n\n\n\nAnda menerima pesan otomatis ini karena nomor anda terdaftar pada sistem pembayaran KOMITE MAN 7 JOMBANG.",
        ],
        'PAYMENT_VERIFIED' => [
            'name' => 'Pesan Notifikasi Pembayaran Telah Diverifikasi',
            'subject' => 'Pembayaran [NOMOR_INVOICE] telah diverifikasi',
            'body' => "Kepada\n[NAMA_SISWA],\n\nSaat ini pembayaran anda telah terverifikasi dan berikut adalah rincian pembayaran :\n\nNomor Invoice\n[NOMOR_INVOICE]\n\nRincian Tagihan\n[RINCIAN_TAGIHAN]\n\nJumlah Tagihan\n[NOMINAL_TAGIHAN]\n\nJumlah Pembayaran\n[NOMINAL_PEMBAYARAN]\n\nMetode Pembayaran\n[METODE_PEMBAYARAN]\n\nWaktu Pembayaran\n[WAKTU_PEMBAYARAN]\n\nTerima kasih.\n\n\n\nAnda menerima pesan otomatis ini karena nomor anda terdaftar pada sistem pembayaran KOMITE MAN 7 JOMBANG.",
        ],
        'BILL_TRANSACTION_CREATED' => [
            'name' => 'Pesan Notifikasi Transaksi Pembayaran Tagihan Dibuat',
            'subject' => 'Transaksi baru [NOMOR_INVOICE]',
            'body' => "Kepada\n[NAMA_SISWA],\n\nAnda memiliki transaksi baru dengan nomor [NOMOR_INVOICE] untuk pembayaran tagihan periode [PERIODE_TAGIHAN] dengan rincian sebagai berikut :\n\nRincian Tagihan\n[RINCIAN_TAGIHAN]\n\nJumlah Tagihan\n[NOMINAL_TAGIHAN]\n\n[INSTRUKSI_PEMBAYARAN]\n\nTerima kasih.\n\n\n\nAnda menerima pesan otomatis ini karena nomor anda terdaftar pada sistem pembayaran KOMITE MAN 7 JOMBANG.",
        ],
        'OTHER_TRANSACTION_CREATED' => [
            'name' => 'Pesan Notifikasi Transaksi Pembayaran Lainnya Dibuat',
            'subject' => 'Transaksi baru [NOMOR_INVOICE]',
            'body' => "Kepada\n[NAMA_SISWA],\n\nAnda memiliki transaksi baru dengan nomor [NOMOR_INVOICE] dengan rincian sebagai berikut :\n\nJumlah Tagihan\n[NOMINAL_TAGIHAN]\n\nDeskripsi Pembayaran\n[RINCIAN_PEMBAYARAN]\n\n[INSTRUKSI_PEMBAYARAN]\n\nTerima kasih.\n\n\n\nAnda menerima pesan otomatis ini karena nomor anda terdaftar pada sistem pembayaran KOMITE MAN 7 JOMBANG.",
        ],
    ];

    /**
     * Variabel yang bisa disisipkan ke dalam pesan.
     *
     * @var array<string, string>
     */
    public const VARIABLES = [
        'NAMA_SISWA' => 'Nama siswa',
        'NOMOR_INVOICE' => 'Nomor invoice',
        'PERIODE_TAGIHAN' => 'Periode tagihan',
        'RINCIAN_TAGIHAN' => 'Rincian tagihan',
        'NOMINAL_TAGIHAN' => 'Jumlah tagihan',
        'NOMINAL_PEMBAYARAN' => 'Jumlah pembayaran',
        'METODE_PEMBAYARAN' => 'Metode pembayaran',
        'WAKTU_PEMBAYARAN' => 'Waktu pembayaran',
        'RINCIAN_PEMBAYARAN' => 'Deskripsi pembayaran',
        'INSTRUKSI_PEMBAYARAN' => 'Instruksi pembayaran',
    ];

    public string $tab = 'PAYMENT_PROCESSED';

    /** @var array<string, array{name: string, channel: string, subject: string, body: string, is_active: bool}> */
    public array $forms = [];

    public function mount(): void
    {
        $this->authorize('settings.view');

        $existing = NotificationTemplate::query()
            ->whereIn('code', array_keys(self::TEMPLATES))
            ->get()
            ->keyBy('code');

        foreach (self::TEMPLATES as $code => $default) {
            $template = $existing->get($code);

            if ($template === null) {
                $template = NotificationTemplate::query()->create([
                    'code' => $code,
                    'name' => $default['name'],
                    'channel' => 'whatsapp',
                    'subject' => $default['subject'],
                    'body' => $default['body'],
                    'is_active' => true,
                ]);
            }

            $this->forms[$code] = [
                'name' => (string) $template->name,
                'channel' => (string) $template->channel,
                'subject' => (string) $template->subject,
                'body' => (string) $template->body,
                'is_active' => (bool) $template->is_active,
            ];
        }
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        $data = $this->validate([
            'forms.*.name' => ['required', 'string', 'max:120'],
            'forms.*.channel' => ['required', 'in:whatsapp,email'],
            'forms.*.subject' => ['nullable', 'string', 'max:160'],
            'forms.*.body' => ['required', 'string', 'max:4000'],
            'forms.*.is_active' => ['required', 'boolean'],
        ], [
            'forms.*.name.required' => 'Nama template wajib diisi.',
            'forms.*.body.required' => 'Isi pesan notifikasi wajib diisi.',
        ]);

        foreach ($data['forms'] as $code => $form) {
            NotificationTemplate::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $form['name'],
                    'channel' => $form['channel'],
                    'subject' => $form['subject'] ?: null,
                    'body' => $form['body'],
                    'is_active' => $form['is_active'],
                ],
            );
        }

        $logger->log('settings.updated', 'Memperbarui template notifikasi pembayaran');
        $this->dispatch('toast', type: 'success', message: 'Template notifikasi berhasil disimpan.');
    }

    public function insertVariable(string $variable): void
    {
        $this->authorize('settings.update');

        if (! array_key_exists($variable, self::VARIABLES)) {
            return;
        }

        $this->forms[$this->tab]['body'] .= '['.$variable.']';
    }

    public function resetTemplate(): void
    {
        $this->authorize('settings.update');

        $default = self::TEMPLATES[$this->tab] ?? null;

        if ($default === null) {
            return;
        }

        $this->forms[$this->tab]['subject'] = $default['subject'];
        $this->forms[$this->tab]['body'] = $default['body'];
    }

    public function render(): View
    {
        $this->authorize('settings.view');

        $active = $this->forms[$this->tab];

        $preview = $active['body'];
        foreach (self::sampleData() as $key => $value) {
            $preview = str_replace('['.strtoupper($key).']', $value, $preview);
        }

        return view('livewire.settings.notification-template-index', [
            'definitions' => self::TEMPLATES,
            'variables' => self::VARIABLES,
            'active' => $active,
            'preview' => $preview,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function sampleData(): array
    {
        return [
            'nama_siswa' => 'Ahmad Fauzi',
            'nomor_invoice' => 'TRX-20261107-0001',
            'periode_tagihan' => 'November 2026',
            'rincian_tagihan' => '1. SPP November 2026 — Rp 350.000',
            'nominal_tagihan' => 'Rp 350.000',
            'nominal_pembayaran' => 'Rp 350.000',
            'metode_pembayaran' => 'Tunai',
            'waktu_pembayaran' => '07/11/2026 10:15',
            'rincian_pembayaran' => 'Pembayaran seragam sekolah',
            'instruksi_pembayaran' => 'Silakan lakukan pembayaran melalui rekening resmi madrasah.',
        ];
    }
}
