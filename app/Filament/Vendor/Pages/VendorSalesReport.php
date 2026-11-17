<?php

namespace App\Filament\Vendor\Pages;

use App\Models\Vendor;
use App\Services\Vendor\VendorSettlementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** What the supplier is owed for goods delivered in a period. Aggregates only: no order or customer data. */
class VendorSalesReport extends Page
{
    protected string $view = 'filament.vendor.pages.sales-report';

    protected static ?string $title = 'Sales & settlement';

    protected static ?string $slug = 'sales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            DatePicker::make('from')->required()->live(),
            DatePicker::make('to')->required()->afterOrEqual('from')->live(),
        ]);
    }

    public function statement(): array
    {
        /** @var Vendor $vendor */
        $vendor = auth('vendor')->user();

        return app(VendorSettlementService::class)->statement(
            $vendor,
            Carbon::parse($this->data['from'] ?? now()->startOfMonth()),
            Carbon::parse($this->data['to'] ?? now()),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => response()->streamDownload(function () {
                    $statement = $this->statement();
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['SKU', 'Product', 'Quantity', 'Unit supply price', 'Amount']);
                    foreach ($statement['lines'] as $line) {
                        fputcsv($out, [$line['sku'], $line['product_name'], $line['quantity'], $line['unit_supply_price'], $line['amount']]);
                    }
                    fputcsv($out, ['', '', '', 'Payable', $statement['payable']]);
                    fputcsv($out, ['', '', '', 'Paid', $statement['paid']]);
                    fputcsv($out, ['', '', '', 'Balance', $statement['balance']]);
                    fclose($out);
                }, 'darkstore-settlement-'.($this->data['from'] ?? 'from').'-to-'.($this->data['to'] ?? 'to').'.csv')),
        ];
    }

    protected function getViewData(): array
    {
        return ['statement' => $this->statement()];
    }
}
