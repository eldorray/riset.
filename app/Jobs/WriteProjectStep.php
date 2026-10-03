<?php

namespace App\Jobs;

use App\Writing\Writing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class WriteProjectStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId, public int $step)
    {
        $this->onConnection('writing')->onQueue('writing');
    }

    public function handle(Writing $writing): void
    {
        $writing->execute($this->runId, $this->step);
    }

    public function failed(?Throwable $exception): void
    {
        app(Writing::class)->fail($this->runId, 'Proses penulisan terputus atau melebihi batas waktu. Bagian yang sudah selesai tetap tersimpan.', $this->step);
    }
}
