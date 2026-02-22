<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvestmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Equity', 'description' => 'Equity investment'],
            ['name' => 'Debt', 'description' => 'Debt financing'],
            ['name' => 'Grant', 'description' => 'Grant funding'],
            ['name' => 'Hybrid', 'description' => 'Mixed investment type'],
            ['name' => 'Venture Capital', 'description' => 'VC investment'],
        ];

        foreach ($types as $type) {
            DB::table('investment_types')->updateOrInsert(
                ['name' => $type['name']],
                array_merge($type, ['created_at' => now()])
            );
        }
    }
}
