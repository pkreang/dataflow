<?php

namespace Database\Seeders;

use App\Models\DocumentForm;
use App\Models\IncomingWebhook;
use App\Models\User;
use Illuminate\Database\Seeder;

class IncomingWebhooksDemoSeeder extends Seeder
{
    /**
     * Optional demo: 2 sample inbound webhooks.
     * Run explicitly: php artisan db:seed --class=IncomingWebhooksDemoSeeder
     */
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@example.com')->value('id');
        $repairFormId = DocumentForm::query()->where('form_key', 'repair_request_default')->value('id');
        $pmFormId = DocumentForm::query()->where('form_key', 'pm_am_plan_default')->value('id');

        if ($repairFormId) {
            IncomingWebhook::updateOrCreate(
                ['name' => 'รับแจ้งซ่อมจาก IoT — เซ็นเซอร์โรงงาน'],
                [
                    'slug' => 'iot-sensor-repair',
                    'token' => IncomingWebhook::generateToken(),
                    'document_form_id' => $repairFormId,
                    'is_active' => true,
                    'created_by' => $adminId,
                ]
            );
        }

        if ($pmFormId) {
            IncomingWebhook::updateOrCreate(
                ['name' => 'รับแผน PM จาก SAP'],
                [
                    'slug' => 'sap-pm-plan',
                    'token' => IncomingWebhook::generateToken(),
                    'document_form_id' => $pmFormId,
                    'is_active' => true,
                    'created_by' => $adminId,
                ]
            );
        }
    }
}
