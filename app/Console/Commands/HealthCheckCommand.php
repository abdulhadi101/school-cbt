<?php

namespace App\Console\Commands;

use App\Services\System\HealthCheck;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check {--json : Output the full health report as JSON}';

    protected $description = 'Check whether the application is ready to serve exams';

    public function handle(HealthCheck $healthCheck): int
    {
        $report = $healthCheck->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        foreach ($report['checks'] as $check) {
            $line = strtoupper($check['status']).' '.$check['name'].': '.$check['message'];

            match ($check['status']) {
                'ok' => $this->info($line),
                default => $this->error($line),
            };
        }

        return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
