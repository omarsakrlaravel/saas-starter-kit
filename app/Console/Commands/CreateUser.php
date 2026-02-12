<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUser extends Command
{
    protected $signature = 'app:create-user';

    protected $description = 'Create a new user with optional admin access';

    public function handle(): int
    {
        $name = $this->ask('Enter the user\'s name');
        $email = $this->ask('Enter the user\'s email');
        $username = $this->ask('Enter the user\'s username');
        $password = $this->secret('Enter the user\'s password');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->line($error);
            }

            return 1;
        }

        // Create user
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make($password),
            'verified' => 1,
        ]);

        // Ask about admin access
        if ($this->confirm('Should this user have admin access?', false)) {
            $user->assignRole('admin');
            $this->info('User created successfully with admin access.');
        } else {
            $this->info('User created successfully.');
        }

        return 0;
    }
}
