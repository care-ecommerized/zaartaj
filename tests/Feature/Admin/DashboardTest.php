<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    #[Test]
    public function a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    #[Test]
    public function a_customer_gets_a_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertNotFound();
    }

    #[Test]
    public function an_admin_sees_the_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('range', '30d')
                ->has('kpis')
                ->has('pipeline')
                ->has('recentOrders')
            );
    }

    #[Test]
    public function the_range_query_is_honoured_and_bad_values_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin?range=7d')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('range', '7d'));

        $this->actingAs($admin)
            ->get('/admin?range=nonsense')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('range', '30d'));
    }

    #[Test]
    public function a_rehomed_admin_page_still_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/products/index'));
    }

    #[Test]
    public function a_placeholder_section_renders_the_coming_soon_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/draft-orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/coming-soon')
                ->where('section', 'Draft Orders')
            );
    }
}
