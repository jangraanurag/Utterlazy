<x-filament-panels::page>
    <x-filament-panels::form wire:submit="downloadReport">
        {{ $this->form }}
        
        <div class="flex gap-4">
            <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                Download Excel Report
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
