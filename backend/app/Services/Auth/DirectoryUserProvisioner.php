<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DirectoryUserProvisioner
{
    /**
     * @param  list<string>  $directoryGroupHints  LDAP memberOf DNs, Entra group id/name, etc. Used for role mapping when non-empty.
     */
    public function findOrCreate(
        string $provider,
        string $externalId,
        string $email,
        ?string $firstName,
        ?string $lastName,
        ?string $ldapDn = null,
        array $directoryGroupHints = []
    ): ?User {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $user = User::query()
            ->where('auth_provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            if (! $user->is_active) {
                return null;
            }
            $user->forceFill([
                'auth_provider' => $provider,
                'external_id' => $externalId,
                'ldap_dn' => $ldapDn ?? $user->ldap_dn,
            ])->save();

            return $user;
        }

        $companyId = Company::query()->where('is_active', true)->orderBy('id')->value('id');
        if (! $companyId) {
            return null;
        }

        $roleName = (string) Setting::get('auth_default_role', 'employee');
        if ($roleName === '') {
            $roleName = 'employee';
        }

        $local = explode('@', $email)[0];
        $user = User::create([
            'email' => $email,
            'first_name' => $firstName !== null && $firstName !== '' ? $firstName : $local,
            'last_name' => $lastName ?? '',
            'password' => Str::password(32),
            'password_changed_at' => now(),
            'password_must_change' => false,
            'company_id' => $companyId,
            'is_active' => true,
            'is_super_admin' => false,
            'auth_provider' => $provider,
            'external_id' => $externalId,
            'ldap_dn' => $ldapDn,
        ]);

        if (Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
            $user->assignRole($roleName);
        } else {
            $user->assignRole('employee');
        }

        $this->syncRolesFromDirectoryHints($user, $directoryGroupHints);

        return $user;
    }

    /**
     * When hints are non-empty and at least one rule matches, replace Spatie roles with the mapped set.
     * If hints are empty or nothing matches, existing roles are unchanged.
     *
     * @param  list<string>  $directoryGroupHints
     */
    private function syncRolesFromDirectoryHints(User $user, array $directoryGroupHints): void
    {
        $hints = array_values(array_filter(
            $directoryGroupHints,
            static fn ($h) => is_string($h) && trim($h) !== ''
        ));
        if ($hints === []) {
            return;
        }

        $roles = DirectoryGroupRoleMapper::resolveRolesFromHints($hints);
        if ($roles === []) {
            return;
        }

        $user->syncRoles($roles);
    }
}
