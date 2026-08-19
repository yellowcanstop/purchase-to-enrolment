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
2. Migration and model for webhook_events, product_course_map, enrolment_requests, enrolment_attempts. Add factories for testing. Add seeder for product course map reference table. Then php artisan db:seed
3. From the local Moodle docker, add a token tied to a dedicated service account and a custom external service attached with the relevant functions. Prove moodle with curl.
4. Redis via a compose file in Laravel project root. Test redis using PingJob. First terminal: "php artisan tinker >>> App\Jobs\PingJob::dispatch();". Second terminal: "php artisan queue:work redis --verbose"
5. Add route and the VerifyHmacSignature middleware. Add request and controller for OrderCompleted. The controller sits between the webhook and the enrolment work. For one event, the controller dispatches ProcessWebhookEventJob which creates one EnrolmentRequest row per mappable item and dispatches one ProcessEnrolmentJob each (each enrolment can succeed and fail independently, even if transacted in one order).
6. Add fake webhook command.
7. Add Moodle client (and associated exceptions) and bind it in the container. Use Moodle client in ProcessEnrolmentJob.
8. Start worker, fire fake webhook, and see a real user land in Moodle. 
