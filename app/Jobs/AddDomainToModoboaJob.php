<?php

namespace App\Jobs;

use App\Models\DomainRotation;
use App\Services\ModoboaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AddDomainToModoboaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $domain;
    protected $price;
    public function __construct($domain, $price)
    {
        $this->domain = $domain;
        $this->price = $price;
    }

    /**
     * Execute the job.
     */
    public function handle(ModoboaService $modoboa)
    {
        $response = $modoboa->createDomain($this->domain);
        
        if ($response) {
            // first insert into DB
            $id = $response['pk'];
            $data = DomainRotation::create([
                'domain_name' => $this->domain,
                'domain_id' => $id,
                'purchase_price' => $this->price,
                'type' => 'public',
                'expires_at' => now()->addYear(),
                'is_active' => false
            ]);
            
            Log::info("data added in db and and fetch dns: " . $data);

            // Modoboa ko thoda waqt chahiye hota hai DKIM generate karne mein
            // Isliye hum next job ko 10-20 seconds ke delay se bhejenge
            FetchDnsFromModoboaJob::dispatch($id)->delay(now()->addSeconds(120));
        }
    }
}
