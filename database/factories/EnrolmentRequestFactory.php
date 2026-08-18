<?php

namespace Database\Factories;

use App\Enums\EnrolmentStatus;
use App\Models\EnrolmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<EnrolmentRequest>
 */
class EnrolmentRequestFactory extends Factory
{
  public function definition(): array
  {
    $orderId = (string) $this->faker->numberBetween(1000, 9999);
    $productId = 'SKU-' . $this->faker->numberBetween(1, 50);

    return [
      'order_id' => $orderId,
      'external_user_email' => $this->faker->safeEmail(),
      'product_id' => $productId,
      'status' => EnrolmentStatus::Pending,
      'attempts' => 0,
      'idempotency_key' => EnrolmentRequest::keyFor($orderId, $productId)

    ];
  }

  public function enrolled(): static
  {
    return $this->state(fn() => [
      'status' => EnrolmentStatus::Enrolled,
      'moodle_user_id' => $this->faker->numberBetween(2, 500),
      'moodle_course_id' => $this->faker->numberBetween(2, 20),
    ]);
  }

  public function failed(): static
  {
    return $this->state(fn() => [
      'status' => EnrolmentStatus::Failed,
      'attempts' => 5,
      'last_error' => 'Moodle: invalidparameter',
    ]);
  }
}
