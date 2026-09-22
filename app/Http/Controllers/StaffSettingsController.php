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

    public function update(Request $request)
    {
        $staff = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($staff->id)],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        unset($data['current_password']);
        if (empty($data['password'])) unset($data['password']);
        $staff->update($data);

        return back()->with('success', 'Your guidance staff account settings have been updated.');
    }
}
