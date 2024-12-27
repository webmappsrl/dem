<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteDemTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dem:delete {--force : Force the deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the DEM and o_4_dem tables from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('This will delete the "dem" and "o_4_dem" tables from the database.');

        if ($this->option('force') || $this->confirm('Do you wish to continue?')) {
            try {
                DB::statement('DROP TABLE IF EXISTS dem CASCADE');
                DB::statement('DROP TABLE IF EXISTS o_4_dem CASCADE');
                $this->info('Tables "dem" and "o_4_dem" have been deleted successfully.');
            } catch (\Exception $e) {
                $this->error('Error deleting tables: ' . $e->getMessage());
            }
        } else {
            $this->info('Operation cancelled.');
        }
    }
}