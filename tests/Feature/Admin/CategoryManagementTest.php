<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    #[Test]
    public function an_admin_can_create_a_category_with_an_auto_generated_slug_and_position(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => 'Evening Gowns',
                'name_ar' => 'فساتين سهرة',
                'is_visible' => true,
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('status');

        $category = Category::firstOrFail();

        $this->assertSame('Evening Gowns', $category->name);
        $this->assertSame('فساتين سهرة', $category->name_ar);
        $this->assertSame('evening-gowns', $category->slug);
        $this->assertSame('Evening Gowns', $category->path);
        $this->assertSame(sha1('evening-gowns'), $category->path_hash);
        $this->assertSame(0, $category->depth);
        $this->assertNull($category->parent_id);
        $this->assertTrue($category->is_visible);
        // First category takes the next slot after the (max: null → 0) baseline.
        $this->assertSame(1, $category->position);
    }

    #[Test]
    public function a_colliding_slug_gets_a_unique_suffix(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Abayas']);
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Abayas']);

        $slugs = Category::query()->orderBy('id')->pluck('slug')->all();

        $this->assertSame(['abayas', 'abayas-2'], $slugs);
    }

    #[Test]
    public function an_explicit_slug_is_respected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.categories.store'), [
            'name' => 'Kaftans',
            'slug' => 'luxury-kaftans',
        ]);

        $this->assertDatabaseHas('categories', ['slug' => 'luxury-kaftans', 'name' => 'Kaftans']);
    }

    #[Test]
    public function updating_a_category_changes_fields_and_keeps_the_slug_unique(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Shoes']);
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Bags']);

        $bags = Category::where('slug', 'bags')->firstOrFail();

        // Renaming Bags → Shoes must not collide with the existing "shoes" slug.
        $this->actingAs($admin)
            ->put(route('admin.categories.update', $bags->id), [
                'name' => 'Shoes',
                'name_ar' => 'أحذية',
                'is_visible' => false,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $bags->refresh();
        $this->assertSame('Shoes', $bags->name);
        $this->assertSame('أحذية', $bags->name_ar);
        $this->assertSame('shoes-2', $bags->slug);
        $this->assertSame(sha1('shoes-2'), $bags->path_hash);
        $this->assertFalse($bags->is_visible);
    }

    #[Test]
    public function updating_a_category_keeps_its_own_slug(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Scarves']);
        $category = Category::firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category->id), [
                'name' => 'Scarves',
                'slug' => 'scarves',
                'is_visible' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('scarves', $category->refresh()->slug);
    }

    #[Test]
    public function reorder_persists_the_new_positions(): void
    {
        $admin = $this->admin();

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => $name]);
        }

        $ids = Category::query()->orderBy('id')->pluck('id')->all();
        $reordered = array_reverse($ids);

        $this->actingAs($admin)
            ->post(route('admin.categories.reorder'), ['ids' => $reordered])
            ->assertRedirect();

        foreach ($reordered as $position => $id) {
            $this->assertSame($position, Category::findOrFail($id)->position);
        }
    }

    #[Test]
    public function destroying_a_category_uncategorises_its_products_but_keeps_them(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.categories.store'), ['name' => 'Jewellery']);
        $category = Category::firstOrFail();

        $product = Product::create([
            'handle' => 'gold-ring',
            'title' => 'Gold Ring',
            'category_id' => $category->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category->id))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        // The product survives, now un-categorised via the nullOnDelete FK.
        $product->refresh();
        $this->assertNull($product->category_id);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
    }

    #[Test]
    public function an_admin_can_upload_a_category_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => 'Gowns',
                'image' => UploadedFile::fake()->image('gown.jpg', 800, 800),
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::firstOrFail();

        $this->assertNotNull($category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
        $this->assertNotNull($category->imageUrl());
    }

    #[Test]
    public function replacing_a_category_image_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Bags',
            'image' => UploadedFile::fake()->image('old.jpg'),
        ]);

        $category = Category::firstOrFail();
        $old = $category->image_path;

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category->id), [
                'name' => 'Bags',
                'image' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category->refresh();

        $this->assertNotSame($old, $category->image_path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($category->image_path);
    }

    #[Test]
    public function remove_image_clears_the_photo_without_replacing_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Shoes',
            'image' => UploadedFile::fake()->image('shoe.jpg'),
        ]);

        $category = Category::firstOrFail();
        $old = $category->image_path;

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category->id), [
                'name' => 'Shoes',
                'remove_image' => true,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category->refresh();

        $this->assertNull($category->image_path);
        $this->assertNull($category->imageUrl());
        Storage::disk('public')->assertMissing($old);
    }

    #[Test]
    public function a_non_admin_gets_a_404_on_the_index(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.categories.index'))
            ->assertNotFound();
    }
}
