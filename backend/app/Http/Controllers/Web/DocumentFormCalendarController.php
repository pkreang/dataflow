<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentFormCalendarController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $forms = DocumentForm::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'form_key', 'name']);

        $canSeeTeam = $user->is_super_admin
            || User::where('manager_id', $user->id)->exists();

        return view('forms.calendar', compact('forms', 'canSeeTeam'));
    }

    public function events(Request $request): JsonResponse
    {
        $user = $request->user();
        $year  = (int) $request->input('year',  now()->year);
        $month = (int) $request->input('month', now()->month);
        $formKey = $request->input('form_key', '');

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        $query = DocumentFormSubmission::query()
            ->with(['form:id,name,form_key', 'user:id,first_name,last_name', 'instance:id,status'])
            ->where('status', '!=', 'draft')
            ->whereNull('document_form_submissions.deleted_at');

        // Role scoping
        $userId = $user->id;
        if (! $user->is_super_admin) {
            $subIds = User::where('manager_id', $userId)->pluck('id');
            if ($subIds->isNotEmpty()) {
                $query->whereIn('user_id', $subIds->push($userId));
            } else {
                $query->where('user_id', $userId);
            }
        }

        if ($formKey !== '') {
            $query->whereHas('form', fn ($q) => $q->where('form_key', $formKey));
        }

        // Fetch submissions that overlap the viewed month.
        // For forms with date_from/date_to payload we use those dates;
        // for others we use created_at. We widen the query window by ±90
        // days to catch long-running leaves whose created_at falls outside
        // the month but whose leave dates land inside it.
        $submissions = $query
            ->whereBetween('document_form_submissions.created_at', [
                $start->copy()->subDays(90),
                $end->copy()->addDays(90),
            ])
            ->get();

        $days = [];

        foreach ($submissions as $sub) {
            $payload = $sub->payload ?? [];
            $effectiveStatus = $sub->effective_status;

            // Skip cancelled/draft
            if (in_array($effectiveStatus, ['draft', 'cancelled'], true)) {
                continue;
            }

            $userName = trim(($sub->user?->first_name ?? '') . ' ' . ($sub->user?->last_name ?? ''));
            $eventData = [
                'id'        => $sub->id,
                'ref_no'    => $sub->reference_no ?? ('#' . $sub->id),
                'form_name' => $sub->form?->name ?? '—',
                'user_name' => $userName,
                'status'    => $effectiveStatus,
                'url'       => route('forms.submission.show', $sub->id),
            ];

            if (isset($payload['date_from']) && $payload['date_from']) {
                // Smart date: use leave period dates from payload
                try {
                    $evStart = Carbon::parse($payload['date_from'])->startOfDay();
                    $evEnd   = isset($payload['date_to']) && $payload['date_to']
                        ? Carbon::parse($payload['date_to'])->startOfDay()
                        : $evStart->copy();

                    // Cap expansion to 60 days to guard against bad data
                    if ($evStart->diffInDays($evEnd) > 60) {
                        $evEnd = $evStart->copy()->addDays(60);
                    }

                    $eventData['start_date'] = $evStart->toDateString();
                    $eventData['end_date']   = $evEnd->toDateString();

                    $cur = $evStart->copy();
                    while ($cur->lte($evEnd)) {
                        $dateStr = $cur->toDateString();
                        if ($cur->between($start, $end)) {
                            $days[$dateStr][] = $eventData;
                        }
                        $cur->addDay();
                    }
                } catch (\Throwable) {
                    // Malformed date in payload — fall back to created_at
                    $dateStr = $sub->created_at->toDateString();
                    if ($sub->created_at->between($start, $end)) {
                        $days[$dateStr][] = $eventData;
                    }
                }
            } else {
                // Fall back: use submission created_at
                $dateStr = $sub->created_at->toDateString();
                if ($sub->created_at->between($start, $end)) {
                    $days[$dateStr][] = $eventData;
                }
            }
        }

        return response()->json(['days' => $days]);
    }
}
