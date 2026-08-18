<?php

namespace Database\Factories;

use App\Models\ProductCourseMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/*
$map = ProductCourseMap::factory()->create([
    'product_id' => 'SKU-42',
    'moodle_course_id' => 7,
]);

// fire webhook for SKU-42, then:
Http::assertSent(fn ($r) => str_contains($r->body(), 'courseid%5D=7'));
*/


class ProductCourseMapFactory extends Factory
{
  protected $model = ProductCourseMap::class;
  public function definition(): array
  {
    return [
      'product_id' => 'SKU-' . $this->faker->unique()->numberBetween(1, 9999),
      'moodle_course_id' => $this->faker->numberBetween(2, 50),
      'moodle_role_id' => 5,
      'active' => true,
    ];
  }

  public function inactive(): static
  {
    return $this->state(fn() => ['active' => false]);
  }
}
