<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Country::count()) {
            return;
        }

        Country::insert([
            [
                'name' => 'Brazil'
            ],
            [
                'name' => 'Germany'
            ],
            [
                'name' => 'France'
            ],
            [
                'name' => 'India'
            ],
            [
                'name' => 'United States'
            ]
        ]);
    }
}
