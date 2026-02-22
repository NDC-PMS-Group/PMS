<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $role = Role::create([
            'name' => 'qa-role',
            'description' => 'QA Role',
            'is_system_role' => false,
        ]);

        $user = User::create([
            'username' => 'qauser',
            'email' => 'qa@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'QA',
            'last_name' => 'User',
            'default_role_id' => $role->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'role'],
                'token',
            ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $role = Role::create([
            'name' => 'inactive-role',
            'description' => 'Inactive Role',
            'is_system_role' => false,
        ]);

        $user = User::create([
            'username' => 'inactiveuser',
            'email' => 'inactive@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'default_role_id' => $role->id,
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Account is deactivated',
            ]);
    }
}

