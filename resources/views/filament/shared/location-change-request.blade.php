<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">Send request</x-filament::button>
    </form>

    @if ($requests->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Recent requests</x-slot>

            @foreach ($requests as $request)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 last:border-0 dark:border-white/10">
                    <div>
                        <p class="text-sm">{{ $request->reason }}</p>
                        <p class="text-xs text-gray-500">{{ $request->created_at->diffForHumans() }}@if ($request->review_note) · {{ $request->review_note }}@endif</p>
                    </div>
                    <x-filament::badge :color="$request->status->getColor()">{{ $request->status->getLabel() }}</x-filament::badge>
                </div>
            @endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
