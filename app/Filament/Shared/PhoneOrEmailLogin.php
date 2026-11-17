<?php

namespace App\Filament\Shared;

use App\Services\Sms\SmsService;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/** Panel login for riders and vendors: they sign up by phone, so either phone or email works. */
class PhoneOrEmailLogin extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Phone or email')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim($data['email']);

        return [
            str_contains($login, '@') ? 'email' : 'phone' => str_contains($login, '@') ? $login : SmsService::normalize($login),
            'password' => $data['password'],
        ];
    }
}
