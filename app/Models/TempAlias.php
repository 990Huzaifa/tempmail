<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'alias_modoboa_id',
        'alias_email',
        'domain_id',
        'expires_at',
        'in_use',
        'selected'
    ];


    public function forwarding()
    {
        return $this->hasOne(TempAliasForwarding::class);
    }

    public function domain()
    {
        return $this->belongsTo(DomainRotation::class, 'domain_id');
    }
}
