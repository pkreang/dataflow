<?php

namespace Tests\Feature\Channels;

use App\Channels\LineMessagingChannel;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineMessagingChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_text_message_via_push_endpoint(): void
    {
        $this->seedBase();
        Setting::set('line_messaging.channel_access_token', 'test-token-xyz');

        Http::fake([
            'api.line.me/v2/bot/message/push' => Http::response('', 200),
        ]);

        $user = $this->makeUser(['line_user_id' => 'U1234567890abcdef']);
        $notification = $this->fakeNotification('Hello from test');

        (new LineMessagingChannel)->send($user, $notification);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://api.line.me/v2/bot/message/push'
                && $req->method() === 'POST'
                && $req->hasHeader('Authorization', 'Bearer test-token-xyz')
                && $req->hasHeader('Content-Type', 'application/json')
                && $req->data() === [
                    'to' => 'U1234567890abcdef',
                    'messages' => [['type' => 'text', 'text' => 'Hello from test']],
                ];
        });
    }

    public function test_skips_when_user_has_no_line_user_id(): void
    {
        $this->seedBase();
        Setting::set('line_messaging.channel_access_token', 'test-token-xyz');
        Http::fake();

        $user = $this->makeUser(['line_user_id' => null]);
        (new LineMessagingChannel)->send($user, $this->fakeNotification('msg'));

        Http::assertNothingSent();
    }

    public function test_skips_when_channel_access_token_missing(): void
    {
        $this->seedBase();
        Setting::set('line_messaging.channel_access_token', '');
        Http::fake();

        $user = $this->makeUser(['line_user_id' => 'U1234567890abcdef']);
        (new LineMessagingChannel)->send($user, $this->fakeNotification('msg'));

        Http::assertNothingSent();
    }

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class, SettingSeeder::class]);
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Recipient',
            'last_name' => 'User',
            'email' => 'recipient-'.uniqid().'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ], $attrs));
    }

    public function test_uses_to_line_messages_when_available(): void
    {
        $this->seedBase();
        Setting::set('line_messaging.channel_access_token', 'test-token-xyz');

        Http::fake([
            'api.line.me/v2/bot/message/push' => Http::response('', 200),
        ]);

        $user = $this->makeUser(['line_user_id' => 'U1234567890abcdef']);

        $notification = new class extends Notification
        {
            public function toLineMessages(object $notifiable): array
            {
                return [['type' => 'flex', 'altText' => 'Test Flex', 'contents' => ['type' => 'bubble']]];
            }

            public function toLineMessage(object $notifiable): string
            {
                return 'fallback should not be used';
            }
        };

        (new LineMessagingChannel)->send($user, $notification);

        Http::assertSent(function ($req) {
            $data = $req->data();

            return $req->url() === 'https://api.line.me/v2/bot/message/push'
                && isset($data['messages'][0]['type'])
                && $data['messages'][0]['type'] === 'flex';
        });
    }

    public function test_falls_back_to_text_when_only_to_line_message_present(): void
    {
        $this->seedBase();
        Setting::set('line_messaging.channel_access_token', 'test-token-xyz');

        Http::fake([
            'api.line.me/v2/bot/message/push' => Http::response('', 200),
        ]);

        $user = $this->makeUser(['line_user_id' => 'U1234567890abcdef']);

        (new LineMessagingChannel)->send($user, $this->fakeNotification('plain text'));

        Http::assertSent(function ($req) {
            $data = $req->data();

            return $data['messages'][0]['type'] === 'text'
                && $data['messages'][0]['text'] === 'plain text';
        });
    }

    private function fakeNotification(string $text): Notification
    {
        return new class($text) extends Notification
        {
            public function __construct(public string $text) {}

            public function toLineMessage(object $notifiable): string
            {
                return $this->text;
            }
        };
    }
}
