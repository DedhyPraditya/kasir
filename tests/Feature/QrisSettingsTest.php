<?php

namespace Tests\Feature;

use App\Livewire\QrisSettings;
use App\Models\QrisSetting;
use App\Models\User;
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class QrisSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const STATIC_PAYLOAD = '00020101021126760024ID.CO.SPEEDCASH.MERCHANT01189360081530002105700215ID10250021057040303UKE51440014ID.CO.QRIS.WWW0215ID10254315212240303UKE5204526253033605802ID5910DYFHAASHOP6005MAROS61059056262330509S3871109301091263389110703A016304108B';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('qris.static_payload', self::STATIC_PAYLOAD);
    }

    private function makeUser(): User
    {
        return User::create([
            'username' => 'admin_'.uniqid(),
            'password' => Hash::make('secret'),
        ]);
    }

    public function test_uploading_valid_manual_payload_creates_active_qris_setting(): void
    {
        $admin = $this->makeUser();

        Livewire::actingAs($admin)
            ->test(QrisSettings::class)
            ->set('useManualInput', true)
            ->set('manualPayload', self::STATIC_PAYLOAD)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, QrisSetting::count());
        $this->assertSame(self::STATIC_PAYLOAD, QrisSetting::first()->payload);
        $this->assertSame($admin->id, QrisSetting::first()->updated_by);
    }

    public function test_invalid_manual_payload_is_rejected_and_not_saved(): void
    {
        $admin = $this->makeUser();

        Livewire::actingAs($admin)
            ->test(QrisSettings::class)
            ->set('useManualInput', true)
            ->set('manualPayload', 'bukan-qris-yang-valid-sama-sekali-panjangnya-cukup')
            ->call('save')
            ->assertHasErrors('manualPayload');

        $this->assertSame(0, QrisSetting::count());
    }

    public function test_activate_creates_new_row_reusing_old_payload_and_becomes_active(): void
    {
        $admin = $this->makeUser();
        $old = QrisSetting::create([
            'payload' => self::STATIC_PAYLOAD,
            'updated_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(QrisSettings::class)
            ->call('activate', $old->id)
            ->assertHasNoErrors();

        $this->assertSame(2, QrisSetting::count());

        $latest = QrisSetting::latest('id')->first();
        $this->assertSame(self::STATIC_PAYLOAD, $latest->payload);
        $this->assertNotSame($old->id, $latest->id);
        $this->assertSame(self::STATIC_PAYLOAD, app(QrisService::class)->getActivePayload());
    }

    public function test_get_active_payload_prefers_db_row_over_config_fallback(): void
    {
        $admin = $this->makeUser();
        QrisSetting::create([
            'payload' => self::STATIC_PAYLOAD,
            'updated_by' => $admin->id,
        ]);

        $this->assertSame(self::STATIC_PAYLOAD, app(QrisService::class)->getActivePayload());
    }

    public function test_get_active_payload_falls_back_to_config_when_no_rows_exist(): void
    {
        $this->assertSame(self::STATIC_PAYLOAD, app(QrisService::class)->getActivePayload());
    }
}
