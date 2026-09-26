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

    public function test_admin_can_save_paper_options(): void
    {
        Livewire::actingAs($this->makeUser('admin'))
            ->test(PrinterSettings::class)
            ->assertSet('feedLines', ReceiptSetting::DEFAULT_FEED_LINES)
            ->assertSet('autoCut', false)
            ->set('feedLines', 2)
            ->set('autoCut', true)
            ->call('save')
            ->assertHasNoErrors();

        $setting = ReceiptSetting::current();
        $this->assertSame(2, $setting->feed_lines);
        $this->assertTrue($setting->auto_cut);
    }

    public function test_feed_lines_must_be_within_range(): void
    {
        Livewire::actingAs($this->makeUser('admin'))
            ->test(PrinterSettings::class)
            ->set('feedLines', 99)
            ->call('save')
            ->assertHasErrors('feedLines');

        $this->assertSame(0, ReceiptSetting::count());
    }

    public function test_receipt_data_carries_paper_options(): void
    {
        ReceiptSetting::create([
            'store_name' => 'Toko', 'header_text' => '', 'footer_text' => '',
            'feed_lines' => 1, 'auto_cut' => false,
        ]);

        $order = Order::create([
            'invoice_number' => 'INV-'.Str::random(8), 'customer_name' => 'A',
            'subtotal' => 1000, 'total' => 1000, 'payment_method' => 'cash', 'status' => 'completed',
        ]);

        $data = $order->receiptData();
        $this->assertSame(1, $data['feed']);
        $this->assertFalse($data['cut']);
    }

    public function test_mobile_api_returns_receipt_settings(): void
    {
        $user = $this->makeUser('kasir');
        $user->forceFill(['api_token' => str_repeat('a', 80)])->save();

        ReceiptSetting::create([
            'store_name' => 'Toko Baru', 'header_text' => "Jl. A\nWA 1", 'footer_text' => 'Makasih',
            'feed_lines' => 2, 'auto_cut' => true,
        ]);

        $this->getJson('/api/receipt-settings', ['X-Api-Token' => str_repeat('a', 80)])
            ->assertOk()
            ->assertExactJson(['data' => [
                'store' => 'Toko Baru',
                'header' => ['Jl. A', 'WA 1'],
                'footer' => ['Makasih'],
                'feed' => 2,
                'cut' => true,
            ]]);

        $this->getJson('/api/receipt-settings')->assertUnauthorized();
    }

    public function test_mobile_api_returns_defaults_when_not_saved(): void
    {
        $user = $this->makeUser('kasir');
        $user->forceFill(['api_token' => str_repeat('b', 80)])->save();

        $this->getJson('/api/receipt-settings', ['X-Api-Token' => str_repeat('b', 80)])
            ->assertOk()
            ->assertJsonPath('data.store', ReceiptSetting::DEFAULT_STORE_NAME)
            ->assertJsonPath('data.feed', ReceiptSetting::DEFAULT_FEED_LINES)
            ->assertJsonPath('data.cut', false);
    }
}
