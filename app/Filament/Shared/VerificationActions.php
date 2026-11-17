<?php

namespace App\Filament\Shared;

use App\Enums\VerificationStatus;
use App\Exceptions\VerificationException;
use App\Services\Verification\VerificationService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/** Admin header actions driving the VerificationService state machine (riders and vendors). */
class VerificationActions
{
    /** @param  Closure(): Model  $party */
    public static function make(Closure $party): array
    {
        $is = fn (VerificationStatus ...$statuses) => fn () => in_array($party()->verification_status, $statuses, true);

        return [
            self::action('startReview', 'Start review', 'info', $is(VerificationStatus::Submitted), false,
                fn (VerificationService $s, Model $p) => $s->startReview($p, auth()->user())),
            self::action('approve', 'Approve', 'success', $is(VerificationStatus::UnderReview), false,
                fn (VerificationService $s, Model $p) => $s->approve($p, auth()->user())),
            self::action('requestChanges', 'Request changes', 'warning', $is(VerificationStatus::UnderReview), true,
                fn (VerificationService $s, Model $p, array $d) => $s->requestChanges($p, auth()->user(), $d['reason'])),
            self::action('reject', 'Reject application', 'danger', $is(VerificationStatus::UnderReview), true,
                fn (VerificationService $s, Model $p, array $d) => $s->reject($p, auth()->user(), $d['reason'])),
            self::action('suspend', 'Suspend', 'danger', $is(VerificationStatus::Approved), true,
                fn (VerificationService $s, Model $p, array $d) => self::suspend($s, $p, $d['reason'])),
            self::action('reactivate', 'Reactivate', 'success', $is(VerificationStatus::Suspended), false,
                fn (VerificationService $s, Model $p) => $s->reactivate($p, auth()->user())),
            self::action('reopen', 'Reopen application', 'warning', $is(VerificationStatus::Rejected), true,
                fn (VerificationService $s, Model $p, array $d) => $s->reopen($p, auth()->user(), $d['reason'])),
        ];
    }

    /** Suspension result message: riders get their unstarted deliveries released. */
    private static function suspend(VerificationService $service, Model $party, string $reason): string
    {
        $released = $service->suspend($party, auth()->user(), $reason);

        if (! method_exists($party, 'orders')) {
            return 'Suspended.';
        }

        $stillHeld = $party->orders()->whereIn('delivery_status', ['picked_up', 'out_for_delivery'])->pluck('order_number');

        return "Suspended. Released {$released} unstarted order(s) back to dispatch."
            .($stillHeld->isNotEmpty() ? ' Still with rider (already picked up): #'.$stillHeld->implode(', #') : '');
    }

    private static function action(string $name, string $label, string $color, Closure $visible, bool $needsReason, Closure $run): Action
    {
        $action = Action::make($name)
            ->label($label)
            ->color($color)
            ->visible($visible)
            ->requiresConfirmation()
            ->action(function (Model $record, array $data) use ($run) {
                try {
                    $message = $run(app(VerificationService::class), $record, $data);
                    Notification::make()->title(is_string($message) ? $message : 'Status updated')->success()->send();
                } catch (VerificationException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });

        if ($needsReason) {
            $action->schema([Textarea::make('reason')->label('Reason (shown to the applicant)')->required()->maxLength(500)]);
        }

        return $action;
    }
}
