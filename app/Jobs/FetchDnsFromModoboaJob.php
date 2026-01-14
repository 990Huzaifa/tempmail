<?php
namespace App\Jobs;

use App\Services\ModoboaService;
use App\Models\DomainRotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchDnsFromModoboaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $domainId;

    /**
     * Create a new job instance.
     */
    public function __construct($domainId)
    {
        $this->domainId = $domainId;
    }

    /**
     * Execute the job.
     */
    public function handle(ModoboaService $modoboa)
    {
        // 1. Database se domain ka naam uthaein ID ke zariye
        $domainRecord = DomainRotation::where('domain_id', $this->domainId)->first();

        if (!$domainRecord) {
            Log::error("FetchDnsFromModoboaJob: Domain record not found for ID: {$this->domainId}");
            return;
        }

        // 2. Modoboa API se DNS configuration fetch karein
        // Note: Modoboa aksar 'dns_configuration' endpoint alag se deta hai ya 
        // domain detail API ke andar hi records bhejta hai.
        $res = $modoboa->getDomainDetails($this->domainId);

        if (isset($res['dkim_public_key'])) {
            // DKIM key nikalne ke liye Modoboa ke records loop karein
            $dkimRecord = null;
            

            if ($dkimRecord) {
                // 3. DKIM mil gaya, ab Namecheap DNS configure karne wali job trigger karein
                // Hum domain ka naam aur cleaned DKIM bhejenge
                ConfigureNamecheapDNSJob::dispatch($domainRecord->domain_name, $dkimRecord);
                
                Log::info("FetchDnsFromModoboaJob: DKIM fetched for {$domainRecord->domain_name}");
            } else {
                // Agar DKIM abhi generate nahi hua, toh dobara try karein (Release back to queue)
                $this->release(120); 
                Log::warning("FetchDnsFromModoboaJob: DKIM not ready for {$domainRecord->domain_name}, retrying...");
            }
        } else {
            Log::error("FetchDnsFromModoboaJob: Failed to fetch DNS details for ID: {$this->domainId}");
        }
    }
}