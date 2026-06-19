<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\User;
use Database\Seeders\FactoryCmmsTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairRequestWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_repair_requests_index(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, FactoryCmmsTemplateSeeder::class]);

        $response = $this->get(route('repair-requests.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_repair_request_index(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, FactoryCmmsTemplateSeeder::class]);

        $user = User::query()->where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);

        $response = $this->actingAsWebSession($user)->get(route('repair-requests.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_submit_repair_request_and_see_detail(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, FactoryCmmsTemplateSeeder::class]);

        $user = User::query()->where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);

        $response = $this->actingAsWebSession($user)->post(route('repair-requests.submit'), [
            'form_key' => 'repair_request_default',
            'form_payload' => [
                'title' => 'UAT — pump noise',
                'detail' => 'Noise from motor M1',
            ],
        ]);

        $instance = ApprovalInstance::query()
            ->where('document_type', 'repair_request')
            ->where('requester_user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($instance);
        $response->assertRedirect(route('repair-requests.show', $instance));
        $this->assertSame('pending', $instance->status);
    }

    /**
     * Mimic web login session shape used by AuthenticateWeb (see AuthController::establishSessionFromUserArray).
     */
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
                'avatar' => $user->avatar,
                'auth_provider' => $user->auth_provider,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
