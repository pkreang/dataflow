<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineLinkFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, SettingSeeder::class]);
        Setting::set('line_login.channel_id', '1234567890');
        Setting::set('line_login.channel_secret', 'secret-abc');
    }

    public function test_line_link_redirect_builds_authorize_url_with_state_in_session(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAsWebSession($user)->get(route('auth.line.redirect'));

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringStartsWith('https://access.line.me/oauth2/v2.1/authorize?', $target);
        $this->assertStringContainsString('client_id=1234567890', $target);
        $this->assertStringContainsString('response_type=code', $target);
        $this->assertStringContainsString('scope=profile+openid', $target);
        $this->assertNotEmpty(session('oauth_line_login_state'));
    }

    public function test_line_link_callback_stores_line_user_id_on_authenticated_user(): void
    {
        $user = $this->makeUser();
        $session = $this->actingAsWebSession($user);

        // Prime the session with a state by hitting redirect first
        $session->get(route('auth.line.redirect'));
        $state = session('oauth_line_login_state');

        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['access_token' => 'line-access-token'], 200),
            'api.line.me/v2/profile' => Http::response([
                'userId' => 'Ulinked1234567890',
                'displayName' => 'Linked LINE User',
            ], 200),
        ]);

        $session->get(route('auth.line.callback', ['code' => 'oauth-code', 'state' => $state]))
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Ulinked1234567890', $user->fresh()->line_user_id);
    }

    public function test_line_link_callback_rejects_mismatched_state(): void
    {
        $user = $this->makeUser();
        $session = $this->actingAsWebSession($user);

        $session->get(route('auth.line.redirect'));
        Http::fake();

        $session->get(route('auth.line.callback', ['code' => 'oauth-code', 'state' => 'wrong-state']))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertNull($user->fresh()->line_user_id);
        Http::assertNothingSent();
    }

    public function test_line_unlink_clears_line_user_id(): void
    {
        $user = $this->makeUser(['line_user_id' => 'Ulinked1234567890']);

        $this->actingAsWebSession($user)
            ->post(route('auth.line.unlink'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertNull($user->fresh()->line_user_id);
    }

    private function makeUser(array $attrs = []): User
    {
        $position = \App\Models\Position::create(['code' => 'TST', 'name' => 'Tester', 'is_active' => true]);

        return User::create(array_merge([
            'first_name' => 'Linker',
            'last_name' => 'User',
            'email' => 'linker-'.uniqid().'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
            'position_id' => $position->id,
        ], $attrs));
    }

    private function actingAsWebSession(User $user): \Illuminate\Testing\TestResponse|\Tests\TestCase
    {
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'department_id' => $user->department_id,
                'can_change_password' => true,
                'roles' => [],
            ],
            'user_permissions' => [],
        ]);
    }
}
