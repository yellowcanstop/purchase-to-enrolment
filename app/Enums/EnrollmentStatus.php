<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
  case Pending = 'pending';
  case Enrolled = 'enrolled';
  case Failed   = 'failed';
  case Skipped  = 'skipped';

  public function label(): string
  {
    return match($this) {
      self::Pending => 'Pending',
      self::Enrolled => 'Enrolled',
      self::Failed   => 'Failed',
      self::Skipped  => 'Skipped',
    }
  }
}