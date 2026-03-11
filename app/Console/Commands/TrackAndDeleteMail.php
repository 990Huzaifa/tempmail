<?php

namespace App\Console\Commands;

use App\Models\DomainRotation;
use App\Models\TempAlias;
use App\Services\ModoboaService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class TrackAndDeleteMail extends Command
{
    protected $signature = 'app:track-and-delete-mail';
    protected $description = 'Downgraded users mail cleanup (keep only 1 mail per alias)';
    private $modoboa;
    
    public function __construct(ModoboaService $modoboa)
    {
        parent::__construct();
        $this->modoboa = $modoboa;
    }
    public function handle()
    {
        $this->info('Starting subscription tracking...');

        Subscription::where('status', '=', 'expired')
            ->where('updated_at', '<', Carbon::now()->subDays(3))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->each(function ($userId) {

                $aliases = TempAlias::where('user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->get();

                if ($aliases->count() <= 1) {
                    return;
                }

                // Keep latest
                $keepAlias = $aliases->first();

                // Delete rest
                $aliases->skip(1)->each(function ($alias) {

                    try {

                        // 1️⃣ Delete from Modoboa first
                        $this->modoboa->deleteTempAlias($alias->alias_modoboa_id);

                        // 2️⃣ Then DB transaction
                        DB::transaction(function () use ($alias) {

                            DomainRotation::where('id', $alias->domain_id)
                                ->decrement('alias_count');

                            $alias->delete();
                        });

                        $this->info("Deleted alias {$alias->id}");

                        $this->info("Deleted alias {$alias->id}");

                    } catch (Exception $e) {
                        $this->error("Failed deleting alias {$alias->id}");
                        return;
                    }
                });

                // update remaining one
                TempAlias::where('id', $keepAlias->id)->update([
                    'expires_at' => Carbon::now()->addMonth()
                ]);
            });

        $this->info('Subscription tracking completed.');
    }
}