<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wms:create-admin {email} {username} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the initial Super Admin account securely for production.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (min 12 chars)');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password is too short (minimum 12 characters required).');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $username = (string) $this->argument('username');

        if (User::where('email', $email)->orWhere('username', $username)->exists()) {
            $this->error('A user with this email or username already exists.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => 'Administrator',
            'email' => $email,
            'username' => $username,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->info("Super Admin [{$user->username}] created successfully.");

        return self::SUCCESS;
    }
}

