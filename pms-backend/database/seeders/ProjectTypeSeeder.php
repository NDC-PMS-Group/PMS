<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Infrastructure', 'description' => 'Infrastructure development projects'],
            ['name' => 'Business Development', 'description' => 'Business development and growth projects'],
            ['name' => 'SVF Project', 'description' => 'Startup Venture Fund projects'],
            ['name' => 'Joint Venture', 'description' => 'Joint venture projects'],
            ['name' => 'Public-Private Partnership', 'description' => 'PPP projects'],
            ['name' => 'Research & Development', 'description' => 'R&D projects'],
        ];

        foreach ($types as $type) {
            DB::table('project_types')->insert(array_merge($type, ['created_at' => now()]));
        }
    }
}