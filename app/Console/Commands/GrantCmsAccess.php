<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kalbek:grant-cms-access {email} {--role=admin}')]
#[Description('Grant CMS access to an existing user')]
class GrantCmsAccess extends Command
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

        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();
        $user->update(['role' => $role]);

        $this->info("Granted {$role->value} CMS access to {$user->email}.");

        return 0;
    }
}
