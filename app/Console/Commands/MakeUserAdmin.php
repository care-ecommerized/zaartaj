<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:admin {email : The email address of the account} {--revoke : Remove admin access instead}';

    protected $description = 'Grant or revoke access to the admin dashboard';

    /**
     * `is_admin` is deliberately not mass-assignable, so this is the intended way
     * to promote an account.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No account found for [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $revoking = (bool) $this->option('revoke');

        $user->forceFill(['is_admin' => ! $revoking])->save();

        $this->info($revoking
            ? "Revoked admin access for {$user->email}."
            : "{$user->email} can now reach /admin/products.");

        return self::SUCCESS;
    }
}
