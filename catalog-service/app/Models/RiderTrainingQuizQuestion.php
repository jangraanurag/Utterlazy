<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderTrainingQuizQuestion extends Model
{
    use HasUuids;

    protected $fillable = ['module_id', 'question', 'options', 'correct_index'];

    protected $casts = ['options' => 'array'];

    public function module()
    {
        return $this->belongsTo(RiderTrainingModule::class);
    }
}
