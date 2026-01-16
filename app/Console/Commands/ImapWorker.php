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

        $this->info("Worker Started for: " . $domain->master_email);

        // Loop taake agar connection drop ho to dobara connect ho sake
        while (true) {
            try {
                $client = Client::make([
                    'host'          => 'mail.techvince.com',
                    'port'          => 993,
                    'encryption'    => 'ssl',
                    'validate_cert' => true,
                    'username'      => $domain->master_email,
                    'password'      => decrypt($domain->master_password),
                    'protocol'      => 'imap'
                ]);

                $client->connect();
                $folder = $client->getFolder('INBOX');

                $this->info("Connected and Checking for mails...");

                // Manual Polling Loop
                while ($client->isConnected()) {
                    // Sirf 'Unseen' mails uthayein
                    $messages = $folder->query()->unseen()->get();

                    if ($messages->count() > 0) {
                        $this->info($messages->count() . " new message(s) found.");
                        foreach ($messages as $message) {
                            $this->processIncomingMail($message, $domain);
                        }
                    }

                    // 5 seconds ka intezar (Isay aap kam ya zyada kar sakte hain)
                    sleep(5);
                    
                    // Connection zinda rakhne ke liye NOOP/Ping
                    $client->getConnection()->noop();
                }
            } catch (\Exception $e) {
                $this->error("Connection lost: " . $e->getMessage() . ". Retrying in 10s...");
                sleep(10);
            }
        }
    }

    protected function processIncomingMail($message, $domain)
    {
        $this->info("--- New Message Detected ---");
        
        // 1. To Address Nikalein
        $toAddress = $message->getTo()[0]->mail; 
        $this->info("Step 1: Mail sent to: " . $toAddress);

        // 2. Database mein Alias Check Karein
        $alias = TempAlias::where('alias_email', $toAddress)->first();

        if (!$alias) {
            $this->error("Step 2: Alias NOT found in database for: " . $toAddress);
            return;
        }

        $this->info("Step 2: Alias found for User ID: " . $alias->user_id);

        try {
            // 3. Email Log Save Karein
            $log = EmailLog::create([
                'user_id'       => $alias->user_id,
                'temp_alias_id' => $alias->id,
                'from_email'    => $message->getFrom()[0]->mail,
                'from_name'     => $message->getFrom()[0]->personal ?? 'No Name',
                'subject'       => $message->getSubject(),
                'body_html'     => $message->getHTMLBody() ?? $message->getTextBody(),
                'received_at'   => now(),
            ]);

            $this->info("Step 3: Email Log saved in DB. ID: " . $log->id);

            // 4. Attachments Check Karein
            if ($message->hasAttachments()) {
                $this->info("Step 4: Attachments detected. Processing...");
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
                $this->info("Step 4: All attachments saved.");
            } else {
                $this->info("Step 4: No attachments found.");
            }

            // 5. Event Trigger
            $this->info("Step 5: Firing Real-time Event...");
            event(new \App\Events\NewMailReceived($log));

            // 6. Mark as Seen
            $message->setFlag('Seen');
            $this->info("Done: Message processed successfully!");

            $message->delete();
            $message->getFolder()->expunge();
            $this->info("Done: Message deleted from Modoboa server.");

        } catch (\Exception $e) {
            $this->error("ERROR at Step 3/4/5: " . $e->getMessage());
            \Log::error("IMAP Worker Error: " . $e->getMessage());
        }
    }
}