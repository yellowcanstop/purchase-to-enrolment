<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderCompletedRequest;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhookEventJob;
use Illuminate\Support\Facades\Log;

final class OrderCompletedController
{
    public function __invoke(OrderCompletedRequest $request)
    {
        $payload = $request->validated();
        $orderId = (string) $payload['order_id'];

        // use withContext so don't need to write ['order_id => $orderId] on every call
        Log::withContext(['order_id' => $orderId]);

        $event = WebhookEvent::firstOrCreate(
            // prefix id with woocommerce:order: to keep namespace clean
            ['external_id' => "woocommerce:order:{$orderId}"],
            [
                'source' => 'woocommerce',
                'payload' => $payload,
                'signature_valid' => true,
            ]
        );

        if (! $event->wasRecentlyCreated) {
            Log::info('Duplicate webhook ignored', ['webhook_event_id' => $event->id]);
            // return 200 since from WooCommerce's side, the delivery succeeded (but is just a duplicate) so it stops retrying
            return response()->json(['status' => 'duplicate'], 200);
        }

        // pass id to force the job to fetch fresh state from database
        ProcessWebhookEventJob::dispatch($event->id);

        return response()->json(['status' => 'accepted'], 202);
    }
}
