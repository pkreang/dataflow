<?php

namespace App\Rules;

use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $min = Setting::getInt('password_min_length', 8);
        $max = Setting::getInt('password_max_length', 255);

        if (mb_strlen($value) < $min) {
            $fail("Password must be at least {$min} characters.");

            return;
        }

        if (mb_strlen($value) > $max) {
            $fail("Password must not exceed {$max} characters.");

            return;
        }

        if (Setting::getBool('password_require_uppercase') && ! preg_match('/[A-Z]/', $value)) {
            $fail('Password must contain at least one uppercase letter (A-Z).');
        }

        if (Setting::getBool('password_require_lowercase') && ! preg_match('/[a-z]/', $value)) {
            $fail('Password must contain at least one lowercase letter (a-z).');
        }

        if (Setting::getBool('password_require_number') && ! preg_match('/[0-9]/', $value)) {
            $fail('Password must contain at least one number (0-9).');
        }

        if (Setting::getBool('password_require_special') && ! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Password must contain at least one special character (!@#$%...).');
        }
    }
}
