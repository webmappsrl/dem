<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        // Copy the sql file to psql dokcer container
        $drop_cmd = 'docker cp ./tests/Feature/Stubs/montepisano25x25_3035.sql postgres_dem:/montepisano25x25_3035.sql';
        exec($drop_cmd);

        // Import the sql to the database
        $drop_cmd = 'docker exec -i postgres_dem psql -U dem -d dem -h localhost -f /montepisano25x25_3035.sql';
        exec($drop_cmd);

        $this->info('Importing Monte Pisano DEM to database completed.');
    }
}
