<?php

namespace App\Services\Sos;

use App\Models\SosAlert;

interface SosPartnerNotifier
{
    /**
     * Notify the safety/insurance partner. Implementations must never throw
     * into the SOS flow: a partner outage must not block rider safety.
     */
    public function notify(SosAlert $alert): void;
}
