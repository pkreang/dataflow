<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Support\WorkdayCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkdayCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): WorkdayCalculator
    {
        return app(WorkdayCalculator::class);
    }

    public function test_no_holidays_counts_inclusive_days(): void
    {
        $this->assertSame(3, $this->calc()->workdaysBetween('2026-06-01', '2026-06-03'));
    }

    public function test_holiday_inside_range_is_subtracted(): void
    {
        Holiday::create(['date' => '2026-06-02', 'name' => 'Test holiday']);

        $this->assertSame(2, $this->calc()->workdaysBetween('2026-06-01', '2026-06-03'));
    }

    public function test_holiday_on_range_edge_is_subtracted(): void
    {
        Holiday::create(['date' => '2026-06-01', 'name' => 'Edge holiday']);

        $this->assertSame(2, $this->calc()->workdaysBetween('2026-06-01', '2026-06-03'));
    }

    public function test_holiday_outside_range_is_ignored(): void
    {
        Holiday::create(['date' => '2026-06-10', 'name' => 'Far holiday']);

        $this->assertSame(3, $this->calc()->workdaysBetween('2026-06-01', '2026-06-03'));
    }

    public function test_inactive_holiday_is_ignored(): void
    {
        Holiday::create(['date' => '2026-06-02', 'name' => 'Off', 'is_active' => false]);

        $this->assertSame(3, $this->calc()->workdaysBetween('2026-06-01', '2026-06-03'));
    }

    public function test_is_holiday(): void
    {
        Holiday::create(['date' => '2026-06-02', 'name' => 'H']);

        $this->assertTrue($this->calc()->isHoliday('2026-06-02'));
        $this->assertFalse($this->calc()->isHoliday('2026-06-03'));
    }

    public function test_cache_busts_on_holiday_save_and_delete(): void
    {
        $this->assertSame([], $this->calc()->activeDates());

        $h = Holiday::create(['date' => '2026-06-02', 'name' => 'H']);
        $this->assertSame(['2026-06-02'], $this->calc()->activeDates());

        $h->delete();
        $this->assertSame([], $this->calc()->activeDates());
    }
}
