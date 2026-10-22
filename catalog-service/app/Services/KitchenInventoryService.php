<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\MenuItemRecipe;
use App\Models\Restaurant;
use Illuminate\Support\Facades\Log;

class KitchenInventoryService
{
    /**
     * Auto-deduct inventory for a menu item when ordered.
     */
    public function deductForOrder(string $restaurantId, string $menuItemId, int $quantity = 1): void
    {
        $restaurant = Restaurant::find($restaurantId);
        if (! $restaurant || ! $restaurant->inventory_enabled) {
            return;
        }

        $recipes = MenuItemRecipe::where('menu_item_id', $menuItemId)
            ->where('restaurant_id', $restaurantId)
            ->with('inventoryItem')
            ->get();

        foreach ($recipes as $recipe) {
            if (! $recipe->inventoryItem || ! $recipe->inventoryItem->is_active) {
                continue;
            }

            $deductAmount = $recipe->quantity_required * $quantity;
            $item = $recipe->inventoryItem;

            $item->decrement('stock_quantity', $deductAmount);

            // Auto-adjust: if item goes negative, restore to 0 and log
            if ($item->stock_quantity < 0) {
                Log::warning("Inventory adjusted: {$item->name} went negative ({$item->stock_quantity}). Restoring to 0.");
                $item->update(['stock_quantity' => 0]);
            }

            // Low stock alert
            if ($item->isLowStock()) {
                Log::info("LOW STOCK: {$item->name} at {$item->stock_quantity} {$item->unit} (threshold: {$item->low_stock_threshold})");
            }

            // If completely out, auto-mark menu items that use this ingredient
            if ($item->stock_quantity <= 0) {
                $this->checkAndMarkUnavailable($item);
            }
        }
    }

    /**
     * Adjust inventory (manual correction by restaurant).
     */
    public function adjustStock(string $itemId, float $newQuantity): void
    {
        $item = InventoryItem::find($itemId);
        if (! $item) {
            return;
        }

        $item->update(['stock_quantity' => max(0, $newQuantity)]);

        if ($item->stock_quantity > 0) {
            // Re-enable menu items that use this ingredient
            $recipes = MenuItemRecipe::where('inventory_item_id', $itemId)->get();
            foreach ($recipes as $recipe) {
                $menuItem = MenuItem::find($recipe->menu_item_id);
                if ($menuItem && ! $menuItem->is_available) {
                    // Only re-enable if all recipe items have stock
                    $allAvailable = $this->checkAllIngredientsAvailable($menuItem->id);
                    if ($allAvailable) {
                        $menuItem->update(['is_available' => true]);
                    }
                }
            }
        }
    }

    /**
     * Get cost breakdown for a menu item.
     */
    public function getItemCost(string $menuItemId): array
    {
        $recipes = MenuItemRecipe::where('menu_item_id', $menuItemId)
            ->with('inventoryItem')
            ->get();

        $totalCost = 0;
        $ingredients = [];

        foreach ($recipes as $recipe) {
            if (! $recipe->inventoryItem || ! $recipe->inventoryItem->cost_per_unit) {
                continue;
            }

            $cost = ($recipe->quantity_required / 1000) * $recipe->inventoryItem->cost_per_unit;
            $totalCost += $cost;

            $ingredients[] = [
                'name' => $recipe->inventoryItem->name,
                'quantity_required' => $recipe->quantity_required,
                'unit' => $recipe->inventoryItem->unit,
                'cost' => round($cost, 2),
            ];
        }

        return [
            'total_cost' => round($totalCost, 2),
            'ingredients' => $ingredients,
            'item_price' => MenuItem::find($menuItemId)?->price ?? 0,
            'margin' => MenuItem::find($menuItemId) ? round((1 - $totalCost / MenuItem::find($menuItemId)->price) * 100, 1) : 0,
        ];
    }

    protected function checkAndMarkUnavailable(InventoryItem $item): void
    {
        $recipes = MenuItemRecipe::where('inventory_item_id', $item->id)->get();
        foreach ($recipes as $recipe) {
            $menuItem = MenuItem::find($recipe->menu_item_id);
            if ($menuItem && $menuItem->is_available) {
                $allOut = $this->checkAllIngredientsAvailable($recipe->menu_item_id);
                if (! $allOut) {
                    $menuItem->update(['is_available' => false]);
                    Log::info("AUTO UNAVAILABLE: {$menuItem->name} — ingredient {$item->name} out of stock");
                }
            }
        }
    }

    protected function checkAllIngredientsAvailable(string $menuItemId): bool
    {
        $recipes = MenuItemRecipe::where('menu_item_id', $menuItemId)->with('inventoryItem')->get();
        foreach ($recipes as $recipe) {
            if (! $recipe->inventoryItem || ! $recipe->inventoryItem->is_active) {
                continue;
            }
            if ($recipe->inventoryItem->stock_quantity <= 0) {
                return false;
            }
        }

        return true;
    }
}
