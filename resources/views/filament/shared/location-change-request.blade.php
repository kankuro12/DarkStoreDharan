<x-filament-panels::page>
    {{-- Scoped styles: Filament's stylesheet does not ship arbitrary Tailwind utilities for custom views. --}}
    <style>
        .loc-req form { display: grid; gap: 1rem; }
        .loc-req .item { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; padding: .7rem 0; border-bottom: 1px solid rgba(120,113,108,.18); }
        .loc-req .item:last-child { border-bottom: 0; }
        .loc-req .reason { font-size: .9rem; }
        .loc-req .meta { font-size: .78rem; color: #6b7280; margin-top: .15rem; }
        .dark .loc-req .meta { color: #9ca3af; }
    </style>

    <div class="loc-req" style="display: grid; gap: 1.5rem;">
        <form wire:submit="submit">
            {{ $this->form }}

            <div>
                <x-filament::button type="submit">Send request</x-filament::button>
            </div>
        </form>

        @if ($requests->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Recent requests</x-slot>

                @foreach ($requests as $request)
                    <div class="item">
                        <div>
                            <p class="reason">{{ $request->reason }}</p>
                            <p class="meta">{{ $request->created_at->diffForHumans() }}@if ($request->review_note) · {{ $request->review_note }}@endif</p>
                        </div>
                        <x-filament::badge :color="$request->status->getColor()">{{ $request->status->getLabel() }}</x-filament::badge>
                    </div>
                @endforeach
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
