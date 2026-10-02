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
            ['name' => 'Convertible Notes', 'description' => 'Convertible debt instrument'],
            ['name' => 'SAFE Notes', 'description' => 'Simple Agreement for Future Equity'],
            ['name' => 'Bonds', 'description' => 'Bond investment instrument'],
            ['name' => 'Others', 'description' => 'Other investment instrument defined by the project'],
            // Retained for historical projects; the creation lookup exposes only the current instrument catalog.
            ['name' => 'Debt', 'description' => 'Legacy debt financing classification'],
            ['name' => 'Grant', 'description' => 'Legacy grant funding classification'],
            ['name' => 'Hybrid', 'description' => 'Legacy mixed investment classification'],
            ['name' => 'Venture Capital', 'description' => 'Legacy venture capital classification'],
        ];

        foreach ($types as $type) {
            DB::table('investment_types')->updateOrInsert(
                ['name' => $type['name']],
                array_merge($type, ['created_at' => now()])
            );
        }
    }
}
