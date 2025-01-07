<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportMontePisanoDEMToDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dem:import-monte-pisano-dem';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Monte Pisano DEM To Database Command';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Importing Monte Pisano DEM to database...');

        // Path to the SQL file
        $sqlFilePath = base_path('tests/Feature/Stubs/montepisano25x25_3035.sql');

        // Check if the file exists
        if (!File::exists($sqlFilePath)) {
            $this->error('SQL file not found at: ' . $sqlFilePath);
            return;
        }

        // Read the SQL file
        $sql = File::get($sqlFilePath);

        // Execute the SQL commands
        try {
            DB::connection()->getPdo()->exec($sql);
            $this->info('Importing Monte Pisano DEM to database completed.');
        } catch (\Exception $e) {
            $this->error('Error importing DEM: ' . $e->getMessage());
        }
    }
}
