<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffSettingsController extends Controller
{
    public function index(Request $request)
    {
        return view('staff.settings', ['staff' => $request->user()]);
    }

    public function passwordChoice(Request $request)
    {
        if (! $request->user()->password_setup_required) {
            return redirect()->route('staff.settings.index');
        }

        return view('staff.password-choice');
    }

    public function keepTemporaryPassword(Request $request)
    {
        $request->user()->update(['password_setup_required' => false]);

        return redirect()->route('staff.dashboard')
            ->with('success', 'You chose to keep your current password. You can change it anytime in Settings.');
    }

    public function update(Request $request)
    {
        $staff = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        unset($data['current_password']);
        if (empty($data['password'])) unset($data['password']);
        if (isset($data['password'])) $data['password_setup_required'] = false;
        $staff->update($data);

        return back()->with('success', 'Your guidance staff account settings have been updated.');
    }
}
