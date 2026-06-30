<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    /**
     * Display a listing of system settings grouped by category.
     */
    public function index(Request $request)
    {
        $query = SystemSetting::query();

        if ($request->has('group')) {
            $query->byCategory($request->group);
            $settings = $query->get();
            return response()->json([
                'settings' => $settings->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'key' => $s->setting_key,
                        'value' => $s->value,
                        'type' => $s->data_type,
                        'label' => ucwords(str_replace('_', ' ', $s->setting_key)),
                        'description' => $s->description,
                    ];
                })
            ]);
        }

        $settings = $query->get();
        $grouped = $settings->groupBy('category')->map(function ($group) {
            return $group->map(function ($s) {
                return [
                    'id' => $s->id,
                    'key' => $s->setting_key,
                    'value' => $s->value,
                    'type' => $s->data_type,
                    'label' => ucwords(str_replace('_', ' ', $s->setting_key)),
                    'description' => $s->description,
                ];
            })->values(); // reset array keys to be sequential
        });

        return response()->json($grouped);
    }

    /**
     * Update bulk settings.
     */
    public function update(Request $request)
    {
        if (auth()->user() && (int) auth()->user()->default_role_id !== 1 && !auth()->user()->hasPermissionTo('system_settings.update')) {
            return response()->json(['message' => 'You do not have permission to update settings'], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:system_settings,setting_key',
            'settings.*.value' => 'present',
        ]);

        foreach ($validated['settings'] as $item) {
            SystemSetting::where('setting_key', $item['key'])
                ->update([
                    'setting_value' => $item['value'],
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json(['message' => 'Settings saved successfully']);
    }

    /**
     * Display a single setting.
     */
    public function show($key)
    {
        $setting = SystemSetting::where('setting_key', $key)->firstOrFail();
        return response()->json([
            'id' => $setting->id,
            'key' => $setting->setting_key,
            'value' => $setting->value,
            'type' => $setting->data_type,
            'label' => ucwords(str_replace('_', ' ', $setting->setting_key)),
            'description' => $setting->description,
        ]);
    }

    /**
     * Update a single setting value.
     */
    public function updateSingle(Request $request, $key)
    {
        if (auth()->user() && (int) auth()->user()->default_role_id !== 1 && !auth()->user()->hasPermissionTo('system_settings.update')) {
            return response()->json(['message' => 'You do not have permission to update settings'], 403);
        }

        $validated = $request->validate([
            'value' => 'present',
        ]);

        $setting = SystemSetting::where('setting_key', $key)->firstOrFail();
        $setting->update([
            'setting_value' => $validated['value'],
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Setting updated successfully',
            'setting' => [
                'id' => $setting->id,
                'key' => $setting->setting_key,
                'value' => $setting->value,
                'type' => $setting->data_type,
                'label' => ucwords(str_replace('_', ' ', $setting->setting_key)),
                'description' => $setting->description,
            ]
        ]);
    }

    /**
     * Upload app logo image.
     */
    public function uploadLogo(Request $request)
    {
        if (auth()->user() && (int) auth()->user()->default_role_id !== 1 && !auth()->user()->hasPermissionTo('system_settings.update')) {
            return response()->json(['message' => 'You do not have permission to update settings'], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp,svg|max:4096',
        ]);

        $path = $request->file('logo')->store('settings', 'public');
        $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'logo_url' => $logoUrl,
        ]);
    }

    /**
     * Retrieve public settings.
     */
    public function publicSettings()
    {
        $settings = SystemSetting::public()->get();
        $formatted = [];
        foreach ($settings as $s) {
            $formatted[$s->setting_key] = $s->value;
        }
        return response()->json($formatted);
    }
}
