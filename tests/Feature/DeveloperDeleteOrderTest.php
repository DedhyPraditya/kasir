<?php

namespace Tests\Feature;

use App\Livewire\Laporan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemTopping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeveloperDeleteOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $roles): User
    {
        $user = User::create([
            'username' => 'user_'.uniqid(),
            'password' => Hash::make('secret'),
        ]);

        foreach ($roles as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role]));
        }

        return $user;
    }

    private function makeOrder(): Order
    {
        $order = Order::create([
            'invoice_number' => 'INV-'.Str::random(8),
            'customer_name' => 'Tes',
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
            'status' => 'paid',
        ]);

        $item = $order->items()->create([
            'product_id' => (string) Str::uuid(),
            'product_name' => 'Produk Tes',
            'quantity' => 1,
            'price' => 10000,
            'subtotal' => 10000,
        ]);

        $item->toppings()->create([
            'topping_id' => (string) Str::uuid(),
            'topping_name' => 'Keju',
            'price' => 0,
        ]);

        return $order;
    }

    public function test_developer_can_delete_single_order_with_items_and_toppings(): void
    {
        $developer = $this->makeUser(['admin', 'developer']);
        $order = $this->makeOrder();
        $keep = $this->makeOrder();

        Livewire::actingAs($developer)
            ->test(Laporan::class)
            ->call('confirmDelete', $order->id)
            ->assertSet('pendingDelete', [$order->id])
            ->call('deleteOrders')
            ->assertSet('pendingDelete', []);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('orders', ['id' => $keep->id]);
        $this->assertSame(1, OrderItem::count());
        $this->assertSame(1, OrderItemTopping::count());
    }

    public function test_developer_can_delete_selected_orders(): void
    {
        $developer = $this->makeUser(['admin', 'developer']);
        $a = $this->makeOrder();
        $b = $this->makeOrder();

        Livewire::actingAs($developer)
            ->test(Laporan::class)
            ->set('selected', [$a->id, $b->id])
            ->call('confirmDelete')
            ->call('deleteOrders')
            ->assertSet('selected', []);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
        $this->assertSame(0, OrderItemTopping::count());
    }

    public function test_select_all_then_unselect_some_before_delete(): void
    {
        $developer = $this->makeUser(['admin', 'developer']);
        $a = $this->makeOrder();
        $b = $this->makeOrder();
        $keep = $this->makeOrder();

        $component = Livewire::actingAs($developer)
            ->test(Laporan::class)
            ->call('toggleSelectPage');

        $this->assertEqualsCanonicalizing([$a->id, $b->id, $keep->id], $component->get('selected'));

        // Lepas centang satu transaksi yang tidak ingin dihapus.
        $component
            ->set('selected', array_values(array_diff($component->get('selected'), [$keep->id])))
            ->call('confirmDelete')
            ->call('deleteOrders');

        $this->assertDatabaseMissing('orders', ['id' => $a->id]);
        $this->assertDatabaseMissing('orders', ['id' => $b->id]);
        $this->assertDatabaseHas('orders', ['id' => $keep->id]);
    }

    public function test_toggle_select_page_twice_clears_page_selection(): void
    {
        $developer = $this->makeUser(['admin', 'developer']);
        $this->makeOrder();
        $this->makeOrder();

        Livewire::actingAs($developer)
            ->test(Laporan::class)
            ->call('toggleSelectPage')
            ->assertCount('selected', 2)
            ->call('toggleSelectPage')
            ->assertSet('selected', []);
    }

    public function test_select_all_filtered_covers_every_page(): void
    {
        $developer = $this->makeUser(['admin', 'developer']);
        for ($i = 0; $i < 17; $i++) {
            $this->makeOrder();
        }

        Livewire::actingAs($developer)
            ->test(Laporan::class)
            ->call('toggleSelectPage')
            ->assertCount('selected', 15)
            ->assertSee('Pilih semua 17 transaksi sesuai filter')
            ->call('selectAllFiltered')
            ->assertCount('selected', 17)
            ->call('clearSelection')
            ->assertSet('selected', []);
    }

    public function test_admin_cannot_use_select_all(): void
    {
        $this->makeOrder();

        Livewire::actingAs($this->makeUser(['admin']))
            ->test(Laporan::class)
            ->call('selectAllFiltered')
            ->assertForbidden();
    }

    public function test_admin_without_developer_role_cannot_delete(): void
    {
        $admin = $this->makeUser(['admin']);
        $order = $this->makeOrder();

        Livewire::actingAs($admin)
            ->test(Laporan::class)
            ->set('pendingDelete', [$order->id])
            ->call('deleteOrders')
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
