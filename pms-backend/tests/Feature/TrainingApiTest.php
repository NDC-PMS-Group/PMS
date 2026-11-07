<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrainingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_training_requires_authentication(): void
    {
        $this->getJson('/api/training')->assertUnauthorized();
        $this->getJson('/api/training/materials/1/finance/manual')->assertForbidden();
    }

    public function test_each_role_receives_only_its_own_materials_regardless_of_numeric_role_id(): void
    {
        Storage::fake('local');
        foreach (['Superadmin', 'Project Officer', 'Staff', 'Supervisor', 'Workgroup Head', 'ManCom', 'Proponent', 'Board', 'Investment Committee', 'Legal', 'Finance'] as $name) {
            $user = $this->userFor($name);
            $slug = Str::slug($name);
            $prefix = 'training/'.$slug.'/NDC_PMS_'.str_replace(' ', '_', $name);
            Storage::disk('local')->put($prefix.'_User_Manual.pdf', '%PDF-training');
            Storage::disk('local')->put($prefix.'_Tutorial.mp4', 'video-content');
            Sanctum::actingAs($user);

            $response = $this->getJson('/api/training?role=finance')->assertOk()->assertJsonPath('role', $name);
            $manual = $response->json('materials.manual');
            $video = $response->json('materials.video');
            $this->assertStringContainsString('/'.$user->id.'/'.$slug.'/manual?', $manual);
            $this->assertStringContainsString('/'.$user->id.'/'.$slug.'/video?', $video);
            $this->get($manual)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->get($video, ['Range' => 'bytes=0-3'])->assertStatus(206)
                ->assertHeader('Content-Type', 'video/mp4')->assertHeader('Content-Range', 'bytes 0-3/13');
        }
    }

    public function test_media_links_reject_tampering_expiry_and_revoked_role_or_account(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('training/finance/NDC_PMS_Finance_User_Manual.pdf', '%PDF-test');
        $user = $this->userFor('Finance');
        Sanctum::actingAs($user);
        $url = $this->getJson('/api/training')->assertOk()->json('materials.manual');

        $this->get(str_replace('/finance/', '/legal/', $url))->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
        $user->update(['default_role_id' => Role::firstOrCreate(['name' => 'Staff'])->id]);
        $this->get($url)->assertForbidden();
        $user->update(['default_role_id' => Role::where('name', 'Finance')->firstOrFail()->id, 'is_active' => false]);
        $this->get($url)->assertForbidden();
        $this->getJson('/api/training')->assertForbidden();
    }

    public function test_missing_materials_and_unknown_roles_do_not_fall_back_to_another_role(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->userFor('Custom Reviewer'));
        $this->getJson('/api/training')->assertOk()->assertJsonPath('materials', ['manual' => null, 'video' => null]);
        Sanctum::actingAs($this->userFor('Finance'));
        Storage::disk('local')->put('training/finance/NDC_PMS_Finance_User_Manual.pdf', '%PDF-test');
        $response = $this->getJson('/api/training')->assertOk()->assertJsonPath('materials.video', null);
        Storage::disk('local')->delete('training/finance/NDC_PMS_Finance_User_Manual.pdf');
        $this->get($response->json('materials.manual'))->assertNotFound();
    }

    private function userFor(string $name): User
    {
        $role = Role::firstOrCreate(['name' => $name], ['is_system_role' => true]);

        return User::create([
            'username' => 'training-'.Str::slug($name), 'email' => 'training-'.Str::slug($name).'@example.test',
            'password_hash' => bcrypt('password'), 'first_name' => 'Training', 'last_name' => 'Test',
            'default_role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
