<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ProductCourseMap extends Model
{
    protected $table = 'product_course_map';   // Laravel would guess product_course_maps
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'moodle_course_id' => 'integer',
        'moodle_role_id' => 'integer',
        'active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
