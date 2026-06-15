<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Database\Seeders\EvaluationFormSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class EvaluationReportTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            EvaluationFormSeeder::class,
        ]);
    }

    public function test_regular_user_without_permission_cannot_view_evaluation_report(): void
    {
        $user = $this->makeRegularUser();

        $this->actingAsWebSession($user)
            ->get(route('reports.evaluations'))
            ->assertForbidden();
    }

    public function test_user_with_manage_settings_can_view_evaluation_report(): void
    {
        $user = $this->makeRegularUser();
        $user->givePermissionTo('manage_settings');

        $this->actingAsWebSession($user)
            ->get(route('reports.evaluations'))
            ->assertOk();
    }

    public function test_super_admin_can_view_evaluation_report(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->get(route('reports.evaluations'))
            ->assertOk();
    }

    public function test_report_renders_empty_state_with_zero_metrics(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAsWebSession($admin)->get(route('reports.evaluations'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('totalCount'));
        $this->assertEquals(0, $response->viewData('overallAvg'));
        $this->assertEquals(0, $response->viewData('responseRate'));
        $this->assertSame(0, $response->viewData('eligibleParents'));
    }

    public function test_report_aggregates_ratings_response_rate_and_distribution(): void
    {
        $admin = $this->makeSuperAdmin();
        $owner = $this->makeRegularUser();

        // Three approved, evaluation-enabled parents; only two get evaluated.
        $parentA = $this->makeEvaluableApprovedParent($owner);
        $parentB = $this->makeEvaluableApprovedParent($owner);
        $this->makeEvaluableApprovedParent($owner);

        $this->attachEvaluation($parentA, '5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม');
        $this->attachEvaluation($parentB, '3 — ⭐⭐⭐ พอใจ');

        $response = $this->actingAsWebSession($admin)->get(route('reports.evaluations'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('totalCount'));
        $this->assertEquals(4, $response->viewData('overallAvg'));        // (5 + 3) / 2
        $this->assertSame(3, $response->viewData('eligibleParents'));
        $this->assertEquals(66.7, $response->viewData('responseRate'));   // 2 of 3

        $distribution = $response->viewData('distribution');
        $this->assertSame(1, $distribution[5]);
        $this->assertSame(1, $distribution[3]);
        $this->assertSame(0, $distribution[4]);
    }

    private function makeEvaluableApprovedParent(User $owner): DocumentFormSubmission
    {
        $form = DocumentForm::firstOrCreate(
            ['form_key' => 'parent_for_report'],
            [
                'name' => 'Parent Form For Report',
                'document_type' => 'test',
                'is_active' => true,
                'evaluation_enabled' => true,
                'layout_columns' => 1,
            ]
        );

        $workflow = ApprovalWorkflow::create([
            'name' => 'Report Workflow',
            'document_type' => 'test',
            'is_active' => true,
        ]);

        $instance = ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $owner->id,
            'document_type' => 'test',
            'reference_no' => 'REP-'.uniqid(),
            'payload' => [],
            'current_step_no' => 1,
            'status' => 'approved',
        ]);

        return DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'approval_instance_id' => $instance->id,
            'payload' => [],
            'status' => 'submitted',
        ]);
    }

    private function attachEvaluation(DocumentFormSubmission $parent, string $rating): void
    {
        $evalForm = DocumentForm::where('form_key', 'evaluation_default')->firstOrFail();

        DocumentFormSubmission::create([
            'form_id' => $evalForm->id,
            'user_id' => $parent->user_id,
            'parent_submission_id' => $parent->id,
            'payload' => ['overall_rating' => $rating],
            'status' => 'submitted',
        ]);
    }
}
