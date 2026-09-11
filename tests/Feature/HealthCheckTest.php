<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_status(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status',
                'checked_at',
                'checks' => [
                    ['name', 'status', 'message'],
                ],
            ]);
    }

    public function test_health_command_reports_ok_status_as_json(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->artisan('health:check', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"status": "ok"');
    }

    public function test_health_checks_fail_when_app_key_is_missing(): void
    {
        config(['app.key' => null]);

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'fail');

        $this->artisan('health:check')->assertFailed();
    }
}
