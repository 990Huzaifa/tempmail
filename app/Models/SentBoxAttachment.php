<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentBoxAttachment extends Model
{
    use HasFactory;


    protected $fillable = [
        'sent_mail_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];
}
