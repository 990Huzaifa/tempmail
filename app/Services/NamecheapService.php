<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NamecheapService
{
    protected $baseUrl;
    protected $config;

    public function __construct()
    {
        $this->config = [

            'ApiUser' => config('services.namecheap.user'),
            'ApiKey' => config('services.namecheap.key'),
            'UserName' => config('services.namecheap.user'),
            'ClientIp' => config('services.namecheap.ip'),
        ];

        $this->baseUrl = config('services.namecheap.base_url');
    }

    // 1. Keyword Auto-Generate (Laravel Helper Use Karein)
    public function generateKeywords()
    {
        // Aap yahan random strings ya kisi wordlist ka use kar sakte hain
        return Str::random(4) . 'mail';
    }

    // 2. Domain Search (Cheap TLDs: .xyz, .site, .online, .top)
    public function searchCheapDomain($customKeyword = null, $customTld = null)
    {
        $keyword = $customKeyword ?? $this->generateKeywords();
        $defaultTlds = ['.store', '.site', '.space'];
        
        if ($customTld) {
            // Ensure karein ke TLD dot (.) se start ho raha ho
            $customTld = str_starts_with($customTld, '.') ? $customTld : '.' . $customTld;
            
            // Custom TLD ko start mein add karein aur duplicates remove karein
            $tlds = array_unique(array_merge([$customTld], $defaultTlds));
        } else {
            $tlds = $defaultTlds;
        }

        $domainList = '';
        foreach ($tlds as $tld) {
            $domainList .= $keyword . $tld . ',';
        }
        $domainList = rtrim($domainList, ',');

        // Step 1: Check Availability
        $response = Http::get($this->baseUrl, array_merge($this->config, [
            'Command' => 'namecheap.domains.check',
            'DomainList' => $domainList,
        ]));

        $xml = simplexml_load_string($response->body());
        $availableDomains = [];
        // return $xml;
        foreach ($xml->CommandResponse->DomainCheckResult as $result) {
            if ((string) $result['Available'] === 'true') {
                $availableDomains[] = (string) $result['Domain'];
            }
        }

        if (empty($availableDomains)) return "no domain found";
       
        return [
            "success" => true,
            "list" => $availableDomains
        ];
    }

    public function getTldPrice($domain)
    {
        $domain = ltrim($domain, '.');
        $parts = explode('.', $domain);
        $tld = end($parts);

        // API Call
        $response = Http::get($this->baseUrl, array_merge($this->config, [
            'Command'     => 'namecheap.users.getPricing',
            'ProductType' => 'DOMAIN',
            'ProductName' => $tld,
        ]));

        $xml = simplexml_load_string($response->body());

        // Error Handling
        if (isset($xml->Errors->Error)) {
            return [
                'success' => false,
                'message' => (string) $xml->Errors->Error
            ];
        }

        $pricingData = null;

        // Loop through ProductCategory (register, renew, etc)
        foreach ($xml->CommandResponse->UserGetPricingResult->ProductType->ProductCategory as $category) {

            // We only want registration price
            if ((string) $category['Name'] !== 'register') {
                continue;
            }

            foreach ($category->Product->Price as $price) {

                // Only 1 year price
                if (
                    (string) $price['Duration'] === '1' &&
                    (string) $price['DurationType'] === 'YEAR'
                ) {
                    $yourPrice     = (float) $price['YourPrice'];
                    $additionalFee = (float) $price['AdditionalCost'];

                    $pricingData = [
                        'domain'       => $domain,
                        'tld'          => $tld,
                        'base_price'   => $yourPrice,
                        'icann_fee'    => $additionalFee,
                        'total_price'  => number_format($yourPrice + $additionalFee, 2),
                        'currency'     => (string) $price['Currency']
                    ];

                    break 2; // Exit both loops
                }
            }
        }

        if ($pricingData) {
            return [
                'success'  => true,
                'price'    => $pricingData['total_price'],
                'currency' => $pricingData['currency']
            ];
        }

        return [
            'success' => false,
            'message' => 'Pricing not found for this domain extension'
        ];
    }

    // 3. Buy Domain & Disable Auto-Renew
    public function purchaseDomain($domain, $userData)
    {
        // Basic Params
        $baseParams = [
            'Command' => 'namecheap.domains.create',
            'DomainName' => $domain,
            'Years' => 1,
            'AddFreeWhoisguard' => 'yes',
            'WGEnabled' => 'yes',
        ];

        // Contact details jo sab jagah repeat hongi
        $contactDetails = [
            'FirstName' => $userData['first_name'],
            'LastName'  => $userData['last_name'],
            'Address1'  => $userData['address'],
            'City'      => $userData['city'],
            'StateProvince' => $userData['state'],
            'PostalCode'    => $userData['zip'],
            'Country'       => $userData['country'],
            'Phone'         => $userData['phone'], // Format: +92.313...
            'EmailAddress'  => $userData['email'],
        ];

        // Chaar types ke contacts generate karna
        $contactTypes = ['Registrant', 'Tech', 'Admin', 'AuxBilling'];
        $finalContacts = [];

        foreach ($contactTypes as $type) {
            foreach ($contactDetails as $key => $value) {
                $finalContacts[$type . $key] = $value;
            }
        }

        $params = array_merge($this->config, $baseParams, $finalContacts);

        $response = Http::get($this->baseUrl, $params);
        $xml = simplexml_load_string($response->body());
        Log::info("Raw Namecheap Response:", json_decode(json_encode($xml), true));

        if ((string)$xml['Status'] === 'OK') {
            
            $createResult = $xml->CommandResponse->DomainCreateResult;
            
            // Auto-Renew OFF (Background mein)
            $this->disableAutoRenew($domain);

            return [
                'success' => true,
                'domain' => (string)$createResult['Domain'],
                'charged' => (string)$createResult['ChargedAmount'],
                'order_id' => (string)$createResult['OrderID']
            ];
        }
        $errorMessage = isset($xml->Errors->Error) ? (string)$xml->Errors->Error : 'Unknown Namecheap Error';
        Log::error("Purchase Failed for $domain: " . $errorMessage);

        return [
            'success' => false,
            'message' => $errorMessage
        ];
    }

    // 4. Disable Auto-Renewal
    public function disableAutoRenew($domain)
    {
        Http::get($this->baseUrl, array_merge($this->config, [
            'Command' => 'namecheap.domains.setRenewalExtension',
            'DomainName' => $domain,
            'PromotionCode' => '',
        ]));
        // Note: Namecheap API mein agar aap renewal command nahi bhejte to wo default OFF hi rehta hai 
        // lekin ye command safety ke liye hai.
    }

    // public function syncDomainWithModoboa($domain, $modoboaApiResponse) 
    // {
    //     // Modoboa API se records extract karna (Example keys)
    //     $rawDkim = $modoboaApiResponse['dkim_record']; // "v=DKIM1; k=rsa; p=MIIB..."
    //     $rawSpf  = $modoboaApiResponse['spf_record'];  // "v=spf1 mx ~all"
        
    //     $data = [
    //         'dkim' => formatModoboaRecord($rawDkim, 'DKIM'),
    //         'spf'  => formatModoboaRecord($rawSpf, 'SPF'),
    //     ];

    //     // Ab wahi purana setup function call karein
    //     return $this->setupModoboaDNS($domain);
    // }

    // 5. Setup Modoboa DNS Records (MX, SPF, DKIM, DMARC)
    public function setupModoboaDNS($domain, $dkimKey)
    {
        // Domain ko SLD aur TLD mein split karein (e.g., techvince aur com)
        $parts = explode('.', $domain);
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array_merge($this->config, [
            'Command' => 'namecheap.domains.dns.setHosts',
            'SLD' => $sld,
            'TLD' => $tld,
            'EmailType' => 'MX',
            
            // 1. A Record for Mail Server (mail.domain.com)
            'HostName1' => 'mail',
            'RecordType1' => 'A',
            'Address1' => $this->config['ClientIp'],

            // 2. MX Record (@ -> mail.domain.com)
            'HostName2' => '@',
            'RecordType2' => 'MX',
            'Address2' => 'mail.' . $domain,
            'MXPref2' => '10',

            // 3. SPF Record (Modoboa provide karta hai)
            'HostName3' => '@',
            'RecordType3' => 'TXT',
            'Address3' => 'v=spf1 mx ~all', // e.g. "v=spf1 mx ~all"

            // 4. DKIM Record (Sabse tricky part)
            // Modoboa ki lambi key ko Namecheap handle kar leta hai agar quotes sahi hon
            'HostName4' => 'modoboa._domainkey',
            'RecordType4' => 'TXT',
            'Address4' => 'v=DKIM1; k=rsa; p=' .$dkimKey, 

            // 5. DMARC Record
            'HostName5' => '_dmarc',
            'RecordType5' => 'TXT',
            'Address5' => 'v=DMARC1; p=quarantine; pct=100;',

            // 6. Autoconfig & Autodiscover (For Outlook/Thunderbird)
            'HostName6' => 'autoconfig',
            'RecordType6' => 'CNAME',
            'Address6' => 'mail.' . $domain,
            
            'HostName7' => 'autodiscover',
            'RecordType7' => 'CNAME',
            'Address7' => 'mail.' . $domain,
        ]);

        $response = Http::get($this->baseUrl, $params);
        return simplexml_load_string($response->body());
    }


    public function getlist()
    {
        $response = Http::get($this->baseUrl, array_merge($this->config, [
            'Command'     => 'namecheap.domains.getList',
        ]));

        $xml = simplexml_load_string($response->body());
        return $xml;
    }
}