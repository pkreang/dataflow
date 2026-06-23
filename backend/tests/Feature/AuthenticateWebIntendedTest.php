<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthenticateWebIntendedTest extends TestCase
{
    public function test_ajax_request_without_session_returns_401_and_does_not_set_intended(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/notifications/unread-count');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
        $this->assertNull(session('intended'));
    }

    public function test_plain_page_request_without_session_redirects_to_login_and_sets_intended(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertSame(url('/dashboard'), session('intended'));
    }
}
