<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\Auth\LoginHistoryRecorder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_local_login_records_success_row(): void
    {
        $this->seedBase();
        $user = $this->makeUser(['password' => bcrypt('Secret123!')]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Secret123!',
        ]);

        $row = LoginHistory::where('user_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('success', $row->result);
        $this->assertSame('local', $row->auth_provider);
        $this->assertSame($user->email, $row->email);
    }

    public function test_failed_local_login_records_failure_with_reason(): void
    {
        $this->seedBase();
        $user = $this->makeUser(['password' => bcrypt('Secret123!')]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $row = LoginHistory::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('failed', $row->result);
        $this->assertNotNull($row->failure_reason);
    }

    public function test_failed_login_without_existing_user_records_with_null_user_id(): void
    {
        $this->seedBase();

        $this->post(route('login'), [
            'email' => 'nonexistent@example.test',
            'password' => 'whatever',
        ]);

        $row = LoginHistory::first();
        $this->assertNotNull($row);
        $this->assertNull($row->user_id);
        $this->assertSame('nonexistent@example.test', $row->email);
        $this->assertSame('failed', $row->result);
    }

    public function test_profile_edit_shows_last_prior_login_and_failure_alert(): void
    {
        $this->seedBase();
        $user = $this->makeUser();

        // Two prior successful logins + 2 recent failures.
        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'result' => 'success', 'auth_provider' => 'local', 'ip_address' => '10.0.0.5', 'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/131.0', 'created_at' => now()->subDays(2)]);
        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'result' => 'success', 'auth_provider' => 'local', 'ip_address' => '10.0.0.7', 'user_agent' => 'Mozilla/5.0 (iPhone) Version/17 Safari', 'created_at' => now()->subHour()]);
        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'result' => 'failed', 'failure_reason' => 'invalid_credentials', 'created_at' => now()->subHours(3)]);
        LoginHistory::create(['user_id' => $user->id, 'email' => $user->email, 'result' => 'failed', 'failure_reason' => 'invalid_credentials', 'created_at' => now()->subHours(2)]);

        $response = $this->actingAsWebSession($user)->get(route('profile.edit'));

        $response->assertOk();
        // The "last_prior" is the 2nd most recent success (diffForHumans renders "2 days ago")
        $response->assertSee('10.0.0.5');
        // Failure alert mentions count=2
        $response->assertSee(__('common.login_failed_alert', ['count' => 2]));
    }

    public function test_login_history_page_lists_entries_for_current_user_only(): void
    {
        $this->seedBase();
        $user = $this->makeUser();
        $other = $this->makeUser();

        LoginHistory::create(['user_id' => $user->id, 'result' => 'success', 'email' => $user->email, 'ip_address' => '10.0.0.1', 'user_agent' => 'UA-MINE', 'created_at' => now()]);
        LoginHistory::create(['user_id' => $other->id, 'result' => 'success', 'email' => $other->email, 'ip_address' => '10.0.0.2', 'user_agent' => 'UA-OTHER', 'created_at' => now()]);

        $response = $this->actingAsWebSession($user)->get(route('profile.login-history'));
        $response->assertOk();
        $response->assertSee('10.0.0.1');
        $response->assertDontSee('10.0.0.2');
    }

    public function test_user_agent_summarizer_parses_common_combinations(): void
    {
        $this->assertSame(
            'Chrome 131 · macOS',
            LoginHistoryRecorder::summarizeUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36')
        );
        $this->assertSame(
            'Safari 17 · iOS',
            LoginHistoryRecorder::summarizeUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Version/17.0 Mobile/15E148 Safari/604.1')
        );
        $this->assertSame('—', LoginHistoryRecorder::summarizeUserAgent(null));
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeUser(array $overrides = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'first_name' => 'Login',
            'last_name' => "User{$counter}",
            'email' => "login{$counter}@example.test",
            'password' => bcrypt('Secret123!'),
            'is_active' => true,
            'is_super_admin' => false,
        ], $overrides));
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
