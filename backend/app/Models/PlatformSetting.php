<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Central key/value platform configuration (name, branding, contact details).
 */
class PlatformSetting extends Model
{
    protected $connection = 'central';

    protected $table = 'platform_settings';

    protected $fillable = ['key', 'value'];

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string|null> every requested key, null when unset
     */
    public static function values(array $keys): array
    {
        $stored = self::query()->whereIn('key', $keys)->pluck('value', 'key');
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $stored[$key] ?? null;
        }

        return $out;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
