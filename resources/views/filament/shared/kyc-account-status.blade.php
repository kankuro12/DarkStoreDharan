<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Verification status</x-slot>

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::badge :color="$party->verification_status->getColor()" size="lg">
                {{ $party->verification_status->label() }}
            </x-filament::badge>

            @if ($party->submitted_at)
                <span class="text-sm text-gray-500">Submitted {{ $party->submitted_at->diffForHumans() }}</span>
            @endif
        </div>

        @if ($party->rejection_reason)
            <p class="mt-3 text-sm text-danger-600">Note from our team: {{ $party->rejection_reason }}</p>
        @endif

        @if ($party->verification_status->isEditable())
            <div class="mt-4">
                <x-filament::button tag="a" :href="$onboardingUrl">Continue verification</x-filament::button>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Documents</x-slot>

        @forelse ($documents as $document)
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2 last:border-0 dark:border-white/10">
                <div>
                    <p class="font-medium">{{ $document->type->label() }}</p>
                    @if ($document->expires_on)
                        <p class="text-xs text-gray-500">Valid until {{ $document->expires_on->toFormattedDateString() }}</p>
                    @endif
                    @if ($document->rejection_reason)
                        <p class="text-xs text-danger-600">{{ $document->rejection_reason }}</p>
                    @endif
                </div>
                <x-filament::badge :color="$document->status->getColor()">{{ $document->status->getLabel() }}</x-filament::badge>
            </div>
        @empty
            <p class="text-sm text-gray-500">No documents uploaded yet.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
