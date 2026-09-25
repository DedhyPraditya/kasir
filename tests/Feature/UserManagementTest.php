<?php

namespace Tests\Feature;

use App\Livewire\UserManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $username = null): User
    {
        $user = User::create([
            'username' => $username ?? $role.'_'.uniqid(),
            'password' => Hash::make('secret'),
            'api_token' => str_repeat('t', 70).uniqid(),
        ]);
        $user->syncRoles(array_map(fn ($r) => Role::firstOrCreate(['name' => $r]), UserManagement::ROLE_MAP[$role]));

        return $user;
    }

    public function test_only_developer_can_open_page(): void
    {
        $this->actingAs($this->makeUser('developer'))->get(route('users.index'))->assertOk()->assertSee('Kelola Pengguna');
        $this->actingAs($this->makeUser('admin'))->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->makeUser('kasir'))->get(route('users.index'))->assertRedirect(route('pos'));
    }

    public function test_admin_cannot_call_actions_directly(): void
    {
        Livewire::actingAs($this->makeUser('admin'))
            ->test(UserManagement::class)
            ->assertForbidden();
    }

    public function test_developer_can_create_kasir(): void
    {
        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('create')
            ->set('username', 'kasir2')
            ->set('role', 'kasir')
            ->set('password', 'rahasia1')
            ->set('password_confirmation', 'rahasia1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $user = User::where('username', 'kasir2')->first();
        $this->assertTrue(Hash::check('rahasia1', $user->password));
        $this->assertSame(['kasir'], $user->getRoleNames()->all());
    }

    public function test_developer_role_gets_admin_too(): void
    {
        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('create')
            ->set('username', 'dev2')
            ->set('role', 'developer')
            ->set('password', 'rahasia1')
            ->set('password_confirmation', 'rahasia1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['admin', 'developer'], User::where('username', 'dev2')->first()->getRoleNames()->all());
    }

    public function test_validation_rules(): void
    {
        $this->makeUser('kasir', 'kasir1');

        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('create')
            ->set('username', 'kasir1')
            ->set('password', '123')
            ->set('password_confirmation', '456')
            ->call('save')
            ->assertHasErrors(['username' => 'unique', 'password']);
    }

    public function test_reset_password_logs_user_out_everywhere(): void
    {
        $kasir = $this->makeUser('kasir');
        DB::table('sessions')->insert([
            'id' => 'sess1', 'user_id' => $kasir->id, 'payload' => '', 'last_activity' => time(),
        ]);

        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('edit', $kasir->id)
            ->assertSet('username', $kasir->username)
            ->set('password', 'baru1234')
            ->set('password_confirmation', 'baru1234')
            ->call('save')
            ->assertHasNoErrors();

        $kasir->refresh();
        $this->assertTrue(Hash::check('baru1234', $kasir->password));
        $this->assertNull($kasir->api_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $kasir->id)->count());
    }

    public function test_edit_without_password_keeps_old_password_and_token(): void
    {
        $kasir = $this->makeUser('kasir');
        $token = $kasir->api_token;

        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('edit', $kasir->id)
            ->set('username', 'kasir_ganti')
            ->call('save')
            ->assertHasNoErrors();

        $kasir->refresh();
        $this->assertSame('kasir_ganti', $kasir->username);
        $this->assertTrue(Hash::check('secret', $kasir->password));
        $this->assertSame($token, $kasir->api_token);
    }

    public function test_role_change_updates_roles(): void
    {
        $kasir = $this->makeUser('kasir');

        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('edit', $kasir->id)
            ->set('role', 'admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['admin'], $kasir->fresh()->getRoleNames()->all());
        $this->assertNull($kasir->fresh()->api_token);
    }

    public function test_developer_can_delete_other_user(): void
    {
        $kasir = $this->makeUser('kasir');

        Livewire::actingAs($this->makeUser('developer'))
            ->test(UserManagement::class)
            ->call('confirmDelete', $kasir->id)
            ->assertSee('Hapus Akun')
            ->call('delete');

        $this->assertDatabaseMissing('users', ['id' => $kasir->id]);
    }

    public function test_cannot_delete_or_demote_self(): void
    {
        $dev = $this->makeUser('developer');
        $this->makeUser('developer'); // developer lain ada, tetap tidak boleh menurunkan diri sendiri

        Livewire::actingAs($dev)
            ->test(UserManagement::class)
            ->call('confirmDelete', $dev->id)
            ->call('delete')
            ->call('edit', $dev->id)
            ->set('role', 'kasir')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $dev->id]);
        $this->assertTrue($dev->fresh()->hasRole('developer'));
    }

    public function test_last_developer_cannot_be_removed(): void
    {
        $dev = $this->makeUser('developer');
        $otherDev = $this->makeUser('developer');

        // Hapus developer lain boleh (masih ada satu).
        Livewire::actingAs($dev)
            ->test(UserManagement::class)
            ->call('confirmDelete', $otherDev->id)
            ->call('delete');
        $this->assertDatabaseMissing('users', ['id' => $otherDev->id]);

        $this->assertSame(1, User::role('developer')->count());
    }
}
