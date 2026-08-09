<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    #[Test]
    public function set_and_get_round_trip_with_a_default_fallback(): void
    {
        $this->assertSame('fallback', Setting::get('store.name', 'fallback'));

        Setting::set('store.name', 'Zaartaj Elegance');

        $this->assertSame('Zaartaj Elegance', Setting::get('store.name', 'fallback'));
        $this->assertSame('other', Setting::get('missing.key', 'other'));
    }

    #[Test]
    public function an_admin_can_load_the_settings_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/settings/index')
                ->has('settings')
                ->has('baseCurrency')
                ->has('currencyCount')
                ->has('zoneCount'));
    }

    #[Test]
    public function an_admin_can_update_settings_and_they_persist(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'store' => ['name' => 'New Store Name', 'email' => 'hello@zaartaj.test'],
                'social' => ['instagram' => 'https://instagram.com/zaartaj'],
                'policy' => ['privacy' => ['en' => 'We respect your privacy.']],
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHas('status');

        Setting::flush();

        $this->assertSame('New Store Name', Setting::get('store.name'));
        $this->assertSame('hello@zaartaj.test', Setting::get('store.email'));
        $this->assertSame('https://instagram.com/zaartaj', Setting::get('social.instagram'));
        $this->assertSame('We respect your privacy.', Setting::get('policy.privacy.en'));
    }

    #[Test]
    public function validation_rejects_a_blank_store_name(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), ['store' => ['name' => '']])
            ->assertSessionHasErrors('store.name');
    }

    #[Test]
    public function validation_rejects_a_bad_email(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'store' => ['name' => 'Zaartaj', 'email' => 'not-an-email'],
            ])
            ->assertSessionHasErrors('store.email');
    }

    #[Test]
    public function a_non_admin_gets_a_404(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.settings.index'))
            ->assertNotFound();
    }
}
