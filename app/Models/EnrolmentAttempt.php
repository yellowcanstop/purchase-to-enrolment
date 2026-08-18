<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\EnrolmentRequest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EnrolmentAttempt extends Model
{
    const UPDATED_AT = null; // since only has created_at timestamp, given these are write-once logs
    protected $guarded = [];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'succeeded' => 'boolean',
    ];

    public function enrolmentRequest(): BelongsTo
    {
        return $this->belongsTo(EnrolmentRequest::class);
    }
}
