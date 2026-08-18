<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WebhookEvent extends Model
{
    public $timestamps = false; // since already have received_at and processed_at
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array', // json 
        'signature_valid' => 'boolean',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
