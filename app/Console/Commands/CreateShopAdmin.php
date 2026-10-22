<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateShopAdmin extends Command
{
    protected $signature = 'shop:create-admin {email} {--name=}';

    protected $description = 'Create or promote a SHOP administrator account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $password = (string) $this->secret('Password (minimum 8 characters)');

        if (mb_strlen($password) < 8) {
            $this->error('Password must contain at least 8 characters.');
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
            'is_active' => true,
        ])->save();

        $this->info("Administrator {$email} is ready.");
        return self::SUCCESS;
    }
}
