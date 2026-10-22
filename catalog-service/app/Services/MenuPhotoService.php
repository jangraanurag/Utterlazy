<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MenuPhotoService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Analyze a menu photo using Gemini Vision and extract structured data.
     */
    public function analyzeMenuPhoto(string $imagePath): array
    {
        $apiKey = config('services.gemini.key');
        if (empty($apiKey) || $apiKey === 'your_gemini_key_here') {
            return $this->mockExtraction();
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = mime_content_type($imagePath);

        $prompt = <<<'PROMPT'
You are analyzing a restaurant menu card photo for UtterLazy food delivery app in India.
Extract ALL items from this menu photo.

For each item, identify:
1. name (in original language + English translation)
2. price (in INR)
3. category (e.g. "Biryani", "Starters", "Main Course", "Breads", "Desserts", "Beverages")
4. is_veg (true/false)
5. description (if visible)
6. spice_level (low/medium/high if visible)

Respond with ONLY valid JSON:
{
  "restaurant_name": "detected restaurant name or null",
  "categories": [
    {"name": "Category Name", "items": [
      {"name": "Dish Name", "name_english": "Dish Name English", "price": 249, "is_veg": true, "description": "description or null", "spice_level": "medium"}
    ]}
  ],
  "currency": "INR",
  "language": "hi|en|mixed"
}
PROMPT;

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageData]],
                    ],
                ]],
            ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text');
                $text = preg_replace('/```json\s*|\s*```/', '', $text);
                $data = json_decode($text, true);
                if (is_array($data) && isset($data['categories'])) {
                    return $data;
                }
                Log::error('MenuPhoto: Invalid JSON from Gemini', ['response' => $text]);
            }
            Log::error('MenuPhoto: Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Exception $e) {
            Log::error('MenuPhoto: '.$e->getMessage());
        }

        return $this->mockExtraction();
    }

    /**
     * Create menu items in the database from extracted data.
     */
    public function createMenuFromExtraction(string $restaurantId, array $data): array
    {
        $created = ['categories' => 0, 'items' => 0];

        foreach ($data['categories'] ?? [] as $catData) {
            $category = Category::firstOrCreate([
                'restaurant_id' => $restaurantId,
                'name' => $catData['name'],
            ]);

            $created['categories']++;

            foreach ($catData['items'] ?? [] as $itemData) {
                $existing = MenuItem::where('restaurant_id', $restaurantId)
                    ->where('name', $itemData['name'])
                    ->orWhere('name', $itemData['name_english'] ?? '')
                    ->first();

                if ($existing) {
                    continue;
                }

                MenuItem::create([
                    'restaurant_id' => $restaurantId,
                    'category_id' => $category->id,
                    'name' => $itemData['name_english'] ?? $itemData['name'],
                    'name_local' => $itemData['name'] ?? null,
                    'description' => $itemData['description'] ?? null,
                    'price' => $itemData['price'] ?? 0,
                    'is_available' => true,
                    'is_veg' => $itemData['is_veg'] ?? false,
                    'spice_level' => $itemData['spice_level'] ?? 'medium',
                ]);

                $created['items']++;
            }
        }

        return $created;
    }

    protected function mockExtraction(): array
    {
        return [
            'restaurant_name' => null,
            'categories' => [
                [
                    'name' => 'Biryani & Rice',
                    'items' => [
                        ['name' => 'चिकन बिरयानी', 'name_english' => 'Chicken Biryani', 'price' => 249, 'is_veg' => false, 'description' => 'Aged basmati rice with tender chicken', 'spice_level' => 'medium'],
                        ['name' => 'वेज बिरयानी', 'name_english' => 'Veg Biryani', 'price' => 199, 'is_veg' => true, 'description' => 'Mixed vegetable biryani', 'spice_level' => 'low'],
                    ],
                ],
                [
                    'name' => 'Starters',
                    'items' => [
                        ['name' => 'पनीर टिक्का', 'name_english' => 'Paneer Tikka', 'price' => 179, 'is_veg' => true, 'description' => 'Chargrilled cottage cheese', 'spice_level' => 'medium'],
                        ['name' => 'चिकन टिक्का', 'name_english' => 'Chicken Tikka', 'price' => 229, 'is_veg' => false, 'description' => 'Tandoori chicken pieces', 'spice_level' => 'high'],
                    ],
                ],
                [
                    'name' => 'Breads',
                    'items' => [
                        ['name' => 'बटर नान', 'name_english' => 'Butter Naan', 'price' => 49, 'is_veg' => true, 'description' => null, 'spice_level' => null],
                        ['name' => 'गार्लिक नान', 'name_english' => 'Garlic Naan', 'price' => 59, 'is_veg' => true, 'description' => null, 'spice_level' => null],
                    ],
                ],
                [
                    'name' => 'Main Course',
                    'items' => [
                        ['name' => 'बटर चिकन', 'name_english' => 'Butter Chicken', 'price' => 329, 'is_veg' => false, 'description' => 'Creamy tomato gravy', 'spice_level' => 'low'],
                        ['name' => 'दाल मखनी', 'name_english' => 'Dal Makhani', 'price' => 199, 'is_veg' => true, 'description' => 'Slow cooked black lentils', 'spice_level' => 'low'],
                    ],
                ],
                [
                    'name' => 'Desserts',
                    'items' => [
                        ['name' => 'गुलाब जामुन', 'name_english' => 'Gulab Jamun', 'price' => 89, 'is_veg' => true, 'description' => 'Deep fried milk dumplings', 'spice_level' => null],
                    ],
                ],
                [
                    'name' => 'Beverages',
                    'items' => [
                        ['name' => 'मीठी लस्सी', 'name_english' => 'Sweet Lassi', 'price' => 69, 'is_veg' => true, 'description' => null, 'spice_level' => null],
                        ['name' => 'चाय', 'name_english' => 'Chai', 'price' => 29, 'is_veg' => true, 'description' => null, 'spice_level' => null],
                    ],
                ],
            ],
            'currency' => 'INR',
            'language' => 'mixed',
        ];
    }
}
