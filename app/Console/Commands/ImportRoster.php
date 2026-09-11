<?php

namespace App\Console\Commands;

use App\Services\Imports\RosterImporter;
use Illuminate\Console\Command;

class ImportRoster extends Command
{
    protected $signature = 'roster:import
        {path : CSV file path}
        {--create-users : Create login accounts for students}
        {--default-password= : Default password for created users. Defaults to admission number}';

    protected $description = 'Import students and enrollments from a CSV roster';

    public function handle(RosterImporter $importer): int
    {
        $result = $importer->import((string) $this->argument('path'), [
            'create_users' => (bool) $this->option('create-users'),
            'default_password' => $this->option('default-password') ?: null,
        ]);

        $this->info("Created: {$result->created}");
        $this->info("Updated: {$result->updated}");
        $this->info("Skipped: {$result->skipped}");

        foreach ($result->errors as $error) {
            $this->warn($error);
        }

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
