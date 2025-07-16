<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        if (Category::count()) {
            return;
        }

        Category::create(['id' => 1, 'name' => 'Computer']);
        Category::create(['id' => 2, 'name' => 'Smartphone']);
        Category::create(['id' => 3, 'name' => 'Sound']);
    }
}
