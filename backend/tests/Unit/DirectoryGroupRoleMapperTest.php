<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Auth\DirectoryGroupRoleMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectoryGroupRoleMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_empty_when_no_hints(): void
    {
        Role::create(['name' => 'approver', 'guard_name' => 'web']);
        Setting::set('auth_directory_group_role_map', json_encode([
            ['pattern' => 'CN=App', 'role' => 'approver'],
        ]));

        $this->assertSame([], DirectoryGroupRoleMapper::resolveRolesFromHints([]));
    }

    public function test_matches_substring_case_insensitive(): void
    {
        Role::create(['name' => 'approver', 'guard_name' => 'web']);
        Role::create(['name' => 'employee', 'guard_name' => 'web']);
        Setting::set('auth_directory_group_role_map', json_encode([
            ['pattern' => 'cn=app-approvers', 'role' => 'approver'],
            ['pattern' => 'other', 'role' => 'employee'],
        ]));

        $hints = ['CN=App-Approvers,OU=Groups,DC=corp,DC=local'];
        $roles = DirectoryGroupRoleMapper::resolveRolesFromHints($hints);

        $this->assertSame(['approver'], $roles);
    }

    public function test_filters_nonexistent_roles(): void
    {
        Role::create(['name' => 'employee', 'guard_name' => 'web']);
        Setting::set('auth_directory_group_role_map', json_encode([
            ['pattern' => 'grp', 'role' => 'ghost-role'],
        ]));

        $roles = DirectoryGroupRoleMapper::resolveRolesFromHints(['CN=grp-x']);

        $this->assertSame([], $roles);
    }

    public function test_entra_guid_pattern(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $guid = 'AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE';
        Setting::set('auth_directory_group_role_map', json_encode([
            ['pattern' => strtolower($guid), 'role' => 'admin'],
        ]));

        $roles = DirectoryGroupRoleMapper::resolveRolesFromHints([
            'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);

        $this->assertSame(['admin'], $roles);
    }
}
