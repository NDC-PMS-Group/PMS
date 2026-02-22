<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_key' => 'app_name', 'setting_value' => 'NDC Project Management System', 'data_type' => 'string', 'category' => 'general', 'description' => 'Application name', 'is_public' => true],
            ['setting_key' => 'project_code_prefix', 'setting_value' => 'BDG', 'data_type' => 'string', 'category' => 'project', 'description' => 'Default project code prefix', 'is_public' => false],
            ['setting_key' => 'max_file_upload_size', 'setting_value' => '10485760', 'data_type' => 'integer', 'category' => 'upload', 'description' => 'Max file upload size in bytes (10MB)', 'is_public' => false],
            ['setting_key' => 'enable_email_notifications', 'setting_value' => 'true', 'data_type' => 'boolean', 'category' => 'notification', 'description' => 'Enable email notifications', 'is_public' => false],
            ['setting_key' => 'trash_retention_days', 'setting_value' => '30', 'data_type' => 'integer', 'category' => 'system', 'description' => 'Days to keep items in trash before permanent deletion', 'is_public' => false],
            ['setting_key' => 'auto_archive_completed_days', 'setting_value' => '90', 'data_type' => 'integer', 'category' => 'project', 'description' => 'Days after completion to auto-archive projects', 'is_public' => false],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $setting['setting_key']],
                array_merge($setting, ['updated_at' => now()])
            );
        }
    }
}
