<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountManagerActivityLog extends Model
{
    protected $table = 'account_manager_activity_log';

    protected $fillable = ['account_manager_id', 'restaurant_id', 'action', 'entity_type', 'entity_id', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
