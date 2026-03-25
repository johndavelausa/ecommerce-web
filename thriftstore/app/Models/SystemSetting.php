<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return $setting->value;
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value)]
        );
    }

    /**
     * Get a setting and treat it as a URL (handling Base64 vs Storage paths).
     */
    public static function get_url(string $key, ?string $default = null): ?string
    {
        $value = self::get($key);
        if (!$value) {
            return $default;
        }

        if (str_starts_with((string) $value, 'data:')) {
            return $value;
        }

        return asset('storage/' . $value);
    }
}
