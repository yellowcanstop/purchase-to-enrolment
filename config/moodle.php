<?php

// config('moodle.token')

return [
  // Site root only - MoodleClient appends /webservice/rest/server.php itself.
  'base_url' => env('MOODLE_BASE_URL', 'http://localhost:8080'),
  'token'    => env('MOODLE_TOKEN'),
  'format'   => env('MOODLE_FORMAT', 'json'),
  'timeout'  => env('MOODLE_TIMEOUT', 10),
];