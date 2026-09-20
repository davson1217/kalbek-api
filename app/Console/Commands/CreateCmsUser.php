<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('kalbek:create-cms-user {email} {--name=} {--role=admin} {--password=}')]
#[Description('Create or update a CMS user with password access')]
class CreateCmsUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $role = UserRole::from((string) $this->option('role'));

        if (! $role->canAccessCms()) {
            $this->error('Role must be admin or teacher.');

            return 1;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));
        $user = User::query()->updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->option('name') ?: $this->argument('email'),
                'password' => Hash::make($password),
                'role' => $role,
                'email_verified_at' => now(),
            ],
        );

        $this->info("CMS {$role->value} user ready: {$user->email}");

        if (! $this->option('password')) {
            $this->warn("Generated password: {$password}");
        }

        return 0;
    }
}
