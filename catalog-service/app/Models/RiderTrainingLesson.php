<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiderTrainingLesson extends Model
{
    use HasUuids;

    protected $fillable = ['module_id', 'title', 'content', 'video_url', 'sort_order'];

    public function module()
    {
        return $this->belongsTo(RiderTrainingModule::class);
    }
}
