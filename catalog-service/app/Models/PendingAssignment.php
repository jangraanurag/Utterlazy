<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingAssignment extends Model
{
    use HasUuids;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Illuminate\Support\Str::orderedUuid();
            }
        });
    }

    public $timestamps = false;

    protected $fillable = [
        'id', 'order_id', 'attempt_count',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
