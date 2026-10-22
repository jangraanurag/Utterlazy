<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderTrainingProgress extends Model
{
    use HasUuids;

    protected $fillable = ['rider_id', 'module_id', 'completed_lessons', 'quiz_score', 'quiz_passed', 'completed_at'];

    protected $casts = [
        'completed_lessons' => 'array',
        'quiz_passed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function module()
    {
        return $this->belongsTo(RiderTrainingModule::class);
    }
}
