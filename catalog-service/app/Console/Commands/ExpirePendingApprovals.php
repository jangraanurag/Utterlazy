<?php

namespace App\Console\Commands;

use App\Models\Offer;
use App\Models\SponsoredListing;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingApprovals extends Command
{
    protected $signature = 'approvals:expire-stale';

    protected $description = 'Expire pending approvals whose scheduled start date has passed';

    public function handle(): int
    {
        $count = 0;

        $listings = SponsoredListing::where('approval_status', 'pending')
            ->where('start_date', '<', now())
            ->get();

        foreach ($listings as $listing) {
            $listing->update(['approval_status' => 'expired']);

            if ($listing->created_by_type === 'account_manager' && $listing->created_by_id) {
                $creator = User::find($listing->created_by_id);
                if ($creator) {
                    try {
                        app(FcmService::class)->sendToUser($creator, 'general', [
                            'title' => 'Approval Expired',
                            'body' => 'Your ad submission has expired because the approval window passed.',
                            'type' => 'approval_expired',
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Expire notification failed: '.$e->getMessage());
                    }
                }
            }

            $count++;
        }

        $offers = Offer::where('approval_status', 'pending')
            ->where('start_date', '<', now())
            ->get();

        foreach ($offers as $offer) {
            $offer->update(['approval_status' => 'expired']);

            if ($offer->created_by_type === 'account_manager' && $offer->created_by_id) {
                $creator = User::find($offer->created_by_id);
                if ($creator) {
                    try {
                        app(FcmService::class)->sendToUser($creator, 'general', [
                            'title' => 'Approval Expired',
                            'body' => 'Your offer submission has expired because the approval window passed.',
                            'type' => 'approval_expired',
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Expire notification failed: '.$e->getMessage());
                    }
                }
            }

            $count++;
        }

        $this->info("Expired {$count} pending approval(s).");
        Log::info("ExpirePendingApprovals: expired {$count} item(s).");

        return Command::SUCCESS;
    }
}
