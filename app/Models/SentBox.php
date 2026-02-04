<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_email',
        'to_email',
        'subject',
        'body_html',
        'temp_alias_id',
        'mail_size',
    ];
}
