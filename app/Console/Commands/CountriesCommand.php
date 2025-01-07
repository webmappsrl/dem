<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Country;

class CountriesCommand extends Command
{
    protected $signature = 'dem:countries-list {name? : The name of the country to search for}';
    protected $description = 'Display a list of all countries in the database';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $countries = Country::all();

        if ($countries->isEmpty()) {
            $this->info('No countries found in the database.');
        } else {
            if ($this->argument('name')) {
                $countries = Country::where('name', 'ILIKE', '%' . $this->argument('name') . '%')->get();
            } else {
                $countries = Country::all();
            }

            foreach ($countries as $country) {
                $this->info("ID: {$country->code}, Name: {$country->name}");
            }
        }

        return 0;
    }
}