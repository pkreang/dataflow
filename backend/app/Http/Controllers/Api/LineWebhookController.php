<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LineConfirmationNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LineWebhookController extends Controller
{
    public function handle(Request $request, ApprovalFlowService $service): JsonResponse
    {
        $secret = Setting::get('line_messaging.channel_secret', '');
        $body   = $request->getContent();
        $sig    = $request->header('X-Line-Signature', '');
        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));

        if (! $secret || ! hash_equals($expected, $sig)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        foreach ($request->input('events', []) as $event) {
            if (($event['type'] ?? '') !== 'postback') {
                continue;
            }

            $lineUserId = $event['source']['userId'] ?? null;
            if (! $lineUserId) {
                continue;
            }

            parse_str($event['postback']['data'] ?? '', $pb);
            $action     = $pb['a'] ?? null;
            $instanceId = (int) ($pb['i'] ?? 0);
            $stepNo     = (int) ($pb['s'] ?? 0);

            if (! in_array($action, ['approve', 'reject'], true) || ! $instanceId) {
                continue;
            }

            $user     = User::where('line_user_id', $lineUserId)->first();
            $instance = ApprovalInstance::with('steps')->find($instanceId);

            if (! $user || ! $instance) {
                continue;
            }

            $step = $instance->steps->firstWhere('step_no', $stepNo);
            if (! $step || $step->action !== 'pending') {
                continue;
            }

            if (! $service->canUserActOnStep($instance, $step, $user->id)) {
                continue;
            }

            $outcome = $action === 'approve' ? 'approved' : 'rejected';
            $service->act($instanceId, $user->id, $outcome, null, null);

            $ref = $instance->reference_no ?? "#{$instanceId}";
            $msg = $outcome === 'approved'
                ? "อนุมัติสำเร็จ -- {$ref}"
                : "ไม่อนุมัติ -- {$ref}";

            $user->notify(new LineConfirmationNotification($msg));
        }

        return response()->json(['status' => 'ok']);
    }
}
