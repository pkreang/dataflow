<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\LoginHistory;
use App\Models\SubmissionActivityLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get(route('profile.activity'))->assertRedirect(route('login'));
    }

    public function test_page_returns_only_current_user_rows(): void
    {
        [$me, $other] = $this->makeTwoUsers();

        // Submission rows — one for me, one for other
        $form = DocumentForm::create([
            'form_key' => 'pa_form', 'name' => 'PA', 'document_type' => 'generic', 'is_active' => true,
        ]);
        $mySub = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $me->id, 'payload' => [], 'status' => 'draft',
        ]);
        $otherSub = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $other->id, 'payload' => [], 'status' => 'draft',
        ]);
        SubmissionActivityLog::record($mySub->id, $me->id, 'created');
        SubmissionActivityLog::record($otherSub->id, $other->id, 'created');

        // Login rows
        LoginHistory::create([
            'user_id' => $me->id, 'email' => $me->email, 'auth_provider' => 'local',
            'ip_address' => '127.0.0.1', 'result' => 'success', 'created_at' => now(),
        ]);
        LoginHistory::create([
            'user_id' => $other->id, 'email' => $other->email, 'auth_provider' => 'local',
            'ip_address' => '10.0.0.1', 'result' => 'success', 'created_at' => now(),
        ]);

        $response = $this->actingAsWebSession($me)->get(route('profile.activity'));
        $response->assertOk();

        // My data shows
        $response->assertSee($mySub->reference_no ?? '#'.$mySub->id);
        $response->assertSee('127.0.0.1');

        // Other user's data does NOT show
        $response->assertDontSee('#'.$otherSub->id);
        $response->assertDontSee('10.0.0.1');
    }

    public function test_submission_filter_hides_login_rows(): void
    {
        [$me] = $this->makeTwoUsers();
        LoginHistory::create([
            'user_id' => $me->id, 'email' => $me->email, 'auth_provider' => 'local',
            'ip_address' => '192.168.0.99', 'result' => 'success', 'created_at' => now(),
        ]);

        $response = $this->actingAsWebSession($me)
            ->get(route('profile.activity', ['kind' => 'submission']));
        $response->assertOk();
        $response->assertDontSee('192.168.0.99');
    }

    public function test_login_filter_hides_submission_rows(): void
    {
        [$me] = $this->makeTwoUsers();
        $form = DocumentForm::create([
            'form_key' => 'pa_form2', 'name' => 'PA2', 'document_type' => 'generic', 'is_active' => true,
        ]);
        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $me->id, 'payload' => [],
            'status' => 'submitted', 'reference_no' => 'PA-FILTER-1',
        ]);
        SubmissionActivityLog::record($sub->id, $me->id, 'submitted', ['reference_no' => 'PA-FILTER-1']);

        $response = $this->actingAsWebSession($me)
            ->get(route('profile.activity', ['kind' => 'login']));
        $response->assertOk();
        $response->assertDontSee('PA-FILTER-1');
    }

    /**
     * @return array{0:User, 1:User}
     */
    private function makeTwoUsers(): array
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $me = User::create([
            'first_name' => 'Me', 'last_name' => 'A',
            'email' => 'pa_me_'.uniqid().'@example.test',
            'password' => 'password', 'is_active' => true, 'is_super_admin' => false,
        ]);
        $other = User::create([
            'first_name' => 'Other', 'last_name' => 'B',
            'email' => 'pa_other_'.uniqid().'@example.test',
            'password' => 'password', 'is_active' => true, 'is_super_admin' => false,
        ]);
        return [$me, $other];
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-pa')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'is_super_admin' => false,
                'can_change_password' => true,
                'roles' => [],
            ],
            'user_permissions' => [],
        ]);
    }
}
