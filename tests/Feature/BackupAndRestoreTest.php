<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupAndRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_create_writes_manifest_and_records_run(): void
    {
        Storage::fake('local');

        SchoolSetting::query()->create([
            'name' => 'Pilot School',
            'code' => 'pilot',
        ]);

        $this->artisan('backup:create')->assertSuccessful();

        $run = BackupRun::query()->sole();

        $this->assertSame('completed', $run->status->value);
        $this->assertNotNull($run->checksum);
        Storage::disk('local')->assertExists($run->path);
    }

    public function test_backup_restore_replaces_data_from_verified_backup(): void
    {
        Storage::fake('local');

        $setting = SchoolSetting::query()->create([
            'name' => 'Original School',
            'code' => 'original',
        ]);

        $this->artisan('backup:create')->assertSuccessful();

        $path = BackupRun::query()->sole()->path;
        $setting->update(['name' => 'Changed School']);

        $this->artisan('backup:restore', [
            'path' => $path,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame('Original School', SchoolSetting::query()->where('code', 'original')->sole()->name);
    }

    public function test_backup_restore_rejects_corrupted_data(): void
    {
        Storage::fake('local');

        SchoolSetting::query()->create([
            'name' => 'Original School',
            'code' => 'original',
        ]);

        $this->artisan('backup:create')->assertSuccessful();

        $run = BackupRun::query()->sole();
        $manifest = json_decode(Storage::disk('local')->get($run->path), true);
        Storage::disk('local')->put($manifest['data_path'], '{"corrupted":true}');

        $this->artisan('backup:restore', [
            'path' => $run->path,
            '--force' => true,
        ])->assertFailed();

        $this->assertSame('Original School', SchoolSetting::query()->where('code', 'original')->sole()->name);
    }
}
