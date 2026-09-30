<?php

namespace App\Filament\Exports;

use App\Models\Inventory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class InventoryExporter extends Exporter
{
    protected static ?string $model = Inventory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('warehouse.name')->label('Warehouse'),
            ExportColumn::make('variant.sku')->label('SKU'),
            ExportColumn::make('variant.product.name')->label('Product'),
            ExportColumn::make('variant.name')->label('Variant'),
            ExportColumn::make('quantity')->label('Quantity'),
            ExportColumn::make('reserved_quantity')->label('Reserved'),
            ExportColumn::make('available')->label('Available'),
            ExportColumn::make('reorder_level')->label('Reorder Level'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Stock export completed: '.number_format($export->successful_rows).' rows exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' rows failed.';
        }

        return $body;
    }
}
