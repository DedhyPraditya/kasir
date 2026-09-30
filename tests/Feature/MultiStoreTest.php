<?php

namespace Tests\Feature;

use App\Livewire\Laporan;
use App\Livewire\Produk;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrisSetting;
use App\Models\ReceiptSetting;
use App\Models\Store;
use App\Models\Topping;
use App\Models\User;
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(string $name, string $slug): Store
    {
        return Store::create(['name' => $name, 'slug' => $slug]);
    }

    private function makeUser(array $roles, ?Store $store = null): User
    {
        $user = User::create([
            'username' => 'u_'.uniqid(),
            'password' => Hash::make('secret'),
            'store_id' => $store?->id,
        ]);
        $user->forceFill(['api_token' => Str::random(80)])->save();

        foreach ($roles as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role]));
        }

        return $user;
    }

    private function makeOrder(Store $store, string $customer): Order
    {
        return Order::create([
            'store_id' => $store->id,
            'invoice_number' => 'INV-'.Str::random(8),
            'customer_name' => $customer,
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
    }

    private function makeProduct(Store $store, string $name): Product
    {
        $category = Category::create(['store_id' => $store->id, 'name' => 'Kat '.$name, 'slug' => Str::slug('Kat '.$name)]);

        return Product::create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'base_price' => 5000,
            'is_active' => true,
        ]);
    }

    public function test_accounts_only_see_their_own_store_orders(): void
    {
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $storeB = $this->makeStore('Toko B', 'toko-b');
        $this->makeOrder($storeA, 'Pelanggan A');
        $this->makeOrder($storeB, 'Pelanggan B');

        Livewire::actingAs($this->makeUser(['admin'], $storeA))->test(Laporan::class)
            ->assertSee('Pelanggan A')->assertDontSee('Pelanggan B');

        Livewire::actingAs($this->makeUser(['admin'], $storeB))->test(Laporan::class)
            ->assertSee('Pelanggan B')->assertDontSee('Pelanggan A');

        Livewire::actingAs($this->makeUser(['kasir'], $storeA))->test(Laporan::class)
            ->assertSee('Pelanggan A')->assertDontSee('Pelanggan B');
    }

    public function test_two_admins_of_the_same_store_share_data(): void
    {
        $store = $this->makeStore('Toko A', 'toko-a');
        $this->makeOrder($store, 'Pelanggan A');

        Livewire::actingAs($this->makeUser(['admin'], $store))->test(Laporan::class)->assertSee('Pelanggan A');
        Livewire::actingAs($this->makeUser(['admin'], $store))->test(Laporan::class)->assertSee('Pelanggan A');
    }

    public function test_account_without_store_sees_nothing(): void
    {
        $store = $this->makeStore('Toko A', 'toko-a');
        $this->makeOrder($store, 'Pelanggan A');

        Livewire::actingAs($this->makeUser(['admin']))->test(Laporan::class)->assertDontSee('Pelanggan A');
    }

    public function test_new_records_get_store_of_current_user(): void
    {
        $storeB = $this->makeStore('Toko B', 'toko-b');

        $this->actingAs($this->makeUser(['admin'], $storeB));
        $topping = Topping::create(['name' => 'Keju', 'price' => 1000]);

        $this->assertSame($storeB->id, $topping->store_id);
    }

    public function test_product_page_cannot_use_other_stores_category(): void
    {
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $storeB = $this->makeStore('Toko B', 'toko-b');
        $foreign = $this->makeProduct($storeB, 'Produk B')->category;

        Livewire::actingAs($this->makeUser(['admin'], $storeA))->test(Produk::class)
            ->call('openModal')
            ->set('name', 'Curang')
            ->set('category_id', $foreign->id)
            ->set('base_price', 1000)
            ->call('save')
            ->assertHasErrors('category_id');

        $this->assertDatabaseMissing('products', ['name' => 'Curang']);
    }

    public function test_same_slug_allowed_in_different_stores(): void
    {
        $this->makeProduct($this->makeStore('Toko A', 'toko-a'), 'Gabin Fla');
        $this->makeProduct($this->makeStore('Toko B', 'toko-b'), 'Gabin Fla');

        $this->assertSame(2, Product::withoutGlobalScopes()->where('slug', 'gabin-fla')->count());
    }

    public function test_mobile_api_is_limited_to_users_store(): void
    {
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $storeB = $this->makeStore('Toko B', 'toko-b');
        $kasirB = $this->makeUser(['kasir'], $storeB);
        $this->makeProduct($storeA, 'Produk A');
        $this->makeProduct($storeB, 'Produk B');

        $this->getJson('/api/products', ['X-Api-Token' => $kasirB->api_token])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Produk B');

        $this->postJson('/api/orders/sync', [
            'invoice_number' => 'INV-API-1',
            'subtotal' => 1000, 'total' => 1000, 'payment_method' => 'cash', 'status' => 'completed',
            'items' => [['product_id' => 'x', 'product_name' => 'Produk B', 'quantity' => 1, 'price' => 1000, 'subtotal' => 1000]],
        ], ['X-Api-Token' => $kasirB->api_token])->assertCreated();

        $this->assertSame($storeB->id, Order::withoutGlobalScopes()->where('invoice_number', 'INV-API-1')->value('store_id'));
    }

    public function test_receipt_settings_are_separate_per_store_with_store_based_defaults(): void
    {
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $storeB = Store::create(['name' => 'Toko B', 'slug' => 'toko-b', 'address' => 'Jl. Baru 2', 'phone' => '0812']);
        Store::whereKey($storeA->id)->update(['created_at' => now()->subDay()]);
        ReceiptSetting::create(['store_id' => $storeA->id, 'store_name' => 'Struk A', 'feed_lines' => 4, 'auto_cut' => false]);

        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $this->makeUser(['admin'], $storeA)->api_token])
            ->assertJsonPath('data.store', 'Struk A');

        // Toko B belum menyimpan apa pun: nama & alamat diambil dari data tokonya.
        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $this->makeUser(['admin'], $storeB)->api_token])
            ->assertJsonPath('data.store', 'Toko B')
            ->assertJsonPath('data.header', ['Jl. Baru 2', 'Telp: 0812']);
    }

    public function test_qris_config_fallback_only_for_default_store(): void
    {
        config(['qris.static_payload' => 'PAYLOAD-DEFAULT']);
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $storeB = $this->makeStore('Toko B', 'toko-b');
        Store::whereKey($storeA->id)->update(['created_at' => now()->subDay()]); // A = toko default

        $this->actingAs($this->makeUser(['admin'], $storeA));
        $this->assertSame('PAYLOAD-DEFAULT', app(QrisService::class)->getActivePayload());

        $this->actingAs($this->makeUser(['admin'], $storeB));
        $this->assertNull(app(QrisService::class)->getActivePayload());

        QrisSetting::create(['payload' => 'PAYLOAD-B']);
        $this->assertSame('PAYLOAD-B', app(QrisService::class)->getActivePayload());
        $this->assertSame($storeB->id, QrisSetting::withoutGlobalScopes()->value('store_id'));
    }

    public function test_developer_switches_active_store(): void
    {
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $storeB = $this->makeStore('Toko B', 'toko-b');
        Store::whereKey($storeA->id)->update(['created_at' => now()->subDay()]);
        $dev = $this->makeUser(['admin', 'developer']);
        $this->makeOrder($storeA, 'Pelanggan A');
        $this->makeOrder($storeB, 'Pelanggan B');

        // Default: toko paling awal.
        $this->actingAs($dev)->get(route('laporan'))
            ->assertSee('Pelanggan A')->assertDontSee('Pelanggan B');

        $this->actingAs($dev)->post(route('store.switch'), ['store' => $storeB->id])
            ->assertRedirect(route('dashboard'));

        $this->get(route('laporan'))
            ->assertSee('Pelanggan B')->assertDontSee('Pelanggan A');
    }

    public function test_sidebar_shows_store_name(): void
    {
        $store = Store::create(['name' => 'Toko Sidebar', 'slug' => 'toko-sidebar', 'address' => 'Jl. Sidebar 9']);

        $this->actingAs($this->makeUser(['kasir'], $store))->get(route('pos'))
            ->assertSee('Toko Sidebar');
    }

    public function test_only_developer_can_switch_store_and_target_must_exist(): void
    {
        $storeA = $this->makeStore('Toko A', 'toko-a');
        $dev = $this->makeUser(['admin', 'developer']);

        $this->actingAs($this->makeUser(['admin'], $storeA))->post(route('store.switch'), ['store' => $storeA->id])->assertForbidden();
        $this->actingAs($dev)->post(route('store.switch'), ['store' => (string) Str::uuid()])->assertStatus(422);
    }
}
