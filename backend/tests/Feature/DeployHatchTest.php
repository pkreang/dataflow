<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * คุม guard ของ deploy escape-hatch route (routes/web.php) — route นี้รัน artisan ได้
 * จึงต้องปิดสนิทเมื่อไม่มี token และปฏิเสธ token ผิด/คำสั่งนอก whitelist.
 */
class DeployHatchTest extends TestCase
{
    public function test_returns_404_when_deploy_token_not_configured(): void
    {
        config(['app.deploy_token' => null]);

        $this->get('/__deploy/anything/link')->assertNotFound();
    }

    public function test_returns_404_for_wrong_token(): void
    {
        config(['app.deploy_token' => 'correct-secret']);

        $this->get('/__deploy/wrong-secret/link')->assertNotFound();
    }

    public function test_returns_404_for_command_outside_whitelist(): void
    {
        config(['app.deploy_token' => 'correct-secret']);

        $this->get('/__deploy/correct-secret/rm-rf')->assertNotFound();
    }

    public function test_whitelisted_command_runs_with_valid_token(): void
    {
        config(['app.deploy_token' => 'correct-secret']);

        // optimize:clear ไม่มี side-effect ที่อันตรายในเทส
        $this->get('/__deploy/correct-secret/clear')->assertOk();
    }
}
