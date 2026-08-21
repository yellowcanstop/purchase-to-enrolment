<?php

namespace App\Jobs;

use App\Enums\EnrolmentStatus;
use App\Models\EnrolmentRequest;
use App\Models\ProductCourseMap;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessWebhookEventJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $webhookEventId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // get fresh state at execution time using ID (not an injected model)
        $event = WebhookEvent::findOrFail($this->webhookEventId);

        if ($event->processed_at !== null) {
            Log::info('Webhook event already processed, skipping');
            return;
        }

        $payload = $event->payload;
        $orderId = (string) $payload['order_id'];
        $email = $payload['billing']['email'];

        Log::withContext([
            'order_id' => $orderId,
            'webhook_event_id' => $event->id
        ]);

        foreach ($payload['line_items'] as $item) {
            $productId = (string) $item['product_id'];

            $mapping = ProductCourseMap::active()
                ->where('product_id', $productId)
                ->first();

            // processed_at guard makes this job re-runnable
            // if job dies halfway (create request 1, haven't done request 2), then retry re-enters the loop
            // and using firstOrCreate on the idempotency key means request 1 is found, not duplicated, while request 2 gets created
            $request = EnrolmentRequest::firstOrCreate(
                ['idempotency_key' => EnrolmentRequest::keyFor($orderId, $productId)],
                [
                    'order_id' => $orderId,
                    'external_user_email' => $email,
                    'product_id' => $productId,
                    'moodle_course_id' => $mapping?->moodle_course_id,
                    'status' => $mapping ? 'pending' : 'skipped', //handle skipped in admin screen
                    /**
                 * if a product is unmapped today and you add the mapping tomorrow, the existing skipped row is stale — firstOrCreate will find it and moodle_course_id stays null forever. Re-firing the webhook won't fix it, because the row already exists. admin sees skipped rows, adds mapping, clicks retry.
                 * so retry action needs to re-resolve the mapping, not just re-dispatch
                 * // in whatever handles the retry button
                 */
                    // $mapping = ProductCourseMap::active()->where('product_id', $request->product_id)->first();

                    // if ($mapping) {
                    //     $request->update([
                    //         'moodle_course_id' => $mapping->moodle_course_id,
                    //         'status' => 'pending',
                    //     ]);
                    //     ProcessEnrolmentJob::dispatch($request->id);
                    // }
                ]
            );

            if (! $mapping) {
                Log::warning('No active course mapping for product', [
                    'product_id' => $productId,
                    'enrolment_request_id' => $request->id,
                ]);
                continue; // skip dispatch
            }


            if (! $request->wasRecentlyCreated && $request->status === EnrolmentStatus::Enrolled) {
                Log::info('Already enrolled, not re-dispatching', [
                    'enrolment_request_id' => $request->id,
                ]);
                continue;
            }

            ProcessEnrolmentJob::dispatch($request->id);
        }

        $event->update(['processed_at' => now()]);
    }
}
