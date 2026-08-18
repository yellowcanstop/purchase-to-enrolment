<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCourseMap;


class ProductCourseMapSeeder extends Seeder
{
  public function run(): void
  {
    $mappings = [
      ['product_id' => '99', 'moodle_course_id' => 2, 'moodle_role_id' => 5],
      ['product_id' => 'SKU-42', 'moodle_course_id' => 3, 'moodle_role_id' => 5],
    ];

    foreach ($mappings as $m) {
      ProductCourseMap::updateOrCreate(
        ['product_id' => $m['product_id']],
        $m + ['active' => true]
      );
    }
  }
}
