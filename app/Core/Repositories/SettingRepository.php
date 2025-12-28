<?php

namespace App\Core\Repositories;

use App\Core\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SettingRepository
{
    /**
     * Get paginated settings with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Setting::query();

        // Filter by group
        if (isset($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        // Filter by type
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Search
        if (isset($filters['q']) && $filters['q']) {
            $q = $filters['q'];
            $query->where(function ($qry) use ($q) {
                $qry->where('key', 'like', "%{$q}%")
                    ->orWhere('value', 'like', "%{$q}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'key';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        // Validate sort_by
        $allowedSorts = ['key', 'group', 'type', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'key';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get all settings grouped by group.
     */
    public function getAllGrouped(array $filters = []): array
    {
        $query = Setting::query();

        if (isset($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        $grouped = [];
        foreach ($settings as $setting) {
            $group = $setting->group ?? 'general';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$setting->key] = $this->castValue($setting);
        }

        return $grouped;
    }

    /**
     * Get settings by group.
     */
    public function getByGroup(string $group): array
    {
        $settings = Setting::where('group', $group)
            ->orderBy('key')
            ->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->castValue($setting);
        }

        return $result;
    }

    /**
     * Get setting by key.
     */
    public function getByKey(string $key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return $this->castValue($setting);
    }

    /**
     * Get multiple settings by keys.
     */
    public function getByKeys(array $keys): array
    {
        $settings = Setting::whereIn('key', $keys)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->castValue($setting);
        }

        return $result;
    }

    /**
     * Cast setting value based on type.
     */
    protected function castValue(Setting $setting)
    {
        $value = $setting->value;

        switch ($setting->type) {
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === true;
            case 'json':
                return json_decode($value, true);
            case 'number':
            case 'integer':
                return is_numeric($value) ? (int) $value : 0;
            case 'float':
            case 'decimal':
                return is_numeric($value) ? (float) $value : 0.0;
            default:
                return $value;
        }
    }
}

