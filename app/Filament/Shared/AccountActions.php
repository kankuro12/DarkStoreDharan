<?php

namespace App\Filament\Shared;

use App\Models\AuditLog;
use App\Services\Sms\SmsService;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;

/** Admin "Account" menu for a rider or vendor: hard-block login and text a password reset link. */
class AccountActions
{
    /**
     * @param  Closure(): Model  $party
     * @param  string  $prefix  audit action prefix, e.g. "rider" / "vendor"
     */
    public static function make(Closure $party, string $prefix, string $broker, string $panelId): ActionGroup
    {
        $setLogin = function (string $status) use ($party, $prefix) {
            $record = $party();
            $before = $record->status;
            $record->update(['status' => $status]);
            $verb = $status === 'inactive' ? 'blocked' : 'allowed';
            AuditLog::record("{$prefix}_login_{$verb}", $record, ['status' => $before], ['status' => $status]);
            Notification::make()->title("Login {$verb}")->success()->send();
        };

        return ActionGroup::make([
            Action::make('deactivateLogin')
                ->label('Block login')
                ->color('danger')
                ->visible(fn () => $party()->status !== 'inactive')
                ->requiresConfirmation()
                ->action(fn () => $setLogin('inactive')),
            Action::make('activateLogin')
                ->label('Allow login')
                ->visible(fn () => $party()->status === 'inactive')
                ->requiresConfirmation()
                ->action(fn () => $setLogin('active')),
            Action::make('sendPasswordReset')
                ->label('Text password reset link')
                ->requiresConfirmation()
                ->action(function () use ($party, $prefix, $broker, $panelId) {
                    $record = $party();
                    $token = Password::broker($broker)->createToken($record);
                    $url = Filament::getPanel($panelId)->getResetPasswordUrl($token, $record);
                    app(SmsService::class)->send($record->phone, "DarkStore: reset your password: {$url}");
                    AuditLog::record("{$prefix}_password_reset_sent", $record);
                    Notification::make()->title('Reset link texted')->success()->send();
                }),
        ])->label('Account')->button();
    }
}
