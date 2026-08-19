<?php

namespace App\Models;

use App\Enums\EnrolmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


final class EnrolmentRequest extends Model
{
    // allow all attributes to be mass assigned. may change to fillable.
    protected $guarded = [];

    protected $casts = [
        'moodle_user_id' => 'integer',
        'moodle_course_id' => 'integer',
        // counter. $request->attempts
        'attempts' => 'integer',
        'status' => EnrolmentStatus::class,
    ];

    // relation method. $request->history or $request->history()->get()
    // returns the list of EnrolmentAttempt records
    public function history(): HasMany
    {
        return $this->hasMany(EnrolmentAttempt::class);
    }

    public static function keyFor(string $orderId, string $productId): string
    {
        return hash('sha256', "{$orderId}:{$productId}");
    }
}
