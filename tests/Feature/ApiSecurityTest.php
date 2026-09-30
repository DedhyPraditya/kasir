<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Topping;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeKasir(Store $store, string $password = 'secret'): User
    {
        $user = User::create(['username' => 'k_'.Str::random(6), 'password' => Hash::make($password), 'store_id' => $store->id]);
        $user->assignRole(Role::firstOrCreate(['name' => 'kasir']));

        return $user;
    }

    private function makeProduct(Store $store, int $price = 10000): Product
    {
        $category = Category::create(['store_id' => $store->id, 'name' => 'K'.Str::random(4), 'slug' => Str::random(8)]);

        return Product::create([
            'store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Kopi '.Str::random(4),
            'slug' => Str::random(8), 'base_price' => $price, 'is_active' => true,
        ]);
    }

    private function orderPayload(array $items, string $invoice = 'INV-1'): array
    {
        return ['invoice_number' => $invoice, 'subtotal' => 1, 'total' => 1, 'payment_method' => 'cash', 'status' => 'completed', 'items' => $items];
    }

    // ── Harga dihitung di server ────────────────────────────────

    public function test_sync_ignores_prices_and_totals_sent_by_client(): void
    {
        $store = Store::create(['name' => 'A', 'slug' => 'a']);
        $token = $this->giveApiToken($this->makeKasir($store));
        $product = $this->makeProduct($store, 10000);
        $variant = Variant::create(['product_id' => $product->id, 'name' => 'Large', 'price' => 15000, 'is_active' => true]);
        $topping = Topping::create(['store_id' => $store->id, 'name' => 'Boba', 'price' => 3000, 'is_active' => true]);

        $this->postJson('/api/orders/sync', $this->orderPayload([[
            'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2,
            'price' => 1, 'subtotal' => 1, 'product_name' => 'Palsu',
            'toppings' => [['topping_id' => $topping->id, 'topping_name' => 'Palsu', 'price' => 0]],
        ]]), ['X-Api-Token' => $token])->assertCreated();

        $order = Order::withoutGlobalScopes()->with('items.toppings')->where('invoice_number', 'INV-1')->firstOrFail();
        $this->assertEquals(36000, $order->total);
        $this->assertEquals(36000, $order->subtotal);
        $this->assertEquals(18000, $order->items[0]->price);
        $this->assertEquals(36000, $order->items[0]->subtotal);
        $this->assertSame($product->name, $order->items[0]->product_name);
        $this->assertSame('Large', $order->items[0]->variant_name);
        $this->assertSame('Boba', $order->items[0]->toppings[0]->topping_name);
        $this->assertEquals(3000, $order->items[0]->toppings[0]->price);
    }

    public function test_sync_rejects_product_topping_or_variant_from_another_store(): void
    {
        $storeA = Store::create(['name' => 'A', 'slug' => 'a']);
        $storeB = Store::create(['name' => 'B', 'slug' => 'b']);
        $token = $this->giveApiToken($this->makeKasir($storeA));
        $productB = $this->makeProduct($storeB);
        $variantB = Variant::create(['product_id' => $productB->id, 'name' => 'X', 'price' => 1, 'is_active' => true]);
        $toppingB = Topping::create(['store_id' => $storeB->id, 'name' => 'T', 'price' => 1, 'is_active' => true]);
        $productA = $this->makeProduct($storeA);

        $this->postJson('/api/orders/sync', $this->orderPayload([['product_id' => $productB->id, 'quantity' => 1]]), ['X-Api-Token' => $token])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');

        $this->postJson('/api/orders/sync', $this->orderPayload([['product_id' => $productA->id, 'variant_id' => $variantB->id, 'quantity' => 1]]), ['X-Api-Token' => $token])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.variant_id');

        $this->postJson('/api/orders/sync', $this->orderPayload([['product_id' => $productA->id, 'quantity' => 1, 'toppings' => [['topping_id' => $toppingB->id]]]]), ['X-Api-Token' => $token])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.toppings.0.topping_id');

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_sync_only_accepts_completed_status(): void
    {
        $store = Store::create(['name' => 'A', 'slug' => 'a']);
        $token = $this->giveApiToken($this->makeKasir($store));
        $payload = $this->orderPayload([['product_id' => $this->makeProduct($store)->id, 'quantity' => 1]]);
        $payload['status'] = 'refunded-lol';

        $this->postJson('/api/orders/sync', $payload, ['X-Api-Token' => $token])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_resending_same_invoice_is_idempotent_and_does_not_duplicate(): void
    {
        $store = Store::create(['name' => 'A', 'slug' => 'a']);
        $token = $this->giveApiToken($this->makeKasir($store));
        $payload = $this->orderPayload([['product_id' => $this->makeProduct($store)->id, 'quantity' => 1]]);

        $first = $this->postJson('/api/orders/sync', $payload, ['X-Api-Token' => $token])->assertCreated();
        $second = $this->postJson('/api/orders/sync', $payload, ['X-Api-Token' => $token])->assertCreated();

        $this->assertSame($first->json('order_id'), $second->json('order_id'));
        $this->assertSame(1, Order::withoutGlobalScopes()->count());
    }

    public function test_invoice_used_by_another_store_is_rejected(): void
    {
        $storeA = Store::create(['name' => 'A', 'slug' => 'a']);
        $storeB = Store::create(['name' => 'B', 'slug' => 'b']);
        $tokenA = $this->giveApiToken($this->makeKasir($storeA));
        $tokenB = $this->giveApiToken($this->makeKasir($storeB));

        $this->postJson('/api/orders/sync', $this->orderPayload([['product_id' => $this->makeProduct($storeA)->id, 'quantity' => 1]]), ['X-Api-Token' => $tokenA])->assertCreated();
        $this->postJson('/api/orders/sync', $this->orderPayload([['product_id' => $this->makeProduct($storeB)->id, 'quantity' => 1]]), ['X-Api-Token' => $tokenB])
            ->assertStatus(422)->assertJsonValidationErrors('invoice_number');
    }

    // ── Login & token ───────────────────────────────────────────

    public function test_login_stores_only_a_hash_and_returns_working_token(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));

        $token = $this->postJson('/api/login', ['username' => $user->username, 'password' => 'secret'])
            ->assertOk()->json('token');

        $this->assertSame(80, strlen($token));
        $this->assertDatabaseMissing('api_tokens', ['token' => $token]);
        $this->assertDatabaseHas('api_tokens', ['user_id' => $user->id, 'token' => ApiToken::hash($token)]);
        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $token])->assertOk();
    }

    public function test_each_login_gets_its_own_token_so_devices_do_not_kick_each_other_out(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));

        $phone = $this->postJson('/api/login', ['username' => $user->username, 'password' => 'secret'])->json('token');
        $tablet = $this->postJson('/api/login', ['username' => $user->username, 'password' => 'secret'])->json('token');

        $this->assertNotSame($phone, $tablet);
        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $phone])->assertOk();
        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $tablet])->assertOk();
    }

    public function test_login_is_locked_after_too_many_wrong_passwords(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['username' => $user->username, 'password' => 'salah'])->assertStatus(401);
        }

        // Percobaan ke-6 dikunci, bahkan dengan password yang benar.
        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'secret'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_successful_login_resets_the_attempt_counter(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', ['username' => $user->username, 'password' => 'salah'])->assertStatus(401);
        }
        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'secret'])->assertOk();
        $this->postJson('/api/login', ['username' => $user->username, 'password' => 'salah'])->assertStatus(401);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));
        $token = $this->giveApiToken($user);
        ApiToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $token])->assertStatus(401);
    }

    public function test_using_a_token_extends_its_expiry(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));
        $token = $this->giveApiToken($user);
        ApiToken::query()->update(['last_used_at' => now()->subDays(2), 'expires_at' => now()->addDay()]);

        $this->getJson('/api/receipt-settings', ['X-Api-Token' => $token])->assertOk();

        $this->assertTrue(ApiToken::first()->expires_at->gt(now()->addDays(ApiToken::LIFETIME_DAYS - 1)));
    }

    public function test_hash_of_a_stored_token_cannot_be_used_as_a_token(): void
    {
        $user = $this->makeKasir(Store::create(['name' => 'A', 'slug' => 'a']));
        $this->giveApiToken($user);

        $this->getJson('/api/receipt-settings', ['X-Api-Token' => ApiToken::first()->token])->assertStatus(401);
    }
}
