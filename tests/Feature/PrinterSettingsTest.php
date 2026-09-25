<?php

namespace Tests\Feature;

use App\Livewire\Laporan;
use App\Livewire\PrinterSettings;
use App\Models\Order;
use App\Models\ReceiptSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrinterSettingsTest extends TestCase
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

    public function test_defaults_are_used_before_anything_is_saved(): void
    {
        $setting = ReceiptSetting::current();

        $this->assertSame(ReceiptSetting::DEFAULT_STORE_NAME, $setting->store_name);
        $this->assertSame(['Purnama Town House Blok H/1', 'Telp: +62 823-9943-0312'], $setting->headerLines());
        $this->assertSame(0, ReceiptSetting::count());
    }

    public function test_all_accounts_can_open_printer_settings(): void
    {
        foreach (['kasir', 'admin'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get(route('printer.settings'))
                ->assertOk()
                ->assertSee('Koneksi Printer')
                ->assertSee('Tes Cetak');
        }
    }

    public function test_admin_can_save_header_and_footer(): void
    {
        $admin = $this->makeUser('admin');

        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('storeName', 'Toko Baru')
            ->set('headerText', "Jl. Merdeka 1\n\nWA 0812")
            ->set('footerText', 'Sampai jumpa')
            ->call('save')
            ->assertHasNoErrors();

        // Simpan ulang tetap satu baris.
        Livewire::actingAs($admin)
            ->test(PrinterSettings::class)
            ->set('footerText', 'Terima kasih')
            ->call('save');

        $this->assertSame(1, ReceiptSetting::count());
        $setting = ReceiptSetting::current();
        $this->assertSame('Toko Baru', $setting->store_name);
        $this->assertSame(['Jl. Merdeka 1', 'WA 0812'], $setting->headerLines());
        $this->assertSame(['Terima kasih'], $setting->footerLines());
        $this->assertSame($admin->id, $setting->updated_by);
    }

    public function test_kasir_cannot_save_header_and_footer(): void
    {
        Livewire::actingAs($this->makeUser('kasir'))
            ->test(PrinterSettings::class)
            ->set('storeName', 'Diubah Kasir')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, ReceiptSetting::count());
    }

    public function test_store_name_is_required(): void
    {
        Livewire::actingAs($this->makeUser('admin'))
            ->test(PrinterSettings::class)
            ->set('storeName', '')
            ->call('save')
            ->assertHasErrors('storeName');
    }

    public function test_receipts_use_saved_header_and_footer(): void
    {
        ReceiptSetting::create([
            'store_name' => 'Toko Baru',
            'header_text' => 'Jl. Merdeka 1',
            'footer_text' => 'Sampai jumpa lagi',
        ]);

        $order = Order::create([
            'invoice_number' => 'INV-'.Str::random(8),
            'customer_name' => 'Budi',
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'qris',
            'status' => 'completed',
        ]);
        $order->items()->create([
            'product_id' => (string) Str::uuid(),
            'product_name' => 'Roti',
            'quantity' => 1,
            'price' => 10000,
            'subtotal' => 10000,
        ]);

        $data = $order->receiptData();
        $this->assertSame('Toko Baru', $data['store']);
        $this->assertSame(['Jl. Merdeka 1'], $data['header']);
        $this->assertSame(['Sampai jumpa lagi'], $data['footer']);

        Livewire::actingAs($this->makeUser('kasir'))
            ->test(Laporan::class)
            ->call('showDetail', $order->id)
            ->assertSee('Toko Baru')
            ->assertSee('Jl. Merdeka 1')
            ->assertSee('Sampai jumpa lagi')
            ->assertDontSee('Purnama Town House');
    }
}
