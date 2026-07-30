<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestOrderLinkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function verifying_a_users_email_links_matching_guest_orders(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);

        $guestOrder = Order::factory()->create([
            'user_id' => null,
            'customer_email' => 'buyer@example.com',
            'placed_at' => now(),
        ]);

        event(new Verified($user));

        $this->assertSame($user->id, $guestOrder->fresh()->user_id);
    }

    #[Test]
    public function a_guest_order_with_a_different_email_is_left_untouched(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);

        $otherOrder = Order::factory()->create([
            'user_id' => null,
            'customer_email' => 'someone-else@example.com',
            'placed_at' => now(),
        ]);

        event(new Verified($user));

        $this->assertNull($otherOrder->fresh()->user_id);
    }

    #[Test]
    public function an_unverified_user_does_not_link_guest_orders(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'buyer@example.com']);

        $guestOrder = Order::factory()->create([
            'user_id' => null,
            'customer_email' => 'buyer@example.com',
            'placed_at' => now(),
        ]);

        // Calling the method directly on an unverified user is a no-op.
        $this->assertSame(0, $user->linkGuestOrders());
        $this->assertNull($guestOrder->fresh()->user_id);
    }
}
