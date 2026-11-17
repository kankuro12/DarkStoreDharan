<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\RiderPanelProvider;
use App\Providers\Filament\VendorPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    RiderPanelProvider::class,
    VendorPanelProvider::class,
];
