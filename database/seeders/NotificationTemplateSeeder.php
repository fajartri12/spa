<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\ReminderSchedule;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $wa = NotificationTemplate::query()->updateOrCreate(
            ['code' => 'BILL_REMINDER_WA'],
            [
                'name' => 'Pengingat Tagihan (WhatsApp)',
                'channel' => 'whatsapp',
                'subject' => null,
                'body' => "Yth. Wali dari {nama},\n\nKami mengingatkan bahwa tagihan *{tagihan}* sebesar *Rp {nominal}* akan jatuh tempo pada *{jatuh_tempo}*.\n\nMohon lakukan pembayaran melalui rekening resmi {sekolah}.\nTerima kasih.",
                'is_active' => true,
            ]
        );

        $mail = NotificationTemplate::query()->updateOrCreate(
            ['code' => 'BILL_REMINDER_EMAIL'],
            [
                'name' => 'Pengingat Tagihan (Email)',
                'channel' => 'email',
                'subject' => 'Pengingat Tagihan {tagihan} - {sekolah}',
                'body' => "Yth. Orang Tua / Wali dari {nama},\n\nTagihan {tagihan} sebesar Rp {nominal} akan jatuh tempo pada {jatuh_tempo}.\n\nSalam,\n{sekolah}",
                'is_active' => true,
            ]
        );

        ReminderSchedule::query()->updateOrCreate(
            ['name' => 'Pengingat H-3 WhatsApp'],
            [
                'notification_template_id' => $wa->id,
                'days_before_due' => 3,
                'send_at' => '08:00:00',
                'is_active' => true,
            ]
        );

        ReminderSchedule::query()->updateOrCreate(
            ['name' => 'Pengingat H-1 Email'],
            [
                'notification_template_id' => $mail->id,
                'days_before_due' => 1,
                'send_at' => '08:00:00',
                'is_active' => true,
            ]
        );
    }
}
