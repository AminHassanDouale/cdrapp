<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run()
    {
        if (Brand::count()) {
            return;
        }

        Brand::create(['id' => 1, 'name' => 'Apple']);
        Brand::create(['id' => 2, 'name' => 'Samsung']);
        Brand::create(['id' => 3, 'name' => 'LG']);
    }
}
