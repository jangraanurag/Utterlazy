<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaWorkforce extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'wa_workforce';

    protected $fillable = [
        'user_id',
        'city_id',
        'shift_start',
        'shift_end',
        'queue_cap',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'queue_cap' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
