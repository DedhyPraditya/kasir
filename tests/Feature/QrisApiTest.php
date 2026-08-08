<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QrisApiTest extends TestCase
{
    use RefreshDatabase;

    private const STATIC_PAYLOAD = '00020101021126760024ID.CO.SPEEDCASH.MERCHANT01189360081530002105700215ID10250021057040303UKE51440014ID.CO.QRIS.WWW0215ID10254315212240303UKE5204526253033605802ID5910DYFHAASHOP6005MAROS61059056262330509S3871109301091263389110703A016304108B';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('qris.static_payload', self::STATIC_PAYLOAD);
    }

    public function test_dynamic_endpoint_requires_api_token(): void
    {
        $response = $this->getJson('/api/qris/dynamic?amount=15000');

        $response->assertStatus(401);
    }

    private function makeUserWithToken(string $token): User
    {
        $user = User::create([
            'username' => 'kasir_'.uniqid(),
            'password' => Hash::make('secret'),
        ]);

        // api_token sengaja tidak mass-assignable (lihat #[Fillable] di App\Models\User),
        // jadi diisi langsung seperti pola di AuthController::login().
        $user->api_token = $token;
        $user->save();

        return $user;
    }

    public function test_dynamic_endpoint_returns_qr_base64_for_valid_token(): void
    {
        $this->makeUserWithToken('test-token-123');

        $response = $this->getJson('/api/qris/dynamic?amount=15000', [
            'X-Api-Token' => 'test-token-123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['amount', 'qr_base64'])
            ->assertJson(['amount' => 15000.0]);
    }

    public function test_dynamic_endpoint_rejects_invalid_amount(): void
    {
        $this->makeUserWithToken('test-token-456');

        $response = $this->getJson('/api/qris/dynamic?amount=0', [
            'X-Api-Token' => 'test-token-456',
        ]);

        $response->assertStatus(422);
    }
}
