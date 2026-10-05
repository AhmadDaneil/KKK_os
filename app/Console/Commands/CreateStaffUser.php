<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

#[Signature('staff:create
    {name : Full staff name}
    {email : Unique staff email address}
    {role : ADMIN, OPERATION_MANAGEMENT, CUSTOMER_SERVICE, DESIGNER, or PRODUCTION}
    {--inactive : Create the account inactive}')]
#[Description('Create a staff account without exposing its password in command history')]
class CreateStaffUser extends Command
{
    public function handle(): int
    {
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm password');
        $input = [
            'name' => trim((string) $this->argument('name')),
            'email' => strtolower(trim((string) $this->argument('email'))),
            'role' => strtoupper(trim((string) $this->argument('role'))),
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();
        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => ! $this->option('inactive'),
        ]);

        $this->info('Staff account created.');
        $this->line("ID: {$staff->id}");
        $this->line("Email: {$staff->email}");
        $this->line("Role: {$staff->role}");
        $this->line('Active: '.($staff->is_active ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
