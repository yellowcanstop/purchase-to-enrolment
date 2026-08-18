## Architecture

```
Fake webhook
        │  POST /api/webhooks/order-completed
        ▼
  WebhookController ──► validate signature ──► store raw payload
        │
        ▼  dispatch
  ProcessOrderJob (queued)
        │
        ├─► resolve product → course via mapping table
        ├─► find-or-create Moodle user
        ├─► enrol via enrol_manual_enrol_users
        └─► record outcome
        │
        ▼ on repeated failure
  failed_jobs ──► admin screen ──► retry
        
  Nightly: ReconcileEnrolments command → compares expected vs actual → logs drift
```

Future: containerise Laravel itself. Put Redis and Moodle in one compose file, or use an external network to bridge the two stacks — and REDIS_HOST would become redis, and MOODLE_BASE_URL would become the Moodle service name.


## Steps
1. Config: point database connection to MySQL, add Moodle config, point queue connection to Redis.
2. Migration and model for webhook_events, product_course_map, enrolment_requests, enrolment_attempts. Add factories for testing. Add seeder for product course map reference table.
3. From the local Moodle docker, add a token tied to a dedicated service account and a custom external service attached with the relevant functions. Prove moodle with curl.
4. Redis via a compose file in Laravel project root. Test redis using PingJob. First terminal: "php artisan tinker >>> App\Jobs\PingJob::dispatch();". Second terminal: "php artisan queue:work redis --verbose"
5. Add route and the VerifyHmacSignature middleware.
6. Controller:  call Log::withContext(['order_id' => ...]) so every later log line in this request is tagged, insert the WebhookEvent row, dispatch ProcessOrderJob with its ID, and return a 200.
7. 
