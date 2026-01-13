<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
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

        $this->baseUrl = "https://api.sandbox.namecheap.com/xml.response";
            // ? 'https://api.sandbox.namecheap.com/xml.response'
            // : 'https://api.namecheap.com/xml.response';
    }

    // 1. Keyword Auto-Generate (Laravel Helper Use Karein)
    public function generateKeywords()
    {
        // Aap yahan random strings ya kisi wordlist ka use kar sakte hain
        return Str::random(8) . rand(10, 99);
    }

    // 2. Domain Search (Cheap TLDs: .xyz, .site, .online, .top)
    public function searchCheapDomain($customKeyword = null, $customTlds = null)
    {
        $keyword = $customKeyword ?? $this->generateKeywords();
        $tlds = $customTlds ?? ['.xyz', '.site', '.online', '.top'];

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

        foreach ($xml->CommandResponse->DomainCheckResult as $result) {
            if ((string) $result['Available'] === 'true') {
                $availableDomains[] = (string) $result['Domain'];
            }
        }

        if (empty($availableDomains)) return "no domain found";

        // Step 2: Get Pricing for Available TLDs
        $pricingResponse = Http::get($this->baseUrl, array_merge($this->config, [
            'Command' => 'namecheap.users.getPricing',
            'ProductType' => 'DOMAIN',
            'ProductName' => 'REGISTER', 
        ]));

        $pricingXml = simplexml_load_string($pricingResponse->body());
        $resultsWithPrice = [];

        // Namecheap XML namespaces handle karne ke liye
        $ns = $pricingXml->getNamespaces(true);
        $commandResponse = $pricingXml->CommandResponse->UserGetPricingResult;

        foreach ($availableDomains as $domain) {
            $ext = pathinfo($domain, PATHINFO_EXTENSION); // e.g., 'xyz'
            
            // ProductCategory loop (Register, Renew, etc.)
            foreach ($commandResponse->ProductType->ProductCategory as $category) {
                if ((string)$category['Name'] !== 'register') continue; // Sirf register wali pricing dekhen

                foreach ($category->Product as $product) {
                    // Namecheap pricing mein TLD name 'xyz' direct product['Name'] mein hota hai
                    if ((string)$product['Name'] === $ext) {
                        
                        // PriceDuration element tak pohnchna (yahan 1 saal ki price hoti hai)
                        $priceDuration = $product->Price->PriceDuration;
                        $price = (float) $priceDuration[0]['Price'];
                        $currency = (string) $priceDuration[0]['Currency'];
                        $additionalFee = (float) $priceDuration[0]['AdditionalCost']; // ICANN Fee

                        $resultsWithPrice[] = [
                            'domain' => $domain,
                            'price'  => $price + $additionalFee, // Total cost
                            'currency' => $currency ?: 'USD'
                        ];
                    }
                }
            }
        }

        return $resultsWithPrice;
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
        $result = simplexml_load_string($response->body());

        if ($result->Status == 'OK') {
            // Purchase successful, ab Auto-Renew OFF karein
            $this->disableAutoRenew($domain);
            return true;
        }
        return $result;
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

    public function syncDomainWithModoboa($domain, $modoboaApiResponse) 
    {
        // Modoboa API se records extract karna (Example keys)
        $rawDkim = $modoboaApiResponse['dkim_record']; // "v=DKIM1; k=rsa; p=MIIB..."
        $rawSpf  = $modoboaApiResponse['spf_record'];  // "v=spf1 mx ~all"
        
        $data = [
            'dkim' => formatModoboaRecord($rawDkim, 'DKIM'),
            'spf'  => formatModoboaRecord($rawSpf, 'SPF'),
        ];

        // Ab wahi purana setup function call karein
        return $this->setupModoboaDNS($domain, $data, 'YOUR_SERVER_IP');
    }

    // 5. Setup Modoboa DNS Records (MX, SPF, DKIM, DMARC)
    public function setupModoboaDNS($domain, $modoboaData, $serverIp)
    {
        // Domain ko SLD aur TLD mein split karein (e.g., techvince aur com)
        $parts = explode('.', $domain);
        $sld = $parts[0];
        $tld = $parts[1];

        $params = array_merge($this->config, [
            'Command' => 'namecheap.domains.dns.setHosts',
            'SLD' => $sld,
            'TLD' => $tld,
            
            // 1. A Record for Mail Server (mail.domain.com)
            'HostName1' => 'mail',
            'RecordType1' => 'A',
            'Address1' => $serverIp,

            // 2. MX Record (@ -> mail.domain.com)
            'HostName2' => '@',
            'RecordType2' => 'MX',
            'Address2' => 'mail.' . $domain,
            'MXPref2' => '10',

            // 3. SPF Record (Modoboa provide karta hai)
            'HostName3' => '@',
            'RecordType3' => 'TXT',
            'Address3' => $modoboaData['spf'], // e.g. "v=spf1 mx ~all"

            // 4. DKIM Record (Sabse tricky part)
            // Modoboa ki lambi key ko Namecheap handle kar leta hai agar quotes sahi hon
            'HostName4' => 'modoboa._domainkey',
            'RecordType4' => 'TXT',
            'Address4' => $modoboaData['dkim'], 

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

}