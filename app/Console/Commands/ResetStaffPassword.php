<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('staff:password {email : Staff email address}')]
#[Description('Reset a staff password through hidden interactive prompts')]
class ResetStaffPassword extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $staff = User::query()
            ->where('email', $email)
            ->whereIn('role', User::STAFF_ROLES)
            ->first();

        if ($staff === null) {
            $this->error('No staff account was found for that email address.');

            return self::FAILURE;
        }

        $input = [
            'password' => (string) $this->secret('New password (at least 8 characters)'),
            'password_confirmation' => (string) $this->secret('Confirm new password'),
        ];
        $validator = Validator::make($input, [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'A new password is required.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The new password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $staff->update([
            'password' => $validator->validated()['password'],
        ]);

        $this->info('The staff password has been reset.');
        $this->line("Email: {$staff->email}");
        $this->line("Role: {$staff->role}");

        return self::SUCCESS;
    }
}
