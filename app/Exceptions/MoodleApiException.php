<?php

namespace App\Exceptions;

use RuntimeException;

class MoodleApiException extends RuntimeException
{

  public function __construct(
    string $message,
    public readonly string $errorCode = 'unknown',
    public readonly ?array $responseBody = null,
  ) {
    parent::__construct($message);
  }

  /**
   * A connection timeout should retry since Moodle might be restarting.
   * However, there are errors that will never succeed on retry — bad input, missing records,
   * revoked token, handled below.
   */
  public function isPermanent(): bool
  {
    return in_array($this->errorCode, [
      'invalidparameter',
      'invalidrecord',
      'invalidtoken',
      'accessexception',
      'nopermissions',
      'usernameexists',
      'emailaddressmustbereal',
      'invalidemail',
    ], true);
  }
}
