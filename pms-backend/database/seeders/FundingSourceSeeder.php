<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FundingSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'NDC Internal', 'description' => 'NDC internal funding'],
            ['name' => 'Government Budget', 'description' => 'Government allocated budget'],
            ['name' => 'Private Investors', 'description' => 'Private sector investors'],
            ['name' => 'International Grants', 'description' => 'International grant funding'],
            ['name' => 'Bank Loans', 'description' => 'Bank financing'],
            ['name' => 'SVF Pool', 'description' => 'Startup Venture Fund pool'],
        ];

        foreach ($sources as $source) {
            DB::table('funding_sources')->insert(array_merge($source, ['created_at' => now()]));
        }
    }
}