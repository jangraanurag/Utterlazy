<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasUuids;

    protected $connection = 'catalog_mysql';

    protected $fillable = ['restaurant_id', 'name', 'description', 'sort_order'];
}
