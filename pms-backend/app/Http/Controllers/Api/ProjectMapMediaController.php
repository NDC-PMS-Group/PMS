<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProjectMapMediaController extends Controller
{
    /**
     * Upload or replace a project thumbnail.
     * POST /api/projects/{project}/thumbnail
     */
    public function uploadThumbnail(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'thumbnail' => 'required|file|max:5120',
        ]);

        $url = $this->replaceMedia(
            oldPath: $project->thumbnail_url,
            file:    $request->file('thumbnail'),
            folder:  "projects/{$project->id}/thumbnails",
        );

        $project->update(['thumbnail_url' => $url]);

        return response()->json([
            'message'       => 'Thumbnail uploaded successfully.',
            'thumbnail_url' => $url,
        ]);
    }

    /**
     * Upload or replace a project logo.
     * POST /api/projects/{project}/logo
     */
    public function uploadLogo(Request $request, Project $project): JsonResponse
    {
        $request->validate([
            'logo' => 'required|file|max:5120',
        ]);

        $url = $this->replaceMedia(
            oldPath:   $project->logo_url,
            file:      $request->file('logo'),
            folder:    "projects/{$project->id}/logos",
        );

        $project->update(['logo_url' => $url]);

        return response()->json([
            'message'    => 'Logo uploaded successfully.',
            'logo_url'   => $url,
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Delete the old file if it was stored locally, then store the new one.
     * Returns the full public URL.
     */
    private function replaceMedia(?string $oldPath, $file, string $folder): string
    {
        // Delete old file only if it was a local storage path (not an external URL)
        if ($oldPath && !str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        // Store and return relative path only e.g. projects/1/thumbnails/xyz.jpg
        // Frontend resolves to full URL using VITE_APP_BASE_URL + /storage/ + path
        return $file->store($folder, 'public');
    }
}