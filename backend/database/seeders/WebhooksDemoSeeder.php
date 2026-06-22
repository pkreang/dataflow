<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Webhook;
use Illuminate\Database\Seeder;

class WebhooksDemoSeeder extends Seeder
{
    /**
     * Optional demo: 3 sample outbound webhooks for /settings/integrations.
     * Run explicitly: php artisan db:seed --class=WebhooksDemoSeeder
     */
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@example.com')->value('id');

        Webhook::updateOrCreate(
            ['name' => 'เชื่อมต่อ ERP กลาง'],
            [
                'url' => 'https://erp.example.co.th/api/inbound/dataflow',
                'secret' => Webhook::generateSecret(),
                'events' => ['form.submitted', 'approval.completed'],
                'field_allowlists' => [
                    'repair_request_default' => ['title', 'location', 'detail'],
                ],
                'is_active' => true,
                'created_by' => $adminId,
            ]
        );

        Webhook::updateOrCreate(
            ['name' => 'แจ้งเตือน Slack — แจ้งซ่อมใหม่'],
            [
                'url' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
                'secret' => Webhook::generateSecret(),
                'events' => ['repair.created'],
                'field_allowlists' => null,
                'is_active' => true,
                'created_by' => $adminId,
            ]
        );

        Webhook::updateOrCreate(
            ['name' => 'เชื่อมต่อระบบ HR (ปิดใช้งาน)'],
            [
                'url' => 'https://hr.internal.example.co.th/api/events',
                'secret' => Webhook::generateSecret(),
                'events' => ['approval.completed', 'approval.rejected'],
                'field_allowlists' => null,
                'is_active' => false,
                'created_by' => $adminId,
            ]
        );
    }
}
