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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LineWebhookController extends Controller
{
    private const REJECT_PENDING_TTL = 600;
    private const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    public function handle(Request $request, ApprovalFlowService $service): JsonResponse
    {
        $secret   = Setting::get('line_messaging.channel_secret', '');
        $body     = $request->getContent();
        $sig      = $request->header('X-Line-Signature', '');
        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));

        if (! $secret || ! hash_equals($expected, $sig)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        foreach ($request->input('events', []) as $event) {
            $type       = $event['type'] ?? '';
            $lineUserId = $event['source']['userId'] ?? null;
            if (! $lineUserId) {
                continue;
            }

            if ($type === 'postback') {
                $this->handlePostback($event, $lineUserId, $service);
            } elseif ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
                $this->handleMessage($event, $lineUserId, $service);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function handlePostback(array $event, string $lineUserId, ApprovalFlowService $service): void
    {
        parse_str($event['postback']['data'] ?? '', $pb);
        $action     = $pb['a'] ?? null;
        $instanceId = (int) ($pb['i'] ?? 0);
        $stepNo     = (int) ($pb['s'] ?? 0);

        if (! in_array($action, ['approve', 'reject'], true) || ! $instanceId) {
            return;
        }

        $user     = User::where('line_user_id', $lineUserId)->first();
        $instance = ApprovalInstance::with('steps')->find($instanceId);

        if (! $user || ! $instance) {
            return;
        }

        $step = $instance->steps->firstWhere('step_no', $stepNo);
        if (! $step || $step->action !== 'pending') {
            return;
        }

        if (! $service->canUserActOnStep($instance, $step, $user->id)) {
            return;
        }

        $ref = $instance->reference_no ?? "#{$instanceId}";

        if ($action === 'approve') {
            $service->act($instanceId, $user->id, 'approved', null, null);
            $user->notify(new LineConfirmationNotification("อนุมัติสำเร็จ -- {$ref}"));
            return;
        }

        // reject: เก็บ pending state แล้วถามเหตุผล
        Cache::put(
            "line_reject_pending:{$lineUserId}",
            ['instance_id' => $instanceId, 'step_no' => $stepNo, 'ref' => $ref],
            self::REJECT_PENDING_TTL
        );

        $this->pushText($lineUserId, "กรุณาพิมพ์เหตุผลที่ไม่อนุมัติเอกสาร {$ref} แล้วส่งเป็นข้อความ\n(หมดอายุใน 10 นาที)");
    }

    private function handleMessage(array $event, string $lineUserId, ApprovalFlowService $service): void
    {
        $cacheKey = "line_reject_pending:{$lineUserId}";
        $pending  = Cache::get($cacheKey);

        if (! $pending) {
            return;
        }

        $comment    = trim($event['message']['text'] ?? '');
        $instanceId = (int) $pending['instance_id'];
        $ref        = $pending['ref'] ?? "#{$instanceId}";

        Cache::forget($cacheKey);

        $user     = User::where('line_user_id', $lineUserId)->first();
        $instance = ApprovalInstance::with('steps')->find($instanceId);

        if (! $user || ! $instance) {
            return;
        }

        $stepNo = (int) $pending['step_no'];
        $step   = $instance->steps->firstWhere('step_no', $stepNo);
        if (! $step || $step->action !== 'pending') {
            $this->pushText($lineUserId, "เอกสาร {$ref} ถูกดำเนินการไปแล้ว");
            return;
        }

        if (! $service->canUserActOnStep($instance, $step, $user->id)) {
            return;
        }

        $service->act($instanceId, $user->id, 'rejected', $comment ?: null, null);
        $user->notify(new LineConfirmationNotification("ไม่อนุมัติสำเร็จ -- {$ref}"));
    }

    private function pushText(string $lineUserId, string $text): void
    {
        $token = Setting::get('line_messaging.channel_access_token');
        if (! $token) {
            return;
        }

        Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post(self::PUSH_ENDPOINT, [
                'to'       => $lineUserId,
                'messages' => [['type' => 'text', 'text' => $text]],
            ]);
    }
}
