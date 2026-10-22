<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->uuid('global_category_id')->nullable()->after('category_id');
            $table->foreign('global_category_id')->references('id')->on('global_categories')->onDelete('set null');
        });

        // Seed global categories
        $categories = [
            [
                'id' => (string) Str::uuid(),
                'name' => 'Biryani',
                'description' => 'Aromatic slow-cooked rice dishes with rich Indian spices.',
                'image_url' => 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Burgers',
                'description' => 'Juicy burgers and patties served with crispy fries.',
                'image_url' => 'https://images.unsplash.com/photo-1571091718767-18b5b1457add?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Pizza',
                'description' => 'Cheesy oven-baked pizzas with various toppings.',
                'image_url' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'North Indian',
                'description' => 'Rich curries, flatbreads, and traditional North Indian cuisine.',
                'image_url' => 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Chinese',
                'description' => 'Authentic noodles, rice, and delicious stir-fries.',
                'image_url' => 'https://images.unsplash.com/photo-1552611052-33e04de081de?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Desserts',
                'description' => 'Sweet treats, ice creams, and desserts.',
                'image_url' => 'https://images.unsplash.com/photo-1551024601-bec78acc704b?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'South Indian',
                'description' => 'Crispy dosas, soft idlis, and traditional South Indian meals.',
                'image_url' => 'https://images.unsplash.com/photo-1630383249896-424e482df921?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Rolls',
                'description' => 'Delicious wraps and rolls filled with savory fillings.',
                'image_url' => 'https://images.unsplash.com/photo-1626776876729-babd0f23583a?auto=format&fit=crop&w=300&q=80',
                'sort_order' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('global_categories')->insert($categories);

        // Map existing items
        $biryaniCategoryId = DB::table('global_categories')->where('name', 'Biryani')->value('id');
        $northIndianCategoryId = DB::table('global_categories')->where('name', 'North Indian')->value('id');

        if ($biryaniCategoryId) {
            DB::table('menu_items')
                ->where('name', 'like', '%Biryani%')
                ->update(['global_category_id' => $biryaniCategoryId]);
        }

        if ($northIndianCategoryId) {
            DB::table('menu_items')
                ->where('name', 'like', '%Kebab%')
                ->orWhere('name', 'like', '%Dal%')
                ->orWhere('name', 'like', '%Chicken%')
                ->orWhere('name', 'like', '%Naan%')
                ->update(['global_category_id' => $northIndianCategoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['global_category_id']);
            $table->dropColumn('global_category_id');
        });

        Schema::dropIfExists('global_categories');
    }
};
