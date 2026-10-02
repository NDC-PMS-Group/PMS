<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvestmentCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvestmentCriterionController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => InvestmentCriterion::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeWrite($request, 'create');

        $validated = $request->validate([
            'key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('investment_criteria', 'key')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $criterion = InvestmentCriterion::create([
            'key' => $validated['key'] ?? $this->uniqueKey($validated['name']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder(),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Investment criterion created successfully.',
            'data' => $criterion,
        ], 201);
    }

    public function update(Request $request, InvestmentCriterion $investmentCriterion)
    {
        $this->authorizeWrite($request, 'update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);

        $investmentCriterion->update($validated);

        return response()->json([
            'message' => 'Investment criterion updated successfully.',
            'data' => $investmentCriterion->fresh(),
        ]);
    }

    public function destroy(Request $request, InvestmentCriterion $investmentCriterion)
    {
        $this->authorizeWrite($request, 'delete');

        $investmentCriterion->delete();

        return response()->noContent();
    }

    private function authorizeWrite(Request $request, string $action): void
    {
        $user = $request->user();

        abort_unless(
            $user && ((int) $user->default_role_id === 1 || $user->hasPermissionTo("access_settings.{$action}")),
            403,
            'You do not have permission to manage investment criteria.'
        );
    }

    private function nextSortOrder(): int
    {
        return ((int) InvestmentCriterion::max('sort_order')) + 10;
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value() ?: 'criterion';

        $key = Str::limit($base, 70, '');
        $candidate = $key;
        $suffix = 2;

        while (InvestmentCriterion::where('key', $candidate)->exists()) {
            $candidate = Str::limit($key, 70, '') . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
