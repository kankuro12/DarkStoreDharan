<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Services\Inventory\StockReservationService;
use App\Support\WarehouseScope;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /**
     * Warehouse managers only ever see their own warehouse's orders. There are
     * no staff policies in this app, so the scope and record checks live here.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                WarehouseScope::managedWarehouseId(),
                fn (Builder $query, int $warehouseId) => $query->where('orders.warehouse_id', $warehouseId)
            );
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Order && WarehouseScope::canAccessWarehouse($record->warehouse_id);
    }

    /**
     * The only fields staff should hand-edit after an order exists: everything else
     * (totals, item lines, status) is either an immutable snapshot or changes only
     * through the status actions below, which carry the correct business logic.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('delivery_agent_id')
                    ->label('Assigned Rider')
                    ->relationship('deliveryAgent', 'name')
                    ->searchable()
                    ->preload(),
                Textarea::make('notes')
                    ->label('Internal Notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('Order')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('order_number')->label('Order #')->fontFamily('mono')->weight('bold'),
                                TextEntry::make('order_status')->label('Status')->badge(),
                                TextEntry::make('payment_status')->badge(),
                                TextEntry::make('payment_method')->badge(),
                                TextEntry::make('placed_at')->dateTime(),
                                TextEntry::make('city.name')->label('City'),
                                TextEntry::make('warehouse.name')->label('Fulfilling Warehouse'),
                                TextEntry::make('deliveryAgent.name')->label('Assigned Rider')->placeholder('Not yet assigned'),
                            ]),
                        Section::make('Customer & Delivery Address')
                            ->columnSpan(2)
                            ->schema([
                                TextEntry::make('customer.name')->label('Customer')->placeholder('Guest checkout'),
                                TextEntry::make('address.full_name')->label('Recipient Name'),
                                TextEntry::make('address.phone')->label('Phone'),
                                TextEntry::make('address.formatted_address')->label('Address')->columnSpanFull(),
                                TextEntry::make('notes')->label('Internal Notes')->placeholder('—')->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('product_name')->label('Product')->columnSpan(2),
                                        TextEntry::make('quantity')->label('Qty'),
                                        TextEntry::make('total')->label('Line Total')->money('NPR'),
                                    ]),
                            ]),
                    ]),
                Section::make('Payment Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('subtotal')->money('NPR'),
                                TextEntry::make('discount')->money('NPR'),
                                TextEntry::make('delivery_fee')->label('Delivery Fee')->money('NPR'),
                                TextEntry::make('grand_total')->label('Grand Total')->money('NPR')->weight('bold'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->fontFamily('mono')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Guest')
                    ->searchable(),
                TextColumn::make('city.name')
                    ->label('City')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge(),
                TextColumn::make('payment_status')
                    ->badge(),
                TextColumn::make('order_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('deliveryAgent.name')
                    ->label('Rider')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('placed_at')
                    ->dateTime()
                    ->sortable()
                    ->since(),
                TextColumn::make('subtotal')->money('NPR')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount')->money('NPR')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax')->money('NPR')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('order_status')
                    ->label('Status')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('payment_status')
                    ->options(PaymentStatus::class)
                    ->multiple(),
                SelectFilter::make('city_id')
                    ->label('City')
                    ->relationship('city', 'name'),
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),
                Filter::make('placed_at')
                    ->schema([
                        DatePicker::make('placed_from'),
                        DatePicker::make('placed_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['placed_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('placed_at', '>=', $date))
                            ->when($data['placed_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('placed_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                self::updateStatusAction(),
                self::cancelOrderAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Change status through the model's real transition method, so status logs and
     * lifecycle timestamps stay correct (never a raw enum field edit).
     */
    public static function updateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Update Status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (Order $record) => WarehouseScope::canAccessWarehouse($record->warehouse_id))
            ->form([
                Select::make('order_status')
                    ->label('New Status')
                    ->options(OrderStatus::class)
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason / Note')
                    ->required(),
            ])
            ->fillForm(fn (Order $record) => ['order_status' => $record->order_status->value])
            ->action(function (Order $record, array $data) {
                $newStatus = $data['order_status'] instanceof OrderStatus
                    ? $data['order_status']
                    : OrderStatus::from($data['order_status']);

                if ($newStatus === $record->order_status) {
                    Notification::make()->title('Order is already in that status.')->warning()->send();

                    return;
                }

                $record->transitionOrderStatus($newStatus, $data['reason'], auth()->id());

                if ($newStatus === OrderStatus::Cancelled) {
                    app(StockReservationService::class)->release($record);
                }

                Notification::make()->title("Order marked as {$newStatus->label()}.")->success()->send();
            });
    }

    /**
     * Mirrors the storefront's self-service cancellation: only available before the
     * store has packed the order, and correctly releases/restores stock either way.
     */
    public static function cancelOrderAction(): Action
    {
        return Action::make('cancelOrder')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Order $record) => WarehouseScope::canAccessWarehouse($record->warehouse_id) && $record->isCancellable())
            ->form([
                Textarea::make('reason')
                    ->label('Cancellation Reason')
                    ->required(),
            ])
            ->action(function (Order $record, array $data) {
                $record->transitionOrderStatus(OrderStatus::Cancelled, $data['reason'], auth()->id());
                app(StockReservationService::class)->release($record);

                Notification::make()->title('Order cancelled and stock released.')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
