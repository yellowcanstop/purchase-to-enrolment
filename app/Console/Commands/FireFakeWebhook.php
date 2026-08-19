<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * You need three terminals: php artisan serve, php artisan queue:work redis --verbose, and the one you type in. The command makes a real HTTP request to your own app, so something must be listening — and with php artisan serve (single-threaded PHP dev server) that's fine here because the CLI process and the server are separate processes.
 * php artisan optimize:clear // clear cached routes
 * php artisan webhook:fire                                  # happy path, new order
 * php artisan webhook:fire --order-id=555 --times=5         # layer-1 idempotency: 1 accepted, 4 duplicate
 * php artisan webhook:fire --tamper                         # expect 401, no row written
 * php artisan webhook:fire --product=404                    # unmapped product → skipped (M5)
 
 * for second one: five fires, one webhook_events row, one queued job
 * If you see five rows, your unique index on external_id isn't doing what you think

 */

// Future test for true concurrency: The genuine test is two simultaneous requests, which you'd do with xargs -P or by firing the command twice from two shells at once. Worth doing in M3 when you also run two queue workers — the whole reason for choosing firstOrCreate over check-then-insert is behaviour that only appears under concurrency, and sequential tests will happily pass on the racy version too

class FireFakeWebhook extends Command
{
    protected $signature = 'webhook:fire
                            {--order-id= : Fixed order ID (omit for random)}
                            {--product=99 : Product ID in line_items}
                            {--email=student@example.com}
                            {--times=1 : Fire N times, same payload}
                            {--tamper : Send a valid signature over a modified body}';

    protected $description = 'Fire a signed fake order-completed webhook at this app';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orderId = $this->option('order-id') ?: rand(1000, 9000);

        $payload = [
            'id' => (int) $orderId,
            'status' => 'completed',
            'billing' => ['email' => $this->option('email')],
            'line_items' => [['product_id' => (int) $this->option('product')]],
        ];

        // encode once
        $json = json_encode($payload);

        $secret = config('services.woocommerce.webhook_secret');

        // sign the string
        $signature = base64_encode(hash_hmac('sha256', $json, $secret, true));


        if ($this->option('tamper')) {
            $payload['line_items'][0]['product_id'] = 1;
            $json = json_encode($payload);   // signature now stale — should 401
        }

        for ($i = 1; $i <= (int) $this->option('times'); $i++) {
            // send the literal string
            $response = Http::withHeaders(['x-wc-webhook-signature' => $signature])
                ->withBody($json, 'application/json')
                ->post(url('/api/webhooks/order-completed'));

            $this->line(sprintf(
                '[%d] order %s → %d %s',
                $i,
                $orderId,
                $response->status(),
                $response->body()
            ));
        }

        return self::SUCCESS;
    }
}
