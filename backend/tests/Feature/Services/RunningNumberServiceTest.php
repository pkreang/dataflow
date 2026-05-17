<?php

namespace Tests\Feature\Services;

use App\Models\RunningNumberConfig;
use App\Services\RunningNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RunningNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private RunningNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RunningNumberService::class);
    }

    public function test_sequential_calls_produce_distinct_monotonic_numbers(): void
    {
        RunningNumberConfig::create([
            'document_type' => 'test_doc',
            'prefix' => 'TST',
            'digit_count' => 5,
            'reset_mode' => 'none',
            'include_year' => false,
            'include_month' => false,
            'is_active' => true,
        ]);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->service->generate('test_doc');
        }

        $this->assertCount(100, array_unique($results));
        $this->assertSame('TST-00001', $results[0]);
        $this->assertSame('TST-00100', $results[99]);
        $this->assertSame(100, RunningNumberConfig::firstWhere('document_type', 'test_doc')->last_number);
    }

    public function test_returns_null_when_no_config_exists(): void
    {
        $this->assertNull($this->service->generate('no_such_type'));
    }

    public function test_returns_null_when_config_is_inactive(): void
    {
        RunningNumberConfig::create([
            'document_type' => 'inactive_doc',
            'prefix' => 'INA',
            'digit_count' => 4,
            'reset_mode' => 'none',
            'is_active' => false,
        ]);

        $this->assertNull($this->service->generate('inactive_doc'));
    }

    public function test_yearly_reset_at_year_boundary(): void
    {
        $config = RunningNumberConfig::create([
            'document_type' => 'yearly_doc',
            'prefix' => 'Y',
            'digit_count' => 4,
            'reset_mode' => 'yearly',
            'include_year' => true,
            'last_number' => 50,
            'last_reset_at' => '2025-06-01',
            'is_active' => true,
        ]);

        Carbon::setTestNow('2026-01-15 10:00:00');
        $first2026 = $this->service->generate('yearly_doc');
        Carbon::setTestNow(null);

        $this->assertSame('Y2026-0001', $first2026);
        $this->assertSame(1, $config->fresh()->last_number);
    }

    public function test_monthly_reset_at_month_boundary(): void
    {
        $config = RunningNumberConfig::create([
            'document_type' => 'monthly_doc',
            'prefix' => 'M',
            'digit_count' => 3,
            'reset_mode' => 'monthly',
            'include_year' => true,
            'include_month' => true,
            'last_number' => 27,
            'last_reset_at' => '2026-04-15',
            'is_active' => true,
        ]);

        Carbon::setTestNow('2026-05-01 09:00:00');
        $firstMay = $this->service->generate('monthly_doc');
        Carbon::setTestNow(null);

        $this->assertSame('M202605-001', $firstMay);
        $this->assertSame(1, $config->fresh()->last_number);
    }

    public function test_format_excludes_year_and_month_by_default(): void
    {
        RunningNumberConfig::create([
            'document_type' => 'plain_doc',
            'prefix' => 'PLN',
            'digit_count' => 6,
            'reset_mode' => 'none',
            'include_year' => false,
            'include_month' => false,
            'is_active' => true,
        ]);

        $this->assertSame('PLN-000001', $this->service->generate('plain_doc'));
    }

    public function test_format_includes_year_when_configured(): void
    {
        RunningNumberConfig::create([
            'document_type' => 'year_doc',
            'prefix' => 'YR',
            'digit_count' => 4,
            'reset_mode' => 'none',
            'include_year' => true,
            'is_active' => true,
        ]);

        Carbon::setTestNow('2026-07-04 12:00:00');
        $result = $this->service->generate('year_doc');
        Carbon::setTestNow(null);

        $this->assertSame('YR2026-0001', $result);
    }

    public function test_uses_lock_for_update_inside_transaction(): void
    {
        // Note: SQLite ignores `FOR UPDATE` syntax (silent no-op) — this test
        // only validates the structural call when running against MySQL/PostgreSQL.
        // On SQLite it just confirms the query is emitted at all.
        RunningNumberConfig::create([
            'document_type' => 'lock_doc',
            'prefix' => 'LK',
            'digit_count' => 3,
            'reset_mode' => 'none',
            'is_active' => true,
        ]);

        DB::enableQueryLog();
        $this->service->generate('lock_doc');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $hasLockSyntax = collect($log)->contains(
            fn ($q) => str_contains(strtolower($q['query']), 'for update')
        );
        $hasSelectByPk = collect($log)->contains(
            fn ($q) => str_contains(strtolower($q['query']), 'running_number_configs')
                && str_contains(strtolower($q['query']), '"id"')
                || (str_contains(strtolower($q['query']), 'running_number_configs')
                    && str_contains(strtolower($q['query']), '`id`'))
        );

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->assertTrue($hasLockSyntax, 'Expected FOR UPDATE clause on '.$driver);
        }

        $this->assertTrue($hasSelectByPk, 'Expected select-by-id query inside transaction');
    }
}
