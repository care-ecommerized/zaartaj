<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountAddressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'recipient_name' => 'Rahim Uddin',
            'phone' => '01712345678',
            'address_line' => 'House 1, Road 2, Gulshan',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'postcode' => '1212',
            'country' => 'BD',
        ], $overrides);
    }

    #[Test]
    public function a_user_can_store_an_address(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('addresses.store'), $this->payload())
            ->assertRedirect(route('addresses.index'));

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'recipient_name' => 'Rahim Uddin',
            'country' => 'BD',
        ]);
    }

    #[Test]
    public function the_index_lists_only_the_users_own_addresses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $user->addresses()->create($this->payloadModel());
        $other->addresses()->create($this->payloadModel(['recipient_name' => 'Someone Else']));

        $this->actingAs($user)
            ->get(route('addresses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/addresses')
                ->has('addresses', 1)
            );
    }

    #[Test]
    public function a_user_cannot_update_or_delete_another_users_address(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $theirs = $other->addresses()->create($this->payloadModel());

        $this->actingAs($user)
            ->put(route('addresses.update', $theirs), $this->payload())
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('addresses.destroy', $theirs))
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', ['id' => $theirs->id]);
    }

    #[Test]
    public function a_user_can_delete_their_own_address(): void
    {
        $user = User::factory()->create();
        $address = $user->addresses()->create($this->payloadModel());

        $this->actingAs($user)
            ->delete(route('addresses.destroy', $address))
            ->assertRedirect(route('addresses.index'));

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    #[Test]
    public function setting_an_address_as_default_clears_the_flag_on_siblings(): void
    {
        $user = User::factory()->create();
        $first = $user->addresses()->create($this->payloadModel(['is_default' => true]));
        $second = $user->addresses()->create($this->payloadModel());

        $this->actingAs($user)
            ->put(route('addresses.update', $second), $this->payload(['is_default' => true]))
            ->assertRedirect(route('addresses.index'));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    #[Test]
    public function the_country_must_be_a_supported_destination(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('addresses.index'))
            ->post(route('addresses.store'), $this->payload(['country' => 'ZZ']))
            ->assertSessionHasErrors('country');

        $this->assertSame(0, Address::count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payloadModel(array $overrides = []): array
    {
        $payload = $this->payload($overrides);
        unset($payload['is_default']);

        return array_merge([
            'recipient_name' => $payload['recipient_name'],
            'phone' => $payload['phone'],
            'address_line' => $payload['address_line'],
            'city' => $payload['city'],
            'state' => $payload['state'],
            'postcode' => $payload['postcode'],
            'country' => $payload['country'],
            'label' => $payload['label'],
        ], $overrides);
    }
}
