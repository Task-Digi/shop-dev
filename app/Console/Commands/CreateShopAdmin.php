<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateShopAdmin extends Command
{
    protected $signature = 'shop:create-admin {email} {--name=}';

    protected $description = 'Create or promote a SHOP administrator account';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = (string) ($this->option('name') ?: $this->ask('Name'));

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'is_admin' => true,
            'is_active' => true,
        ]);

        if (! $user->exists) {
            $user->password = Hash::make(Str::random(64));
        }

        $user->save();

        $this->info("Administrator {$email} is ready.");

        return self::SUCCESS;
    }
}
