<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents.
     */
    public function index(Request $request)
    {
        $query = Document::with(['project', 'uploadedBy']);

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $query->active();

        $perPage = $request->get('per_page', 15);
        $documents = $query->paginate($perPage);

        return DocumentResource::collection($documents);
    }

    /**
     * Store a newly created document.
     */
    public function store(StoreDocumentRequest $request)
    {
        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'project_id' => $request->project_id,
            'task_id' => $request->task_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'category' => $request->category,
            'version' => 1,
            'is_public' => $request->get('is_public', false),
            'requires_approval' => $request->get('requires_approval', false),
            'uploaded_by' => auth()->id(),
        ]);

        // Create initial version
        DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'change_description' => 'Initial upload',
            'created_by' => auth()->id(),
        ]);

        return new DocumentResource($document->load(['project', 'uploadedBy']));
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document)
    {
        $document->load(['project', 'task', 'uploadedBy', 'versions']);

        return new DocumentResource($document);
    }

    /**
     * Download document.
     */
    public function download(Document $document)
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Delete document.
     */
    public function destroy(Document $document)
    {
        $document->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json(['message' => 'Document deleted successfully'], 200);
    }
}