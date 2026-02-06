<?php

namespace App\Services;

use App\Models\DomainRotation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                "quota"        => $quota ? (int)$quota : 5 // Quota int format mein
            ],
            "role"          => "SimpleUsers",
            "language"      => "en",
            "phone_number"  => $phoneNumber ?? "+923133021352",
            "totp_enabled"  => false,
            "webauthn_enabled" => false,
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

    public function createTempAlias($aliasEmail, $forwardTo)
    {
        try {
            
            $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl  . 'aliases/', [
                'address'     => $aliasEmail, // Jo naya temp mail user ko dikhega
                'recipients'  => [$forwardTo], // Jahan emails receive honi hain (Master Inbox)
                'enabled'     => true,
                'description' => 'Temporary mail for app user'
            ]);

            if ($response->successful()) {
                return [
                    'status'  => 'success',
                    'alias'   => $aliasEmail,
                    'target'  => $forwardTo,
                    'data'    => $response->json()
                ];
            }

            return [
                'status'  => 'error',
                'message' => $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'status'  => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function deleteTempAlias($modoboaId)
    {
        $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type'  => 'application/json',
            ])->delete($this->baseUrl  . 'aliases/' . $modoboaId . '/');

        return $response->json();
    }

    public function updateAliasRecipients($aliasId, array $recipients)
    {
        // $recipients array looks like: ['master@domain.com', 'user@gmail.com']
        $response = Http::withHeaders([
                'Authorization' => 'Token ' . $this->apiToken,
                'Content-Type'  => 'application/json',
            ])->patch("{$this->baseUrl}aliases/{$aliasId}/", [
                'recipients' => $recipients
            ]);
            Log::info('service response'.$response->body());
        return $response->successful();
    }


    // ModoboaService.php

    // public function sendOutgoingEmail($masterUser, $fromEmail, $toEmails, $subject, $bodyHtml, $attachments = [])
    // {
    //     // Agar $toEmails array hai, to usay comma-separated string banayein
    //     $toRecipientString = implode(',', $toEmails);

    //     $url = "http://72.60.114.133:8001/send-email";

    //     $request = Http::withHeaders([
    //         'x-api-key' => config('services.mail_hook.api_key'), // Aapki security key
    //     ])->asMultipart();

    //     // Basic Form Fields
    //     $request->attach('master_user', $masterUser)
    //             ->attach('from_email', $fromEmail)
    //             ->attach('to_email', $toRecipientString)
    //             ->attach('subject', $subject)
    //             ->attach('body_html', $bodyHtml);

    //     // Attachments Handling
    //     if (!empty($attachments)) {
    //         foreach ($attachments as $file) {
    //             // $file should be an instance of UploadedFile
    //             $request->attach(
    //                 'attachments', 
    //                 file_get_contents($file->getRealPath()), 
    //                 $file->getClientOriginalName()
    //             );
    //         }
    //     }

    //     $response = $request->post($url);

    //     return $response->json();
    // }

    public function sendOutgoingEmail($masterUser, $fromEmail, $toEmails, $subject, $bodyHtml, $attachments = [])
    {
        $toRecipientString = implode(',', $toEmails);
        $url = "http://72.60.114.133:8001/send-email";

        $pendingRequest = Http::withHeaders([
            'x-api-key' => config('services.mail_hook.api_key'),
        ]);

        // Attachments ko loop mein add karein
        if (!empty($attachments)) {
            foreach ($attachments as $file) {
                $pendingRequest->attach(
                    'attachments', 
                    fopen($file->getRealPath(), 'r'), 
                    $file->getClientOriginalName()
                );
            }
        }

        // Baqi sara data POST body mein bhejein
        $response = $pendingRequest->post($url, [
            'master_user' => $masterUser,
            'from_email'  => $fromEmail,
            'to_email'    => $toRecipientString,
            'subject'     => $subject,
            'body_html'   => $bodyHtml,
        ]);

        return $response->json();
    }

}