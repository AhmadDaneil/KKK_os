<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffThemePreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'staff_theme' => ['required', 'string', Rule::in(User::STAFF_THEMES)],
        ]);

        $request->user()->update([
            'staff_theme' => $data['staff_theme'],
        ]);

        return back()->with('status', 'Tema paparan telah dikemas kini.');
    }
}
