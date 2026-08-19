<?php

namespace App\Services;

use App\Exceptions\MoodleApiException;
use App\Exceptions\MoodleUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MoodleClient
{
  public function __construct(
    private readonly string $baseUrl,
    private readonly string $token,

  ) {}

  private function call(string $function, array $params = []): array
  {
    $response = Http::asForm()
      ->timeout(15)
      ->connectTimeout(5)
      ->retry(3, 200, throw: false) // retries in milliseconds inside one job execution. handle DNS blips, connection resets. throw:false so persistent failure returns failed response and triggers our own error handling, not raising Guzzle's own exception
      ->post("{$this->baseUrl}/webservice/rest/server.php", [
        'wstoken' => $this->token,
        'wsfunction' => $function,
        'moodlewsrestformat' => 'json',
        ...$params,
      ]);

    if ($response->failed()) {
      throw new MoodleUnavailableException(
        "Moodle HTTP {$response->status()} calling {$function}"
      );
    }
    $body = $response->json();

    // Moodle's enrol_manual_enrol_users returns null on success
    // so call() must tolerate a null body
    if ($body === null) return [];

    if (! is_array($body)) {
      throw new MoodleUnavailableException("Non-JSON response from {$function}");
    }

    // Moodle returns HTTP 200 even for errors.So need to check for body shaped like {"exception":"webservice_access_exception","errorcode":"accessexception","message":"..."}
    if (isset($body['exception'])) {
      throw new MoodleApiException(
        $body['message'] ?? 'Unknown Moodle error',
        $body['errorcode'] ?? 'unknown',
        $body,
      );
    }

    return $body;
  }

  public function findUserByEmail(string $email): ?array
  {
    // Moodle returns a bare array, not {users: [...]}
    $body = $this->call('core_user_get_users_by_field', [
      'field' => 'email',
      'values' => [$email]
    ]);

    return $body[0] ?? null;
  }

  public function createUser(string $email, string $firstName, string $lastName): int
  {
    $body = $this->call('core_user_create_users', [
      'users' => [[
        'username' => Str::lower($email), // Moodle enforces lowercase usernames
        'email' => $email,
        'firstname' => $firstName ?: 'Student',
        'lastname' => $lastName ?: 'Account',
        'auth' => 'manual', // this matters because some auth plugins reject programmatic creation
        'createpassword' => 1, // createpassword: 1 makes Moodle generate a password and email a setup link
      ]],
    ]);

    return (int) $body[0]['id'];
  }

  public function isEnrolled(int $userId, int $courseId): bool
  {
    $courses = $this->call('core_enrol_get_users_courses', [
      'userid' => $userId,
    ]);

    return collect($courses)->contains(fn($c) => (int) $c['id'] === $courseId);
  }

  public function enrol(int $userId, int $courseId, int $roleId = 5): void
  {
    $this->call('enrol_manual_enrol_users', [
      'enrolments' => [[
        'roleid' => $roleId,
        'userid' => $userId,
        'courseid' => $courseId,
      ]],
    ]);
  }
}
