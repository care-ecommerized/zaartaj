<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_an_admin_can_list_currencies(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.currencies.index'))
            ->assertOk();
    }

    public function test_a_non_admin_gets_a_404(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.currencies.index'))
            ->assertNotFound();
    }

    public function test_an_admin_can_update_a_rate_and_flags(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.currencies.update', 'SAR'), [
                'rate_to_base' => 1.11,
                'is_active' => false,
                'manual_override' => true,
            ])
            ->assertRedirect();

        $sar = Currency::findOrFail('SAR');

        $this->assertSame('1.11000000', $sar->rate_to_base);
        $this->assertFalse($sar->is_active);
        $this->assertTrue($sar->manual_override);
        $this->assertNotNull($sar->rate_updated_at);
    }

    public function test_the_base_currency_stays_one_to_one(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.currencies.update', 'AED'), [
                'rate_to_base' => 5,
                'is_active' => false,
                'manual_override' => true,
            ])
            ->assertRedirect();

        $aed = Currency::findOrFail('AED');

        $this->assertSame('1.00000000', $aed->rate_to_base);
        $this->assertTrue($aed->is_active);
        $this->assertFalse($aed->manual_override);
    }

    public function test_a_non_positive_rate_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.currencies.update', 'SAR'), [
                'rate_to_base' => 0,
                'is_active' => true,
                'manual_override' => false,
            ])
            ->assertSessionHasErrors('rate_to_base');
    }

    public function test_a_non_admin_cannot_update(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->put(route('admin.currencies.update', 'SAR'), [
                'rate_to_base' => 1.11,
                'is_active' => true,
                'manual_override' => false,
            ])
            ->assertNotFound();
    }
}
