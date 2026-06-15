<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Thai public holidays 2026 — a starting set the admin can edit at
 * /settings/holidays. updateOrCreate by date, so re-seeding never duplicates
 * and never resurrects rows the admin deactivated (only `name` is refreshed).
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            '2026-01-01' => 'วันขึ้นปีใหม่',
            '2026-01-02' => 'วันหยุดชดเชยปีใหม่',
            '2026-03-03' => 'วันมาฆบูชา',
            '2026-04-06' => 'วันจักรี',
            '2026-04-13' => 'วันสงกรานต์',
            '2026-04-14' => 'วันสงกรานต์',
            '2026-04-15' => 'วันสงกรานต์',
            '2026-05-01' => 'วันแรงงานแห่งชาติ',
            '2026-05-04' => 'วันฉัตรมงคล',
            '2026-06-01' => 'วันวิสาขบูชา (ชดเชย)',
            '2026-06-03' => 'วันเฉลิมพระชนมพรรษาสมเด็จพระราชินี',
            '2026-07-28' => 'วันเฉลิมพระชนมพรรษา ร.10',
            '2026-07-29' => 'วันอาสาฬหบูชา',
            '2026-07-30' => 'วันเข้าพรรษา',
            '2026-08-12' => 'วันแม่แห่งชาติ',
            '2026-10-13' => 'วันนวมินทรมหาราช',
            '2026-10-23' => 'วันปิยมหาราช',
            '2026-12-07' => 'วันชดเชยวันพ่อแห่งชาติ',
            '2026-12-10' => 'วันรัฐธรรมนูญ',
            '2026-12-31' => 'วันสิ้นปี',
        ];

        foreach ($holidays as $date => $name) {
            Holiday::query()->updateOrCreate(['date' => $date], ['name' => $name]);
        }
    }
}
