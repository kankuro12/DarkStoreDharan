<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Resources\ReturnRequests\Pages\ManageReturnRequests;
use App\Models\ReturnRequest;
use App\Services\Returns\ReturnService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $navigationLabel = 'Return Requests';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'order_number')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('reason_code')
                    ->required(),
                Textarea::make('reason_details')
                    ->columnSpanFull(),
                TextInput::make('refund_amount')
                    ->required()
                    ->numeric(),
                Select::make('status')
                    ->options([
                        'requested' => 'Requested',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'inspected' => 'Inspected',
                        'completed' => 'Completed',
                    ])
                    ->required(),
                Toggle::make('restocked'),
                Textarea::make('admin_notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('reason_code')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('refund_amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'requested' => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'inspected' => 'primary',
                        'completed' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                IconColumn::make('restocked')
                    ->label('Stock Restored')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-m-check-badge')
                    ->color('info')
                    ->visible(fn (ReturnRequest $record) => $record->status === 'requested')
                    ->form([
                        Radio::make('decision')
                            ->options([
                                '1' => 'Approve Return',
                                '0' => 'Reject Return',
                            ])
                            ->required(),
                        Textarea::make('notes')->label('Reason / Notes'),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        $approved = $data['decision'] === '1';
                        app(ReturnService::class)->reviewReturn($record, $approved, $data['notes'], auth()->id());
                        Notification::make()->title($approved ? 'Return Approved' : 'Return Rejected')->success()->send();
                    }),

                Action::make('inspect')
                    ->label('Inspect & Restock')
                    ->icon('heroicon-m-archive-box-arrow-down')
                    ->color('warning')
                    ->visible(fn (ReturnRequest $record) => $record->status === 'approved')
                    ->form([
                        Radio::make('restore')
                            ->label('Condition at Warehouse Inspection')
                            ->options([
                                '1' => 'Item is in good condition (Restore to stock)',
                                '0' => 'Item is damaged/unsellable (Write off with audit log)',
                            ])
                            ->required(),
                        Textarea::make('notes')->label('Inspection Notes'),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        $restore = $data['restore'] === '1';
                        app(ReturnService::class)->inspectAndRestock($record, $restore, $data['notes'], auth()->id());
                        Notification::make()->title('Warehouse Inspection Completed')->success()->send();
                    }),

                Action::make('refund')
                    ->label('Process Refund')
                    ->icon('heroicon-m-currency-dollar')
                    ->color('success')
                    ->visible(fn (ReturnRequest $record) => $record->status === 'inspected')
                    ->form([
                        Textarea::make('notes')->label('Finance Reconciliation Notes'),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        app(ReturnService::class)->processRefund($record, $data['notes'], auth()->id());
                        Notification::make()->title('Refund Completed Successfully')->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReturnRequests::route('/'),
        ];
    }
}
