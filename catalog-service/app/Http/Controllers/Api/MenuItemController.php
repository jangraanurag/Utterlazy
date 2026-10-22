<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return MenuItemResource::collection(MenuItem::with([
            'variantType.variants', 'addOnGroups.items', 'images', 'category',
        ])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMenuItemRequest $request)
    {
        $menuItem = MenuItem::create($request->validated());

        return new MenuItemResource($menuItem->load([
            'variantType.variants', 'addOnGroups.items', 'images', 'category',
        ]));
    }

    /**
     * Display the specified resource.
     */
    public function show(MenuItem $menuItem)
    {
        return new MenuItemResource($menuItem->load([
            'variantType.variants', 'addOnGroups.items', 'images', 'category',
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
