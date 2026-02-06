<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'plan',
        'status',
        'renewal_period',
        'expires_at',
        'transaction_id',
        'canceled_at',
    ];
}
