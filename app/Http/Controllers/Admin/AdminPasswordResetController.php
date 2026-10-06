<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\RecordStaffAuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AdminPasswordResetController extends Controller
{
    public function createRequest(): View|RedirectResponse
    {
        if (Auth::guard('admin')->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.forgot-password');
    }

    public function storeRequest(Request $request, RecordStaffAuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $admin = User::query()->where('email', $data['email'])->first();

        if ($admin?->isAdmin()) {
            $status = Password::broker('users')->sendResetLink(['email' => $admin->email]);

            if ($status === Password::RESET_LINK_SENT) {
                $auditLog->record($request, 'ADMIN_PASSWORD_RESET_LINK_SENT', target: $admin);
            }
        }

        return back()->with('status', 'Jika e-mel itu milik admin yang aktif, pautan tetapan semula telah dihantar.');
    }

    public function createReset(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::guard('admin')->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function storeReset(Request $request, RecordStaffAuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $admin = User::query()->where('email', $data['email'])->first();

        if (! $admin?->isAdmin()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Pautan tetapan semula tidak sah atau telah tamat tempoh.']);
        }

        $status = Password::broker('users')->reset($data, function (User $user, string $password) use ($request, $auditLog): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => null,
            ])->save();

            event(new PasswordReset($user));
            $auditLog->record($request, 'ADMIN_PASSWORD_RESET', target: $user);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Pautan tetapan semula tidak sah atau telah tamat tempoh.']);
        }

        return redirect()
            ->route('admin.login')
            ->with('status', 'Kata laluan admin berjaya ditetapkan semula. Sila log masuk.');
    }
}
