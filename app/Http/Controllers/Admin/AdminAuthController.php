<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\RecordStaffAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::guard('admin')->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request, RecordStaffAuditLogService $auditLog): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials)) {
            $auditLog->record($request, 'STAFF_LOGIN_FAILED', metadata: [
                'email' => mb_strtolower($credentials['email']),
                'portal' => 'admin',
            ]);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata laluan admin tidak sah.',
            ]);
        }

        if (! Auth::guard('admin')->user()?->isAdmin()) {
            /** @var User|null $staffUser */
            $staffUser = Auth::guard('admin')->user();
            $auditLog->record($request, 'STAFF_LOGIN_BLOCKED', target: $staffUser, metadata: ['portal' => 'admin']);
            Auth::guard('admin')->logout();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akaun ini ialah akaun staff. Sila log masuk melalui halaman Staff Login.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $admin */
        $admin = Auth::guard('admin')->user();
        $auditLog->record($request, 'STAFF_LOGIN_SUCCEEDED', actor: $admin, target: $admin, metadata: ['portal' => 'admin']);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request, RecordStaffAuditLogService $auditLog): RedirectResponse
    {
        /** @var User|null $admin */
        $admin = Auth::guard('admin')->user();
        $auditLog->record($request, 'STAFF_LOGOUT', actor: $admin, target: $admin, metadata: ['portal' => 'admin']);
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
