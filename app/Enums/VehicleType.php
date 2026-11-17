<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VehicleType: string implements HasLabel
{
    case Bicycle = 'bicycle';
    case Scooter = 'scooter';
    case Motorcycle = 'motorcycle';

    public function isMotorized(): bool
    {
        return $this !== self::Bicycle;
    }

    /** Nepal license categories: A = motorcycle (also covers scooter), K = scooter only. */
    public function acceptedLicenseCategories(): array
    {
        return match ($this) {
            self::Bicycle => [],
            self::Scooter => ['A', 'K'],
            self::Motorcycle => ['A'],
        };
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
