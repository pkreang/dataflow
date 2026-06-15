<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HasPerPage;
use App\Http\Controllers\Controller;
use App\Models\DocumentFormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use HasPerPage;

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        $perPage = $this->resolvePerPage($request, 'notifications_per_page');

        // ?unread=1 — bell dropdown shows actionable items only; the full
        // history lives on this same route's HTML page (no filter).
        $query = $request->boolean('unread')
            ? $user->unreadNotifications()
            : $user->notifications();
        $notifications = $query->paginate($perPage);

        if ($request->wantsJson()) {
            return response()->json($notifications);
        }

        return view('notifications.index', compact('notifications', 'perPage'));
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // Normalise stored URLs: strip scheme+host so redirect() always follows
        // the current server (avoids wrong-port issues on dev like localhost vs localhost:8000).
        if ($url && str_starts_with($url, 'http')) {
            $url = parse_url($url, PHP_URL_PATH) ?? $url;
        }

        // Legacy notifications stored '/approvals/my-approvals' as fallback for eForm submissions.
        // Resolve the correct submission URL from instance_id when possible.
        if ($url === '/approvals/my-approvals') {
            $instanceId = $notification->data['instance_id'] ?? null;
            if ($instanceId) {
                $submissionId = DocumentFormSubmission::where('approval_instance_id', $instanceId)->value('id');
                if ($submissionId) {
                    $url = route('forms.submission.show', $submissionId, false);
                }
            }
        }

        return $url ? redirect($url) : redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications.index');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
