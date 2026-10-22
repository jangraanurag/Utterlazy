<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArMenuModel;
use App\Models\CryptoPayment;
use App\Models\DroneDelivery;
use App\Models\KitchenStream;
use App\Models\NftCollectible;
use Illuminate\Http\Request;

class P3FeaturesController extends Controller
{
    // ─── AR Menu Preview ───
    public function arModels()
    {
        return response()->json(['models' => ArMenuModel::where('is_active', true)->with('menuItem:id,name,price')->get()]);
    }

    // ─── Kitchen Streams ───
    public function liveKitchens()
    {
        return response()->json([
            'streams' => KitchenStream::where('status', 'live')
                ->with('restaurant:id,name,logo_url')->get(),
        ]);
    }

    // ─── Crypto Payments ───
    public function cryptoOptions()
    {
        return response()->json([
            'supported' => ['BTC', 'ETH', 'USDT'],
            'wallet' => '0xUtterLazyWalletAddressHere',
            'note' => 'Crypto payments are processed manually. Order will be confirmed after 1 blockchain confirmation.',
        ]);
    }

    public function submitCryptoPayment(Request $request)
    {
        $v = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'currency' => 'required|in:BTC,ETH,USDT',
            'amount' => 'required|numeric|min:0.001',
            'tx_hash' => 'required|string',
        ]);

        $payment = CryptoPayment::create(array_merge($v, ['wallet_address' => '0xUtterLazyWalletAddressHere']));

        return response()->json([
            'message' => 'Crypto payment submitted! Order will be confirmed after verification.',
            'payment' => $payment,
        ], 201);
    }

    // ─── NFT Collectibles ───
    public function myNfts(Request $request)
    {
        return response()->json([
            'nfts' => NftCollectible::where('user_id', $request->user()->id)->get(),
        ]);
    }

    public function mintNft(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'image_url' => 'required|string',
            'rarity' => 'sometimes|in:common,rare,epic,legendary',
        ]);

        $nft = NftCollectible::create(array_merge($v, ['user_id' => $request->user()->id]));

        return response()->json(['message' => 'NFT minted!', 'nft' => $nft], 201);
    }

    // ─── Drone Delivery ───
    public function droneStatus(Request $request, $orderId)
    {
        $delivery = DroneDelivery::where('order_id', $orderId)->first();
        if (! $delivery) {
            return response()->json(['error' => 'Not a drone delivery.'], 404);
        }

        return response()->json(['delivery' => $delivery]);
    }
}
