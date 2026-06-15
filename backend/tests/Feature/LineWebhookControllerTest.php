<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LineWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/line/webhook';
    private const SECRET   = 'test-channel-secret-xyz';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, SettingSeeder::class]);
        Setting::set('line_messaging.channel_secret', self::SECRET);
        Setting::set('line_messaging.channel_access_token', 'fake-token');
        Http::fake(['api.line.me/*' => Http::response('', 200)]);
        Notification::fake();
    }

    // ---------- signature verification ----------

    public function test_rejects_invalid_signature(): void
    {
        $data = ['events' => []];
        $this->withHeaders(['X-Line-Signature' => 'invalid-sig'])
            ->postJson(self::ENDPOINT, $data)
            ->assertStatus(401);
    }

    public function test_accepts_valid_signature_with_empty_events(): void
    {
        $this->postSigned(['events' => []])
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    // ---------- event type filtering ----------

    public function test_ignores_non_postback_events(): void
    {
        $data = ['events' => [
            ['type' => 'message', 'source' => ['userId' => 'Uxxx'], 'message' => ['text' => 'hi']],
        ]];
        $this->postSigned($data)->assertStatus(200)->assertJson(['status' => 'ok']);
    }

    // ---------- approve postback ----------

    public function test_approve_postback_calls_act_and_returns_ok(): void
    {
        [$instance, $step, $approver] = $this->makePendingInstance();

        $this->postSigned($this->buildPostbackData($approver->line_user_id, 'approve', $instance->id, $step->step_no))
            ->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('approval_instances', [
            'id' => $instance->id,
            'status' => 'approved',
        ]);
    }

    // ---------- reject postback: now prompts for reason ----------

    public function test_reject_postback_stores_pending_state_and_prompts(): void
    {
        [$instance, $step, $approver] = $this->makePendingInstance();
        $lineUserId = $approver->line_user_id;

        $this->postSigned($this->buildPostbackData($lineUserId, 'reject', $instance->id, $step->step_no))
            ->assertStatus(200)->assertJson(['status' => 'ok']);

        // Instance should still be pending (waiting for reason)
        $this->assertDatabaseHas('approval_instances', ['id' => $instance->id, 'status' => 'pending']);

        // Cache should hold the pending state
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get("line_reject_pending:{$lineUserId}"));
    }

    public function test_message_reply_completes_rejection_with_comment(): void
    {
        [$instance, $step, $approver] = $this->makePendingInstance();
        $lineUserId = $approver->line_user_id;

        // First: reject postback sets pending state
        $this->postSigned($this->buildPostbackData($lineUserId, 'reject', $instance->id, $step->step_no))
            ->assertStatus(200);

        // Then: message reply with reason completes the rejection
        $msgData = ['events' => [[
            'type'    => 'message',
            'source'  => ['userId' => $lineUserId],
            'message' => ['type' => 'text', 'text' => 'ข้อมูลไม่ครบถ้วน'],
        ]]];

        $this->postSigned($msgData)->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('approval_instances', ['id' => $instance->id, 'status' => 'rejected']);
        $this->assertNull(\Illuminate\Support\Facades\Cache::get("line_reject_pending:{$lineUserId}"));
    }

    public function test_message_without_pending_state_is_ignored(): void
    {
        [$instance, $step, $approver] = $this->makePendingInstance();

        $msgData = ['events' => [[
            'type'    => 'message',
            'source'  => ['userId' => $approver->line_user_id],
            'message' => ['type' => 'text', 'text' => 'สวัสดี'],
        ]]];

        $this->postSigned($msgData)->assertStatus(200)->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('approval_instances', ['id' => $instance->id, 'status' => 'pending']);
    }

    // ---------- guard cases ----------

    public function test_unknown_line_user_id_is_ignored_gracefully(): void
    {
        [$instance, $step] = $this->makePendingInstance();

        $this->postSigned($this->buildPostbackData('Uunknown999', 'approve', $instance->id, $step->step_no))
            ->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('approval_instances', ['id' => $instance->id, 'status' => 'pending']);
    }

    public function test_already_acted_step_is_skipped(): void
    {
        [$instance, $step, $approver] = $this->makePendingInstance();
        $step->update(['action' => 'approved']);

        $this->postSigned($this->buildPostbackData($approver->line_user_id, 'approve', $instance->id, $step->step_no))
            ->assertStatus(200)->assertJson(['status' => 'ok']);
    }

    public function test_non_approver_cannot_act_via_webhook(): void
    {
        [$instance, $step] = $this->makePendingInstance();

        $stranger = User::create([
            'first_name' => 'Stranger',
            'last_name' => 'User',
            'email' => 'stranger@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
            'line_user_id' => 'Ustranger999',
        ]);

        $this->postSigned($this->buildPostbackData($stranger->line_user_id, 'approve', $instance->id, $step->step_no))
            ->assertStatus(200)->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('approval_instances', ['id' => $instance->id, 'status' => 'pending']);
    }

    // ---------- helpers ----------

    private function makeSignature(array $data): string
    {
        return base64_encode(hash_hmac('sha256', json_encode($data), self::SECRET, true));
    }

    private function postSigned(array $data): \Illuminate\Testing\TestResponse
    {
        $sig = $this->makeSignature($data);
        return $this->withHeaders(['X-Line-Signature' => $sig])
            ->postJson(self::ENDPOINT, $data);
    }

    private function buildPostbackData(string $lineUserId, string $action, int $instanceId, int $stepNo): array
    {
        return ['events' => [[
            'type'     => 'postback',
            'source'   => ['userId' => $lineUserId],
            'postback' => ['data' => "a={$action}&i={$instanceId}&s={$stepNo}"],
        ]]];
    }

    /** @return array{0: ApprovalInstance, 1: ApprovalInstanceStep, 2: User} */
    private function makePendingInstance(): array
    {
        static $seq = 0;
        $seq++;

        $approver = User::create([
            'first_name' => 'Approver',
            'last_name' => "LW{$seq}",
            'email' => "appr-lw{$seq}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
            'line_user_id' => "Uapprover{$seq}",
        ]);
        $approver->givePermissionTo('approval.approve');

        $requester = User::create([
            'first_name' => 'Requester',
            'last_name' => "LW{$seq}",
            'email' => "req-lw{$seq}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => "LW WF {$seq}",
            'document_type' => 'lw_test',
            'description' => null,
            'is_active' => true,
            'allow_requester_as_approver' => false,
        ]);

        $instance = ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'department_id' => null,
            'requester_user_id' => $requester->id,
            'document_type' => 'lw_test',
            'reference_no' => "LW-{$seq}",
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'pending',
        ]);

        $step = ApprovalInstanceStep::create([
            'approval_instance_id' => $instance->id,
            'step_no' => 1,
            'stage_name' => 'Approve',
            'approver_type' => 'user',
            'approver_ref' => (string) $approver->id,
            'approver_rules' => null,
            'min_approvals' => 1,
            'approved_by' => [],
            'action' => 'pending',
        ]);

        return [$instance, $step, $approver];
    }
}
