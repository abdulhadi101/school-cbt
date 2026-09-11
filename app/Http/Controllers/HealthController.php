<?php

namespace App\Http\Controllers;

use App\Services\System\HealthCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(HealthCheck $healthCheck): JsonResponse
    {
        $report = $healthCheck->report();

        return response()->json($report, $report['status'] === 'ok' ? 200 : 503);
    }
}
