<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'description', 'ingredients', 'steps', 'image_url', 'cuisine', 'prep_time_min', 'cook_time_min', 'difficulty'];

    protected $casts = ['ingredients' => 'array', 'steps' => 'array'];
}
