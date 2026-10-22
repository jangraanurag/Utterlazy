<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Recovery extends Model
{
    use HasUuids;

    protected $fillable = [
        'restaurant_id', 'reference_type', 'reference_id',
        'amount', 'notes', 'recovery_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recovery_date' => 'date',
        ];
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
