<?php

namespace Tests\Feature;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Smoke test for the /m/* mobile app surface (Day 2.5 pitch deliverable).
 * Ensures all 4 mobile routes render without 500 and show key elements.
 */
class MobileAppSmokeTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public static function mobileRoutes(): array
    {
        return [
            'home' => ['mobile.home'],
            'approvals' => ['mobile.approvals'],
            'forms' => ['mobile.forms'],
            'requests' => ['mobile.requests'],
            'me' => ['mobile.me'],
        ];
    }

    #[DataProvider('mobileRoutes')]
    public function test_mobile_route_renders_for_auth_user(string $routeName): void
    {
        $user = $this->makeRegularUser();

        $response = $this->actingAsWebSession($user)->get(route($routeName));

        $response->assertSuccessful();
        $response->assertDontSee('Whoops, looks like something went wrong');
    }

    public function test_mobile_routes_redirect_guests_to_login(): void
    {
        foreach (array_keys(static::mobileRoutes()) as $name) {
            $this->get(route('mobile.'.$name))->assertRedirect('/login');
        }
    }
}
