<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use Illuminate\View\View;

class EvaluationReportController extends Controller
{
    public function index(): View
    {
        $evalFormIds = DocumentForm::where('document_type', 'evaluation')->pluck('id');

        $evaluations = DocumentFormSubmission::query()
            ->whereIn('form_id', $evalFormIds)
            ->whereNotNull('parent_submission_id')
            ->with(['originalSubmission.form', 'user'])
            ->latest()
            ->get();

        // Extract numeric rating (1-5) from string like "5 — ⭐⭐⭐⭐⭐ ดีเยี่ยม"
        $extractRating = function ($raw): ?int {
            if (! $raw) {
                return null;
            }
            if (preg_match('/^\s*([1-5])/', (string) $raw, $m)) {
                return (int) $m[1];
            }

            return null;
        };

        // Total + overall average
        $totalCount = $evaluations->count();
        $allRatings = $evaluations
            ->map(fn ($e) => $extractRating($e->payload['overall_rating'] ?? null))
            ->filter()
            ->values();
        $overallAvg = $allRatings->isNotEmpty() ? round($allRatings->avg(), 2) : 0;

        // Per-form aggregation
        $perForm = $evaluations
            ->groupBy(fn ($e) => $e->originalSubmission?->form?->name ?? '—')
            ->map(function ($group) use ($extractRating) {
                $ratings = $group->map(fn ($e) => $extractRating($e->payload['overall_rating'] ?? null))->filter()->values();

                return [
                    'name' => $group->first()->originalSubmission?->form?->name ?? '—',
                    'count' => $group->count(),
                    'avg' => $ratings->isNotEmpty() ? round($ratings->avg(), 2) : 0,
                ];
            })
            ->sortByDesc('avg')
            ->values();

        // Distribution 1-5
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($allRatings as $r) {
            $distribution[$r] = ($distribution[$r] ?? 0) + 1;
        }

        // Recent 5 evaluations
        $recent = $evaluations->take(5);

        // Response rate: count approved parent submissions where evaluation enabled
        $eligibleParents = DocumentFormSubmission::query()
            ->whereHas('form', fn ($q) => $q->where('evaluation_enabled', true))
            ->whereHas('instance', fn ($q) => $q->where('status', 'approved'))
            ->whereNull('parent_submission_id')
            ->count();
        $responseRate = $eligibleParents > 0 ? round(($totalCount / $eligibleParents) * 100, 1) : 0;

        return view('reports.evaluations', [
            'totalCount' => $totalCount,
            'overallAvg' => $overallAvg,
            'eligibleParents' => $eligibleParents,
            'responseRate' => $responseRate,
            'perForm' => $perForm,
            'distribution' => $distribution,
            'recent' => $recent,
            'extractRating' => $extractRating,
        ]);
    }
}
