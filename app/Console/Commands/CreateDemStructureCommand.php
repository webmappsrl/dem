<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CreateDEMStructureCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dem:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the DEM database structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating DEM database structure...');

        // Check if the tables already exist
        $tablesExist = DB::select("SELECT to_regclass('public.dem') AS dem, to_regclass('public.o_4_dem') AS o_4_dem");

        if ($tablesExist[0]->dem && $tablesExist[0]->o_4_dem) {
            $this->info('Tables "dem" and "o_4_dem" already exist. Skipping creation.');
            return;
        }

        // Read the SQL file
        $sql = <<<SQL
-- Create the "dem" Table and related functions / triggers
CREATE TABLE "dem" ("rid" serial PRIMARY KEY,"rast" raster,"filename" text);
CREATE INDEX ON "dem" USING gist (st_convexhull("rast"));
SELECT AddRasterConstraints('','dem','rast',TRUE,TRUE,TRUE,TRUE,TRUE,TRUE,FALSE,TRUE,TRUE,TRUE,TRUE,TRUE);
ANALYZE "dem";
CREATE OR REPLACE FUNCTION handle_duplicate_rasters_dem()
RETURNS TRIGGER AS $$
BEGIN
    -- Elimina raster uguali, se esistono
    DELETE FROM dem
    WHERE ST_Equals(ST_Envelope(rast), ST_Envelope(NEW.rast));

    -- Procede con l'inserimento
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER check_duplicates_before_insert_dem
BEFORE INSERT ON dem
FOR EACH ROW
EXECUTE FUNCTION handle_duplicate_rasters_dem();

-- Create the "o_4_dem" Table and related functions / triggers
CREATE TABLE "o_4_dem" ("rid" serial PRIMARY KEY,"rast" raster,"filename" text);
CREATE INDEX ON "o_4_dem" USING gist (st_convexhull("rast"));
SELECT AddRasterConstraints('','o_4_dem','rast',TRUE,TRUE,TRUE,TRUE,TRUE,TRUE,FALSE,TRUE,TRUE,TRUE,TRUE,TRUE);
ANALYZE "o_4_dem";
CREATE OR REPLACE FUNCTION handle_duplicate_rasters_o_4_dem()
RETURNS TRIGGER AS $$
BEGIN
    -- Elimina raster uguali, se esistono
    DELETE FROM o_4_dem
    WHERE ST_Equals(ST_Envelope(rast), ST_Envelope(NEW.rast));

    -- Procede con l'inserimento
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER check_duplicates_before_insert_o_4_dem
BEFORE INSERT ON o_4_dem
FOR EACH ROW
EXECUTE FUNCTION handle_duplicate_rasters_o_4_dem();


-- Relative dem and o_4_dem constraints
SELECT AddOverviewConstraints('','o_4_dem','rast','','dem','rast',4);
SQL;

        // Execute the SQL commands
        try {
            DB::connection()->getPdo()->exec($sql);
            $this->info('Importing DEM SQL file to database completed.');
        } catch (\Exception $e) {
            $this->error('Error importing DEM: ' . $e->getMessage());
        }
    }
}
