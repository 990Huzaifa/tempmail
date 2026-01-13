<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DomainRotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'domain_name',
        'domain_id',
        'purchase_price',
        'expires_at',
        'type',
        'is_active',
    ];
}
