<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DomainRotation;
use App\Models\TempAlias;
use App\Models\EmailLog;
use App\Models\EmailAttachment;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImapWorker extends Command
{
    // Command chalane ka tareeka: php artisan imap:listen {domain_id}
    protected $signature = 'imap:listen {id}';
    protected $description = 'Listen to IMAP for a specific master account';

    public function handle()
    {
        $id = $this->argument('id');
        $domain = DomainRotation::find($id);

        if (!$domain || !$domain->master_email) {
            $this->error("Domain or Master Account not found!");
            return;
        }

        // 1. Connection Config (Dynamic)
        $client = Client::make([
            'host'          => 'mail.techvince.com',
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => true,
            'username'      => $domain->master_email,
            'password'      => decrypt($domain->master_password), // Password decrypt kiya
            'protocol'      => 'imap'
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        $this->info("Listening for: " . $domain->master_email);

        // 2. IMAP IDLE (Real-time wait)
        $folder->idle(function($message) use ($domain) {
            $this->processIncomingMail($message, $domain);
        });
    }

    protected function processIncomingMail($message, $domain)
    {
        // Kis alias ko bheji gayi hai mail?
        $toAddress = $message->getTo()[0]->mail; 

        // Database mein alias check karein
        $alias = TempAlias::where('alias_email', $toAddress)->first();

        if ($alias) {
            // A. Email Log Save Karein
            $log = EmailLog::create([
                'user_id'       => $alias->user_id,
                'temp_alias_id' => $alias->id,
                'from_email'    => $message->getFrom()[0]->mail,
                'from_name'     => $message->getFrom()[0]->personal,
                'subject'       => $message->getSubject(),
                'body_html'     => $message->getHTMLBody() ?? $message->getTextBody(),
                'received_at'   => now(),
            ]);

            // B. Attachments Handle Karein
            if ($message->hasAttachments()) {
                foreach ($message->getAttachments() as $at) {
                    $filename = Str::uuid() . '_' . $at->getName();
                    Storage::disk('public')->put('attachments/' . $filename, $at->getContent());

                    EmailAttachment::create([
                        'email_log_id' => $log->id,
                        'file_name'    => $at->getName(),
                        'file_path'    => 'storage/attachments/' . $filename,
                        'file_type'    => $at->getMimeType(),
                        'file_size'    => $at->getSize(),
                    ]);
                }
            }

            // C. Real-time Event Fire Karein (Pusher/Reverb)
            event(new \App\Events\NewMailReceived($log));

            $this->info("New mail saved for alias: " . $toAddress);
            
            // Mail ko 'Seen' mark kar dein taake loop na bane
            $message->setFlag('Seen');
        }
    }
}