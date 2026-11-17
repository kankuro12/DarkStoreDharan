<?php

namespace App\Filament\Vendor\Pages;

use App\Exceptions\VerificationException;
use App\Filament\Shared\KycFormSchemas;
use App\Models\City;
use App\Models\Vendor;
use App\Services\Verification\LocationChangeService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RequestLocationChange extends Page
{
    protected string $view = 'filament.shared.location-change-request';

    protected static ?string $title = 'Change pickup location';

    protected static ?string $slug = 'location-change';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth('vendor')->user()?->isApproved();
    }

    private function vendor(): Vendor
    {
        return auth('vendor')->user();
    }

    public function mount(): void
    {
        $this->form->fill($this->vendor()->only($this->vendor()->locationAttributes()));
    }

    public function form(Schema $schema): Schema
    {
        $editable = fn () => false;

        return $schema->statePath('data')->columns(2)->components([
            Select::make('city_id')->label('City')->options(fn () => City::where('status', 'active')->pluck('name', 'id'))->required(),
            ...KycFormSchemas::addressFields('pickup', true, $editable),
            TextInput::make('pickup_latitude')->numeric()->minValue(26.3)->maxValue(30.5)->required(),
            TextInput::make('pickup_longitude')->numeric()->minValue(80.0)->maxValue(88.3)->required(),
            Textarea::make('reason')->label('Why are you moving?')->required()->maxLength(500)->columnSpanFull(),
        ]);
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        try {
            app(LocationChangeService::class)->request($this->vendor(), $state, $state['reason']);
        } catch (VerificationException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Request sent. We will let you know by SMS.')->success()->send();
    }

    protected function getViewData(): array
    {
        return ['requests' => $this->vendor()->locationChangeRequests()->latest()->limit(5)->get()];
    }
}
