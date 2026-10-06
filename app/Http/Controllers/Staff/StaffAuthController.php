<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\RecordStaffAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (Auth::guard('staff')->user()?->isActiveStaff()) {
            return redirect()->route('staff.dashboard');
        }

        return view('staff.auth.login');
    }

    public function store(Request $request, RecordStaffAuditLogService $auditLog): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('staff')->attempt($credentials)) {
            $auditLog->record($request, 'STAFF_LOGIN_FAILED', metadata: [
                'email' => mb_strtolower($credentials['email']),
                'portal' => 'staff',
            ]);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata laluan tidak sah.',
            ]);
        }

        if (! Auth::guard('staff')->user()?->isActiveStaff()) {
            /** @var User|null $inactiveUser */
            $inactiveUser = Auth::guard('staff')->user();
            $auditLog->record($request, 'STAFF_LOGIN_BLOCKED', target: $inactiveUser, metadata: ['portal' => 'staff']);
            Auth::guard('staff')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akaun ini tidak mempunyai akses staff aktif.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $staff */
        $staff = Auth::guard('staff')->user();
        $auditLog->record($request, 'STAFF_LOGIN_SUCCEEDED', actor: $staff, target: $staff, metadata: ['portal' => 'staff']);

        return redirect()->intended(route('staff.dashboard'));
    }

    public function destroy(Request $request, RecordStaffAuditLogService $auditLog): RedirectResponse
    {
        /** @var User|null $staff */
        $staff = Auth::guard('staff')->user();
        $auditLog->record($request, 'STAFF_LOGOUT', actor: $staff, target: $staff, metadata: ['portal' => 'staff']);
        Auth::guard('staff')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}
