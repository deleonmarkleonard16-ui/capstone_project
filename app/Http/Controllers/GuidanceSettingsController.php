<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuidanceSettingsController extends Controller
{
    public function index()
    {
        return view('staff.guidance-settings', [
            'courses' => Course::orderBy('code')->get(),
            'users' => User::whereHas('roleLookup', fn ($query) => $query->where('slug', 'staff'))->orderBy('name')->get(),
        ]);
    }

    public function course(Request $request, ?Course $course = null)
    {
        $data = $request->validate([
            'code' => ['required', Rule::in(array_keys(CourseCatalog::OPTIONS))],
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);
        abort_unless($data['name'] === CourseCatalog::OPTIONS[$data['code']] && $data['is_active'], 422, 'The official program names and availability are fixed.');
        if ($course) abort_unless($course->code === $data['code'], 422, 'Course codes cannot be changed.');
        if (! $course && Course::where('code', $data['code'])->exists()) return back()->withErrors(['code' => 'This program already exists. Edit its existing row.'])->withInput();
        Course::updateOrCreate(['code' => $data['code']], ['name' => $data['name'], 'is_active' => $data['is_active']]);
        return back()->with('success', 'Program updated.');
    }

    public function user(Request $request, ?User $user = null)
    {
        if ($user) abort_unless($user->role === 'staff', 404);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'is_active' => 'required|boolean',
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12'],
        ]);
        if (empty($data['password'])) unset($data['password']);
        if ($user) $user->update($data); else User::create($data + ['role' => 'staff']);
        return back()->with('success', 'Staff account updated.');
    }
}
