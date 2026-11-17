<?php

namespace App\Filament\Shared;

use App\Enums\KycDocumentStatus;
use App\Exceptions\VerificationException;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Services\Verification\VerificationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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
                TextColumn::make('type')->badge(false),
                TextColumn::make('document_number')
                    ->label('Number')
                    ->formatStateUsing(fn (?string $state) => $state ? '••••'.substr($state, -4) : '—')
                    ->placeholder('—'),
                TextColumn::make('meta')
                    ->label('Details')
                    ->formatStateUsing(fn ($state, KycDocument $record) => collect($record->meta ?? [])
                        ->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(is_array($v) ? implode(', ', $v) : $v))
                        ->implode(' · '))
                    ->placeholder('—'),
                TextColumn::make('expires_on')->date()->placeholder('—')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('rejection_reason')->label('Reason')->wrap()->placeholder('—'),
                TextColumn::make('file_front')
                    ->label('Files')
                    ->formatStateUsing(fn () => 'Front')
                    ->url(fn (KycDocument $record) => route('admin.kyc.file', [$record, 'front']), shouldOpenInNewTab: true)
                    ->description(fn (KycDocument $record) => $record->file_back
                        ? new HtmlString('<a class="underline" target="_blank" href="'.e(route('admin.kyc.file', [$record, 'back'])).'">Back</a>')
                        : null),
            ])
            ->recordActions([
                Action::make('revealNumber')
                    ->label('Reveal')
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
