<?php

// config('moodle.token')

return [
  'base_url' => env('MOODLE_BASE_URL', 'http://localhost:8080/webservice/rest/server.php'),
  'token'    => env('MOODLE_TOKEN'),
  'format'   => env('MOODLE_FORMAT', 'json'),
  'timeout'  => env('MOODLE_TIMEOUT', 10),
];
