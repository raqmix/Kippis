<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    protected $casts = [
        // Value is stored as text, no casting needed
    ];


    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general'): void
    {
        // Convert boolean to string for storage
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
            $type = 'boolean';
        } elseif (is_array($value)) {
            $value = json_encode($value);
            $type = 'json';
        } else {
            $value = (string) $value;
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    /**
     * Get a setting value by key with proper type casting
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        $value = $setting->value;

        // Cast based on type
        switch ($setting->type) {
            case 'boolean':
                return $value === '1' || $value === 'true' || $value === true;
            case 'json':
                return json_decode($value, true) ?? $default;
            case 'number':
                return is_numeric($value) ? (int) $value : $default;
            default:
                return $value ?? $default;
        }
    }
}

