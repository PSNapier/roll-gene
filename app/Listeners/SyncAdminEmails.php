<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class SyncAdminEmails
{
    /**
     * Ensure all users whose email is in config(auth.admin_emails) have is_admin set.
     */
    public function handle(Login $event): void
    {
        $emails = config('auth.admin_emails', []);

        if ($emails === [] || ! is_array($emails)) {
            return;
        }

        User::query()
            ->whereIn('email', $emails)
            ->update(['is_admin' => true]);
    }
}
