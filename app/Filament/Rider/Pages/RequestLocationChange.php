<?php

namespace App\Filament\Rider\Pages;

use App\Exceptions\VerificationException;
use App\Models\City;
use App\Models\DeliveryAgent;
use App\Models\Warehouse;
use App\Services\Verification\LocationChangeService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RequestLocationChange extends Page
{
    protected string $view = 'filament.shared.location-change-request';

    protected static ?string $title = 'Change my hub / city';

    protected static ?string $slug = 'location-change';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth('delivery')->user()?->isApproved();
    }

    private function rider(): DeliveryAgent
    {
        return auth('delivery')->user();
    }

    public function mount(): void
    {
        $this->form->fill($this->rider()->only($this->rider()->locationAttributes()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Select::make('city_id')
                ->label('City')
                ->options(fn () => City::where('status', 'active')->pluck('name', 'id'))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('home_warehouse_id', null)),
            Select::make('home_warehouse_id')
                ->label('Home hub')
                ->options(fn (Get $get) => Warehouse::where('status', 'active')
                    ->whereHas('cities', fn ($q) => $q->whereKey($get('city_id')))
                    ->pluck('name', 'id'))
                ->required(),
            TextInput::make('base_latitude')->numeric()->minValue(26.3)->maxValue(30.5)->required(),
            TextInput::make('base_longitude')->numeric()->minValue(80.0)->maxValue(88.3)->required(),
            Textarea::make('reason')->label('Why do you need to move?')->required()->maxLength(500),
        ]);
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        try {
            app(LocationChangeService::class)->request($this->rider(), $state, $state['reason']);
        } catch (VerificationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Request sent. We will let you know by SMS.')->success()->send();
    }

    protected function getViewData(): array
    {
        return ['requests' => $this->rider()->locationChangeRequests()->latest()->limit(5)->get()];
    }
}
