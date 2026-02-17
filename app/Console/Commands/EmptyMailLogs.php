<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Console\Command;

class EmptyMailLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:empty-mail-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $freeUserIds = User::whereDoesntHave('subscription', function ($query) {
            $query->where('status', 'active');
        })->pluck('id');

        EmailLog::whereIn('user_id', $freeUserIds)->delete();
    }
}
