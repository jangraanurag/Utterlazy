<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderTrainingModule extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'description', 'icon', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function lessons()
    {
        return $this->hasMany(RiderTrainingLesson::class)->orderBy('sort_order');
    }

    public function quizQuestions()
    {
        return $this->hasMany(RiderTrainingQuizQuestion::class);
    }
}
