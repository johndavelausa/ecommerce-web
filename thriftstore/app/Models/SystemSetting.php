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
     * Store a file for a setting and delete the old one if it exists.
     */
    public static function set_file(string $key, $file, string $folder = 'site'): void
    {
        $old = self::get($key);
        if ($old && !str_starts_with((string) $old, 'data:')) {
             \Illuminate\Support\Facades\Storage::disk('public')->delete((string) $old);
        }
        $path = $file->store($folder, 'public');
        self::set($key, $path);
    }

    /**
     * Get a setting and treat it as a URL (handling Base64 vs Storage paths).
     */
    public static function get_url(string $key, ?string $default = null): ?string
    {
        $value = trim((string) self::get($key));
        if (empty($value)) {
            return $default;
        }

        if (str_starts_with($value, 'http') || str_starts_with($value, 'data:')) {
            return $value;
        }

        return asset('storage/' . $value);
    }
}
