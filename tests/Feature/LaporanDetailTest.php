<?php

namespace Tests\Feature;

use App\Livewire\Laporan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaporanDetailTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $user = User::create([
            'username' => $role.'_'.uniqid(),
            'password' => Hash::make('secret'),
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function makeOrder(): Order
    {
        $order = Order::create([
            'invoice_number' => 'INV-'.Str::random(8),
            'customer_name' => 'Budi',
            'subtotal' => 15000,
            'total' => 15000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $item = $order->items()->create([
            'product_id' => (string) Str::uuid(),
            'product_name' => 'Roti Bakar',
            'variant_name' => 'Jumbo',
            'quantity' => 1,
            'price' => 13000,
            'subtotal' => 15000,
        ]);

        $item->toppings()->create([
            'topping_id' => (string) Str::uuid(),
            'topping_name' => 'Keju Parut',
            'price' => 2000,
        ]);

        return $order;
    }

    public function test_kasir_can_open_laporan_page(): void
    {
        $this->actingAs($this->makeUser('kasir'))
            ->get(route('laporan'))
            ->assertOk()
            ->assertDontSee('Total Pendapatan')
            ->assertDontSee('Export PDF');
    }

    public function test_admin_sees_summary_and_export(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get(route('laporan'))
            ->assertOk()
            ->assertSee('Total Pendapatan')
            ->assertSee('Export PDF');
    }

    public function test_kasir_can_view_detail_and_reprint_receipt(): void
    {
        $order = $this->makeOrder();

        Livewire::actingAs($this->makeUser('kasir'))
            ->test(Laporan::class)
            ->call('showDetail', $order->id)
            ->assertSee('Detail Transaksi')
            ->assertSee($order->invoice_number)
            ->assertSee('Roti Bakar - Jumbo')
            ->assertSee('Keju Parut')
            ->assertSee('Cetak Ulang Struk')
            ->assertSee('** CETAK ULANG **')
            ->assertSeeHtml('data-receipt=')
            ->assertSee('Pengaturan Printer')
            ->call('closeDetail')
            ->assertDontSee('Detail Transaksi');
    }

    public function test_receipt_data_for_thermal_printer(): void
    {
        $order = $this->makeOrder();

        $reprint = $order->receiptData(reprint: true);
        $this->assertSame($order->invoice_number, $reprint['invoice']);
        $this->assertSame('Roti Bakar - Jumbo', $reprint['items'][0]['name']);
        $this->assertSame('Keju Parut', $reprint['items'][0]['toppings'][0]['name']);
        $this->assertNull($reprint['kasir']);
        $this->assertNull($reprint['paid']);
        $this->assertTrue($reprint['reprint']);

        $fresh = $order->receiptData('kasir1', 5000);
        $this->assertSame('kasir1', $fresh['kasir']);
        $this->assertSame(20000.0, $fresh['paid']);
        $this->assertSame(5000.0, $fresh['change']);
    }

    public function test_pos_receipt_modal_renders_thermal_button(): void
    {
        $order = $this->makeOrder();
        $kasir = $this->makeUser('kasir');

        Livewire::actingAs($kasir)
            ->test(\App\Livewire\Pos::class)
            ->set('lastOrder', Order::with('items.toppings')->find($order->id))
            ->set('lastKembalian', 5000)
            ->set('showReceiptModal', true)
            ->assertSee('Kasir: '.$kasir->username)
            ->assertSee('Kembali')
            ->assertSeeHtml('data-receipt=')
            ->assertDontSee('** CETAK ULANG **');
    }

    public function test_kasir_cannot_delete_orders(): void
    {
        $order = $this->makeOrder();

        Livewire::actingAs($this->makeUser('kasir'))
            ->test(Laporan::class)
            ->call('confirmDelete', $order->id)
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_kasir_still_cannot_export(): void
    {
        $this->actingAs($this->makeUser('kasir'))
            ->get(route('laporan.export'))
            ->assertRedirect(route('pos'));
    }
}
