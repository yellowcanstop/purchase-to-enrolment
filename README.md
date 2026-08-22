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


## TODO
ADMIN CRUD:
- php artisan queue:failed-table && php artisan migrate for the failed_jobs table.
- Blade admin screen listing failed enrolment requests: order ID, email, product, attempts, last error, timestamp. Link each to its enrolment_attempts history.
- A "Retry" button that re-dispatches. A "Retry all" for a filtered set.
- Add job middleware: WithoutOverlapping keyed on the Moodle instance, and RateLimited to be gentle with the API.
- fire an EnrolmentFailed event and have a listener log it 


MAPPING TABLE:
- product_course_map with an Artisan command or a small CRUD screen to manage it. Handle the unmapped case explicitly: status skipped, a clear log line, and surface it on the admin screen. 


CSV bulk import with --dry-run:
- php artisan enrolments:import storage/app/imports/batch.csv --dry-run
- php artisan enrolments:import storage/app/imports/batch.csv
- Stream the file with SplFileObject or fgetcsv. validate every row. collect errors with line numbers. 
- --dry-run prints a table: would create N users, would enrol M, would skip K (already enrolled), R rows invalid — and changes nothing. Prove it changes nothing by diffing the database before and after.
- Use $this->withProgressBar() for the real run.
- Chunk the work — dispatch jobs in batches rather than doing it all inline, and consider Bus::batch() to get progress and a batch-level completion callback.
- Handle a UTF-8 BOM in the first header cell. Excel adds one. 


Scheduled reconciliation
// routes/console.php
'''
Schedule::command('enrolments:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
'''
- Pull all enrolment_requests with status enrolled from the last N days. For each course involved, call core_enrol_get_enrolled_users once per course — not once per user. Batch the reads. 
- Diff expected against actual, both directions
- Log structured drift and optionally auto-repair the missing ones by re-dispatching the job
- scheduler needs one real cron entry: 
- * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1



## Steps
1. Config: point database connection to MySQL, add Moodle config, point queue connection to Redis.
2. Migration and model for webhook_events, product_course_map, enrolment_requests, enrolment_attempts. Add factories for testing. Add seeder for product course map reference table. Then php artisan db:seed
3. From the local Moodle docker, add a token tied to a dedicated service account and a custom external service attached with the relevant functions. Prove moodle with curl.
4. Redis via a compose file in Laravel project root. Test redis using PingJob. First terminal: "php artisan tinker >>> App\Jobs\PingJob::dispatch();". Second terminal: "php artisan queue:work redis --verbose"
5. Add route and the VerifyHmacSignature middleware. Add request and controller for OrderCompleted. The controller sits between the webhook and the enrolment work. For one event, the controller dispatches ProcessWebhookEventJob which creates one EnrolmentRequest row per mappable item and dispatches one ProcessEnrolmentJob each (each enrolment can succeed and fail independently, even if transacted in one order).
6. Add fake webhook command.
7. Add Moodle client (and associated exceptions) and bind it in the container. Use Moodle client in ProcessEnrolmentJob.
8. Start worker, fire fake webhook, and see a real user land in Moodle. 
