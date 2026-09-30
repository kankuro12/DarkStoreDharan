<?php

namespace App\Filament\Imports;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use RuntimeException;

class InventoryImporter extends Importer
{
    protected static ?string $model = Inventory::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label('SKU')
                ->requiredMapping()
                ->example('COKE-PET-500ML')
                ->helperText('Must match an existing product variant SKU.'),

            ImportColumn::make('quantity')
                ->label('Quantity')
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:1'])
                ->example('50')
                ->helperText('Units being added to this warehouse (stock-in).'),

            ImportColumn::make('reason')
                ->label('Reason')
                ->rules(['nullable', 'max:255'])
                ->example('Supplier delivery')
                ->helperText('Shown in the stock ledger. Defaults to "CSV import".'),
        ];
    }

    /**
     * Map the SKU column to a variant before the record is resolved, so the
     * importer fails loudly on unknown SKUs instead of silently skipping rows.
     */
    protected function beforeFill(): void
    {
        $sku = strtoupper(trim((string) ($this->data['sku'] ?? '')));
        $variant = ProductVariant::whereRaw('UPPER(sku) = ?', [$sku])->first();

        if (! $variant) {
            throw new RuntimeException("SKU not found: {$sku}");
        }

        $this->data['product_variant_id'] = $variant->id;
        $this->data['reason'] = filled($this->data['reason'] ?? null) ? $this->data['reason'] : 'CSV import';
    }

    public function resolveRecord(): Inventory
    {
        return new Inventory([
            'warehouse_id' => $this->options['warehouse_id'],
            'product_variant_id' => $this->data['product_variant_id'],
        ]);
    }

    /**
     * Stock never edits in place: every imported row flows through the inventory
     * service so the ledger gets its opening/in entries and an audit log line.
     */
    public function saveRecord(): void
    {
        app(InventoryService::class)->adjustStock(
            (int) $this->options['warehouse_id'],
            (int) $this->data['product_variant_id'],
            (int) $this->data['quantity'],
            (string) $this->data['reason'],
            auth()->id(),
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Stock import completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' added to the ledger.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed.';
        }

        return $body;
    }
}
