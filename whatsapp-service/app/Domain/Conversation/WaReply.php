<?php

namespace App\Domain\Conversation;

class WaReply
{
    public function __construct(
        public readonly string $waId,
        public readonly ?string $text = null,
        public readonly ?array $interactive = null,
        public readonly ?string $template = null,
        public readonly ?array $templateComponents = null,
    ) {
    }

    public static function text(string $waId, string $text): self
    {
        return new self($waId, text: $text);
    }

    public static function buttons(string $waId, string $text, array $buttons): self
    {
        return new self($waId, text: $text, interactive: [
            'type' => 'button',
            'buttons' => $buttons,
        ]);
    }

    public static function template(string $waId, string $template, array $components = []): self
    {
        return new self($waId, template: $template, templateComponents: $components);
    }
}
