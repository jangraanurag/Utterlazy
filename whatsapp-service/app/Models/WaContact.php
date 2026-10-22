<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaContact extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'wa_id';

    protected $fillable = ['wa_id', 'user_id', 'language', 'order_pin_hash', 'last_seen'];

    protected $casts = ['last_seen' => 'datetime'];

    public function consents()
    {
        return $this->hasOne(WaConsent::class, 'wa_id', 'wa_id');
    }
}
