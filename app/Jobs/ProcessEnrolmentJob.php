<?php

namespace App\Jobs;

use App\Enums\EnrolmentStatus;
use App\Models\EnrolmentRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MoodleClient;
use App\Exceptions\MoodleApiException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessEnrolmentJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    // config properties that queue worker reads automatically
    /// as long as class implements ShouldQueue and is dispatched normally
    // set if want to differ from worker defaults
    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900];
    public int $timeout = 60;

    // ShouldBeUnique and uniqueFor to stop 2 workers grabbing the same request simultaneously
    public int $uniqueFor = 300; // if a worker dies holding the lock, it expires after 5mins rather than blocking that request forever

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $enrolmentRequestId
    ) {
        //
    }

    public function uniqueId(): string
    {
        return (string) $this->enrolmentRequestId;
    }

    /**
     * Execute the job.
     */
    public function handle(MoodleClient $moodle): void
    {
        $request = EnrolmentRequest::findOrFail($this->enrolmentRequestId);

        Log::withContext([
            'order_id' => $request->order_id,
            'enrolment_request_id' => $request->id,
        ]);

        if ($request->status === EnrolmentStatus::Enrolled) {
            Log::info('Already enrolled, nothing to do');
            return;
        }

        if (! $request->moodle_course_id) {
            Log::warning('No course mapped, marking skipped');
            $request->update(['status' => 'skipped']);
            return;
        }

        $request->increment('attempts');
        $attemptNo = $request->attempts;

        try {
            $user = $moodle->findUserByEmail($request->external_user_email);

            // first/last name are hardcoded to Student/Account
            // this is a placeholder. Real WooCommerce payloads have billing.first_name
            $userId = $user
                ? (int) $user['id']
                : $moodle->createUser($request->external_user_email, 'Student', 'Account');

            Log::info(
                $user ? 'Found existing Moodle user' : 'Created Moodle user',
                ['moodle_user_id' => $userId]
            );

            if ($moodle->isEnrolled($userId, $request->moodle_course_id)) {
                Log::info('Already enrolled in Moodle, converging state');
            } else {
                $moodle->enrol($userId, $request->moodle_course_id);
            }

            $request->update([
                'moodle_user_id' => $userId,
                'status' => 'enrolled',
                'last_error' => null,
            ]);

            $request->history()->create([
                'attempt_no' => $attemptNo,
                'request_body' => ['user_id' => $userId, 'course_id' => $request->moodle_course_id],
                'succeeded' => true,
            ]);
        } catch (MoodleApiException $e) {
            $request->history()->create([
                'attempt_no' => $attemptNo,
                'request_body' => [
                    'email' => $request->external_user_email,
                    'wsfunction' => $e->function,
                    'params' => $e->requestParams,
                ],
                'response_body' => $e->responseBody,
                'succeeded' => false,
            ]);

            $request->update(['last_error' => "{$e->function}/{$e->errorCode}: {$e->getMessage()}"]);

            if ($e->isPermanent()) {
                Log::error(
                    'Permanent Moodle error, failing immediately',
                    [
                        'wsfunction' => $e->function,
                        'error_code' => $e->errorCode,
                        // Moodle puts the actual rejected value here, but only
                        // when site debugging is DEVELOPER. Null otherwise.
                        'debuginfo' => $e->responseBody['debuginfo'] ?? null,
                        'moodle_message' => $e->getMessage(),
                    ]
                );
                $this->fail($e); // skip straight to failed($e)
                return;
            }

            throw $e;   // hands control back to the queue worker, which applies $backoff and re-queues
        }
    }

    // if processing throws a retryable exception, Laravel retries it according to $tries and $backoff. Once Laravel decides it has failed permanently,
    // Laravel calls this, such as when retries are exhausted or $this->fail($e) is called
    // failed() runs in a fresh process with no memory of handle(), so it re-queries by ID rather than reusing $request
    public function failed(?Throwable $e): void
    {
        EnrolmentRequest::where('id', $this->enrolmentRequestId)
            ->update([
                'status' => 'failed',
                'last_error' => $e?->getMessage() ?? 'Unknown failure',
            ]);

        Log::error('Enrolment permanently failed', [
            'enrolment_request_id' => $this->enrolmentRequestId,
        ]);
    }
}
