<?php

namespace App\Services\Auth;

use App\Models\User;

class PasswordCapabilityService
{
    /**
     * Whether the user may change the app-stored password (local account).
     * Directory users (LDAP / Entra) authenticate against the IdP; changing only
     * the local hash would not update AD and is therefore disabled in the UI.
     */
    public static function canChangePasswordFromAuthProvider(?string $authProvider): bool
    {
        if ($authProvider === null || $authProvider === '') {
            return true;
        }

        return $authProvider === 'local';
    }

    public static function canChangePasswordInApp(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return self::canChangePasswordFromAuthProvider($user->auth_provider);
    }

    /**
     * Email is the login credential and is immutable after user creation.
     * Always returns false — neither admin nor self may edit email via the app.
     * If a wrong email needs to be corrected, delete the user and recreate
     * (or override via `php artisan tinker`).
     */
    public static function canEditEmailInApp(?User $user): bool
    {
        return false;
    }
}
