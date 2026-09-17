<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isActiveStaff()) {
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
            throw ValidationException::withMessages([
                'email' => 'Email atau kata laluan tidak sah.',
            ]);
        }

        if (! $request->user()?->isActiveStaff()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akaun ini tidak mempunyai akses staff aktif.',
            ]);
        }

        $request->session()->regenerate();

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
