<?php

namespace App\Jobs;

use App\Services\ModoboaService;
use App\Services\NamecheapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ConfigureNamecheapDNSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $domain;
    protected $dkimRecord;
    
    public function __construct($domain, $dkimRecord)
    {
        $this->domain = $domain;
        $this->dkimRecord = $dkimRecord;
    }

    /**
     * Execute the job.
     */
    public function handle(NamecheapService $namecheap)
    {
        $namecheap->setupModoboaDNS($this->domain, $this->dkimRecord);
    }
}
