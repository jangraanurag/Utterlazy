<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GroupOrderItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'group_order_id', 'user_id', 'menu_item_id', 'quantity',
    ];

    public function groupOrder()
    {
        return $this->belongsTo(GroupOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
