<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderOfferDecline extends Model
{
    protected $fillable = ['order_id', 'delivery_agent_id', 'declined_pay'];

    protected function casts(): array
    {
        return ['declined_pay' => 'decimal:2'];
    }
}
