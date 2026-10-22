<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCityIds extends Command
{
    protected $signature = 'cities:backfill
                            {--dry-run : Report what would change without writing}
                            {--orders : Also copy the restaurant city onto existing orders}
                            {--limit=5000 : Max rows per pass}';

    protected $description = 'Map restaurants to cities by district/state match, then optionally backfill order city_id';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $cities = City::where('is_active', true)->get();
        $byNameState = $cities->keyBy(fn ($c) => mb_strtolower($c->name).'|'.mb_strtolower((string) $c->state));
        $byName = $cities->groupBy(fn ($c) => mb_strtolower($c->name));

        $matched = 0;
        $ambiguous = 0;
        $unmatched = 0;

        Restaurant::whereNull('city_id')
            ->limit($limit)
            ->get()
            ->each(function (Restaurant $restaurant) use (
                $byNameState, $byName, $dryRun, &$matched, &$ambiguous, &$unmatched
            ) {
                $district = mb_strtolower(trim((string) $restaurant->district));
                $state = mb_strtolower(trim((string) $restaurant->state));

                if ($district === '') {
                    $unmatched++;

                    return;
                }

                $city = $state !== ''
                    ? $byNameState->get($district.'|'.$state)
                    : null;

                if (! $city) {
                    $candidates = $byName->get($district, collect());

                    if ($candidates->count() === 1) {
                        $city = $candidates->first();
                    } elseif ($candidates->count() > 1) {
                        $ambiguous++;

                        return;
                    }
                }

                if (! $city) {
                    $unmatched++;

                    return;
                }

                if (! $dryRun) {
                    $restaurant->update(['city_id' => $city->id]);
                }

                $matched++;
            });

        $this->info(sprintf(
            '%s: restaurants matched=%d ambiguous=%d unmatched=%d',
            $dryRun ? '[dry-run]' : '[applied]',
            $matched, $ambiguous, $unmatched,
        ));

        if ($this->option('orders')) {
            $ordersUpdated = 0;

            DB::table('orders')
                ->join('restaurants', 'orders.restaurant_id', '=', 'restaurants.id')
                ->whereNull('orders.city_id')
                ->whereNotNull('restaurants.city_id')
                ->select('orders.id', 'restaurants.city_id')
                ->orderBy('orders.id')
                ->chunk(500, function ($rows) use ($dryRun, &$ordersUpdated) {
                    foreach ($rows as $row) {
                        if (! $dryRun) {
                            DB::table('orders')->where('id', $row->id)->update(['city_id' => $row->city_id]);
                        }
                        $ordersUpdated++;
                    }
                });

            $this->info(sprintf(
                '%s: orders city_id backfilled=%d',
                $dryRun ? '[dry-run]' : '[applied]',
                $ordersUpdated,
            ));
        }

        return self::SUCCESS;
    }
}
