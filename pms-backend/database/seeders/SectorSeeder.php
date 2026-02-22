<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            ['name' => 'Public', 'description' => 'Public sector projects'],
            ['name' => 'Private', 'description' => 'Private sector projects'],
            ['name' => 'Non-Profit', 'description' => 'Non-profit organization projects'],
            ['name' => 'Government', 'description' => 'Government projects'],
        ];

        foreach ($sectors as $sector) {
            DB::table('sectors')->updateOrInsert(
                ['name' => $sector['name']],
                array_merge($sector, ['created_at' => now()])
            );
        }
    }
}
