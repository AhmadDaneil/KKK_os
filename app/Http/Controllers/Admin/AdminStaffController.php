<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminStaffController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->whereIn('role', User::STAFF_ROLES);

        if ($request->filled('role') && in_array($request->string('role')->toString(), User::STAFF_ROLES, true)) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $staffMembers = $query->orderByDesc('is_active')->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.staff.index', compact('staffMembers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create($data + ['is_active' => true]);

        return back()->with('admin_success', 'Akaun staff berjaya dicipta.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaffUser($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::STAFF_ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->user()->is($user) && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak boleh menukar role atau menyahaktifkan akaun admin yang sedang digunakan.',
            ]);
        }

        if ($user->role === User::ROLE_ADMIN && ($data['role'] !== User::ROLE_ADMIN || ! $data['is_active'])) {
            $activeAdminCount = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count();

            if ($activeAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'is_active' => 'Sekurang-kurangnya satu akaun admin aktif mesti dikekalkan.',
                ]);
            }
        }

        $user->update($data);

        return back()->with('admin_success', 'Maklumat staff berjaya dikemas kini.');
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaffUser($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('admin_success', "Kata laluan {$user->name} berjaya ditetapkan semula.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureStaffUser($user);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'delete_staff' => 'Anda tidak boleh memadam akaun admin yang sedang digunakan.',
            ]);
        }

        if ($user->role === User::ROLE_ADMIN) {
            $adminCount = User::where('role', User::ROLE_ADMIN)->count();

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'delete_staff' => 'Sekurang-kurangnya satu akaun admin mesti dikekalkan.',
                ]);
            }
        }

        $staffName = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.staff.index')
            ->with('admin_success', "Akaun {$staffName} berjaya dipadam. Tugasan aktifnya kini belum di-assign.");
    }

    private function ensureStaffUser(User $user): void
    {
        abort_unless(in_array($user->role, User::STAFF_ROLES, true), 404);
    }
}
