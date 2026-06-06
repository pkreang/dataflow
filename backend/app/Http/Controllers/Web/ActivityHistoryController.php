<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HasPerPage;
use App\Models\SystemChangeLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityHistoryController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View
    {
        $perPage = $this->resolvePerPage($request, 'activity_history_per_page');

        $query = SystemChangeLog::query()
            ->with('actor')
            ->when($request->entity_type, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->actor_id, fn ($q, $v) => $q->where('actor_user_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->search, function ($q, $v) {
                $q->whereHas('actor', function ($u) use ($v) {
                    $u->where('first_name', 'like', '%'.$v.'%')
                      ->orWhere('last_name', 'like', '%'.$v.'%');
                });
            })
            ->orderByDesc('created_at');

        $logs = $query->paginate($perPage)->withQueryString();

        $entityTypes = SystemChangeLog::query()
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type');

        $actors = User::query()
            ->whereIn('id', SystemChangeLog::query()->distinct()->pluck('actor_user_id')->filter())
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return view('settings.activity-history.index', compact('logs', 'perPage', 'entityTypes', 'actors'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = SystemChangeLog::query()
            ->with('actor')
            ->when($request->entity_type, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->actor_id, fn ($q, $v) => $q->where('actor_user_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->search, function ($q, $v) {
                $q->whereHas('actor', function ($u) use ($v) {
                    $u->where('first_name', 'like', '%'.$v.'%')
                      ->orWhere('last_name', 'like', '%'.$v.'%');
                });
            })
            ->orderByDesc('created_at');

        $filename = 'activity-history-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['วันที่/เวลา', 'ผู้ดำเนินการ', 'entity_type', 'entity_id', 'action', 'รายละเอียด']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    $actorName = $log->actor
                        ? $log->actor->first_name.' '.$log->actor->last_name
                        : '-';
                    $details = $log->changed_fields
                        ? json_encode($log->changed_fields, JSON_UNESCAPED_UNICODE)
                        : '';
                    fputcsv($out, [
                        $log->created_at?->format('Y-m-d H:i:s'),
                        $actorName,
                        $log->entity_type,
                        $log->entity_id,
                        $log->action,
                        $details,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
