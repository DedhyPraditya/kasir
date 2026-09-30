<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Developer memilih toko mana yang datanya ditampilkan (produk, transaksi, pengaturan).
 */
class StoreSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('developer'), 403);

        $data = $request->validate(['store' => ['required', 'string']]);

        abort_unless(Store::whereKey($data['store'])->exists(), 422);

        $request->session()->put(User::ACTIVE_STORE_SESSION_KEY, $data['store']);

        return redirect()->route('dashboard');
    }
}
