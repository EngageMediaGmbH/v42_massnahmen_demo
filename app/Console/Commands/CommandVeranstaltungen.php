<?php

namespace App\Console\Commands;

use App\Jobs\LoaderVeranstaltungen;
use Illuminate\Console\Command;

class CommandVeranstaltungen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'load:veranstaltungen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'CLI Loader for Veranstaltungen';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Load Veranstaltungen
        $this->info('Loading Veranstaltungen via CLI...');
        LoaderVeranstaltungen::dispatch();
        $this->info('Veranstaltungen loaded successfully.');
    }
}
