<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Storage};
use Illuminate\Validation\ValidationException;

class SystemSettingsController extends Controller
{
    public function index(Request $request, SystemSettings $settings)
    {
        $groups = config('system-settings.groups');
        $group = $request->query('group', 'general');
        abort_unless(is_string($group) && isset($groups[$group]), 404);
        $fields = $settings->fields($group);
        $values = [];
        foreach ($fields as $name => $definition) $values[$name] = $settings->get($definition['key'] ?? "$group.$name");
        return view('admin.settings.index', compact('groups', 'group', 'fields', 'values'));
    }

    public function update(Request $request, string $group, SystemSettings $settings)
    {
        $fields = $settings->fields($group);
        abort_unless($fields, 404);
        $rules = [];
        foreach ($fields as $name => $field) {
            $rules[$name] = $field['rules'];
            if ($field['type'] === 'file') $rules['remove_'.$name] = ['sometimes', 'boolean'];
        }
        $validator = validator($request->all(), $rules);
        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('admin.settings.index', ['group' => $group]));
        }
        $data = $validator->validated();
        $newFiles = [];
        $previousFiles = [];
        try {
            foreach ($fields as $name => $field) {
                if ($field['type'] !== 'file') continue;
                unset($data['remove_'.$name], $data[$name]);
                if ($request->hasFile($name) || $request->boolean('remove_'.$name)) {
                    $previousFiles[] = $settings->get("$group.$name");
                    $data[$name] = $request->hasFile($name) ? $request->file($name)->store('system', 'public') : null;
                    if ($data[$name]) $newFiles[] = $data[$name];
                }
            }
            $settings->save($group, $data);
        } catch (\Throwable $exception) {
            foreach ($newFiles as $file) Storage::disk('public')->delete($file);
            throw $exception;
        }
        // Only remove old branding owned by this feature, never mascot or other uploads.
        foreach ($previousFiles as $file) {
            if (is_string($file) && preg_match('#^system/[a-zA-Z0-9_-]+\.(png|jpe?g|webp)$#i', $file)) {
                Storage::disk('public')->delete($file);
            }
        }
        return redirect()->route('admin.settings.index', ['group' => $group])->with('success', 'Settings updated successfully.');
    }

    public function clearCache()
    {
        Artisan::call('optimize:clear');
        AdminActivityLog::record('system_settings.cache_cleared', 'system_settings');
        return redirect()->route('admin.settings.index', ['group' => 'maintenance'])->with('success', 'Application cache cleared successfully.');
    }
}
