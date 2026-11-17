<x-filament-panels::page>
    {{-- Scoped styles: Filament's stylesheet does not ship arbitrary Tailwind utilities for custom views. --}}
    <style>
        .kyc-status .row { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .kyc-status .muted { font-size: .85rem; color: #6b7280; }
        .dark .kyc-status .muted { color: #9ca3af; }
        .kyc-status .note { margin-top: .75rem; font-size: .9rem; color: #dc2626; }
        .kyc-status .warn { margin: 1rem 0 .5rem; font-size: .9rem; color: #b45309; }
        .kyc-status .actions { margin-top: 1rem; }
        .kyc-status .doc { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; padding: .7rem 0; border-bottom: 1px solid rgba(120,113,108,.18); }
        .kyc-status .doc:last-child { border-bottom: 0; }
        .kyc-status .doc-name { font-weight: 600; }
        .kyc-status .doc-meta { font-size: .78rem; color: #6b7280; margin-top: .15rem; }
        .kyc-status .doc-reason { font-size: .78rem; color: #dc2626; margin-top: .15rem; }
    </style>

    <div class="kyc-status" style="display: grid; gap: 1.5rem;">
        <x-filament::section>
            <x-slot name="heading">Verification status</x-slot>

            <div class="row">
                <x-filament::badge :color="$party->verification_status->getColor()" size="lg">
                    {{ $party->verification_status->label() }}
                </x-filament::badge>

                @if ($party->submitted_at)
                    <span class="muted">Submitted {{ $party->submitted_at->diffForHumans() }}</span>
                @endif
            </div>

            @if ($party->rejection_reason)
                <p class="note">Note from our team: {{ $party->rejection_reason }}</p>
            @endif

            @if ($party->isApproved() && $party->renewableDocuments()->isNotEmpty())
                <p class="warn">
                    {{ $party->renewableDocuments()->map(fn ($d) => $d->type->label())->implode(', ') }} expiring soon.
                </p>
                <x-filament::button tag="a" :href="$onboardingUrl" color="warning">Renew documents</x-filament::button>
            @endif

            @if ($party->verification_status->isEditable())
                <div class="actions">
                    <x-filament::button tag="a" :href="$onboardingUrl">Continue verification</x-filament::button>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Documents</x-slot>

            @forelse ($documents as $document)
                <div class="doc">
                    <div>
                        <p class="doc-name">{{ $document->type->label() }}</p>
                        @if ($document->expires_on)
                            <p class="doc-meta">Valid until {{ $document->expires_on->toFormattedDateString() }}</p>
                        @endif
                        @if ($document->rejection_reason)
                            <p class="doc-reason">{{ $document->rejection_reason }}</p>
                        @endif
                    </div>
                    <x-filament::badge :color="$document->status->getColor()">{{ $document->status->getLabel() }}</x-filament::badge>
                </div>
            @empty
                <p class="muted">No documents uploaded yet.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
