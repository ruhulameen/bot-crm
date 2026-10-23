<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('user:create {--name= : Full name} {--email= : Email address} {--password= : Password} {--role= : admin or user}')]
#[Description('Create an active user (registration is closed, so users are created here)')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $input = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => $this->option('email') ?? text('Email address', required: true),
            'password' => $this->option('password') ?? password('Password', required: true),
            'role' => $this->option('role') ?? select('Role', collect(UserRole::cases())->mapWithKeys(
                fn (UserRole $role) => [$role->value => $role->label()]
            )->all(), UserRole::Admin->value),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::create($validator->validated());

        $this->components->info("Created {$user->role->label()} {$user->email}.");

        return self::SUCCESS;
    }
}
