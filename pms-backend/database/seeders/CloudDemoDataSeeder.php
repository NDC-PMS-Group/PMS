<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CloudDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreDataSeeder::class,
            TaskDemoSeeder::class,
        ]);
    }
}
