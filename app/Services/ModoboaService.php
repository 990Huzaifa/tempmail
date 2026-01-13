<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ModoboaService {
    protected $baseUrl;
    protected $apiToken;

    public function __construct() {
        // Modoboa URL: https://mail.techvince.com/api/v2/
        $this->baseUrl = 'https://mail.techvince.com/api/v2/';
        $this->apiToken = config('services.modoboa.token');
    }

    // 1. Modoboa mein Naya Domain Add Karein
    public function createDomain($domainName) {
        $response = Http::withToken($this->apiToken)
            ->post($this->baseUrl . 'domains/', [
                'name' => $domainName,
                'type' => 'domain', // Default domain type
                'enabled' => true,
                'enable_dkim' => true, // DKIM hamesha on rakhein
            ]);

        return $response->json();
    }

    // 2. Domain ki DNS details (DKIM/SPF) fetch karein
    public function getDomainDetails($id) {
        // Modoboa API se DNS info nikalne ka endpoint
        $response = Http::withToken($this->apiToken)
            ->get($this->baseUrl . "domains/{$id}");

        return $response->json();
    }

    public function formatDkimForNamecheap($modoboaDnsArray) 
    {
    // Modoboa response mein DKIM record dhoondein
        foreach ($modoboaDnsArray as $record) {
            if ($record['type'] === 'dkim') {
                // Quotes aur brackets hata kar clean string banayein
                return str_replace(['"', '(', ')', ' ', "\n", "\r"], '', $record['value']);
            }
        }
        return null;
    }
}