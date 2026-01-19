<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TempAlias;
use App\Models\EmailLog;
use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class TempMailCleanup extends Command
{
    protected $signature = 'tempmail:cleanup';
    protected $description = 'Delete free aliases and all associated data after 10 minutes';

    public function handle()
    {
        // 1. Sirf 'free' users ke wo aliases uthao jo 10 mins purane hain
        $expiredAliases = TempAlias::whereHas('user', function($q) {
                $q->where('plan', 'free'); 
            })
            ->where('created_at', '<', now()->subMinutes(10))
            ->get();

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
                    $purePath = str_replace('storage/', '', $at->file_path);
                    if (Storage::disk('public')->exists($purePath)) {
                        Storage::disk('public')->delete($purePath);
                    }
                    $at->delete();
                }
                $log->delete();
            }

            // 4. Database se Alias Remove karna
            $alias->delete();
            $this->info("Permanently deleted: " . $alias->alias_email);
        }
    }

    protected function deleteFromModoboa($alias)
    {
        try {
            // Modoboa Admin API Token aur URL
            $response = Http::withToken('YOUR_MODOBOA_API_TOKEN')
                ->delete("https://mail.techvince.com/api/v2/aliases/{$alias->modoboa_id}/");
            
            return $response->successful();
        } catch (\Exception $e) {
            \Log::error("Modoboa Deletion Failed for {$alias->alias_email}: " . $e->getMessage());
            return false;
        }
    }
}