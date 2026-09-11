<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CreateBackup extends Command
{
    protected $signature = 'backup:create
        {--type=manual : Backup type, usually manual or scheduled}
        {--disk=local : Filesystem disk to write to}';

    protected $description = 'Create a local application data backup with a checksumed manifest';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $type = (string) $this->option('type');

        $run = BackupRun::query()->create([
            'type' => $type,
            'status' => 'running',
            'disk' => $disk,
            'started_at' => now(),
        ]);

        $directory = 'backups/'.now()->format('Y-m-d_His').'-'.$run->id;
        $dataPath = $directory.'/data.json';
        $manifestPath = $directory.'/manifest.json';

        try {
            $tables = $this->backupTables();
            $payload = [
                'created_at' => now()->toIso8601String(),
                'database' => DB::connection()->getName(),
                'tables' => $tables->mapWithKeys(fn (string $table): array => [
                    $table => DB::table($table)->orderBy($this->firstColumn($table))->get()->all(),
                ])->all(),
            ];

            $data = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $checksum = hash('sha256', $data);

            Storage::disk($disk)->put($dataPath, $data);

            $manifest = json_encode([
                'format' => 'school-cbt-data-backup-v1',
                'created_at' => $payload['created_at'],
                'data_path' => $dataPath,
                'checksum' => $checksum,
                'tables' => $tables->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

            Storage::disk($disk)->put($manifestPath, $manifest);

            $run->update([
                'status' => 'completed',
                'path' => $manifestPath,
                'size_bytes' => strlen($data) + strlen($manifest),
                'checksum' => $checksum,
                'finished_at' => now(),
            ]);

            $this->info('Backup completed: '.$manifestPath);
            $this->info('Checksum: '.$checksum);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function backupTables(): Collection
    {
        return collect(Schema::getTableListing())
            ->reject(fn (string $table): bool => str_starts_with($table, 'sqlite_'))
            ->reject(fn (string $table): bool => in_array($table, [
                'backup_runs',
                'cache',
                'cache_locks',
                'failed_jobs',
                'job_batches',
                'jobs',
                'migrations',
                'password_reset_tokens',
                'sessions',
            ], true))
            ->values();
    }

    private function firstColumn(string $table): string
    {
        return Schema::getColumnListing($table)[0] ?? 'id';
    }
}
