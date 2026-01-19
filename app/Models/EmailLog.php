<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'temp_alias_id',
        'from_email',
        'from_name',
        'subject',
        'body_html',
        'received_at',
        'is_read',
    ];


    public function attachments()
    {
        return $this->hasMany(EmailAttachment::class, 'email_log_id', 'id');
    }
}
