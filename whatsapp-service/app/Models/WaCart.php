<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaCart extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'wa_id';

    protected $fillable = ['wa_id', 'restaurant_id', 'lines'];

    protected $casts = ['lines' => 'array'];

    public function total(): float
    {
        return collect($this->lines ?? [])->sum(fn ($l) => ($l['unit_price'] ?? 0) * ($l['quantity'] ?? 0));
    }
}
