<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrainingController extends Controller
{
    private const ROLES = [
        'superadmin' => 'Superadmin',
        'project-officer' => 'Project Officer',
        'staff' => 'Staff',
        'supervisor' => 'Supervisor',
        'workgroup-head' => 'Workgroup Head',
        'mancom' => 'ManCom',
        'proponent' => 'Proponent',
        'board' => 'Board',
        'investment-committee' => 'Investment Committee',
        'legal' => 'Legal',
        'finance' => 'Finance',
    ];

    private const FILES = [
        'manual' => ['User_Manual.pdf', 'application/pdf'],
        'video' => ['Tutorial.mp4', 'video/mp4'],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->is_active, 403);
        $role = Str::slug($user->defaultRole?->name ?? '');
        $materials = [];

        foreach (self::FILES as $kind => $file) {
            $materials[$kind] = isset(self::ROLES[$role]) && Storage::disk('local')->exists($this->path($role, $kind))
                ? URL::temporarySignedRoute('training.material', now()->addHour(), [
                    'user' => $user->id, 'role' => $role, 'kind' => $kind,
                ], absolute: false)
                : null;
        }

        return response()->json([
            'role' => self::ROLES[$role] ?? $user->defaultRole?->name,
            'materials' => $materials,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function material(User $user, string $role, string $kind): BinaryFileResponse
    {
        abort_unless($user->is_active && Str::slug($user->defaultRole?->name ?? '') === $role, 403);
        abort_unless(isset(self::ROLES[$role], self::FILES[$kind]), 404);
        $disk = Storage::disk('local');
        $path = $this->path($role, $kind);
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => self::FILES[$kind][1],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ])->setContentDisposition('inline', basename($path));
    }

    private function path(string $role, string $kind): string
    {
        $name = str_replace(' ', '_', self::ROLES[$role]);

        return "training/{$role}/NDC_PMS_{$name}_".self::FILES[$kind][0];
    }
}
