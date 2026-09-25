<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

#[Signature('admin:manage')]
#[Description('TUI to manage Super Admins and Admins')]
class ManageAdminsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Welcome to the DarkStore Admin Manager TUI');

        while (true) {
            $action = select(
                label: 'What would you like to do?',
                options: [
                    'list' => 'List all staff (Admins/Managers)',
                    'create' => 'Create a new staff user',
                    'promote' => 'Promote/Change role of an existing user',
                    'demote' => 'Demote a staff member to customer',
                    'password' => 'Change a user\'s password',
                    'exit' => 'Exit',
                ],
                default: 'list'
            );

            if ($action === 'exit') {
                $this->info('Goodbye!');
                break;
            }

            match ($action) {
                'list' => $this->listStaff(),
                'create' => $this->createStaff(),
                'promote' => $this->changeRole(),
                'demote' => $this->demoteUser(),
                'password' => $this->changePassword(),
            };
        }
    }

    protected function listStaff()
    {
        $staff = User::where('role', '!=', UserRole::Customer)->get();

        if ($staff->isEmpty()) {
            $this->warn('No staff members found.');

            return;
        }

        $headers = ['ID', 'Name', 'Email', 'Role'];
        $rows = $staff->map(fn ($user) => [
            $user->id,
            $user->name,
            $user->email,
            $user->role->label(),
        ])->toArray();

        table($headers, $rows);
    }

    protected function createStaff()
    {
        $name = text(label: 'Full Name', required: true);
        $email = text(
            label: 'Email Address',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'The email address must be valid.',
                User::where('email', $value)->exists() => 'A user with this email already exists.',
                default => null
            }
        );
        $pass = password(label: 'Password', required: true);

        $roles = collect(UserRole::cases())
            ->filter(fn ($role) => $role !== UserRole::Customer)
            ->mapWithKeys(fn ($role) => [$role->value => $role->label()])
            ->toArray();

        $roleValue = select(
            label: 'Select Role',
            options: $roles,
            default: UserRole::SuperAdmin->value
        );

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($pass),
            'role' => UserRole::tryFrom($roleValue),
        ]);

        $this->info("Successfully created {$user->role->label()} {$user->name}.");
    }

    protected function changeRole()
    {
        $userId = search(
            label: 'Search user by name or email',
            options: fn (string $value) => strlen($value) > 0
                ? User::where('name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->pluck('email', 'id')
                    ->toArray()
                : []
        );

        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        $roles = collect(UserRole::cases())
            ->mapWithKeys(fn ($role) => [$role->value => $role->label()])
            ->toArray();

        $roleValue = select(
            label: "Select new role for {$user->name} (Current: {$user->role->label()})",
            options: $roles,
            default: $user->role->value
        );

        if ($user->role->value === $roleValue) {
            $this->info('Role is unchanged.');

            return;
        }

        if (confirm("Are you sure you want to change {$user->name}'s role to ".UserRole::tryFrom($roleValue)->label().'?')) {
            $user->update(['role' => UserRole::tryFrom($roleValue)]);
            $this->info("Successfully updated {$user->name}'s role.");
        }
    }

    protected function demoteUser()
    {
        $staffIds = User::where('role', '!=', UserRole::Customer)->pluck('id')->toArray();
        if (empty($staffIds)) {
            $this->warn('No staff available to demote.');

            return;
        }

        $userId = search(
            label: 'Search staff by name or email to demote',
            options: fn (string $value) => strlen($value) > 0
                ? User::whereIn('id', $staffIds)
                    ->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    })
                    ->pluck('email', 'id')
                    ->toArray()
                : []
        );

        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        if (confirm("Are you sure you want to demote {$user->name} to Customer?")) {
            $user->update(['role' => UserRole::Customer]);
            $this->info("Successfully demoted {$user->name} to Customer.");
        }
    }

    protected function changePassword()
    {
        $userId = search(
            label: 'Search user by name or email to change password',
            options: fn (string $value) => strlen($value) > 0
                ? User::where('name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->pluck('email', 'id')
                    ->toArray()
                : []
        );

        if (! $userId) {
            return;
        }

        $user = User::find($userId);

        $pass = password(
            label: "Enter new password for {$user->name}",
            required: true
        );

        $confirmPass = password(
            label: 'Confirm new password',
            required: true,
            validate: fn (string $value) => $value !== $pass ? 'Passwords do not match.' : null
        );

        if (confirm("Are you sure you want to change the password for {$user->name}?")) {
            $user->update(['password' => Hash::make($pass)]);
            $this->info("Successfully changed password for {$user->name}.");
        }
    }
}
