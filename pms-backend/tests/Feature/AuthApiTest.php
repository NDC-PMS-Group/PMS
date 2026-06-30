<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ProponentRegistrationDocument;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
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
                'message' => 'Account is pending admin approval or deactivated',
            ]);
    }

    public function test_registration_sends_verification_email_and_keeps_account_pending(): void
    {
        Notification::fake();
        Storage::fake('public');

        Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $response = $this->postJson('/api/register', [
            'email' => 'new-proponent@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'first_name' => 'New',
            'last_name' => 'Proponent',
            'phone_number' => '+63 917 000 0000',
            'organization_name' => 'New Proponent Inc.',
            'organization_type' => 'Private Company',
            'organization_registration_no' => 'SEC-TEST-001',
            'address' => 'Makati City',
            'authority_confirmed' => true,
            'registration_document' => UploadedFile::fake()->create('sec-registration.pdf', 120, 'application/pdf'),
            'authorization_document' => UploadedFile::fake()->create('authorization.pdf', 80, 'application/pdf'),
            'company_profile_document' => UploadedFile::fake()->create('company-profile.pdf', 90, 'application/pdf'),
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Registration submitted. An NDC administrator must approve the account before sign in.',
            ]);

        $user = User::where('email', 'new-proponent@example.com')->firstOrFail();

        $this->assertFalse($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertCount(3, $user->registrationDocuments);
        $user->registrationDocuments->each(fn ($document) => Storage::disk('public')->assertExists($document->file_path));
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_profile_response_includes_registration_documents(): void
    {
        $role = Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $user = User::create([
            'username' => 'profiledocs',
            'email' => 'profiledocs@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Profile',
            'last_name' => 'Docs',
            'default_role_id' => $role->id,
            'is_active' => false,
        ]);

        ProponentRegistrationDocument::create([
            'user_id' => $user->id,
            'document_type' => 'registration_proof',
            'title' => 'SEC / DTI / CDA / Agency registration proof',
            'file_name' => 'sec-registration.pdf',
            'file_path' => 'registration-documents/'.$user->id.'/sec-registration.pdf',
            'file_size' => 123456,
            'file_type' => 'application/pdf',
            'uploaded_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.registration_documents.0.document_type', 'registration_proof')
            ->assertJsonPath('data.registration_documents.0.file_name', 'sec-registration.pdf');
    }

    public function test_registration_requires_business_and_authorization_documents(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $response = $this->postJson('/api/register', [
            'email' => 'missing-docs@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'first_name' => 'Missing',
            'last_name' => 'Docs',
            'phone_number' => '+63 917 000 0001',
            'organization_name' => 'Missing Docs Inc.',
            'organization_type' => 'Private Company',
            'organization_registration_no' => 'SEC-TEST-002',
            'address' => 'Quezon City',
            'authority_confirmed' => true,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registration_document', 'authorization_document']);
    }

    public function test_registration_rejects_invalid_document_types(): void
    {
        Notification::fake();
        Storage::fake('public');

        Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $response = $this->postJson('/api/register', [
            'email' => 'bad-file@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'first_name' => 'Bad',
            'last_name' => 'File',
            'phone_number' => '+63 917 000 0002',
            'organization_name' => 'Bad File Inc.',
            'organization_type' => 'Private Company',
            'organization_registration_no' => 'SEC-TEST-003',
            'address' => 'Pasig City',
            'authority_confirmed' => true,
            'registration_document' => UploadedFile::fake()->create('malware.exe', 20, 'application/octet-stream'),
            'authorization_document' => UploadedFile::fake()->create('authorization.pdf', 80, 'application/pdf'),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registration_document']);
    }

    public function test_registration_rejects_oversized_documents(): void
    {
        Notification::fake();
        Storage::fake('public');

        Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $response = $this->postJson('/api/register', [
            'email' => 'large-file@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'first_name' => 'Large',
            'last_name' => 'File',
            'phone_number' => '+63 917 000 0003',
            'organization_name' => 'Large File Inc.',
            'organization_type' => 'Private Company',
            'organization_registration_no' => 'SEC-TEST-004',
            'address' => 'Taguig City',
            'authority_confirmed' => true,
            'registration_document' => UploadedFile::fake()->create('large-registration.pdf', 10_241, 'application/pdf'),
            'authorization_document' => UploadedFile::fake()->create('authorization.pdf', 80, 'application/pdf'),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['registration_document']);
    }

    public function test_signed_verification_link_marks_email_verified_without_activating_account(): void
    {
        $role = Role::create([
            'name' => 'Proponent',
            'description' => 'External proponent',
            'is_system_role' => true,
        ]);

        $user = User::create([
            'username' => 'verifyuser',
            'email' => 'verify@example.com',
            'password_hash' => Hash::make('Password123!'),
            'first_name' => 'Verify',
            'last_name' => 'User',
            'default_role_id' => $role->id,
            'is_active' => false,
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->getJson($url)
            ->assertOk()
            ->assertJson([
                'message' => 'Email address verified. Your account still requires NDC admin approval before sign in.',
            ]);

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->is_active);
    }
}
