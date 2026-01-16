<?php

namespace App\Jobs;

use App\Models\DomainRotation;
use App\Services\ModoboaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AddAccountToModoboa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $domain;

    public function __construct($domain)
    {
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     */
    public function handle(ModoboaService $modoboa): void
    {
        $email = 'master@' . $this->domain;
        $modoboa->createAccount($email);

        // here we add  master account in db as well
        $domainRotation = DomainRotation::where('domain', $this->domain)->first();
        if ($domainRotation) {
            $domainRotation->update([
                'master_email' => $email,
                'master_password' => 'Inbox#pass123', // Password is not stored
                'is_active' => 1
            ]);
        }
    }
}
