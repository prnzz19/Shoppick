<?php

namespace App\Services;

use App\Models\{SystemSetting, LogisticsSetting, AdminActivityLog};
use Illuminate\Support\Facades\{DB, Schema};

class SystemSettings
{
    public function fields(string $group): array
    {
        return config("system-settings.groups.$group.fields", []);
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        if ($key === 'logistics.tracking_enabled') {
            return LogisticsSetting::valueFor('buyer_tracking_visibility', true);
        }
        $request = request();
        if (!$request->attributes->has('system_settings_values')) {
            $values = Schema::hasTable('system_settings') ? SystemSetting::pluck('value', 'key')->all() : [];
            $request->attributes->set('system_settings_values', $values);
        }
        [$group, $field] = array_pad(explode('.', $key, 2), 2, '');
        return $request->attributes->get('system_settings_values')[$key]
            ?? config("system-settings.groups.$group.fields.$field.default", $fallback);
    }

    public function save(string $group, array $values): void
    {
        DB::transaction(function () use ($group, $values) {
            $changes = [];
            foreach ($values as $field => $value) {
                $definition = $this->fields($group)[$field] ?? null;
                if (!$definition || ($definition['locked'] ?? false)) continue;
                $key = $definition['key'] ?? "$group.$field";
                $value = match ($definition['type']) {
                    'boolean' => (bool) $value,
                    'number' => (int) $value,
                    default => $value,
                };
                $before = $this->get($key);
                if ($key === 'logistics.tracking_enabled') {
                    LogisticsSetting::updateOrCreate(['key' => 'buyer_tracking_visibility'], [
                        'value' => ['value' => $value], 'updated_by' => auth()->id(),
                    ]);
                } else {
                    SystemSetting::updateOrCreate(['key' => $key], [
                        'value' => $value, 'type' => $definition['type'], 'group' => explode('.', $key)[0],
                    ]);
                }
                if ($before !== $value) $changes[$key] = ['before' => $before, 'after' => $value];
            }
            if ($changes) AdminActivityLog::record('system_settings.updated', 'system_settings', null, ['group' => $group, 'changes' => $changes]);
        });
        request()->attributes->remove('system_settings_values');
    }
}
