<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.woocommerce.webhook_secret');
        $provided = $request->header('x-wc-webhook-signature', '');

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($expected, $provided)) {
            Log::warning('Webhook signature rejected', [
                'ip' => $request->ip(),
                'has_header' => $request->hasHeader('x-wc-webhook-signature')
            ]);
            abort(401, 'Invalid signature');
        }

        return $next($request);
    }
}
