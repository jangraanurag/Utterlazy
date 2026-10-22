<?php

namespace App\Services\Sos;

use App\Models\SosAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound contract for safety/insurance partners. Enabled only when
 * SOS_PARTNER_WEBHOOK is configured. Failures are logged, never thrown.
 */
class WebhookSosPartnerNotifier implements SosPartnerNotifier
{
    public function notify(SosAlert $alert): void
    {
        $url = (string) config('services.sos.partner_webhook', '');

        if ($url === '') {
            return;
        }

        try {
            Http::timeout(3)->acceptJson()->post($url, [
                'event' => 'safety.sos.v1',
                'alert_id' => $alert->id,
                'rider_id' => $alert->rider_id,
                'order_id' => $alert->order_id,
                'latitude' => $alert->latitude,
                'longitude' => $alert->longitude,
                'triggered_at' => optional($alert->triggered_at)->toIso8601String(),
                'severity' => 'critical',
            ]);
        } catch (\Throwable $e) {
            Log::warning('SOS partner webhook failed: '.$e->getMessage(), ['alert_id' => $alert->id]);
        }
    }
}
