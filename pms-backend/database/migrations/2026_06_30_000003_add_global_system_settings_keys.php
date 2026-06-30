<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'setting_key' => 'app_logo',
                'setting_value' => '/assets/images/logo.png',
                'data_type' => 'string',
                'category' => 'general',
                'description' => 'Application logo image URL or path',
                'is_public' => true,
            ],
            [
                'setting_key' => 'app_theme',
                'setting_value' => 'light',
                'data_type' => 'string',
                'category' => 'general',
                'description' => 'Default application theme (light/dark/system)',
                'is_public' => true,
            ],
            [
                'setting_key' => 'app_primary_color',
                'setting_value' => '#2563eb',
                'data_type' => 'string',
                'category' => 'general',
                'description' => 'Primary theme color of the application',
                'is_public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $setting['setting_key']],
                array_merge($setting, ['updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->whereIn('setting_key', ['app_logo', 'app_theme', 'app_primary_color'])
            ->delete();
    }
};
