<?php

namespace App\Http\Controllers;

use App\Models\WaCart;
use App\Models\WaConsent;
use App\Models\WaContact;
use App\Models\WaHandoff;
use App\Models\WaSession;
use App\Services\CatalogClient;
use App\Services\EventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalController
{
    public function sessions(Request $request): JsonResponse
    {
        $sessions = WaSession::query()
            ->when($request->get('state'), fn ($q, $s) => $q->where('state', $s))
            ->orderByDesc('updated_at')
            ->limit((int) $request->get('limit', 50))
            ->get()
            ->map(function (WaSession $session) {
                $contact = WaContact::find($session->wa_id);
                $consent = WaConsent::find($session->wa_id);
                $handoff = WaHandoff::openFor($session->wa_id);

                return [
                    'wa_id' => $session->wa_id,
                    'state' => $session->state,
                    'language' => $contact?->language,
                    'user_id' => $contact?->user_id,
                    'utility' => $consent?->utility ?? true,
                    'marketing' => $consent?->marketing ?? false,
                    'last_seen' => $contact?->last_seen,
                    'expires_at' => $session->expires_at,
                    'handoff' => $handoff ? [
                        'id' => $handoff->id,
                        'reason' => $handoff->reason,
                        'status' => $handoff->status,
                        'assignee_id' => $handoff->assignee_id,
                        'created_at' => $handoff->created_at,
                    ] : null,
                ];
            })
            ->filter(fn ($row) => $request->boolean('handoff_only') ? $row['handoff'] !== null : true)
            ->values();

        return response()->json(['sessions' => $sessions, 'available' => true]);
    }

    public function assign(Request $request, string $waId): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => 'required|uuid',
        ]);

        $handoff = WaHandoff::openFor($waId);

        if (! $handoff) {
            return response()->json(['assigned' => false, 'error' => 'No open handoff for this conversation.'], 404);
        }

        $handoff->update([
            'assignee_id' => $validated['assignee_id'],
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        return response()->json(['assigned' => true, 'handoff' => $handoff]);
    }

    /**
     * Send a paid recommendation carousel to one opted-in contact.
     * Used by ops/campaign tooling and by the scheduled reco job.
     */
    public function pushReco(Request $request, CatalogClient $catalog, EventPublisher $publisher): JsonResponse
    {
        $validated = $request->validate([
            'wa_id' => 'required|string|max:15',
            'city_id' => 'required|uuid',
        ]);

        $waId = $validated['wa_id'];

        if (! WaConsent::canSendMarketing($waId)) {
            return response()->json(['sent' => 0, 'reason' => 'No marketing consent.'], 422);
        }

        $reco = $catalog->waReco($validated['city_id'], 5);
        $items = $reco['items'] ?? [];

        if (empty($items)) {
            return response()->json(['sent' => 0, 'reason' => 'No paid placements available.']);
        }

        $lines = ['✨ *Aapke liye picks:*'];
        foreach (array_values($items) as $i => $item) {
            $label = ! empty($item['sponsored']) ? ' · Sponsored' : '';
            $lines[] = ($i + 1).'. '.$item['title'].' — '.$item['restaurant_name'].$label;
        }

        $session = WaSession::firstOrNew(['wa_id' => $waId]);
        $session->state = 'NEAR_ME';
        $session->context = array_merge($session->context ?? [], [
            'list' => array_map(fn ($item) => [
                'id' => $item['restaurant_id'],
                'name' => $item['restaurant_name'].(! empty($item['sponsored']) ? ' (Sponsored)' : ''),
                'placement_id' => $item['placement_id'],
            ], $items),
        ]);
        $session->expires_at = now()->addMinutes((int) config('whatsapp.session_ttl_minutes', 30));
        $session->save();

        app(\App\Providers\WhatsAppProvider::class)->send(
            \App\Domain\Conversation\WaReply::text($waId, implode("\n", $lines)."\n\nNumber reply karo — sponsored placement clearly labelled hai.")
        );

        $catalog->recordImpression(
            array_column($items, 'placement_id'),
            'reco-'.substr(sha1($waId.now()->timestamp), 0, 24)
        );

        $publisher->publish('whatsapp.outbound.v1', [
            'event_type' => 'whatsapp.reco_sent',
            'wa_id' => $waId,
            'city_id' => $validated['city_id'],
            'placement_ids' => array_column($items, 'placement_id'),
        ]);

        return response()->json(['sent' => count($items), 'sponsored' => true]);
    }

    public function cart(string $waId): JsonResponse
    {
        $cart = WaCart::find($waId);

        return response()->json([
            'wa_id' => $waId,
            'restaurant_id' => $cart?->restaurant_id,
            'lines' => $cart?->lines ?? [],
            'total' => $cart?->total() ?? 0,
        ]);
    }
}
