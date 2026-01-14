<?php

namespace App\Jobs;

use App\Services\NamecheapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SearchAndBuyDomainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function handle(NamecheapService $namecheap): void
    {
        $domainInfo = $namecheap->searchCheapDomain(); // Apka existing logic
        $domainList = $domainInfo['list'];

        $maxIndex = min(count($domainList) - 1, 3);
        $randomIndex = rand(0, $maxIndex);
        $selectedDomain = $domainList[$randomIndex];

        // verify price
        $pricingInfo = $namecheap->getTldPrice($selectedDomain);
        $currentPrice = (float) $pricingInfo['price'];
        
        Log::info("Checking Price: " . $currentPrice);

        if ($domainInfo['success'] == true && $pricingInfo['currency'] == 'USD' && $currentPrice <= 3) {
            $userData = [
                "first_name" => "suraj",
                "last_name" => "kumar",
                "address" => "Anum Empire",
                "city" => "karachi",
                "state" => "sindh",
                "zip" => "75500",
                "country" => "PK",
                "phone" => "+92.3101285809",
                "email" => "surajkumar00244vk@gmail.com",
            ]; 
            $purchase = $namecheap->purchaseDomain($selectedDomain, $userData);
            Log::info($purchase);
            if ($purchase['success'] === true) {
                // Agli Job ko chain mein daalna
                AddDomainToModoboaJob::dispatch($selectedDomain);
            }
        }
    }
}
