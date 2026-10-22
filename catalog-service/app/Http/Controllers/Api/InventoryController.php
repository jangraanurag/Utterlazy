<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\MenuItemRecipe;
use App\Services\KitchenInventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected KitchenInventoryService $inventoryService;

    public function __construct(KitchenInventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // ─── Toggle ───

    public function toggleInventory(Request $request)
    {
        $restaurant = $request->user();
        $restaurant->update(['inventory_enabled' => ! $restaurant->inventory_enabled]);

        return response()->json([
            'message' => $restaurant->inventory_enabled ? 'Inventory tracking enabled!' : 'Inventory tracking disabled.',
            'inventory_enabled' => $restaurant->inventory_enabled,
        ]);
    }

    // ─── Inventory Items ───

    public function getItems(Request $request)
    {
        $restaurant = $request->user();
        if (! $restaurant->inventory_enabled) {
            return response()->json(['items' => [], 'inventory_enabled' => false, 'message' => 'Inventory tracking is disabled. Toggle it on to manage raw ingredients.']);
        }

        $items = InventoryItem::where('restaurant_id', $restaurant->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'stock_quantity' => $item->stock_quantity,
                'low_stock_threshold' => $item->low_stock_threshold,
                'cost_per_unit' => $item->cost_per_unit,
                'is_active' => $item->is_active,
                'is_low_stock' => $item->isLowStock(),
                'used_in_count' => MenuItemRecipe::where('inventory_item_id', $item->id)->count(),
            ]);

        return response()->json(['items' => $items]);
    }

    public function createItem(Request $request)
    {
        $restaurant = $request->user();
        if (! $restaurant->inventory_enabled) {
            return response()->json(['error' => 'Enable inventory tracking first via /inventory/toggle.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|in:kg,g,litre,ml,pcs,packet',
            'stock_quantity' => 'required|numeric|min:0',
            'low_stock_threshold' => 'sometimes|numeric|min:0',
            'cost_per_unit' => 'sometimes|numeric|min:0',
        ]);

        $item = InventoryItem::create([
            'restaurant_id' => $restaurant->id,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'stock_quantity' => $validated['stock_quantity'],
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 0,
            'cost_per_unit' => $validated['cost_per_unit'] ?? null,
        ]);

        return response()->json(['message' => 'Item added to inventory.', 'item' => $item], 201);
    }

    public function updateItem(Request $request, InventoryItem $inventoryItem)
    {
        $restaurant = $request->user();
        if (! $restaurant->inventory_enabled) {
            return response()->json(['error' => 'Inventory tracking is disabled.'], 422);
        }
        if ($inventoryItem->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'unit' => 'sometimes|in:kg,g,litre,ml,pcs,packet',
            'stock_quantity' => 'sometimes|numeric|min:0',
            'low_stock_threshold' => 'sometimes|numeric|min:0',
            'cost_per_unit' => 'sometimes|numeric|min:0|nullable',
            'is_active' => 'sometimes|boolean',
        ]);

        $inventoryItem->update($validated);

        return response()->json(['message' => 'Inventory item updated.', 'item' => $inventoryItem->fresh()]);
    }

    public function adjustStock(Request $request, InventoryItem $inventoryItem)
    {
        $restaurant = $request->user();
        if (! $restaurant->inventory_enabled) {
            return response()->json(['error' => 'Inventory tracking is disabled.'], 422);
        }
        if ($inventoryItem->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'stock_quantity' => 'required|numeric|min:0',
        ]);

        $this->inventoryService->adjustStock($inventoryItem->id, $validated['stock_quantity']);

        return response()->json([
            'message' => 'Stock adjusted.',
            'item' => $inventoryItem->fresh(),
        ]);
    }

    // ─── Recipes ───

    public function getRecipes(Request $request, MenuItem $menuItem)
    {
        $restaurant = $request->user();
        if ($menuItem->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $recipes = MenuItemRecipe::where('menu_item_id', $menuItem->id)
            ->with('inventoryItem:id,name,unit,stock_quantity,cost_per_unit')
            ->get();

        $cost = $this->inventoryService->getItemCost($menuItem->id);

        return response()->json([
            'menu_item' => $menuItem->only(['id', 'name', 'price']),
            'recipes' => $recipes,
            'cost_analysis' => $cost,
        ]);
    }

    public function saveRecipe(Request $request, MenuItem $menuItem)
    {
        $restaurant = $request->user();
        if ($menuItem->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'recipes' => 'required|array',
            'recipes.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'recipes.*.quantity_required' => 'required|numeric|min:0.001',
        ]);

        // Clear existing recipes for this item
        MenuItemRecipe::where('menu_item_id', $menuItem->id)->delete();

        foreach ($validated['recipes'] as $recipe) {
            MenuItemRecipe::create([
                'restaurant_id' => $restaurant->id,
                'menu_item_id' => $menuItem->id,
                'inventory_item_id' => $recipe['inventory_item_id'],
                'quantity_required' => $recipe['quantity_required'],
            ]);
        }

        return response()->json([
            'message' => 'Recipe saved! Items will auto-deduct from inventory when ordered.',
            'recipes' => MenuItemRecipe::where('menu_item_id', $menuItem->id)->with('inventoryItem:id,name,unit')->get(),
        ]);
    }

    // ─── Dashboard Stats ───

    public function dashboard(Request $request)
    {
        $restaurant = $request->user();
        if (! $restaurant->inventory_enabled) {
            return response()->json(['inventory_enabled' => false, 'total_items' => 0, 'message' => 'Advanced inventory is off. Simple item on/off toggle is always available.']);
        }

        $items = InventoryItem::where('restaurant_id', $restaurant->id)->get();
        $lowStock = $items->filter(fn ($i) => $i->isLowStock());
        $outOfStock = $items->filter(fn ($i) => $i->stock_quantity <= 0);
        $totalStockValue = $items->sum(fn ($i) => ($i->cost_per_unit ?? 0) * ($i->stock_quantity / 1000));

        return response()->json([
            'inventory_enabled' => $restaurant->inventory_enabled,
            'total_items' => $items->count(),
            'low_stock_count' => $lowStock->count(),
            'out_of_stock_count' => $outOfStock->count(),
            'total_stock_value' => round($totalStockValue, 2),
            'low_stock_items' => $lowStock->values(),
        ]);
    }
}
