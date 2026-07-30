<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

class LinkGuestOrders
{
    /**
     * When a customer verifies their email, claim any guest orders that were
     * placed with that address so they appear in the account order history.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            $user->linkGuestOrders();
        }
    }
}
