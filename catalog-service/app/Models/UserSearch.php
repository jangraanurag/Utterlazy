<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserSearch extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'query'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
