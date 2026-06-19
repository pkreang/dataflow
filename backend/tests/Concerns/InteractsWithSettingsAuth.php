<?php

namespace Tests\Concerns;

use App\Models\User;

trait InteractsWithSettingsAuth
{
    protected function makeSuperAdmin(string $email = 'super-admin@example.test'): User
    {
        return User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    protected function makeRegularUser(string $email = 'regular-user@example.test'): User
    {
        return User::create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    protected function actingAsWebSession(User $user): self
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
                'org_unit_id' => $user->org_unit_id,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
