<?php

namespace Tests\Feature;

use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class HolidayCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_non_super_admin_is_blocked(): void
    {
        $user = $this->makeRegularUser();

        $this->actingAsWebSession($user)
            ->get(route('settings.holidays.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_list_create_toggle_delete(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->get(route('settings.holidays.index'))
            ->assertOk();

        $this->actingAsWebSession($admin)
            ->post(route('settings.holidays.store'), [
                'date' => '2026-12-31',
                'name' => 'วันสิ้นปี',
            ])
            ->assertRedirect();

        $holiday = Holiday::query()->where('date', '2026-12-31')->firstOrFail();
        $this->assertTrue($holiday->is_active);

        $this->actingAsWebSession($admin)
            ->patch(route('settings.holidays.toggle', $holiday))
            ->assertRedirect();
        $this->assertFalse($holiday->fresh()->is_active);

        $this->actingAsWebSession($admin)
            ->delete(route('settings.holidays.destroy', $holiday))
            ->assertRedirect();
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        Holiday::create(['date' => '2026-12-31', 'name' => 'มีแล้ว']);

        $this->actingAsWebSession($admin)
            ->post(route('settings.holidays.store'), [
                'date' => '2026-12-31',
                'name' => 'ซ้ำ',
            ])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, Holiday::query()->count());
    }
}
