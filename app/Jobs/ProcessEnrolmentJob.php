<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MoodleClient;

final class ProcessEnrolmentJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    // config properties that queue worker reads automatically
    /// as long as class implements ShouldQueue and is dispatched normally
    // set if want to differ from worker defaults
    public int $tries = 5;
    public array $backoff = [10, 60, 300, 900];
    public int $timeout = 60;
    public int $uniqueFor = 300;

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
        //
    }
}
