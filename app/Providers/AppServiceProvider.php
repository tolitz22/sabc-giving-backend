<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::failing(function (JobFailed $event) {
            $payload = $event->job->payload();

            Log::error('Queue job failed', [
                'event' => 'queue_job_failed',
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
                'job_name' => $payload['displayName'] ?? $event->job->resolveName(),
                'attempts' => $event->job->attempts(),
                'uuid' => $payload['uuid'] ?? null,
                'exception_class' => get_class($event->exception),
                'exception_message' => $event->exception->getMessage(),
            ]);
        });
    }
}
