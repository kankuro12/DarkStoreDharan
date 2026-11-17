<?php

namespace App\Filament\Resources\DeliveryAgents\Pages;

use App\Filament\Resources\DeliveryAgents\DeliveryAgentResource;
use App\Filament\Shared\VerificationActions;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Services\Sms\SmsService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Password;

class ViewDeliveryAgent extends ViewRecord
{
    protected static string $resource = DeliveryAgentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Verification')->columns(4)->schema([
                TextEntry::make('verification_status')->badge(),
                TextEntry::make('status')->label('Login')->badge()->color(fn ($state) => $state === 'inactive' ? 'danger' : 'success'),
                TextEntry::make('training_completed_at')->label('Training')->dateTime()->placeholder('Not completed'),
                TextEntry::make('rejection_reason')->label('Last note')->placeholder('—'),
                TextEntry::make('submitted_at')->dateTime()->placeholder('—'),
                TextEntry::make('approved_at')->dateTime()->placeholder('—'),
                TextEntry::make('agreement_version')->placeholder('—'),
                TextEntry::make('agreement_ip')->label('Agreement IP')->placeholder('—'),
            ]),
            Section::make('Personal')->columns(4)->schema([
                TextEntry::make('name'),
                TextEntry::make('phone'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('date_of_birth')->date()->placeholder('—'),
                TextEntry::make('gender')->placeholder('—'),
                TextEntry::make('permanent_address')->state(fn (DeliveryAgent $r) => collect([$r->perm_tole, $r->perm_municipality.' - '.$r->perm_ward, $r->perm_district, $r->perm_province])->filter()->implode(', '))->columnSpan(3),
                TextEntry::make('emergency_contact_name')->label('Emergency contact')
                    ->formatStateUsing(fn ($state, DeliveryAgent $r) => "{$state} ({$r->emergency_contact_relation}) {$r->emergency_contact_phone}")->placeholder('—'),
            ]),
            Section::make('Operations & payout')->columns(4)->schema([
                TextEntry::make('city.name'),
                TextEntry::make('homeWarehouse.name')->label('Home hub')->placeholder('—'),
                TextEntry::make('vehicle_type')->placeholder('—'),
                TextEntry::make('base_latitude')->label('Base location')
                    ->formatStateUsing(fn ($state, DeliveryAgent $r) => "{$r->base_latitude}, {$r->base_longitude}")->placeholder('—'),
                TextEntry::make('payout_method')->placeholder('—'),
                TextEntry::make('bank_name')->placeholder('—'),
                TextEntry::make('bank_account_name')->placeholder('—'),
                TextEntry::make('wallet_id')->placeholder('—'),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...VerificationActions::make(fn () => $this->record),

            Action::make('markTrainingComplete')
                ->label('Mark training complete')
                ->color('success')
                ->visible(fn () => $this->record->isApproved() && ! $this->record->training_completed_at)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['training_completed_at' => now()]);
                    AuditLog::record('rider_training_completed', $this->record);
                    Notification::make()->title('Rider can now receive orders')->success()->send();
                }),

            ActionGroup::make([
                Action::make('deactivateLogin')
                    ->label('Block login')
                    ->color('danger')
                    ->visible(fn () => $this->record->status !== 'inactive')
                    ->requiresConfirmation()
                    ->action(fn () => $this->setLogin('inactive')),
                Action::make('activateLogin')
                    ->label('Allow login')
                    ->visible(fn () => $this->record->status === 'inactive')
                    ->requiresConfirmation()
                    ->action(fn () => $this->setLogin('active')),
                Action::make('sendPasswordReset')
                    ->label('Text password reset link')
                    ->requiresConfirmation()
                    ->action(function () {
                        $token = Password::broker('delivery_agents')->createToken($this->record);
                        $url = Filament::getPanel('rider')->getResetPasswordUrl($token, $this->record);
                        app(SmsService::class)->send($this->record->phone, "DarkStore: reset your rider password: {$url}");
                        AuditLog::record('rider_password_reset_sent', $this->record);
                        Notification::make()->title('Reset link texted to the rider')->success()->send();
                    }),
            ])->label('Account')->button(),
        ];
    }

    private function setLogin(string $status): void
    {
        $before = $this->record->status;
        $this->record->update(['status' => $status]);
        AuditLog::record('rider_login_'.($status === 'inactive' ? 'blocked' : 'allowed'), $this->record, ['status' => $before], ['status' => $status]);
        Notification::make()->title('Login '.($status === 'inactive' ? 'blocked' : 'allowed'))->success()->send();
    }
}
