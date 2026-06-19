<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server-side smoke check: render several authenticated pages with both
 * density preferences and assert the layout pieces (FOUC script, meta tag,
 * Alpine store ref) emit correctly. Guards against template regressions
 * after the density token migration.
 */
class DensitySmokeTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES_TO_CHECK = [
        'dashboard',
        'forms.index',
        'profile.edit',
    ];

    public function test_layout_emits_fouc_script_and_density_store_button_on_authenticated_pages(): void
    {
        $this->seedBase();
        [$user] = $this->makeUser();

        foreach (self::PAGES_TO_CHECK as $route) {
            $response = $this->actingAsWebSession($user)->get(route($route));
            $response->assertOk();
            // FOUC pre-script reads density from localStorage before Alpine boots
            $response->assertSee("localStorage.getItem('density')", false);
            // Header toggle button references the density store
            $response->assertSee('$store.density.toggle()', false);
        }
    }

    public function test_compact_user_emits_meta_tag_across_pages(): void
    {
        $this->seedBase();
        [$user] = $this->makeUser();
        $user->update(['density' => 'compact']);

        foreach (self::PAGES_TO_CHECK as $route) {
            $response = $this->actingAsWebSession($user)->get(route($route));
            $response->assertOk();
            $response->assertSee('<meta name="user-density" content="compact">', false);
        }
    }

    public function test_comfortable_user_omits_meta_tag_across_pages(): void
    {
        $this->seedBase();
        [$user] = $this->makeUser();
        // density defaults to 'comfortable' from migration

        foreach (self::PAGES_TO_CHECK as $route) {
            $response = $this->actingAsWebSession($user)->get(route($route));
            $response->assertOk();
            $response->assertDontSee('name="user-density"', false);
        }
    }

    public function test_density_tokens_are_present_in_compiled_css(): void
    {
        // Smoke check: the build artifact references the 14 density tokens.
        // If anyone removes a token from app.css :root without updating consumers,
        // this catches the orphan ref before it reaches a browser.
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $cssEntry = collect($manifest)->first(fn ($e) => str_ends_with($e['file'] ?? '', '.css'));
        $this->assertNotNull($cssEntry, 'build/manifest.json missing CSS entry');

        $css = file_get_contents(public_path('build/'.$cssEntry['file']));

        $expected = [
            '--cell-pad-x', '--cell-pad-y', '--header-pad-y',
            '--input-pad-x', '--input-pad-y',
            '--btn-pad-x', '--btn-pad-y',
            '--card-pad-x', '--card-pad-y',
            '--field-gap', '--field-label-gap',
            '--menu-item-pad-x', '--menu-item-pad-y', '--menu-sub-pad-y',
        ];
        foreach ($expected as $token) {
            $this->assertStringContainsString($token.':', $css, "Token $token missing :root declaration");
            $this->assertStringContainsString("var($token)", $css, "Token $token declared but never referenced");
        }
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeUser(): array
    {
        $position = Position::create([
            'code' => 'SMK',
            'name' => 'Smoke',
            'is_active' => true,
        ]);

        $user = User::create([
            'first_name' => 'Smoke',
            'last_name' => 'Tester',
            'email' => 'smoke-'.uniqid().'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
            'position_id' => $position->id,
        ]);

        return [$user, $position];
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
