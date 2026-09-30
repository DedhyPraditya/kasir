<?php

namespace Tests\Feature;

use App\Livewire\StoreManagement;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $roles, ?Store $store = null): User
    {
        $user = User::create([
            'username' => 'u_'.uniqid(),
            'password' => Hash::make('secret'),
            'store_id' => $store?->id,
        ]);

        foreach ($roles as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role]));
        }

        return $user;
    }

    public function test_only_developer_can_open_page(): void
    {
        $store = Store::create(['name' => 'Toko A', 'slug' => 'toko-a']);

        $this->actingAs($this->makeUser(['admin', 'developer']))->get(route('stores.index'))->assertOk()->assertSee('Kelola Toko');
        $this->actingAs($this->makeUser(['admin'], $store))->get(route('stores.index'))->assertForbidden();
        $this->actingAs($this->makeUser(['kasir'], $store))->get(route('stores.index'))->assertRedirect(route('pos'));
    }

    public function test_admin_cannot_call_component_directly(): void
    {
        $store = Store::create(['name' => 'Toko A', 'slug' => 'toko-a']);

        Livewire::actingAs($this->makeUser(['admin'], $store))->test(StoreManagement::class)->assertForbidden();
    }

    public function test_developer_can_create_and_edit_store(): void
    {
        $component = Livewire::actingAs($this->makeUser(['admin', 'developer']))
            ->test(StoreManagement::class)
            ->call('create')
            ->set('name', 'Nyemil Bebs Cabang 2')
            ->set('address', 'Jl. Merdeka 1')
            ->set('phone', '0812')
            ->call('save')
            ->assertHasNoErrors();

        $store = Store::where('name', 'Nyemil Bebs Cabang 2')->first();
        $this->assertSame('nyemil-bebs-cabang-2', $store->slug);
        $this->assertSame('Jl. Merdeka 1', $store->address);

        $component->call('edit', $store->id)
            ->set('name', 'Cabang Dua')
            ->call('save')
            ->assertHasNoErrors();

        $store->refresh();
        $this->assertSame('Cabang Dua', $store->name);
        $this->assertSame('nyemil-bebs-cabang-2', $store->slug); // slug tetap
    }

    public function test_store_name_must_be_unique_and_slug_gets_suffix_on_clash(): void
    {
        Store::create(['name' => 'Toko A', 'slug' => 'toko-a']);

        Livewire::actingAs($this->makeUser(['admin', 'developer']))
            ->test(StoreManagement::class)
            ->call('create')
            ->set('name', 'Toko A')
            ->call('save')
            ->assertHasErrors(['name' => 'unique'])
            ->set('name', 'Toko-A')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['toko-a', 'toko-a-2'], Store::orderBy('created_at')->pluck('slug')->all());
    }

    public function test_store_in_use_cannot_be_deleted_but_empty_store_can(): void
    {
        $used = Store::create(['name' => 'Dipakai', 'slug' => 'dipakai']);
        $empty = Store::create(['name' => 'Kosong', 'slug' => 'kosong']);
        $this->makeUser(['kasir'], $used);

        $component = Livewire::actingAs($this->makeUser(['admin', 'developer']))->test(StoreManagement::class);

        $component->call('confirmDelete', $used->id)->call('delete');
        $this->assertDatabaseHas('stores', ['id' => $used->id]);

        $component->call('confirmDelete', $empty->id)->call('delete');
        $this->assertDatabaseMissing('stores', ['id' => $empty->id]);
    }

    public function test_store_with_only_data_cannot_be_deleted(): void
    {
        $store = Store::create(['name' => 'Ada Data', 'slug' => 'ada-data']);
        Category::create(['store_id' => $store->id, 'name' => 'Kat', 'slug' => 'kat']);

        Livewire::actingAs($this->makeUser(['admin', 'developer']))
            ->test(StoreManagement::class)
            ->call('confirmDelete', $store->id)
            ->call('delete');

        $this->assertDatabaseHas('stores', ['id' => $store->id]);
    }
}
