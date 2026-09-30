<?php

namespace App\Filament\Resources\Inventories\Pages;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Filament\Exports\InventoryExporter;
use App\Filament\Imports\InventoryImporter;
use App\Filament\Resources\Inventories\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ManageInventories extends ManageRecords
{
    protected static string $resource = InventoryResource::class;

    public ?int $warehouseId = null;

    public function mount(): void
    {
        parent::mount();

        $this->warehouseId = auth()->user()?->warehouse_id
            ?? Warehouse::query()->where('status', 'active')->value('id');
    }

    /**
     * Inventory is always read for one warehouse at a time — the warehouse
     * chosen in the page header — never across all stores at once.
     */
    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()?->where('inventories.warehouse_id', $this->warehouseId);
    }

    public function updatedWarehouseId(): void
    {
        $this->resetTable();
    }

    public function getHeader(): ?View
    {
        return view('filament.resources.inventories-header', [
            'warehouses' => Warehouse::query()->where('status', 'active')->orderBy('name')->get(),
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string, tone: string}>
     */
    public function getStats(): array
    {
        $warehouseId = $this->warehouseId;

        if (! $warehouseId) {
            return [];
        }

        $orders = Order::query()->where('warehouse_id', $warehouseId);

        $ordersToday = (clone $orders)->whereDate('placed_at', today())->count();

        $salesToday = (clone $orders)
            ->whereDate('placed_at', today())
            ->whereNotIn('order_status', [OrderStatus::Cancelled])
            ->sum('grand_total');

        $activeOrders = (clone $orders)
            ->whereIn('order_status', [
                OrderStatus::Confirmed,
                OrderStatus::Processing,
                OrderStatus::Packed,
                OrderStatus::ReadyForDispatch,
                OrderStatus::Dispatched,
            ])
            ->count();

        $unitsSoldToday = (int) abs(InventoryMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('type', InventoryMovementType::Out)
            ->whereDate('created_at', today())
            ->sum('quantity'));

        $lowStock = Inventory::query()
            ->where('warehouse_id', $warehouseId)
            ->get()
            ->filter(fn (Inventory $inventory) => $inventory->isLowStock())
            ->count();

        return [
            ['label' => "Today's Orders", 'value' => (string) $ordersToday, 'hint' => 'Placed today in this warehouse', 'tone' => 'info'],
            ['label' => "Today's Sales", 'value' => 'Rs '.number_format((float) $salesToday), 'hint' => 'Cancelled orders excluded', 'tone' => 'success'],
            ['label' => 'Units Sold Today', 'value' => (string) $unitsSoldToday, 'hint' => 'Stock-out movements (sales)', 'tone' => 'warning'],
            ['label' => 'Active Orders', 'value' => (string) $activeOrders, 'hint' => 'Confirmed through dispatched', 'tone' => 'info'],
            ['label' => 'Low Stock', 'value' => (string) $lowStock, 'hint' => $lowStock > 0 ? 'Needs restocking' : 'All healthy', 'tone' => $lowStock > 0 ? 'danger' : 'success'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_stock')
                ->label('Add Stock')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => (bool) $this->warehouseId)
                ->form([
                    Select::make('product_variant_id')
                        ->label('Product Variant')
                        ->options(fn () => ProductVariant::with('product')->get()->mapWithKeys(
                            fn (ProductVariant $variant) => [$variant->id => "{$variant->product?->name} - {$variant->name} ({$variant->sku})"]
                        ))
                        ->searchable()
                        ->required(),
                    TextInput::make('quantity')
                        ->label('Quantity to Add')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('reason')
                        ->label('Reason')
                        ->placeholder('e.g. Supplier delivery, Opening stock count')
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(InventoryService::class)->adjustStock(
                        (int) $this->warehouseId,
                        (int) $data['product_variant_id'],
                        (int) $data['quantity'],
                        $data['reason'],
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Stock added and recorded in the ledger.')
                        ->success()
                        ->send();
                }),

            ImportAction::make()
                ->importer(InventoryImporter::class)
                ->options(fn (): array => ['warehouse_id' => $this->warehouseId])
                ->label('Import Stock CSV')
                ->color('gray')
                ->visible(fn (): bool => (bool) $this->warehouseId),

            ExportAction::make()
                ->exporter(InventoryExporter::class)
                ->label('Export Stock CSV')
                ->color('gray')
                ->visible(fn (): bool => (bool) $this->warehouseId),
        ];
    }
}
