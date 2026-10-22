<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'config_key',
        'config_value',
        'type',
        'description',
    ];

    public static function getValue(string $key, string $default = ''): string
    {
        return Cache::remember("system_config:{$key}", 3600, function () use ($key, $default) {
            return self::where('config_key', $key)->value('config_value') ?? $default;
        });
    }

    public static function setValue(string $key, string $value): void
    {
        self::updateOrCreate(
            ['config_key' => $key],
            ['config_value' => $value]
        );
        Cache::forget("system_config:{$key}");
    }
}
