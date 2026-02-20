<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndustrySeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            ['name' => 'Agriculture', 'description' => 'Agriculture and farming'],
            ['name' => 'Technology', 'description' => 'Technology and IT'],
            ['name' => 'Manufacturing', 'description' => 'Manufacturing and production'],
            ['name' => 'Tourism', 'description' => 'Tourism and hospitality'],
            ['name' => 'Energy', 'description' => 'Energy and utilities'],
            ['name' => 'Healthcare', 'description' => 'Healthcare and medical'],
            ['name' => 'Education', 'description' => 'Education and training'],
            ['name' => 'Real Estate', 'description' => 'Real estate and construction'],
            ['name' => 'Financial Services', 'description' => 'Banking and finance'],
            ['name' => 'Retail', 'description' => 'Retail and e-commerce'],
        ];

        foreach ($industries as $industry) {
            DB::table('industries')->insert(array_merge($industry, ['created_at' => now()]));
        }
    }
}