<?php

namespace App\Console\Commands;

use App\Jobs\SearchAndBuyDomainJob;
use Illuminate\Console\Command;

class RotateMailDomain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rotate-mail-domain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SearchAndBuyDomainJob::dispatch();
        $this->info('Domain Rotation Process Started Seemlessly!');
    }
}
