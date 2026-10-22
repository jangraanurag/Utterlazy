<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaConsent extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'wa_id';

    protected $fillable = ['wa_id', 'utility', 'marketing', 'marketing_opted_at'];

    protected $casts = [
        'utility' => 'boolean',
        'marketing' => 'boolean',
        'marketing_opted_at' => 'datetime',
    ];

    public static function canSendMarketing(string $waId): bool
    {
        return (bool) static::where('wa_id', $waId)->where('marketing', true)->exists();
    }

    public static function canSendUtility(string $waId): bool
    {
        $consent = static::find($waId);

        return $consent ? (bool) $consent->utility : true;
    }
}
