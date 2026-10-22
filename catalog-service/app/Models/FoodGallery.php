<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FoodGallery extends Model
{
    use HasUuids;

    protected $table = 'food_gallery';

    protected $fillable = ['user_id', 'menu_item_id', 'order_id', 'image_url', 'caption', 'likes_count'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
