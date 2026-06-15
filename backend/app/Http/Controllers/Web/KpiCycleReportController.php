<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KpiCycle;
use App\Services\Kpi\KpiCycleReporter;
use Illuminate\View\View;

/**
 * Read-only 360° report for a KPI cycle — feeds the per-target × per-role
 * averages table. Heavy lifting lives in KpiCycleReporter; this controller
 * is just the HTTP entry point.
 */
class KpiCycleReportController extends Controller
{
    public function __construct(
        protected KpiCycleReporter $reporter,
    ) {}

    public function show(KpiCycle $kpiCycle): View
    {
        $summary = $this->reporter->summarize($kpiCycle);

        return view('settings.kpi-cycles.report', [
            'cycle' => $kpiCycle,
            'summary' => $summary,
        ]);
    }
}
