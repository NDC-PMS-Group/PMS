<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_migration_accounts_keep_their_ids_and_history_when_demo_users_are_seeded(): void
    {
        $legal = DB::table('users')->where('username', 'legal')->first();
        $finance = DB::table('users')->where('username', 'finance')->first();
        $this->assertNotNull($legal);
        $this->assertNotNull($finance);
        DB::table('notifications')->insert([
            'user_id' => $finance->id, 'type' => 'account_security', 'title' => 'Preserved history',
            'message' => 'Belongs to the original Finance account.', 'is_read' => false, 'is_email_sent' => false, 'created_at' => now(),
        ]);
        $this->seed(RoleSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);
        $this->assertDatabaseHas('users', ['id' => $legal->id, 'username' => 'legal', 'created_at' => $legal->created_at]);
        $this->assertDatabaseHas('users', ['id' => $finance->id, 'username' => 'finance', 'created_at' => $finance->created_at]);
        $this->assertNotSame($finance->id, DB::table('users')->where('username', 'superadmin')->value('id'));
        $this->assertDatabaseHas('notifications', ['user_id' => $finance->id, 'title' => 'Preserved history']);
    }

    public function test_conflicting_username_and_email_are_not_merged_or_overwritten(): void
    {
        $this->seed(RoleSeeder::class);
        foreach ([['superadmin', 'different@example.test'], ['different', 'sa@gmail.com']] as [$name, $email]) {
            User::create(['username' => $name, 'email' => $email, 'password_hash' => Hash::make('password'),
                'first_name' => 'Existing', 'last_name' => 'Account', 'default_role_id' => 1, 'is_active' => true]);
        }
        $before = DB::table('users')->orderBy('id')->get()->toArray();
        try {
            $this->seed(UserSeeder::class);
            $this->fail('Conflicting identities must stop the seed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('different accounts', $exception->getMessage());
        }
        $this->assertEquals($before, DB::table('users')->orderBy('id')->get()->toArray());
    }
}
