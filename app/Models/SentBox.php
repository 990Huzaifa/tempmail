<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentBox extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'temp_alias_id',
        'from_email',
        'to_email',
        'subject',
        'body_html',
        'mail_size',
    ];


    public function attachments()
    {
        return $this->hasMany(SentBoxAttachment::class, 'sent_box_id', 'id');
    }
}
