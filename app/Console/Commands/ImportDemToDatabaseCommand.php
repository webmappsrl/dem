<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportDEMToDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dem:import {file : The path to the DEM SQL file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import DEM SQL file to the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        $this->info('Importing DEM SQL file to database...');

        // Check if the file exists
        if (!File::exists($filePath)) {
            $this->error('SQL file not found at: ' . $filePath);
            return;
        }

        // Read the SQL file
        $sql = File::get($filePath);

        // Execute the SQL commands
        try {
            DB::connection()->getPdo()->exec($sql);
            $this->info('Importing DEM SQL file to database completed.');
        } catch (\Exception $e) {
            $this->error('Error importing DEM: ' . $e->getMessage());
        }
    }
}
