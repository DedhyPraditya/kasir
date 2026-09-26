<?php

namespace Tests\Feature;

use App\Livewire\LoginSettings;
use App\Models\LoginSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginSettingsTest extends TestCase
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

    public function test_login_page_shows_defaults(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(LoginSetting::DEFAULT_HEADLINE)
            ->assertSee('Sistem Kasir')
            ->assertSee(asset('logo.png'))
            ->assertDontSee('Google');
    }

    public function test_login_still_works(): void
    {
        $user = $this->makeUser('admin');

        $this->post(route('login'), ['username' => $user->username, 'password' => 'secret'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_only_admin_can_open_settings(): void
    {
        $this->actingAs($this->makeUser('admin'))->get(route('login.settings'))->assertOk()->assertSee('Tampilan Login');
        $this->actingAs($this->makeUser('kasir'))->get(route('login.settings'))->assertRedirect(route('pos'));
    }

    public function test_kasir_cannot_call_component_directly(): void
    {
        Livewire::actingAs($this->makeUser('kasir'))
            ->test(LoginSettings::class)
            ->assertForbidden();
    }

    public function test_admin_can_change_text_and_logo(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->makeUser('admin'))
            ->test(LoginSettings::class)
            ->set('headline', 'Cemilan favorit Maros')
            ->set('description', 'Dibuat setiap pagi.')
            ->set('logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->call('save')
            ->assertHasNoErrors();

        $setting = LoginSetting::current();
        $this->assertSame('Cemilan favorit Maros', $setting->headline);
        Storage::disk('public')->assertExists($setting->logo_path);

        auth()->logout();
        $this->get(route('login'))
            ->assertSee('Cemilan favorit Maros')
            ->assertSee('Dibuat setiap pagi.')
            ->assertSee('storage/'.$setting->logo_path);
    }

    public function test_replacing_and_resetting_logo_deletes_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->makeUser('admin');

        Livewire::actingAs($admin)
            ->test(LoginSettings::class)
            ->set('logo', UploadedFile::fake()->image('a.png'))
            ->call('save');
        $first = LoginSetting::current()->logo_path;

        Livewire::actingAs($admin)
            ->test(LoginSettings::class)
            ->set('logo', UploadedFile::fake()->image('b.png'))
            ->call('save');
        $second = LoginSetting::current()->logo_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        Livewire::actingAs($admin)
            ->test(LoginSettings::class)
            ->call('useDefaultLogo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, LoginSetting::count());
        Storage::disk('public')->assertMissing($second);
        $this->assertNull(LoginSetting::current()->logo_path);
        $this->assertSame(asset('logo.png'), LoginSetting::current()->logoUrl());
    }

    public function test_rejects_non_image_and_empty_text(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->makeUser('admin'))
            ->test(LoginSettings::class)
            ->set('logo', UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))
            ->assertHasErrors('logo')
            ->set('logo', null)
            ->set('headline', '')
            ->call('save')
            ->assertHasErrors(['headline']);

        $this->assertSame(0, LoginSetting::count());
    }
}
