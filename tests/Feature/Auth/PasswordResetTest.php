<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_route_does_not_exist(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(404);
    }

    public function test_forgot_password_post_returns_404(): void
    {
        $response = $this->post('/forgot-password', ['email' => 'test@example.com']);

        $response->assertStatus(404);
    }

    public function test_reset_password_route_does_not_exist(): void
    {
        $response = $this->get('/reset-password/some-token');

        $response->assertStatus(404);
    }

    public function test_reset_password_post_returns_404(): void
    {
        $response = $this->post('/reset-password', [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
    }
}
