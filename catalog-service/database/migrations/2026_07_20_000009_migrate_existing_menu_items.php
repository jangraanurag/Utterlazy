<?php

use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Set sensible defaults for existing menu items
        // Detect prep type based on name/description keywords (backward compat)
        $nonVegKeywords = [
            'chicken', 'mutton', 'beef', 'fish', 'pork', 'egg',
            'shrimp', 'crab', 'meat', 'bacon', 'ham', 'lamb', 'prawn',
            'salmon', 'tuna', 'pepperoni', 'salami', 'kabab', 'kebab', 'tikka',
        ];

        MenuItem::whereNull('prep_type')->chunkById(100, function ($items) use ($nonVegKeywords) {
            foreach ($items as $item) {
                $nameDesc = strtolower(($item->name ?? '').' '.($item->description ?? ''));
                $isNonVeg = false;
                foreach ($nonVegKeywords as $keyword) {
                    if (str_contains($nameDesc, $keyword)) {
                        $isNonVeg = true;
                        break;
                    }
                }

                DB::table('menu_items')
                    ->where('id', $item->id)
                    ->update([
                        'prep_type' => $isNonVeg ? 'Non-Veg' : 'Veg',
                        'fasting_suitable' => false,
                        'tax_type' => 'NONE',
                        'tax_included' => false,
                        'packaging_tax_type' => 'NONE',
                    ]);
            }
        });

        // Migrate existing image_url to menu_item_images table
        MenuItem::whereNotNull('image_url')->chunkById(100, function ($items) {
            foreach ($items as $item) {
                $exists = MenuItemImage::where('menu_item_id', $item->id)->exists();
                if (! $exists) {
                    DB::table('menu_item_images')->insert([
                        'id' => (string) Str::uuid(),
                        'menu_item_id' => $item->id,
                        'image_url' => $item->image_url,
                        'is_cover' => true,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // No reverse — data migration for defaults
    }
};
