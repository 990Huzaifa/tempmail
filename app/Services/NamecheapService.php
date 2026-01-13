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
        // Agar user ne keyword nahi diya to auto-generate karo
        $keyword = $customKeyword ?? $this->generateKeywords();

        // Agar user ne TLDs nahi diye to default saste wale use karo
        $tlds = $customTlds ?? ['.xyz', '.site', '.online', '.top'];

        // Namecheap API ko ek hi baar mein list bhejna zyada fast hai (comma separated)
        $domainList = '';
        foreach ($tlds as $tld) {
            $domainList .= $keyword . $tld . ',';
        }
        $domainList = rtrim($domainList, ',');

        $response = Http::get($this->baseUrl, array_merge($this->config, [
            'Command' => 'namecheap.domains.check',
            'DomainList' => $domainList,
        ]));

        $xml = simplexml_load_string($response->body());
        
        // Namecheap XML mein har domain ke liye alag result bhejta hai
        foreach ($xml->CommandResponse->DomainCheckResult as $result) {
            if ((string) $result['Available'] === 'true') {
                // Hum pehla available domain return kar denge
                return (string) $result['Domain']; 
            }
        }

        return null;
    }

    // 3. Buy Domain & Disable Auto-Renew
    public function purchaseDomain($domain, $userData)
    {
        $params = array_merge($this->config, [
            'Command' => 'namecheap.domains.create',
            'DomainName' => $domain,
            'Years' => 1,
            'AddFreeWhoisguard' => 'yes',
            'WGEnabled' => 'yes',
            // Is Command se auto-renew by default handle hota hai, 
            // lekin hum purchase ke baad alag se command bhejenge safety ke liye.
            'RegistrantFirstName' => $userData['first_name'],
            'RegistrantLastName' => $userData['last_name'],
            'RegistrantAddress1' => $userData['address'],
            'RegistrantCity' => $userData['city'],
            'RegistrantStateProvince' => $userData['state'],
            'RegistrantPostalCode' => $userData['zip'],
            'RegistrantCountry' => $userData['country'],
            'RegistrantPhone' => $userData['phone'],
            'RegistrantEmailAddress' => $userData['email'],
        ]);

        $response = Http::get($this->baseUrl, $params);
        $result = simplexml_load_string($response->body());

        if ($result->Status == 'OK') {
            // Purchase successful, ab Auto-Renew OFF karein
            $this->disableAutoRenew($domain);
            return true;
        }
        return false;
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