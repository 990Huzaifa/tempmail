<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempAliasForwarding extends Model
{
    use HasFactory;


    protected $fillable = [
        'temp_alias_id',
        'recipients',
        'is_active',
        'keep_local'
     ];
}
