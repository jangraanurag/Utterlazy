<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Services\Search\OpenSearchClient;
use App\Services\Search\RestaurantSearchDocument;
use Illuminate\Console\Command;

class IndexRestaurants extends Command
{
    protected $signature = 'search:index-restaurants {--limit=2000}';

    protected $description = 'Bulk index active restaurants into OpenSearch (no-op when disabled)';

    public function handle(): int
    {
        $client = OpenSearchClient::fromConfig();

        if (! $client) {
            $this->line('OpenSearch disabled (OPENSEARCH_ENABLED=false).');

            return self::SUCCESS;
        }

        $documents = Restaurant::where('is_active', true)
            ->limit((int) $this->option('limit'))
            ->get(['id', 'name', 'cuisine_type', 'rating', 'city_id', 'latitude', 'longitude', 'is_active', 'is_accepting_orders'])
            ->map(fn (Restaurant $restaurant) => RestaurantSearchDocument::build($restaurant->toArray()))
            ->all();

        $ok = $client->bulk($documents);

        $this->info(($ok ? 'Indexed ' : 'Failed to index ').count($documents).' restaurant(s).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
