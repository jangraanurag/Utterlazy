<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Restaurant;

class VoiceSearchService
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function parseIntent(string $text): array
    {
        $prompt = <<<PROMPT
You are a food ordering assistant for UtterLazy, a food delivery app in India.
Understand the user's natural language query in ANY language (Hindi, English, Hinglish, Tamil, Telugu, etc.)
and extract structured search parameters.

User said: "{$text}"

Respond with ONLY a JSON object (no markdown, no explanation):
{
  "food_items": ["list", "of", "food", "items", "user", "wants"],
  "cuisine": "cuisine type or null",
  "budget_max": number or null,
  "budget_min": number or null,
  "is_veg": true/false/null,
  "quantity": number or null,
  "location_hint": "area or null",
  "restaurant_name": "specific restaurant or null",
  "is_open": true,
  "original_text": "{$text}"
}

Examples:
- "mujhe chicken biryani chahiye 200 rupees me" → {"food_items":["chicken biryani"],"cuisine":null,"budget_max":200,"budget_min":null,"is_veg":false,"quantity":null,"location_hint":null,"restaurant_name":null,"is_open":true,"original_text":"mujhe chicken biryani chahiye 200 rupees me"}
- "2 paneer butter masala and 4 naan from darbar" → {"food_items":["paneer butter masala","naan"],"cuisine":null,"budget_max":null,"budget_min":null,"is_veg":true,"quantity":6,"location_hint":null,"restaurant_name":"darbar","is_open":true,"original_text":"2 paneer butter masala and 4 naan from darbar"}
- "kuch sasta aur spicy khana hai" → {"food_items":[],"cuisine":"spicy","budget_max":null,"budget_min":null,"is_veg":null,"quantity":null,"location_hint":null,"restaurant_name":null,"is_open":true,"original_text":"kuch sasta aur spicy khana hai"}
- "veg food near gomti nagar" → {"food_items":[],"cuisine":null,"budget_max":null,"budget_min":null,"is_veg":true,"quantity":null,"location_hint":"gomti nagar","restaurant_name":null,"is_open":true,"original_text":"veg food near gomti nagar"}
PROMPT;

        $response = $this->gemini->callGemini($prompt, '{}');

        $intent = json_decode($response, true);
        if (! is_array($intent)) {
            return [
                'food_items' => [],
                'cuisine' => null,
                'budget_max' => null,
                'budget_min' => null,
                'is_veg' => null,
                'quantity' => null,
                'location_hint' => null,
                'restaurant_name' => null,
                'is_open' => true,
                'original_text' => $text,
                '_raw' => $response,
            ];
        }

        return $intent;
    }

    public function searchByIntent(array $intent, ?float $lat = null, ?float $lng = null): array
    {
        $query = Restaurant::where('is_active', true);

        $foodItems = $intent['food_items'] ?? [];
        $cuisine = $intent['cuisine'] ?? null;
        $restaurantName = $intent['restaurant_name'] ?? null;
        $isVeg = $intent['is_veg'] ?? null;
        $budgetMax = $intent['budget_max'] ?? null;
        $isOpen = $intent['is_open'] ?? true;

        // Location filter
        if ($lat && $lng) {
            $radius = 0.1;
            $query->whereBetween('latitude', [$lat - $radius, $lat + $radius])
                ->whereBetween('longitude', [$lng - $radius, $lng + $radius]);
        }

        // Restaurant name
        if ($restaurantName) {
            $query->where('name', 'LIKE', "%{$restaurantName}%");
        }

        // Cuisine
        if ($cuisine) {
            $query->where(function ($q) use ($cuisine) {
                $q->where('cuisine_type', 'LIKE', "%{$cuisine}%")
                    ->orWhere('description', 'LIKE', "%{$cuisine}%");
            });
        }

        // Text search on food items
        if (! empty($foodItems)) {
            $searchTerm = implode(' ', $foodItems);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('menuItems', function ($mq) use ($searchTerm) {
                        $mq->where('name', 'LIKE', "%{$searchTerm}%")
                            ->where('is_available', true);
                    });
            });
        }

        $restaurants = $query->with(['menuItems' => function ($mq) use ($foodItems, $budgetMax) {
            if (! empty($foodItems)) {
                $searchTerm = implode(' ', $foodItems);
                $mq->where('name', 'LIKE', "%{$searchTerm}%");
            }
            if ($budgetMax) {
                $mq->where('price', '<=', $budgetMax);
            }
            $mq->where('is_available', true);
        }])->limit(20)->get();

        // Find matching menu items across all restaurants
        $menuQuery = MenuItem::where('is_available', true);

        if (! empty($foodItems)) {
            $searchTerm = implode(' ', $foodItems);
            $menuQuery->where('name', 'LIKE', "%{$searchTerm}%");
        }
        if ($budgetMax) {
            $menuQuery->where('price', '<=', $budgetMax);
        }
        if ($budgetMin) {
            $menuQuery->where('price', '>=', $budgetMin);
        }
        if ($isVeg) {
            $menuQuery->where('is_veg', true);
        }

        $menuItems = $menuQuery->with('restaurant:id,name,logo_url,address,latitude,longitude')
            ->limit(30)
            ->get();

        return [
            'query' => $intent['original_text'],
            'intent' => $intent,
            'restaurants' => $restaurants,
            'menu_items' => $menuItems,
            'total_restaurants' => $restaurants->count(),
            'total_items' => $menuItems->count(),
        ];
    }
}
