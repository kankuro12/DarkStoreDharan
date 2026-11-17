<?php

namespace App\Filament\Resources\LocationChangeRequests;

use App\Enums\LocationChangeStatus;
use App\Exceptions\VerificationException;
use App\Filament\Concerns\HidesFromWarehouseManagers;
use App\Filament\Resources\LocationChangeRequests\Pages\ManageLocationChangeRequests;
use App\Models\City;
use App\Models\LocationChangeRequest;
use App\Models\Warehouse;
use App\Services\Verification\LocationChangeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class LocationChangeRequestResource extends Resource
{
    use HidesFromWarehouseManagers;

    protected static ?string $model = LocationChangeRequest::class;

    protected static ?string $navigationLabel = 'Location requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    /** Morph aliases this staff member may decide on. */
    public static function allowedTypes(): array
    {
        return array_keys(array_filter([
            'delivery_agent' => Gate::allows('manage-riders'),
            'vendor' => Gate::allows('manage-vendors'),
        ]));
    }

    public static function canViewAny(): bool
    {
        return static::allowedTypes() !== [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('requestable_type', static::allowedTypes())->with('requestable');
    }

    public static function describe(array $location): string
    {
        return collect($location)->map(fn ($value, $key) => match ($key) {
            'city_id' => 'City: '.City::whereKey($value)->value('name'),
            'home_warehouse_id' => 'Hub: '.Warehouse::whereKey($value)->value('name'),
            default => str_replace('_', ' ', $key).': '.$value,
        })->implode(' · ');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('requester')->label('Requested by')
                    ->state(fn (LocationChangeRequest $r) => $r->requestable?->name)
                    ->description(fn (LocationChangeRequest $r) => $r->requestable_type === 'vendor' ? 'Vendor' : 'Rider'),
                TextColumn::make('current')->label('Current')->formatStateUsing(fn ($state, LocationChangeRequest $r) => static::describe($r->current))->wrap(),
                TextColumn::make('proposed')->label('Proposed')->formatStateUsing(fn ($state, LocationChangeRequest $r) => static::describe($r->proposed))->wrap(),
                TextColumn::make('reason')->wrap(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->since()->label('Requested'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->color('success')
                    ->visible(fn (LocationChangeRequest $r) => $r->status === LocationChangeStatus::Pending)
                    ->schema([Textarea::make('note')->maxLength(500)])
                    ->action(fn (LocationChangeRequest $r, array $data) => static::decide(fn (LocationChangeService $s) => $s->approve($r, auth()->user(), $data['note'] ?? null))),
                Action::make('reject')
                    ->color('danger')
                    ->visible(fn (LocationChangeRequest $r) => $r->status === LocationChangeStatus::Pending)
                    ->schema([Textarea::make('note')->label('Reason (texted to the requester)')->required()->maxLength(500)])
                    ->action(fn (LocationChangeRequest $r, array $data) => static::decide(fn (LocationChangeService $s) => $s->reject($r, auth()->user(), $data['note']))),
            ]);
    }

    private static function decide(callable $callback): void
    {
        try {
            $callback(app(LocationChangeService::class));
            Notification::make()->title('Request updated')->success()->send();
        } catch (VerificationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLocationChangeRequests::route('/'),
        ];
    }
}
