<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'featured_image', 'tags', 'author', 'status', 'published_at'];

    protected $casts = ['tags' => 'array', 'published_at' => 'datetime'];
}
