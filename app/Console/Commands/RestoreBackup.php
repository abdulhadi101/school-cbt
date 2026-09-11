<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {path : Backup manifest path or backup directory on the selected disk}
        {--disk=local : Filesystem disk to read from}
        {--force : Restore without interactive confirmation}';

    protected $description = 'Restore application data from a verified local backup';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will replace current application data. Continue?')) {
            $this->warn('Restore cancelled.');

            return self::FAILURE;
        }

        $disk = (string) $this->option('disk');
        $manifestPath = $this->manifestPath((string) $this->argument('path'));

        if (! Storage::disk($disk)->exists($manifestPath)) {
            $this->error('Backup manifest not found: '.$manifestPath);

            return self::FAILURE;
        }

        try {
            $manifest = json_decode(Storage::disk($disk)->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

            if (($manifest['format'] ?? null) !== 'school-cbt-data-backup-v1') {
                $this->error('Unsupported backup format.');

                return self::FAILURE;
            }

            $dataPath = (string) $manifest['data_path'];
            $data = Storage::disk($disk)->get($dataPath);

            if (hash('sha256', $data) !== ($manifest['checksum'] ?? null)) {
                $this->error('Backup checksum verification failed.');

                return self::FAILURE;
            }

            $payload = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
            $tables = Arr::get($payload, 'tables', []);

            Schema::disableForeignKeyConstraints();
            DB::transaction(function () use ($tables): void {
                foreach (array_keys($tables) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                foreach ($tables as $table => $rows) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        if ($chunk !== []) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                }
            });
            Schema::enableForeignKeyConstraints();

            $this->info('Restore completed from: '.$manifestPath);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Schema::enableForeignKeyConstraints();
            $this->error('Restore failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function manifestPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return str_ends_with($path, '.json') ? $path : $path.'/manifest.json';
    }
}
