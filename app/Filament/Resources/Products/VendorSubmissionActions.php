<?php

namespace App\Filament\Resources\Products;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\Sms\SmsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/** Staff review of vendor catalog submissions (status `pending_review`). */
class VendorSubmissionActions
{
    public static function make(): array
    {
        $isSubmission = fn (Product $record) => $record->status === 'pending_review' && Gate::allows('manage-vendors');

        return [
            Action::make('approveSubmission')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible($isSubmission)
                ->requiresConfirmation()
                ->modalDescription('Set a city selling price for every pack first; the vendor\'s supply price is never shown as the selling price.')
                ->action(function (Product $record) {
                    $variantIds = $record->variants()->where('status', 'active')->pluck('id');
                    $priced = ProductPrice::whereIn('product_variant_id', $variantIds)->distinct()->pluck('product_variant_id');

                    if ($variantIds->isEmpty() || $variantIds->diff($priced)->isNotEmpty()) {
                        Notification::make()->title('Set a city selling price for every pack before approving.')->danger()->send();

                        return;
                    }

                    $record->update(['status' => 'active']);
                    AuditLog::record('vendor_product_approved', $record, ['status' => 'pending_review'], ['status' => 'active']);
                    self::notifyVendor($record, "DarkStore: your product \"{$record->name}\" is now live.");
                    Notification::make()->title('Product is live')->success()->send();
                }),
            Action::make('rejectSubmission')
                ->label('Request changes')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible($isSubmission)
                ->schema([Textarea::make('reason')->label('What should the vendor fix?')->required()->maxLength(500)])
                ->action(function (Product $record, array $data) {
                    $record->update(['status' => 'draft']);
                    AuditLog::record('vendor_product_returned', $record, ['status' => 'pending_review'], ['status' => 'draft'], $data['reason']);
                    self::notifyVendor($record, "DarkStore: please update \"{$record->name}\": {$data['reason']}");
                    Notification::make()->title('Sent back to the vendor')->success()->send();
                }),
        ];
    }

    private static function notifyVendor(Product $product, string $message): void
    {
        if ($phone = $product->vendor?->phone) {
            app(SmsService::class)->send($phone, $message);
        }
    }
}
