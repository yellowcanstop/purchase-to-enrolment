<?php

namespace App\Exceptions;

use RuntimeException;

class MoodleApiException extends RuntimeException
{

  public function __construct(
    string $message,
    public readonly string $errorCode = 'unknown',
    public readonly ?array $responseBody = null,
    /** The wsfunction that failed. Without this the error code alone can't
     * tell you which of the four Moodle calls in an enrolment threw. */
    public readonly ?string $function = null,
    /** Params sent, token stripped. */
    public readonly ?array $requestParams = null,
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
