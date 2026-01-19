<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TempAlias;
use App\Models\EmailLog;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Support\Facades\Log;

class TempMailCleanup extends Command
{
    protected $signature = 'tempmail:cleanup';
    protected $description = 'Delete free aliases and all associated data after 10 minutes';

    public function handle()
    {
        $expiredAliases = TempAlias::where('expires_at', '<', now())->get();

        $this->info("Found " . $expiredAliases->count() . " expired aliases.");

        foreach ($expiredAliases as $alias) {
            
            // 2. Modoboa se Alias Khatam Karna
            $this->deleteFromModoboa($alias);

            // 3. Attachments aur Logs ka Safaya
            $logs = EmailLog::where('temp_alias_id', $alias->id)->get();
            
            foreach ($logs as $log) {
                // Physical storage se files delete karna
                $attachments = EmailAttachment::where('email_log_id', $log->id)->get();
                foreach ($attachments as $at) {
                    unlink(public_path($at->file_path));
                }
                $log->delete();
            }

            // 4. Database se Alias Remove karna
            $alias->delete();
            $this->info("Permanently deleted: " . $alias->alias_email);
        }
    }

}