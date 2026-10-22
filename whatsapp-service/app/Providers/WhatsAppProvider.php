<?php

namespace App\Providers;

use App\Domain\Conversation\WaReply;

interface WhatsAppProvider
{
    /**
     * Send a reply through the configured WhatsApp provider (Meta Cloud API
     * today; a BSP adapter can be swapped without touching state machines).
     */
    public function send(WaReply $reply): void;

    public function sendTemplate(string $waId, string $templateName, array $components = []): void;

    /**
     * Mark a message as read (Meta webhook ack best practice).
     */
    public function markRead(string $waMessageId): void;
}
