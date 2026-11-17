<?php

namespace App\Filament\Shared;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Exceptions\VerificationException;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Services\Verification\VerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/** KYC document review table, shared by the rider and vendor admin view pages. */
class KycDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'kycDocuments';

    protected static ?string $title = 'KYC documents';

    // The documents are the point of the review page; render them with it.
    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows($ownerRecord->getMorphClass() === 'vendor' ? 'manage-vendors' : 'manage-riders');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (KycDocument $record) => $record->type->label())
            ->columns([
                // Details sit under the type and the reason under the status, keeping row actions on screen.
                TextColumn::make('type')
                    ->badge(false)
                    ->wrap()
                    ->description(fn (KycDocument $record) => collect($record->meta ?? [])
                        ->except('reminded_days')
                        ->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(is_array($v) ? implode(', ', $v) : $v))
                        ->implode(' · ') ?: null),
                TextColumn::make('document_number')
                    ->label('Number')
                    ->formatStateUsing(fn (?string $state) => $state ? '••••'.substr($state, -4) : '—')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('expires_on')->date()->placeholder('—')->sortable()->visibleFrom('md'),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (KycDocument $record) => $record->rejection_reason),
                TextColumn::make('file_front')
                    ->label('Files')
                    ->formatStateUsing(fn () => 'Front')
                    ->url(fn (KycDocument $record) => route('admin.kyc.file', [$record, 'front']), shouldOpenInNewTab: true)
                    ->description(fn (KycDocument $record) => $record->file_back
                        ? new HtmlString('<a class="underline" target="_blank" href="'.e(route('admin.kyc.file', [$record, 'back'])).'">Back</a>')
                        : null),
            ])
            ->headerActions([
                // Staff upload on the applicant's behalf (e.g. walk-in suppliers); audited under the staff user.
                Action::make('uploadDocument')
                    ->label('Upload document')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Select::make('type')
                            ->options(collect(KycDocumentType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                            ->required(),
                        KycFormSchemas::upload('file_front', 'Front / document', fn () => $this->directory(), true, fn () => false),
                        KycFormSchemas::upload('file_back', 'Back (if any)', fn () => $this->directory(), false, fn () => false),
                        TextInput::make('document_number')->maxLength(40),
                        DatePicker::make('expires_on')->label('Valid until (AD)')->after('today'),
                    ])
                    ->action(function (array $data) {
                        $this->getOwnerRecord()->upsertKycDocument(
                            KycDocumentType::from($data['type']),
                            Arr::only($data, ['file_front', 'file_back', 'document_number', 'expires_on']),
                        );
                        Notification::make()->title('Document uploaded for review')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('revealNumber')
                    ->label('Reveal number')
                    ->iconButton()
                    ->icon('heroicon-o-eye')
                    ->visible(fn (KycDocument $record) => filled($record->document_number))
                    ->modalHeading(fn (KycDocument $record) => $record->type->label())
                    ->modalContent(function (KycDocument $record) {
                        AuditLog::record('kyc_document_number_revealed', $record);

                        return new HtmlString('<p class="text-lg font-mono">'.e($record->document_number).'</p>');
                    })
                    ->modalSubmitAction(false),
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (KycDocument $record) => $record->status !== KycDocumentStatus::Approved)
                    ->action(fn (KycDocument $record) => $this->review(fn (VerificationService $s) => $s->approveDocument($record, auth()->user()))),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (KycDocument $record) => $record->status !== KycDocumentStatus::Rejected)
                    ->schema([Textarea::make('reason')->label('Reason shown to the applicant')->required()->maxLength(500)])
                    ->action(fn (KycDocument $record, array $data) => $this->review(fn (VerificationService $s) => $s->rejectDocument($record, auth()->user(), $data['reason']))),
            ]);
    }

    private function directory(): string
    {
        $owner = $this->getOwnerRecord();

        return 'kyc/'.$owner->getMorphClass().'/'.$owner->getKey();
    }

    private function review(callable $callback): void
    {
        try {
            $callback(app(VerificationService::class));
            Notification::make()->title('Document updated')->success()->send();
        } catch (VerificationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
