<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AdminManual extends Page
{
    protected string $view = 'filament.pages.admin-manual';

    protected static ?string $navigationLabel = 'Admin Manual';

    protected static ?string $title = 'Admin Manual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 99;
}
