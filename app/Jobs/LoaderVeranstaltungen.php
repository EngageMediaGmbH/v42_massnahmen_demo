<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\VeranstaltungenService;

class LoaderVeranstaltungen implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $start = microtime(true);

        // Veranstaltungen Demo
        $veranstaltungenService = new VeranstaltungenService();
        // Request Layer
        $veranstaltungenService->request();
        // Validate Layer
        $veranstaltungenService->validate();
        // Transform Layer
        $veranstaltungenService->transform();
        // Cache Layer
        $veranstaltungenService->cache();

        // Monitoring (simplified)
        $time_elapsed_secs = microtime(true) - $start;

        echo "Gesamtzeit: $time_elapsed_secs seconds\n";
    }
}
