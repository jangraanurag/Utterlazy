<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaMessage extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'wa_message_id';

    protected $fillable = ['wa_message_id', 'wa_id', 'direction', 'payload'];

    protected $casts = ['payload' => 'array'];

    public static function isDuplicate(string $waMessageId): bool
    {
        return $waMessageId !== '' && static::where('wa_message_id', $waMessageId)->exists();
    }
}
