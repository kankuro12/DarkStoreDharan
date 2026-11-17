<?php

namespace App\Filament\Shared;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\VerificationStatus;
use App\Exceptions\VerificationException;
use App\Models\KycDocument;
use App\Services\Verification\VerificationService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

/**
 * Shared KYC wizard for self-onboarding parties (riders, vendors).
 * Editing rules: everything is editable in Draft; in NeedsChanges only rejected / expired / missing
 * documents are; otherwise the form is read-only. Disabled fields are not dehydrated, so tampered
 * Livewire state for locked fields never reaches the database.
 */
abstract class KycOnboardingPage extends Page
{
    protected string $view = 'filament.shared.kyc-onboarding';

    public ?array $data = [];

    /** The authenticated party (DeliveryAgent / Vendor). */
    abstract protected function party(): Model;

    /** Party columns the wizard may write while in Draft. */
    abstract protected function profileFields(): array;

    /** @return array<int, Step> wizard steps before the shared "Review & agree" step */
    abstract protected function steps(): array;

    abstract protected function agreementText(): string;

    abstract protected function statusPageUrl(): string;

    /** Hook to adjust validated state before saving (e.g. copy a business number onto its document). */
    protected function prepareState(array $state): array
    {
        return $state;
    }

    /** Document detail fields stored in `meta` rather than columns. */
    protected function metaFields(): array
    {
        return [];
    }

    public function mount(): void
    {
        $party = $this->party();
        $state = Arr::only($party->attributesToArray(), $this->profileFields());
        $state['bank_account_number'] = $party->bank_account_number;

        foreach ($party->kycDocuments as $doc) {
            $state['docs'][$doc->type->value] = array_merge([
                'file_front' => $doc->file_front,
                'file_back' => $doc->file_back,
                'document_number' => $doc->document_number,
                'issuing_authority' => $doc->issuing_authority,
                'issued_on' => $doc->issued_on?->toDateString(),
                'expires_on' => $doc->expires_on?->toDateString(),
            ], $doc->meta ?? []);
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    ...$this->steps(),
                    Step::make('Review & agree')
                        ->schema([
                            Text::make($this->agreementText()),
                            Checkbox::make('agreement')
                                ->label('I have read and agree to the terms above')
                                ->accepted()
                                ->required()
                                ->dehydrated(false)
                                ->disabled(fn () => ! $this->isEditable()),
                        ]),
                ])->submitAction(new HtmlString(
                    '<button type="submit" class="fi-btn fi-color-primary fi-btn-size-md">Submit for verification</button>'
                )),
            ]);
    }

    public function isEditable(): bool
    {
        return $this->party()->verification_status->isEditable();
    }

    /** Closure for ->disabled() on profile fields. */
    protected function profileLocked(): \Closure
    {
        return fn () => $this->party()->verification_status !== VerificationStatus::Draft;
    }

    /** Closure for ->required() on profile fields: an applicant can't fix a locked field, so never block on it. */
    protected function requiredWhileEditable(): \Closure
    {
        return fn () => $this->party()->verification_status === VerificationStatus::Draft;
    }

    /** Closure for ->disabled() on one document's fields. */
    protected function documentLocked(KycDocumentType $type): \Closure
    {
        return fn () => ! $this->documentEditable($type);
    }

    /** Closure for ->required() on a document's extra detail fields (only while that document is editable). */
    protected function documentRequired(KycDocumentType $type): \Closure
    {
        return fn () => $this->documentEditable($type);
    }

    protected function documentEditable(KycDocumentType $type): bool
    {
        $party = $this->party();

        return match ($party->verification_status) {
            VerificationStatus::Draft => true,
            VerificationStatus::NeedsChanges => ! ($doc = $party->kycDocuments->firstWhere('type', $type))
                || in_array($doc->status, [KycDocumentStatus::Rejected, KycDocumentStatus::Expired], true),
            default => false,
        };
    }

    /** Citizenship OR National ID, chosen by a radio; only the chosen one is validated and saved. */
    protected function identityDocumentFields(string $label): array
    {
        $dir = $this->documentDirectory();
        $citizenship = KycDocumentType::Citizenship;
        $nid = KycDocumentType::NationalId;

        return [
            Radio::make('identity_document')
                ->label($label)
                ->options([$citizenship->value => $citizenship->label(), $nid->value => $nid->label()])
                ->formatStateUsing(fn (?string $state) => $state
                    ?? ($this->party()->kycDocuments->contains('type', $nid) ? $nid->value : $citizenship->value))
                ->required()
                ->live()
                ->dehydrated(false),
            KycFormSchemas::documentSection($citizenship, $dir, true, $this->documentLocked($citizenship), [
                Select::make('docs.citizenship.issuing_authority')
                    ->label('Issuing district')
                    ->options(fn () => collect(config('nepal.districts'))->flatten()->sort()->mapWithKeys(fn ($d) => [$d => $d]))
                    ->searchable()
                    ->required($this->documentRequired($citizenship))
                    ->disabled($this->documentLocked($citizenship)),
                DatePicker::make('docs.citizenship.issued_on')->label('Issue date (AD)')->beforeOrEqual('today')
                    ->disabled($this->documentLocked($citizenship)),
            ])->visible(fn (Get $get) => $get('identity_document') === $citizenship->value),
            KycFormSchemas::documentSection($nid, $dir, true, $this->documentLocked($nid))
                ->visible(fn (Get $get) => $get('identity_document') === $nid->value),
        ];
    }

    protected function documentDirectory(): \Closure
    {
        return fn () => 'kyc/'.$this->party()->getMorphClass().'/'.$this->party()->getKey();
    }

    public function save(): bool
    {
        if (! $this->isEditable()) {
            Notification::make()->title('Your application is locked while it is being reviewed.')->warning()->send();

            return false;
        }

        $state = $this->prepareState($this->form->getState());
        $party = $this->party();

        if ($party->verification_status === VerificationStatus::Draft) {
            $party->update(Arr::only($state, $this->profileFields()));
        }

        foreach (KycDocumentType::cases() as $type) {
            $input = $state['docs'][$type->value] ?? null;

            if (! $input || blank($input['file_front'] ?? null) || ! $this->documentEditable($type)) {
                continue;
            }

            $attributes = [
                'file_front' => $input['file_front'],
                'file_back' => $input['file_back'] ?? null,
                'document_number' => $input['document_number'] ?? null,
                'issuing_authority' => $input['issuing_authority'] ?? null,
                'issued_on' => $input['issued_on'] ?? null,
                'expires_on' => $input['expires_on'] ?? null,
                'meta' => array_filter(Arr::only($input, $this->metaFields()), fn ($v) => filled($v)) ?: null,
            ];

            $existing = $party->kycDocuments->firstWhere('type', $type);
            if ($existing && ! $this->documentChanged($existing, $attributes)) {
                continue;
            }

            $party->upsertKycDocument($type, $attributes);
        }

        $party->unsetRelation('kycDocuments');

        return true;
    }

    public function submit(): void
    {
        if (! $this->save()) {
            return;
        }

        try {
            app(VerificationService::class)->submit($this->party(), config('kyc.agreement_version'), (string) request()->ip());
        } catch (VerificationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Submitted! We will review your documents shortly.')->success()->send();
        $this->redirect($this->statusPageUrl());
    }

    protected function documentChanged(KycDocument $existing, array $attributes): bool
    {
        return $existing->file_front !== $attributes['file_front']
            || $existing->file_back !== $attributes['file_back']
            || (string) $existing->document_number !== (string) $attributes['document_number']
            || $existing->expires_on?->toDateString() !== $attributes['expires_on']
            || ($existing->meta ?? null) != $attributes['meta'];
    }
}
