<?php

namespace App\Filament\Shared;

use App\Enums\KycDocumentType;
use App\Enums\PayoutMethod;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Form building blocks shared by the rider and vendor KYC wizards. */
class KycFormSchemas
{
    public const FILE_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    public const MAX_UPLOAD_KB = 5120;

    public const NIN_PATTERN = '/^\d{10}$/';

    /** Nepal address: province → district (dependent) → municipality → ward → tole. */
    public static function addressFields(string $prefix, bool|Closure $required, Closure $disabled): array
    {
        return [
            Select::make("{$prefix}_province")
                ->label('Province')
                ->options(array_combine(config('nepal.provinces'), config('nepal.provinces')))
                ->required($required)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set("{$prefix}_district", null))
                ->disabled($disabled),
            Select::make("{$prefix}_district")
                ->label('District')
                ->options(function (Get $get) use ($prefix) {
                    $districts = config('nepal.districts')[$get("{$prefix}_province")] ?? [];

                    return array_combine($districts, $districts);
                })
                ->required($required)
                ->disabled($disabled),
            TextInput::make("{$prefix}_municipality")
                ->label('Municipality / Rural municipality')
                ->maxLength(120)
                ->required($required)
                ->disabled($disabled),
            TextInput::make("{$prefix}_ward")
                ->label('Ward no.')
                ->integer()
                ->minValue(1)
                ->maxValue(35)
                ->required($required)
                ->disabled($disabled),
            TextInput::make("{$prefix}_tole")
                ->label('Tole / Street')
                ->maxLength(120)
                ->disabled($disabled),
        ];
    }

    /**
     * Payout destination. The bank proof upload is added by the caller (documentSection) so it
     * follows the same per-document editing rules as every other document.
     */
    public static function payoutFields(Closure $disabled): array
    {
        $isBank = fn (Get $get) => self::enumValue($get('payout_method')) === PayoutMethod::Bank->value;
        $isWallet = fn (Get $get) => self::enumValue($get('payout_method')) === PayoutMethod::Wallet->value;
        // Locked fields can't be fixed by the applicant, so they never block a resubmission.
        $bankRequired = fn (Get $get) => $isBank($get) && ! $disabled();
        $walletRequired = fn (Get $get) => $isWallet($get) && ! $disabled();

        return [
            Select::make('payout_method')
                ->label('How should we pay you?')
                ->options(PayoutMethod::class)
                ->required(fn () => ! $disabled())
                ->live()
                ->disabled($disabled),
            Select::make('bank_name')
                ->options(array_combine(config('nepal.banks'), config('nepal.banks')))
                ->searchable()
                ->visible($isBank)
                ->required($bankRequired)
                ->disabled($disabled),
            TextInput::make('bank_account_name')
                ->label('Account holder name (must match your legal name)')
                ->maxLength(150)
                ->visible($isBank)
                ->required($bankRequired)
                ->disabled($disabled),
            TextInput::make('bank_account_number')
                ->label('Account number')
                ->regex('/^[0-9A-Za-z\-]{6,30}$/')
                ->visible($isBank)
                ->required($bankRequired)
                ->disabled($disabled),
            TextInput::make('wallet_id')
                ->label('eSewa / Khalti ID (mobile number)')
                ->regex(PhoneOtpFields::NEPALI_MOBILE)
                ->visible($isWallet)
                ->required($walletRequired)
                ->disabled($disabled),
        ];
    }

    /**
     * One document's upload + detail fields under `docs.{type}.*`.
     *
     * @param  Closure(): string  $directory  private storage directory for this party
     * @param  array<int, Component>  $extra  type-specific fields (kept in `meta`)
     */
    public static function documentSection(
        KycDocumentType $type,
        Closure $directory,
        bool|Closure $required,
        Closure $disabled,
        array $extra = [],
        ?string $description = null,
        bool $askNumber = true, // false when the number is captured once elsewhere (e.g. vendor PAN)
    ): Section {
        $key = "docs.{$type->value}";
        // An already-approved (locked) document is never re-required during a resubmission.
        $required = fn (Get $get) => ! $disabled() && ($required instanceof Closure ? $required($get) : $required);

        $fields = [
            self::upload("{$key}.file_front", $type->needsBack() ? 'Front side' : 'Upload', $directory, $required, $disabled),
        ];

        if ($type->needsBack()) {
            $fields[] = self::upload("{$key}.file_back", 'Back side', $directory, $required, $disabled);
        }

        if ($askNumber && $type->needsNumber()) {
            $number = TextInput::make("{$key}.document_number")
                ->label($type === KycDocumentType::NationalId ? 'National ID number (NIN, 10 digits)' : 'Document number')
                ->maxLength(40)
                ->required($required)
                ->disabled($disabled);

            if ($type === KycDocumentType::NationalId) {
                $number->regex(self::NIN_PATTERN); // confirm NIN length with Department of National ID before go-live
            }
            if ($type === KycDocumentType::PanCertificate) {
                $number->regex('/^\d{9}$/')->label('PAN (9 digits)');
            }

            $fields[] = $number;
        }

        if ($type->needsExpiry()) {
            $fields[] = DatePicker::make("{$key}.expires_on")
                ->label('Valid until (AD date)')
                ->helperText('Convert the BS date printed on the document to AD.')
                ->after('today')
                ->validationMessages(['after' => 'This document has already expired. Renew it before applying.'])
                ->required($required)
                ->disabled($disabled);
        }

        return Section::make($type->label())
            ->description($description)
            ->schema([...$fields, ...$extra])
            ->columns(2);
    }

    public static function upload(string $name, string $label, Closure $directory, bool|Closure $required, Closure $disabled): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('local')
            ->visibility('private')
            ->directory($directory)
            ->acceptedFileTypes(self::FILE_TYPES)
            ->maxSize(self::MAX_UPLOAD_KB)
            ->required($required)
            ->disabled($disabled);
    }

    private static function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : $value;
    }
}
