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
            $this->error('Akaun staff dengan e-mel tersebut tidak ditemui.');

            return self::FAILURE;
        }

        $input = [
            'password' => (string) $this->secret('Password baharu (minimum 8 aksara)'),
            'password_confirmation' => (string) $this->secret('Sahkan password baharu'),
        ];
        $validator = Validator::make($input, [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Password baharu diperlukan.',
            'password.min' => 'Password baharu mestilah sekurang-kurangnya 8 aksara.',
            'password.confirmed' => 'Pengesahan password baharu tidak sepadan.',
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

        $this->info('Password staff berjaya ditetapkan semula.');
        $this->line("Email: {$staff->email}");
        $this->line("Role: {$staff->role}");

        return self::SUCCESS;
    }
}
