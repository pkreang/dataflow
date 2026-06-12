<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\UserShiftSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class ShiftCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_non_super_admin_is_blocked(): void
    {
        $user = $this->makeRegularUser();

        $this->actingAsWebSession($user)
            ->get(route('settings.shifts.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_crud_shift(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->get(route('settings.shifts.index'))
            ->assertOk();

        $this->actingAsWebSession($admin)
            ->post(route('settings.shifts.store'), [
                'code' => 'MORNING',
                'name' => 'กะเช้า',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'break_minutes' => 60,
            ])
            ->assertRedirect();

        $shift = Shift::query()->where('code', 'MORNING')->firstOrFail();

        $this->actingAsWebSession($admin)
            ->patch(route('settings.shifts.toggle', $shift))
            ->assertRedirect();
        $this->assertFalse($shift->fresh()->is_active);

        $this->actingAsWebSession($admin)
            ->delete(route('settings.shifts.destroy', $shift))
            ->assertRedirect();
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_night_shift_crossing_midnight_is_allowed(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->post(route('settings.shifts.store'), [
                'code' => 'NIGHT',
                'name' => 'กะดึก',
                'start_time' => '20:00',
                'end_time' => '05:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', ['code' => 'NIGHT']);
    }

    public function test_assign_shift_to_user_and_current_shift_resolves(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = $this->makeRegularUser('shift-user@example.test');
        $shift = Shift::create([
            'code' => 'M1', 'name' => 'กะเช้า',
            'start_time' => '08:00', 'end_time' => '17:00',
        ]);

        $this->actingAsWebSession($admin)
            ->post(route('settings.shifts.assign'), [
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'effective_from' => '2026-06-01',
                'work_days' => [1, 2, 3, 4, 5, 6],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_shift_schedules', [
            'user_id' => $user->id,
            'shift_id' => $shift->id,
        ]);
        $this->assertSame('M1', $user->fresh()->currentShift(\Carbon\Carbon::parse('2026-06-15'))?->code);
        // Before effective_from → no shift
        $this->assertNull($user->fresh()->currentShift(\Carbon\Carbon::parse('2026-05-15')));
    }

    public function test_overlapping_assignment_for_same_user_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = $this->makeRegularUser('shift-overlap@example.test');
        $shift = Shift::create([
            'code' => 'M2', 'name' => 'กะเช้า',
            'start_time' => '08:00', 'end_time' => '17:00',
        ]);
        UserShiftSchedule::create([
            'user_id' => $user->id, 'shift_id' => $shift->id,
            'effective_from' => '2026-06-01', 'effective_to' => null,
        ]);

        $this->actingAsWebSession($admin)
            ->post(route('settings.shifts.assign'), [
                'user_id' => $user->id,
                'shift_id' => $shift->id,
                'effective_from' => '2026-07-01',
            ])
            ->assertSessionHasErrors('effective_from');

        $this->assertSame(1, UserShiftSchedule::query()->count());
    }

    public function test_end_assignment(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = $this->makeRegularUser('shift-end@example.test');
        $shift = Shift::create([
            'code' => 'M3', 'name' => 'กะเช้า',
            'start_time' => '08:00', 'end_time' => '17:00',
        ]);
        $schedule = UserShiftSchedule::create([
            'user_id' => $user->id, 'shift_id' => $shift->id,
            'effective_from' => '2026-06-01', 'effective_to' => null,
        ]);

        $this->actingAsWebSession($admin)
            ->delete(route('settings.shifts.assignments.destroy', $schedule))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_shift_schedules', ['id' => $schedule->id]);
    }
}
