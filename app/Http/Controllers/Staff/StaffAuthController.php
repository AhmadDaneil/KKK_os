<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user?->isActiveStaff()) {
            return redirect()->route('staff.dashboard');
        }

        return view('staff.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'Email atau kata laluan tidak sah.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user instanceof User || ! $user->isActiveStaff()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Akaun ini tidak mempunyai akses staff aktif.'])
                ->onlyInput('email');
        }

        return redirect()->intended(route('staff.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}