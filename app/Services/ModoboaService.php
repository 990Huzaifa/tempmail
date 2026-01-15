<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $this->apiToken,
        ])->post($this->baseUrl . 'domains/', [
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
        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $this->apiToken,
        ])->get($this->baseUrl . "domains/{$id}/");

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

    public function createAccount($email, $password = null, $phoneNumber = null, $quota = null)
    {
        // URL ke end mein trailing slash hona lazmi hai
        $url = $this->baseUrl . "accounts/";

        $payload = [
            "username"      => $email,
            "first_name"    => "master",
            "last_name"     => "inbox",
            "is_active"     => true,
            "mailbox"       => [
                "full_address" => $email,
                "quota"        => $quota.'MB' ?? "5MB" // Quota string format mein
            ],
            "role"          => "SimpleUsers",
            "language"      => "en",
            "phone_number"  => $phoneNumber ?? "+92.311123456",
            "totp_enabled"  => true,
            "webauthn_enabled" => true,
            "password"      => $password ?? "Inbox#pass123"
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->post($url, $payload);

            // Agar list wapas aa rahi hai, toh response code 200 hoga lekin 
            // successful creation par Modoboa 201 Created return karta hai.
            if ($response->status() === 201) {
                return $response->json();
            }

            // Debugging ke liye full response log karein
            Log::error("Modoboa API Failed", [
                'status' => $response->status(),
                'body'   => $response->json()
            ]);
            
            return false;

        } catch (\Exception $e) {
            Log::error("Modoboa Service Exception: " . $e->getMessage());
            return false;
        }
    }

}