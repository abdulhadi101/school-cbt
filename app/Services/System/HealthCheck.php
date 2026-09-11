<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HealthCheck
{
    public function report(): array
    {
        $checks = [
            $this->appKey(),
            $this->database(),
            $this->storage(),
            $this->productionConfig(),
        ];

        return [
            'status' => collect($checks)->contains(fn (array $check): bool => $check['status'] === 'fail') ? 'fail' : 'ok',
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function appKey(): array
    {
        $key = (string) config('app.key');

        return [
            'name' => 'app_key',
            'status' => $key !== '' ? 'ok' : 'fail',
            'message' => $key !== '' ? 'Application key is configured.' : 'Application key is missing. Run php artisan key:generate.',
        ];
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return [
                'name' => 'database',
                'status' => Schema::hasTable('migrations') ? 'ok' : 'fail',
                'message' => Schema::hasTable('migrations') ? 'Database is reachable and migrated.' : 'Database is reachable but migrations have not run.',
            ];
        } catch (\Throwable $exception) {
            return [
                'name' => 'database',
                'status' => 'fail',
                'message' => 'Database check failed: '.$exception->getMessage(),
            ];
        }
    }

    private function storage(): array
    {
        $path = 'health-checks/'.Str::uuid().'.txt';

        try {
            Storage::disk('local')->put($path, 'ok');
            $exists = Storage::disk('local')->exists($path);
            Storage::disk('local')->delete($path);

            return [
                'name' => 'storage',
                'status' => $exists ? 'ok' : 'fail',
                'message' => $exists ? 'Local storage is writable.' : 'Local storage write verification failed.',
            ];
        } catch (\Throwable $exception) {
            return [
                'name' => 'storage',
                'status' => 'fail',
                'message' => 'Storage check failed: '.$exception->getMessage(),
            ];
        }
    }

    private function productionConfig(): array
    {
        if (! app()->environment('production')) {
            return [
                'name' => 'production_config',
                'status' => 'ok',
                'message' => 'Production-only checks skipped outside production.',
            ];
        }

        if ((bool) config('app.debug')) {
            return [
                'name' => 'production_config',
                'status' => 'fail',
                'message' => 'APP_DEBUG must be false in production.',
            ];
        }

        return [
            'name' => 'production_config',
            'status' => 'ok',
            'message' => 'Production configuration is safe.',
        ];
    }
}
