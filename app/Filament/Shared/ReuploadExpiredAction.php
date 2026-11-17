<?php

namespace App\Filament\Shared;

use App\Enums\VerificationStatus;
use App\Exceptions\VerificationException;
use App\Services\Verification\VerificationService;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/** Status-page button for a party suspended only because a document expired: reopen the wizard to re-upload. */
class ReuploadExpiredAction
{
    /**
     * @param  Closure(): Model  $party
     * @param  Closure(): string  $onboardingUrl
     */
    public static function make(Closure $party, Closure $onboardingUrl): Action
    {
        return Action::make('reuploadExpired')
            ->label('Upload renewed document')
            ->color('warning')
            ->visible(fn () => $party()->verification_status === VerificationStatus::Suspended
                && str_starts_with((string) $party()->rejection_reason, VerificationService::EXPIRY_REASON_PREFIX))
            ->action(function (Action $action) use ($party, $onboardingUrl) {
                try {
                    app(VerificationService::class)->resubmitAfterExpiry($party());
                } catch (VerificationException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $action->redirect($onboardingUrl());
            });
    }
}
